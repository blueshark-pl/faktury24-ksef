#!/usr/bin/env node
/**
 * Syntesys Credit Check Scraper
 * ==============================
 * Loguje się do https://syntesys.pl i pobiera dane z API credit-check-advices.
 *
 * Wymagania:
 *   node >= 18, npm i (puppeteer ^22)
 *
 * Użycie:
 *   SYNTESYS_USER=login@firma.pl SYNTESYS_PASS=haslo \
 *     node bin/syntesys-scraper.js [all|done|waiting|no-advice|error]
 *
 * Wyjście: JSON na stdout
 *   { "success": true, "data": { "WITH_OPINION": [...], "PROCESSING": [...], ... } }
 *   { "success": false, "error": "opis błędu" }
 *
 * Plik sesji:
 *   tmp/syntesys-session.json  — cookies zachowane po zalogowaniu
 *   Jeśli jest ważny (< 8h) — logowania można pominąć.
 */

'use strict';

const puppeteer = require('puppeteer');
const fs        = require('fs');
const path      = require('path');

// ─── Konfiguracja ────────────────────────────────────────────────────────────

const BASE_URL       = 'https://syntesys.pl';
const API_BASE       = 'https://syntesys.pl/syntesys-api';
const LOGIN_URL      = `${BASE_URL}/login`;
const SESSION_FILE   = path.resolve(__dirname, '..', 'tmp', 'syntesys-session.json');
const SESSION_TTL_MS = 8 * 60 * 60 * 1000; // 8 godzin

const USERNAME = process.env.SYNTESYS_USER || '';
const PASSWORD = process.env.SYNTESYS_PASS || '';

// arg: all | done | waiting | no-advice | error
const ARG = (process.argv[2] || 'all').toLowerCase();

const LIST_MAP = {
    'done':       'WITH_OPINION',
    'waiting':    'PROCESSING',
    'no-advice':  'NO_OPINION',
    'error':      'BUSINESS_ERROR',
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function log(msg) {
    // Logi na stderr, żeby nie zanieczyszczać stdout (JSON output)
    process.stderr.write('[syntesys-scraper] ' + msg + '\n');
}

function readSession() {
    try {
        if (!fs.existsSync(SESSION_FILE)) return null;
        const raw  = fs.readFileSync(SESSION_FILE, 'utf8');
        const data = JSON.parse(raw);
        if (!data.savedAt || Date.now() - data.savedAt > SESSION_TTL_MS) {
            log('Sesja wygasła lub brak savedAt — wymaga ponownego logowania');
            return null;
        }
        return data.cookies || null;
    } catch (e) {
        return null;
    }
}

function saveSession(cookies) {
    try {
        const dir = path.dirname(SESSION_FILE);
        if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
        fs.writeFileSync(SESSION_FILE, JSON.stringify({ savedAt: Date.now(), cookies }), 'utf8');
    } catch (e) {
        log('Nie można zapisać sesji: ' + e.message);
    }
}

function clearSession() {
    try { if (fs.existsSync(SESSION_FILE)) fs.unlinkSync(SESSION_FILE); } catch (_) {}
}

// ─── Logowanie ────────────────────────────────────────────────────────────────

async function login(page) {
    log('Otwieranie strony logowania: ' + LOGIN_URL);
    await page.goto(LOGIN_URL, { waitUntil: 'networkidle2', timeout: 45000 });

    // Czekamy na pojawienie się formularza
    // Angular może renderować asynchronicznie — próbujemy kilku selektorów
    const userSelectors = [
        'input[name="username"]',
        'input[type="email"]',
        'input[type="text"]',
        'input[id*="login"]',
        'input[id*="user"]',
        'input[placeholder*="login"]',
        'input[placeholder*="Login"]',
        'input[placeholder*="email"]',
        'input[placeholder*="Email"]',
    ];
    const passSelectors = [
        'input[name="password"]',
        'input[type="password"]',
    ];

    let userInput = null;
    for (const sel of userSelectors) {
        try {
            await page.waitForSelector(sel, { timeout: 3000 });
            userInput = sel;
            break;
        } catch (_) {}
    }
    if (!userInput) {
        throw new Error('Nie znaleziono pola login na stronie ' + LOGIN_URL);
    }

    let passInput = null;
    for (const sel of passSelectors) {
        try {
            await page.waitForSelector(sel, { timeout: 2000 });
            passInput = sel;
            break;
        } catch (_) {}
    }
    if (!passInput) {
        throw new Error('Nie znaleziono pola hasła');
    }

    log('Wypełniam formularz logowania...');
    await page.click(userInput, { clickCount: 3 });
    await page.type(userInput, USERNAME, { delay: 30 });

    await page.click(passInput, { clickCount: 3 });
    await page.type(passInput, PASSWORD, { delay: 30 });

    // Kliknij przycisk submit
    const submitSelectors = [
        'button[type="submit"]',
        'input[type="submit"]',
        'button.btn-primary',
        'button.login-btn',
        'button:contains("Zaloguj")',
    ];

    let clicked = false;
    for (const sel of submitSelectors) {
        try {
            const btn = await page.$(sel);
            if (btn) {
                await Promise.all([
                    page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }),
                    btn.click(),
                ]);
                clicked = true;
                break;
            }
        } catch (_) {}
    }

    if (!clicked) {
        // Fallback: Enter na polu hasła
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }),
            page.keyboard.press('Enter'),
        ]);
    }

    // Sprawdź czy logowanie się powiodło (nie powinniśmy być nadal na /login)
    const currentUrl = page.url();
    if (currentUrl.includes('/login') || currentUrl.includes('error') || currentUrl.includes('invalid')) {
        throw new Error('Logowanie nieudane — nadal na stronie logowania: ' + currentUrl);
    }

    log('Zalogowano pomyślnie. URL: ' + currentUrl);
}

