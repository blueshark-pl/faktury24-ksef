'use strict';

const puppeteer = require('puppeteer');
const session   = require('./session');

const BASE_URL  = 'https://syntesys.pl';
const API_BASE  = 'https://syntesys.pl/syntesys-api';
const PAGE_SIZE = 15;

const TIMEOUT_MS = parseInt(process.env.SCRAPER_TIMEOUT_MS || '90000', 10);

// ─── List status map ──────────────────────────────────────────────────────────

const LIST_MAP = {
    'done':      'WITH_OPINION',
    'waiting':   'PROCESSING',
    'no-advice': 'NO_OPINION',
    'error':     'BUSINESS_ERROR',
};

// Odwrotne mapowanie statusCode → typ zakładki w URL Angulara
const STATUS_TO_TAB = {
    'WITH_OPINION':   'done',
    'PROCESSING':     'waiting',
    'NO_OPINION':     'no-advice',
    'BUSINESS_ERROR': 'error',
};

function toStatusCode(list) {
    return LIST_MAP[list] ?? list;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function log(msg) {
    process.stderr.write(`[scraper] ${new Date().toISOString()} ${msg}\n`);
}

function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

async function launchBrowser() {
    const args = [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--disable-extensions',
    ];

    const headless = process.env.PUPPETEER_HEADLESS !== 'false';
    const opts = { headless, args };
    if (process.env.CHROME_EXECUTABLE) {
        opts.executablePath = process.env.CHROME_EXECUTABLE;
    }

    return puppeteer.launch(opts);
}

// ─── XHR interceptor ─────────────────────────────────────────────────────────

const API_CREDIT_BASE = `${API_BASE}/insurance/credit-check-advices`;

/**
 * Loguje WSZYSTKIE żądania/odpowiedzi do syntesys-api w konsoli (debug).
 */
function enableXhrLogging(page) {
    page.on('request', req => {
        if (req.url().includes('syntesys-api')) {
            log(`[XHR →] ${req.method()} ${req.url()}`);
        }
    });
    page.on('response', resp => {
        if (resp.url().includes('syntesys-api')) {
            log(`[XHR ←] HTTP ${resp.status()} ${resp.url()}`);
        }
    });
}

/**
 * Czeka na pierwszą odpowiedź XHR do credit-check-advices,
 * przechwytuje Bearer token z nagłówka żądania i body odpowiedzi.
 * MUSI być wywołane PRZED page.goto().
 *
 * @returns {Promise<{ token: string, status: number, body: string }>}
 */
function captureFirstApiCall(page) {
    return page
        .waitForResponse(
            resp => resp.url().includes('/syntesys-api/insurance/credit-check-advices'),
            { timeout: 30000 }
        )
        .then(async resp => {
            const token  = resp.request().headers()['authorization'] ?? '';
            const status = resp.status();
            const body   = await resp.text();
            log(`[XHR ←] Przechwycona odpowiedź: HTTP ${status} ${resp.url()}`);
            log(`[XHR] Token Bearer: ${token.length} znaków`);
            return { token, status, body };
        });
}

// ─── Login ───────────────────────────────────────────────────────────────────

async function acceptCookies(page) {
    try {
        await page.waitForSelector('button.cc_btn[name="cookies"]', { timeout: 8000 });
        await page.click('button.cc_btn[name="cookies"]');
        log('Cookie consent accepted — waiting for dialog close...');
        // Poczekaj na zamknięcie dialogu i stabilizację strony
        await sleep(2000);
    } catch {
        log('No cookie consent dialog (or already accepted)');
    }
}

async function login(page) {
    const loginUrl = `${BASE_URL}/syntesys-oauth/login`;
    log(`Navigating to login: ${loginUrl}`);
    await page.goto(loginUrl, { waitUntil: 'domcontentloaded', timeout: TIMEOUT_MS });

    // 1. Zaakceptuj cookies — NAJPIERW, zanim cokolwiek inne
    await acceptCookies(page);

    // 2. Poczekaj na formularz (Angular może renderować asynchronicznie)
    log('Waiting for login form...');
    await page.waitForSelector('input#username', { timeout: 20000 });
    await page.waitForSelector('input#password', { timeout: 10000 });
    await sleep(800); // dodatkowa pauza: Angular może nie być gotowy mimo widoczności pola

    // 3. Wypełnij formularz
    log('Filling login form...');
    await page.$eval('input#username', el => el.value = '');
    await page.type('input#username', process.env.SYNTESYS_USER || '', { delay: 60 });
    await sleep(400);

    await page.$eval('input#password', el => el.value = '');
    await page.type('input#password', process.env.SYNTESYS_PASS || '', { delay: 60 });
    await sleep(500);

    // 4. Wyślij formularz
    log('Submitting login form...');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
        page.click('input[name="login"][type="submit"]'),
    ]);

    const currentUrl = page.url();
    if (currentUrl.includes('/login') || currentUrl.includes('error')) {
        throw new Error(`Login failed — still on: ${currentUrl}`);
    }
    log(`Login success. URL: ${currentUrl}`);

    // Poczekaj aż strona główna w pełni się załaduje (Angular inicjalizuje sesję)
    log('Waiting for main page to fully load...');
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 20000 }).catch(() => {});
    await sleep(1000);

    // 5. Włącz logowanie wszystkich XHR + ustaw interceptor odpowiedzi PRZED goto
    const appUrl = `${BASE_URL}/insurance/credit-check/requests-lists/(type:done)`;
    enableXhrLogging(page);
    log('Czekam na pierwsze żądanie API od Angulara...');
    const apiCallPromise = captureFirstApiCall(page);

    log(`Navigating to app: ${appUrl}`);
    await page.goto(appUrl, { waitUntil: 'domcontentloaded', timeout: 40000 });

    // 6. Czekamy na odpowiedź — mamy token i body strony 1
    const { token, status: firstStatus, body: firstBody } = await apiCallPromise;
    log(`Token przechwycony, pierwsza strona: HTTP ${firstStatus}`);

    return { token, firstBody: { status: firstStatus, body: firstBody } };
}

