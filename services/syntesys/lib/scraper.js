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

async function login(page) {
    const loginUrl = `${BASE_URL}/login`;
    log(`Navigating to login: ${loginUrl}`);
    await page.goto(loginUrl, { waitUntil: 'networkidle2', timeout: TIMEOUT_MS });

    // Czekaj na pole loginu — Angular SSR może renderować asynchronicznie
    const userSelectors = [
        'input[name="username"]',
        'input[type="email"]',
        'input[type="text"]',
        'input[id*="login"]',
        'input[id*="user"]',
        'input[placeholder*="l" i]',   // placeholder zawierający "l" (login/Login)
    ];

    let userSel = null;
    for (const sel of userSelectors) {
        try { await page.waitForSelector(sel, { timeout: 4000 }); userSel = sel; break; } catch { /* next */ }
    }
    if (!userSel) throw new Error(`Cannot find username field on ${loginUrl}`);

    let passSel = null;
    for (const sel of ['input[name="password"]', 'input[type="password"]']) {
        try { await page.waitForSelector(sel, { timeout: 2000 }); passSel = sel; break; } catch { /* next */ }
    }
    if (!passSel) throw new Error('Cannot find password field');

    log('Filling login form...');
    await page.click(userSel, { clickCount: 3 });
    await page.type(userSel, process.env.SYNTESYS_USER || '', { delay: 25 });
    await page.click(passSel, { clickCount: 3 });
    await page.type(passSel, process.env.SYNTESYS_PASS || '', { delay: 25 });

    // Submit
    let submitted = false;
    for (const sel of ['button[type="submit"]', 'input[type="submit"]', 'button.btn-primary']) {
        try {
            const btn = await page.$(sel);
            if (btn) {
                await Promise.all([
                    page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }),
                    btn.click(),
                ]);
                submitted = true;
                break;
            }
        } catch { /* try next */ }
    }

    if (!submitted) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }),
            page.keyboard.press('Enter'),
        ]);
    }

    const currentUrl = page.url();
    if (currentUrl.includes('/login') || currentUrl.includes('error')) {
        throw new Error(`Login failed — still on: ${currentUrl}`);
    }
    log(`Logged in. URL: ${currentUrl}`);
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
            return { status: resp.status, body };
        }, url);

        if (result.status === 401) {
            // Sesja wygasła — rzuć błąd żeby caller mógł zalogować ponownie
            throw Object.assign(new Error('HTTP 401 — session expired'), { code: 'SESSION_EXPIRED' });
        }

        if (result.status < 200 || result.status >= 300) {
            throw new Error(`HTTP ${result.status} for ${statusCode}: ${result.body.slice(0, 200)}`);
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
            // Szybkie przywrócenie sesji bez weryfikacji przez pełne ładowanie aplikacji.
            // Nawigujemy do domeny głównej (domcontentloaded = szybko) żeby page.evaluate
            // fetch() działało z credentials:include w kontekście tej domeny.
            // Jeśli sesja wygasła na serwerze, fetchList() rzuci SESSION_EXPIRED → re-login.
            log(`Restoring session (${savedCookies.length} cookies, TTL ok)...`);
            await page.goto(BASE_URL, { waitUntil: 'domcontentloaded', timeout: 15000 });
            await page.setCookie(...savedCookies);
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
