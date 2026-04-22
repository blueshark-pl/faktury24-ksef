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

    const extractBatch = p => {
        if (Array.isArray(p)) return p;
        if (Array.isArray(p?.items)) return p.items;
        if (Array.isArray(p?.data)) return p.data;
        if (Array.isArray(p?.content)) return p.content;
        return [];
    };
    const getTotal = p => p?.totalCount ?? p?.totalItems ?? p?.total ?? p?.totalElements ?? null;

    items.push(...extractBatch(parsed));
    log(`  strona 1: ${items.length} / ${getTotal(parsed) ?? '?'} elementów`);
    log(`  JSON keys: ${Object.keys(parsed || {}).join(', ')}`);

    // ── Kolejne strony — klikanie #nextPage ───────────────────────────────────
    const total = getTotal(parsed);
    let   page2 = 2;

    while (true) {
        if (total !== null && items.length >= total) break;
        if (extractBatch(parsed).length < PAGE_SIZE) break;

        // Poczekaj aż Angular wygeneruje paginację, potem sprawdź stan przycisku
        await sleep(800);

        const canNext = await page.evaluate(() => {
            const btn = document.querySelector('#nextPage');
            if (!btn) return false;
            if (btn.disabled) return false;
            if (btn.classList.contains('disabled')) return false;
            if (btn.hasAttribute('disabled')) return false;
            return true;
        }).catch(() => false);

        if (!canNext) {
            log(`  #nextPage niedostępny — koniec stronicowania`);
            break;
        }

        log(`  strona ${page2}: klikam #nextPage...`);
        const respPromise = waitForListResponse(page);

        // Kliknij przez evaluate (bardziej niezawodne niż page.click gdy Angular re-renderuje)
        await page.evaluate(() => {
            document.querySelector('#nextPage').click();
        });

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

        // Zawsze loguj — najpierw login, potem dane
        log('Logging in...');
        enableXhrLogging(page);
        const loginResult  = await login(page);
        let   token        = loginResult.token;
        const loginFirstBody = loginResult.firstBody;

        // Pobierz wszystkie żądane statusy
        const output = {};
        let   isFirst = true;
        for (const statusCode of statusCodes) {
            // Strona 1 WITH_OPINION już przechwycona przy logowaniu — za darmo
            const firstPage = (isFirst && loginFirstBody) ? loginFirstBody : null;
            isFirst = false;
            try {
                const result = await fetchList(page, statusCode, token, firstPage);
                output[statusCode] = result.items;
                token = result.token;
            } catch (e) {
                log(`ERROR for ${statusCode}: ${e.message}`);
                output[statusCode] = [];
            }
        }

        return output;
    } finally {
        await browser.close();
    }
}

// ─── Check single opinion ─────────────────────────────────────────────────────

/**
 * Sprawdza opinię kredytową dla podanego NIP (lub VAT EU).
 * Loguje do Syntesys, wypełnia formularz, czeka na wynik.
 *
 * @param {string} nip  Numer identyfikacyjny (tylko cyfry)
 * @returns {Promise<object>}  Surowy obiekt odpowiedzi API z advice
 */