// ─── Fetch one list (all pages) ───────────────────────────────────────────────

/**
 * Czeka na odpowiedź XHR do credit-check-advices (dowolna strona).
 * Przechwytuje body — bez własnego fetch().
 * @returns {Promise<{ status: number, body: string, token: string }>}
 */
function waitForListResponse(page) {
    return page
        .waitForResponse(
            resp => resp.url().includes('/syntesys-api/insurance/credit-check-advices'),
            { timeout: 30000 }
        )
        .then(async resp => {
            const status = resp.status();
            const body   = await resp.text();
            const token  = resp.request().headers()['authorization'] ?? '';
            log(`[XHR ←] Przechwycono: HTTP ${status} ${resp.url()}`);
            return { status, body, token };
        });
}

/**
 * Pobiera jedną listę (wszystkie strony) przez nawigację Angulara + przechwycenie XHR.
 * NIE robi własnych fetch() — Angular sam wysyła żądania przy goto i kliknięciu #nextPage.
 *
 * @param {object} page
 * @param {string} statusCode    np. 'WITH_OPINION'
 * @param {string|null} token    aktualny token (używany do zapisu sesji po re-login)
 * @param {{ status, body }|null} firstPage  opcjonalne — body strony 1 już przechwycone
 * @returns {Promise<{ items: object[], token: string }>}
 */