// ─── Pobieranie jednej listy (z paginacją) ────────────────────────────────────

async function fetchList(page, listStatus) {
    const items    = [];
    let   pageNo   = 1;
    const pageSize = 100;

    log(`Pobieranie listy ${listStatus}...`);

    while (true) {
        // Dla WITH_OPINION potrzebujemy filter.activeOnly=true
        let url = `${API_BASE}/insurance/credit-check-advices?filter.listStatus=${listStatus}&page.no=${pageNo}&page.size=${pageSize}&sortDirection=DESC&sortBy=sendDate`;
        if (listStatus === 'WITH_OPINION') {
            url += '&filter.activeOnly=true';
        }

        log(`  Strona ${pageNo}: ${url}`);

        let result;
        try {
            result = await page.evaluate(async (fetchUrl) => {
                const resp = await fetch(fetchUrl, {
                    method:      'GET',
                    credentials: 'include',
                    headers:     { 'Accept': 'application/json' },
                });
                if (!resp.ok) {
                    return { __httpError: resp.status, __body: await resp.text() };
                }
                return resp.json();
            }, url);
        } catch (e) {
            throw new Error(`Błąd fetch dla ${listStatus} str.${pageNo}: ${e.message}`);
        }

        if (result && result.__httpError) {
            // 401 = sesja wygasła
            if (result.__httpError === 401) {
                throw new Error('HTTP 401 — sesja wygasła');
            }
            throw new Error(`HTTP ${result.__httpError} dla ${listStatus}: ${result.__body}`);
        }

        const batch = Array.isArray(result)
            ? result
            : (result && Array.isArray(result.items) ? result.items : []);

        if (batch.length === 0) break;
        items.push(...batch);

        // Sprawdź czy mamy totalCount / totalItems do walidacji
        const total = result.totalCount ?? result.totalItems ?? null;
        if (total !== null && items.length >= total) break;

        if (batch.length < pageSize) break;
        pageNo++;
    }

    log(`  Pobrano ${items.length} rekordów dla ${listStatus}`);
    return items;
}

// ─── Główna funkcja ──────────────────────────────────────────────────────────

async function main() {
    if (!USERNAME || !PASSWORD) {
        process.stdout.write(JSON.stringify({
            success: false,
            error:   'Brak danych logowania (SYNTESYS_USER / SYNTESYS_PASS)',
        }));
        process.exit(1);
    }

    // Ustal które statusy pobieramy
    let statusesToFetch;
    if (ARG === 'all') {
        statusesToFetch = Object.values(LIST_MAP);
    } else if (LIST_MAP[ARG]) {
        statusesToFetch = [LIST_MAP[ARG]];
    } else {
        process.stdout.write(JSON.stringify({
            success: false,
            error:   `Nieznany argument: ${ARG}. Dozwolone: all, done, waiting, no-advice, error`,
        }));
        process.exit(1);
    }

    const browser = await puppeteer.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-extensions',
            '--disable-gpu',
        ],
    });

    try {
        const page = await browser.newPage();

        // Ustaw User-Agent na standardowy przeglądarkowy
        await page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
        );

        // Spróbuj załadować zachowaną sesję
        const savedCookies = readSession();
        if (savedCookies && savedCookies.length > 0) {
            log('Ładowanie zapisanej sesji...');
            await page.setCookie(...savedCookies);
        }

        // Sprawdź czy sesja jest ważna (navigate do chronionej strony)
        let sessionValid = false;
        if (savedCookies) {
            try {
                await page.goto(`${BASE_URL}/insurance/credit-check/requests-lists/(type:done)`, {
                    waitUntil: 'networkidle2',
                    timeout:   20000,
                });
                const url = page.url();
                sessionValid = !url.includes('/login');
                if (sessionValid) {
                    log('Sesja z pliku jest ważna');
                } else {
                    log('Sesja z pliku wygasła — pomiń, zaloguj ponownie');
                    clearSession();
                }
            } catch (_) {
                sessionValid = false;
            }
        }

        // Zaloguj jeśli sesja nieważna
        if (!sessionValid) {
            let retries = 2;
            while (retries-- > 0) {
                try {
                    await login(page);
                    break;
                } catch (e) {
                    if (retries === 0) throw e;
                    log(`Błąd logowania, próba ponowna: ${e.message}`);
                    await new Promise(r => setTimeout(r, 2000));
                }
            }
            // Zapisz cookies po zalogowaniu
            const cookies = await page.cookies();
            saveSession(cookies);
        }

        // Pobierz dane
        const output = {};
        for (const status of statusesToFetch) {
            try {
                output[status] = await fetchList(page, status);
            } catch (e) {
                // Jeśli 401 podczas fetch — sesja wygasła w trakcie
                if (e.message.includes('401')) {
                    log('Sesja wygasła podczas fetch — loguję ponownie');
                    clearSession();
                    await login(page);
                    const cookies = await page.cookies();
                    saveSession(cookies);
                    // Spróbuj jeszcze raz
                    output[status] = await fetchList(page, status);
                } else {
                    output[status] = [];
                    log(`BŁĄD dla ${status}: ${e.message}`);
                }
            }
        }

        process.stdout.write(JSON.stringify({ success: true, data: output }));

    } catch (e) {
        process.stdout.write(JSON.stringify({ success: false, error: e.message }));
        process.exit(1);
    } finally {
        await browser.close();
    }
}

main().catch(e => {
    process.stdout.write(JSON.stringify({ success: false, error: e.message }));
    process.exit(1);
});
