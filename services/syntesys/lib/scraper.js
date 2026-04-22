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
 * @param {object} page
 * @param {string} statusCode    np. 'WITH_OPINION'
 * @param {string} token         nagłówek Authorization np. 'Bearer eyJ...'
 * @param {object|null} firstPage  opcjonalny { status, body } z przechwyconego XHR strony 1
 */
async function fetchList(page, statusCode, token, firstPage = null) {
    const items  = [];
    let   pageNo = 1;

    log(`Fetching list: ${statusCode}`);

    while (true) {
        // Zbuduj URL — base + podmieniona query string
        const params = new URLSearchParams({
            'filter.listStatus': statusCode,
            'page.no':           pageNo,
            'page.size':         PAGE_SIZE,
            'sortDirection':     'DESC',
            'sortBy':            'sendDate',
        });
        if (statusCode === 'WITH_OPINION') {
            params.set('filter.activeOnly', 'true');
        }
        const url = `${API_CREDIT_BASE}?${params}`;
        log(`  page ${pageNo}: ${url}`);

        let result;

        // Strona 1 — użyj przechwyconego body jeśli dostępne (oszczędza jeden request)
        if (pageNo === 1 && firstPage) {
            log(`  → używam przechwyconą odpowiedź Angulara (HTTP ${firstPage.status})`);
            result = firstPage;
        } else {
            result = await page.evaluate(async (fetchUrl, authToken) => {
                const resp = await fetch(fetchUrl, {
                    method:  'GET',
                    headers: {
                        'Accept':        'application/json',
                        'Authorization': authToken,
                    },
                });
                const body = await resp.text();
                return { status: resp.status, body };
            }, url, token);
            log(`  → HTTP ${result.status}`);
        }

        if (result.status !== 200) {
            log(`  → Body: ${result.body.slice(0, 1000)}`);
        }

        if (result.status === 401) {
            // Sesja wygasła — rzuć błąd żeby caller mógł zalogować ponownie
            throw Object.assign(new Error('HTTP 401 — session expired'), { code: 'SESSION_EXPIRED' });
        }

        if (result.status < 200 || result.status >= 300) {
            throw new Error(`HTTP ${result.status} for ${statusCode}: ${result.body.slice(0, 400)}`);
        }

        let parsed;
        try { parsed = JSON.parse(result.body); } catch {
            throw new Error(`Invalid JSON from API: ${result.body.slice(0, 200)}`);
        }

        const batch = Array.isArray(parsed)
            ? parsed
            : (Array.isArray(parsed?.items) ? parsed.items : []);

        if (batch.length === 0) break;
        items.push(...batch);

        const total = parsed?.totalCount ?? parsed?.totalItems ?? null;
        if (total !== null && items.length >= total) break;
        if (batch.length < PAGE_SIZE) break;
        pageNo++;
    }

    log(`  fetched ${items.length} items for ${statusCode}`);
    return items;
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
            // Dla pierwszego statusu (WITH_OPINION) użyj przechwyconego body z logowania
            const firstPage = (isFirst && loginFirstBody) ? loginFirstBody : null;
            isFirst = false;
            try {
                output[statusCode] = await fetchList(page, statusCode, token, firstPage);
            } catch (e) {
                if (e.code === 'SESSION_EXPIRED') {
                    log('Session expired during fetch — re-login...');
                    session.clear();
                    const loginResult = await login(page);
                    token = loginResult.token;
                    session.save({ token });
                    // retry once
                    output[statusCode] = await fetchList(page, statusCode, token);
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