async function fetchList(page, statusCode, token, firstPage = null) {
    const items  = [];
    const tabType = STATUS_TO_TAB[statusCode] ?? 'done';

    log(`Fetching list: ${statusCode} (tab: ${tabType})`);

    // ── Strona 1 ─────────────────────────────────────────────────────────────
    let result;

    if (firstPage) {
        // Strona 1 już przechwycona przez Angular przy logowaniu/nawigacji
        log(`  strona 1: używam przechwycony XHR (HTTP ${firstPage.status})`);
        result = firstPage;
    } else {
        // Nawiguj do właściwej zakładki i poczekaj aż Angular wyśle XHR
        const tabUrl = `${BASE_URL}/insurance/credit-check/requests-lists/(type:${tabType})`;
        log(`  strona 1: nawigacja do ${tabUrl}`);
        const respPromise = waitForListResponse(page);
        await page.goto(tabUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
        result = await respPromise;
    }

    // ── Przetwarzaj stronę ───────────────────────────────────────────────────
    // Aktualizuj token z odpowiedzi (jest świeży)
    if (result.token) token = result.token;

    if (result.status === 401) {
        throw Object.assign(new Error('HTTP 401 — session expired'), { code: 'SESSION_EXPIRED' });
    }
    if (result.status < 200 || result.status >= 300) {
        throw new Error(`HTTP ${result.status} for ${statusCode}: ${result.body.slice(0, 400)}`);
    }

    let parsed;
    try { parsed = JSON.parse(result.body); } catch {
        throw new Error(`Invalid JSON from API: ${result.body.slice(0, 200)}`);
    }

    const extractBatch = p => Array.isArray(p) ? p : (Array.isArray(p?.items) ? p.items : []);
    const getTotal     = p => p?.totalCount ?? p?.totalItems ?? null;

    items.push(...extractBatch(parsed));
    log(`  strona 1: ${items.length} / ${getTotal(parsed) ?? '?'} elementów`);

    // ── Kolejne strony — klikanie #nextPage ───────────────────────────────────
    const total = getTotal(parsed);
    let   page2 = 2;

    while (true) {
        if (total !== null && items.length >= total) break;
        if (extractBatch(parsed).length < PAGE_SIZE) break;

        // Sprawdź czy przycisk następnej strony istnieje i nie jest wyłączony
        const canNext = await page.$eval(
            '#nextPage',
            btn => btn && !btn.disabled && !btn.classList.contains('disabled')
        ).catch(() => false);

        if (!canNext) {
            log(`  brak przycisku #nextPage — koniec stronicowania`);
            break;
        }

        log(`  strona ${page2}: klikam #nextPage...`);
        const respPromise = waitForListResponse(page);
        await page.click('#nextPage');
        result = await respPromise;

        if (result.token) token = result.token;

        if (result.status === 401) {
            throw Object.assign(new Error('HTTP 401 — session expired'), { code: 'SESSION_EXPIRED' });
        }
        if (result.status < 200 || result.status >= 300) {
            throw new Error(`HTTP ${result.status} strona ${page2}: ${result.body.slice(0, 400)}`);
        }

        try { parsed = JSON.parse(result.body); } catch {
            throw new Error(`Invalid JSON strona ${page2}: ${result.body.slice(0, 200)}`);
        }

        const batch = extractBatch(parsed);
        if (batch.length === 0) break;
        items.push(...batch);
        log(`  strona ${page2}: ${items.length} / ${getTotal(parsed) ?? '?'} elementów`);
        page2++;
    }

    log(`Fetched ${items.length} items for ${statusCode}`);
    return { items, token };
}

// ─── Main scraper function ────────────────────────────────────────────────────

/**
 * Run the scraper for given list codes.
 *
 * @param {string[]} statusCodes  e.g. ['WITH_OPINION', 'PROCESSING']
 * @returns {Promise<Record<string, Object[]>>}  keyed by statusCode
 */
async function scrape(statusCodes) {
    if (!process.env.SYNTESYS_USER || !process.env.SYNTESYS_PASS) {
        throw new Error('Missing SYNTESYS_USER / SYNTESYS_PASS env vars');
    }

    const browser = await launchBrowser();
    try {
        const page = await browser.newPage();
        await page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
        );

        // Wczytaj zachowaną sesję
        const savedSession = session.load();
        const savedToken   = savedSession?.token;
        let   token;
        let   loginFirstBody = null; // body strony 1 przechwycone przy logowaniu

        if (savedToken) {
            // Mamy token — nawiguj do app i włącz logowanie XHR
            log('Restoring session (token cached, TTL ok)...');
            enableXhrLogging(page);
            await page.goto(
                `${BASE_URL}/insurance/credit-check/requests-lists/(type:done)`,
                { waitUntil: 'domcontentloaded', timeout: 20000 }
            );
            await sleep(1500);
            token = savedToken;
            log('Session restored — skipping login');
        } else {
            log('No valid session — logging in...');
            const loginResult = await login(page);
            token         = loginResult.token;
            loginFirstBody = loginResult.firstBody;
            session.save({ token });
        }

        // Pobierz wszystkie żądane statusy
        const output = {};
        let   isFirst = true;
        for (const statusCode of statusCodes) {
            // Dla pierwszego statusu użyj przechwyconego body z logowania (strona 1 za darmo)
            const firstPage = (isFirst && loginFirstBody) ? loginFirstBody : null;
            isFirst = false;
            try {
                const result = await fetchList(page, statusCode, token, firstPage);
                output[statusCode] = result.items;
                token = result.token; // odśwież token (może być świeższy)
            } catch (e) {
                if (e.code === 'SESSION_EXPIRED') {
                    log('Session expired during fetch — re-login...');
                    session.clear();
                    const loginResult = await login(page);
                    token = loginResult.token;
                    session.save({ token });
                    // retry once
                    const retryResult = await fetchList(page, statusCode, token);
                    output[statusCode] = retryResult.items;
                    token = retryResult.token;
                } else {
                    log(`ERROR for ${statusCode}: ${e.message}`);
                    output[statusCode] = [];
                }
            }
        }

        return output;
    } finally {
        await browser.close();
    }
}

module.exports = { scrape, toStatusCode, LIST_MAP };
