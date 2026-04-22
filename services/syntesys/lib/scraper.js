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

    // 5. Nawiguj do widoku list — Angular musi się załadować żeby ustawić XSRF/session cookies
    const appUrl = `${BASE_URL}/insurance/credit-check/requests-lists/(type:done)`;
    log(`Navigating to app: ${appUrl}`);
    await page.goto(appUrl, { waitUntil: 'domcontentloaded', timeout: 40000 });

    // 6. Poczekaj aż Angular zainicjuje się i ustawi cookies sesji XHR
    await sleep(3000);
    log(`App ready. URL: ${page.url()}`);
}

// ─── Fetch one list (all pages) ───────────────────────────────────────────────

async function fetchList(page, statusCode) {
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

        const result = await page.evaluate(async (fetchUrl) => {
            const resp = await fetch(fetchUrl, {
                method:      'GET',
                credentials: 'include',
                headers:     { 'Accept': 'application/json' },
            });
            const body = await resp.text();
            // zbierz nagłówki do debugowania
            const headers = {};
            resp.headers.forEach((v, k) => { headers[k] = v; });
            return { status: resp.status, body, headers };
        }, url);

        log(`  → HTTP ${result.status}`);
        log(`  → Headers: ${JSON.stringify(result.headers)}`);
        if (result.body) {
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
        const savedCookies = session.load();

        if (savedCookies?.length > 0) {
            // Przywróć sesję: wejdź na stronę app (nie login) żeby mieć właściwy origin
            // dla fetch() z credentials:include. Jeśli sesja wygasła na serwerze,
            // fetchList() rzuci SESSION_EXPIRED → re-login.
            log(`Restoring session (${savedCookies.length} cookies, TTL ok)...`);
            await page.goto(
                `${BASE_URL}/insurance/credit-check/requests-lists/(type:done)`,
                { waitUntil: 'domcontentloaded', timeout: 20000 }
            );
            await page.setCookie(...savedCookies);
            await sleep(1500); // poczekaj aż strona przetworzy przywrócone cookies
            log('Session restored — skipping login');
        } else {
            log('No valid session — logging in...');
            await login(page);
            session.save(await page.cookies());
        }

        // Pobierz wszystkie żądane statusy
        const output = {};
        for (const statusCode of statusCodes) {
            try {
                output[statusCode] = await fetchList(page, statusCode);
            } catch (e) {
                if (e.code === 'SESSION_EXPIRED') {
                    log('Session expired during fetch — re-login...');
                    session.clear();
                    await login(page);
                    session.save(await page.cookies());
                    // retry once
                    output[statusCode] = await fetchList(page, statusCode);
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
