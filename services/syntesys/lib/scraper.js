'use strict';

const puppeteer = require('puppeteer');
const session   = require('./session');

const BASE_URL  = 'https://syntesys.pl';
const API_BASE  = 'https://syntesys.pl/syntesys-api';
const PAGE_SIZE = 100;

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

// ─── Token capture ───────────────────────────────────────────────────────────

/**
 * Czeka na pierwsze żądanie XHR do syntesys-api i wyciąga Bearer token
 * z nagłówka Authorization. MUSI być wywołane PRZED page.goto().
 * @returns {Promise<string>}  pełny nagłówek np. "Bearer eyJ..."
 */
function captureToken(page) {
    const apiPattern = /syntesys-api\/insurance\/credit-check/;
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            page.off('request', onReq);
            reject(new Error('Timeout: Angular nie wysłał żądania API w ciągu 30s'));
        }, 30000);

        function onReq(req) {
            if (!apiPattern.test(req.url())) return;
            const auth = req.headers()['authorization'];
            if (auth && auth.startsWith('Bearer ')) {
                clearTimeout(timer);
                page.off('request', onReq);
                log(`XHR przechwycony: ${req.url()}`);
                resolve(auth);
            }
        }

        page.on('request', onReq);
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

    // 5. Ustaw interceptor PRZED nawigacją — Angular wyśle XHR zaraz po załadowaniu strony
    const appUrl = `${BASE_URL}/insurance/credit-check/requests-lists/(type:done)`;
    log('Ustawiam interceptor tokenu Bearer...');
    const tokenPromise = captureToken(page);

    log(`Navigating to app: ${appUrl}`);
    await page.goto(appUrl, { waitUntil: 'domcontentloaded', timeout: 40000 });

    // 6. Czekamy aż Angular wystrzeli pierwsze żądanie API i przechwycimy token
    log('Czekam na pierwsze żądanie API od Angulara...');
    const token = await tokenPromise;
    log(`Token Bearer przechwycony (${token.length} znaków)`);

    return token;
}

// ─── Fetch one list (all pages) ───────────────────────────────────────────────

/**
 * @param {object} page
 * @param {string} statusCode  np. 'WITH_OPINION'
 * @param {string} token       nagłówek Authorization np. 'Bearer eyJ...'
 */
async function fetchList(page, statusCode, token) {
    const items  = [];
    let   pageNo = 1;

    log(`Fetching list: ${statusCode}`);

    while (true) {
        let url = `${API_BASE}/insurance/credit-check-advices`
            + `?filter.listStatus=${statusCode}`
            + `&page.no=${pageNo}&page.size=${PAGE_SIZE}`
            + `&sortDirection=DESC&sortBy=sendDate`;

        if (statusCode === 'WITH_OPINION') {
            url += '&filter.activeOnly=true';
        }

        log(`  page ${pageNo}: ${url}`);

        const result = await page.evaluate(async (fetchUrl, authToken) => {
            const resp = await fetch(fetchUrl, {
                method:  'GET',
                headers: {
                    'Accept':        'application/json',
                    'Authorization': authToken,
                },
            });
            const body = await resp.text();
            const headers = {};
            resp.headers.forEach((v, k) => { headers[k] = v; });
            return { status: resp.status, body, headers };
        }, url, token);

        log(`  → HTTP ${result.status}`);
        if (result.status !== 200) {
            log(`  → Headers: ${JSON.stringify(result.headers)}`);
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

        if (savedToken) {
            // Mamy token — nawiguj do app żeby mieć właściwy origin dla fetch()
            log('Restoring session (token cached, TTL ok)...');
            await page.goto(
                `${BASE_URL}/insurance/credit-check/requests-lists/(type:done)`,
                { waitUntil: 'domcontentloaded', timeout: 20000 }
            );
            await sleep(1500);
            token = savedToken;
            log('Session restored — skipping login');
        } else {
            log('No valid session — logging in...');
            token = await login(page);
            session.save({ token });
        }

        // Pobierz wszystkie żądane statusy
        const output = {};
        for (const statusCode of statusCodes) {
            try {
                output[statusCode] = await fetchList(page, statusCode, token);
            } catch (e) {
                if (e.code === 'SESSION_EXPIRED') {
                    log('Session expired during fetch — re-login...');
                    session.clear();
                    token = await login(page);
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