async function checkOpinion(nip) {
    if (!process.env.SYNTESYS_USER || !process.env.SYNTESYS_PASS) {
        throw new Error('Missing SYNTESYS_USER / SYNTESYS_PASS env vars');
    }

    const browser = await launchBrowser();
    try {
        const page = await browser.newPage();
        await page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
        );

        // Login — przechwytuje token z pierwszego XHR Angulara
        enableXhrLogging(page);
        const { token } = await login(page);

        // Zbieraj odpowiedzi GET na /credit-check-advices/* zanim zostaną skonsumowane
        // Angular odpytuje GET natychmiast po POST — musimy przechwycić zanim to przetworzymy
        const capturedAdviceResponses = []; // { adviceId, body }
        const captureHandler = async (response) => {
            const m = response.url().match(/\/credit-check-advices\/(\d+)(?:[/?]|$)/);
            if (m && response.request().method() === 'GET' && response.status() === 200) {
                try {
                    const text = await response.text();
                    capturedAdviceResponses.push({ adviceId: m[1], body: text });
                    log(`checkOpinion: przechwycono GET /${m[1]} — ${text.slice(0, 80)}`);
                } catch (_) {}
            }
        };
        page.on('response', captureHandler);

        // Nawiguj do formularza zlecenia opinii
        const createUrl = `${BASE_URL}/insurance/credit-check/create/(type:single)`;
        log(`checkOpinion: nawigacja do ${createUrl}`);
        await page.goto(createUrl, { waitUntil: 'networkidle2', timeout: 30000 });

        // Poczekaj na pole NIP
        const nipSelector = 'sn-input#identifier-value-nip input';
        log('checkOpinion: czekam na pole NIP...');
        await page.waitForSelector(nipSelector, { timeout: 15000 });
        await sleep(500);

        // Wpisz NIP
        await page.click(nipSelector, { clickCount: 3 });
        await page.type(nipSelector, nip, { delay: 60 });
        log(`checkOpinion: wpisano NIP "${nip}"`);

        // Przygotuj przechwycenie odpowiedzi POST zanim klikniemy
        const postPromise = page.waitForResponse(
            r => r.url().includes('/syntesys-api/insurance/credit-check-advices')
                  && r.request().method() === 'POST',
            { timeout: 20000 }
        );

        // Kliknij przycisk "Zamów opinię"
        await page.evaluate(() => {
            const btn = document.querySelector('button[data-test="form-submit-button"]');
            if (btn) btn.click();
        });
        log('checkOpinion: kliknięto submit');

        // Poczekaj na odpowiedź POST z ID wniosku
        const postResp = await postPromise;
        const postBody = await postResp.text();
        log(`checkOpinion: POST HTTP ${postResp.status()} — ${postBody.slice(0, 200)}`);

        let postJson;
        try { postJson = JSON.parse(postBody); } catch {
            throw new Error(`Nieprawidłowy JSON po złożeniu wniosku: ${postBody.slice(0, 300)}`);
        }

        // API może zwrócić gołe ID (liczba) albo obiekt { id: ... }
        let adviceId;
        if (postJson && typeof postJson === 'object') {
            adviceId = postJson.id ?? null;
        } else if (String(postJson).match(/^\d+$/)) {
            adviceId = String(postJson);
        } else {
            adviceId = null;
        }

        if (!adviceId) {
            throw new Error(`Nie można uzyskać ID wniosku. Odpowiedź: ${JSON.stringify(postJson).slice(0, 300)}`);
        }
        log(`checkOpinion: adviceId=${adviceId}, czekam na XHR Angulara...`);

        // Czekamy aż Angular sam (co kilka sekund) odpyta GET i zwróci status != PROCESSING.
        // Nie robimy własnych fetch — nasłuchujemy tylko odpowiedzi przeglądarki.
        const result = await new Promise((resolve, reject) => {
            const TIMEOUT_MS = 120_000;
            const timer = setTimeout(() => {
                page.off('response', onResponse);
                reject(new Error('Timeout: opinia nie jest gotowa po 120 sekundach'));
            }, TIMEOUT_MS);

            // Najpierw sprawdź co już zostało przechwycone przed przysięgą
            for (const { adviceId: id, body } of capturedAdviceResponses) {
                if (id === String(adviceId)) {
                    try {
                        const j = JSON.parse(body);
                        if (j.status && j.status !== 'PROCESSING') {
                            clearTimeout(timer);
                            log(`checkOpinion: gotowe (już przechwycone) — status=${j.status}`);
                            page.off('response', captureHandler);
                            resolve(j);
                            return;
                        }
                    } catch (_) {}
                }
            }

            async function onResponse(response) {
                const m = response.url().match(/\/credit-check-advices\/(\d+)(?:[/?]|$)/);
                if (!m || m[1] !== String(adviceId) || response.request().method() !== 'GET') return;
                if (response.status() !== 200) return;

                try {
                    const text = await response.text();
                    log(`checkOpinion: Angular GET /${adviceId} — ${text.slice(0, 120)}`);
                    const j = JSON.parse(text);
                    if (j.status && j.status !== 'PROCESSING') {
                        clearTimeout(timer);
                        page.off('response', onResponse);
                        log(`checkOpinion: gotowe! status=${j.status}`);
                        resolve(j);
                    }
                    // jeśli PROCESSING — czekamy na następny XHR
                } catch (e) {
                    log(`checkOpinion: błąd parsowania odpowiedzi — ${e.message}`);
                }
            }

            page.on('response', onResponse);
        });

        page.off('response', captureHandler);
        return result;
    } finally {
        await browser.close();
    }
}

module.exports = { scrape, checkOpinion, toStatusCode, LIST_MAP };
