'use strict';

require('dotenv').config();

const express = require('express');
const cors    = require('cors');
const db      = require('./lib/db');
const session = require('./lib/session');
const scraper = require('./lib/scraper');

const PORT    = parseInt(process.env.PORT    || '3400', 10);
const API_KEY = process.env.API_KEY || '';

const app = express();

// ─── Middleware ───────────────────────────────────────────────────────────────

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: false }));

// Logowanie żądań
app.use((req, _res, next) => {
    process.stderr.write(`[api] ${new Date().toISOString()} ${req.method} ${req.path}\n`);
    next();
});

// Autentykacja przez X-Api-Key (pomiń dla /health)
app.use((req, res, next) => {
    if (req.path === '/health') return next();
    if (!API_KEY) return next(); // brak klucza = otwarte (dev mode)

    const provided = req.headers['x-api-key'] || req.query.api_key || '';
    if (provided !== API_KEY) {
        return res.status(401).json({ success: false, error: 'Invalid or missing X-Api-Key' });
    }
    next();
});

// ─── Aktywna synchronizacja (blokujemy równoległe reqy) ──────────────────────

let syncInProgress = false;

// ─── Routes ───────────────────────────────────────────────────────────────────

/**
 * GET /health
 * Sprawdza stan serwisu: wersja, sesja Puppeteer, liczniki rekordów.
 */
app.get('/health', (_req, res) => {
    res.json({
        service:    'syntesys-credit-check',
        status:     'ok',
        syncBusy:   syncInProgress,
        session:    session.info(),
        counts:     db.counts(),
        lastSyncAt: db.lastSyncedAt(),
    });
});

/**
 * GET /checks
 * Zwraca listę rekordów z lokalnej bazy SQLite.
 *
 * Query params:
 *   status   — WITH_OPINION | PROCESSING | NO_OPINION | BUSINESS_ERROR
 *   search   — filtr po identifierze (NIP) — LIKE
 *   page     — numer strony (domyślnie 1)
 *   pageSize — rozmiar strony (domyślnie 50, max 500)
 */
app.get('/checks', (req, res) => {
    const status   = req.query.status   || null;
    const search   = req.query.search   || null;
    const page     = Math.max(1, parseInt(req.query.page     || '1',  10));
    const pageSize = Math.min(500, Math.max(1, parseInt(req.query.pageSize || '50', 10)));

    const result = db.query({ status, search, page, pageSize });
    res.json({
        success: true,
        ...result,
    });
});

/**
 * GET /checks/:externalId
 * Zwraca pojedynczy rekord po external_id (ID z API Syntesys).
 */
app.get('/checks/:externalId', (req, res) => {
    const externalId = parseInt(req.params.externalId, 10);
    if (!externalId) return res.status(400).json({ success: false, error: 'Invalid externalId' });

    const record = db.getByExternalId(externalId);
    if (!record) return res.status(404).json({ success: false, error: 'Not found' });

    res.json({ success: true, item: record });
});

/**
 * DELETE /checks/:externalId
 * Usuwa rekord z lokalnej bazy.
 */
app.delete('/checks/:externalId', (req, res) => {
    const externalId = parseInt(req.params.externalId, 10);
    if (!externalId) return res.status(400).json({ success: false, error: 'Invalid externalId' });

    const deleted = db.deleteByExternalId(externalId);
    if (!deleted) return res.status(404).json({ success: false, error: 'Not found' });

    res.json({ success: true });
});

/**
 * POST /sync
 * Uruchamia synchronizację przez Puppeteer i zapisuje wyniki do SQLite.
 *
 * Body (JSON lub form):
 *   list — all | done | waiting | no-advice | error  (domyślnie: all)
 *
 * Czas trwania: do ~90s (SCRAPER_TIMEOUT_MS). Żądanie blokuje do zakończenia.
 * Przy równoległym wywołaniu zwraca 409 Conflict.
 */
app.post('/sync', async (req, res) => {
    if (syncInProgress) {
        return res.status(409).json({
            success: false,
            error:   'Sync already in progress — try again in a moment',
        });
    }

    const list = (req.body.list || req.query.list || 'all').toLowerCase();

    // Przetłumacz listę na kody statusów
    let statusCodes;
    if (list === 'all') {
        statusCodes = Object.values(scraper.LIST_MAP);
    } else if (scraper.LIST_MAP[list]) {
        statusCodes = [scraper.LIST_MAP[list]];
    } else {
        return res.status(400).json({
            success: false,
            error:  `Unknown list "${list}". Allowed: all, done, waiting, no-advice, error`,
        });
    }

    syncInProgress = true;
    const startedAt = Date.now();

    try {
        const data = await scraper.scrape(statusCodes);

        let totalInserted = 0;
        let totalUpdated  = 0;

        for (const [statusCode, items] of Object.entries(data)) {
            if (!Array.isArray(items)) continue;
            const stats = db.upsertBatch(items, statusCode);
            totalInserted += stats.inserted;
            totalUpdated  += stats.updated;
        }

        const durationMs = Date.now() - startedAt;
        res.json({
            success:     true,
            list,
            statuses:    statusCodes,
            inserted:    totalInserted,
            updated:     totalUpdated,
            durationMs,
            counts:      db.counts(),
            lastSyncAt:  db.lastSyncedAt(),
        });
    } catch (e) {
        res.status(500).json({
            success:    false,
            error:      e.message,
            durationMs: Date.now() - startedAt,
        });
    } finally {
        syncInProgress = false;
    }
});

/**
 * POST /fetch
 * Scrapuje dane z Syntesys i zwraca je bezpośrednio w odpowiedzi JSON
 * BEZ zapisywania do lokalnej bazy SQLite.
 *
 * Używaj tego gdy chcesz zapisać dane do zewnętrznej bazy (np. MySQL w CakePHP).
 *
 * Body (JSON lub form):
 *   list — all | done | waiting | no-advice | error  (domyślnie: all)
 *
 * Response:
 *   { success, list, data: { WITH_OPINION: [...], PROCESSING: [...], ... }, durationMs }
 */
app.post('/fetch', async (req, res) => {
    if (syncInProgress) {
        return res.status(409).json({
            success: false,
            error:   'Sync already in progress — try again in a moment',
        });
    }

    const list = (req.body.list || req.query.list || 'all').toLowerCase();

    let statusCodes;
    if (list === 'all') {
        statusCodes = Object.values(scraper.LIST_MAP);
    } else if (scraper.LIST_MAP[list]) {
        statusCodes = [scraper.LIST_MAP[list]];
    } else {
        return res.status(400).json({
            success: false,
            error:  `Unknown list "${list}". Allowed: all, done, waiting, no-advice, error`,
        });
    }

    syncInProgress = true;
    const startedAt = Date.now();

    try {
        const data = await scraper.scrape(statusCodes);

        res.json({
            success:    true,
            list,
            statuses:   statusCodes,
            data,
            durationMs: Date.now() - startedAt,
        });
    } catch (e) {
        res.status(500).json({
            success:    false,
            error:      e.message,
            durationMs: Date.now() - startedAt,
        });
    } finally {
        syncInProgress = false;
    }
});

/**
 * POST /check-opinion
 * Sprawdza opinię kredytową dla podanego NIP przez Puppeteer.
 * Loguje do Syntesys, wypełnia formularz, czeka na wynik (do 90s).
 *
 * Body: { nip: "6572944171" }
 * Response: { success, result: { id, status, advice, companyName, ... } }
 */
app.post('/check-opinion', async (req, res) => {
    const clientType      = (req.body.client_type      || 'pl').trim();
    const nip             = (req.body.nip              || '').replace(/\D/g, '');
    const countryIso      = (req.body.country_iso      || '').trim().toUpperCase();
    const countryName     = (req.body.country_name     || '').trim();
    const searchMode      = (req.body.search_mode      || 'id').trim();
    const identifierValue = (req.body.identifier_value || '').trim();
    const companyName     = (req.body.company_name     || '').trim();
    const city            = (req.body.city             || '').trim();
    const street          = (req.body.street           || '').trim();
    const streetNo        = (req.body.street_no        || '').trim();
    const ehid            = (req.body.ehid             || '').trim();

    if (clientType === 'pl') {
        if (!nip || nip.length < 9 || nip.length > 15) {
            return res.status(400).json({ success: false, error: 'Nieprawidłowy NIP (9–15 cyfr)' });
        }
    } else {
        if (!countryIso) {
            return res.status(400).json({ success: false, error: 'Wymagany kod kraju (country_iso)' });
        }
        if (!identifierValue && !companyName) {
            return res.status(400).json({ success: false, error: 'Wymagany identifierValue lub companyName dla klientów zagranicznych' });
        }
    }

    const startedAt = Date.now();
    try {
        const result = await scraper.checkOpinion({
            type: clientType, nip, countryIso, countryName,
            searchMode, identifierValue, companyName, city, street, streetNo, ehid,
        });
        res.json({ success: true, result, durationMs: Date.now() - startedAt });
    } catch (e) {
        res.status(500).json({ success: false, error: e.message, durationMs: Date.now() - startedAt });
    }
});

/**
 * POST /foreign-search
 * Wyszukuje firmy zagraniczne przez Puppeteer i zwraca listę wyników bez składania wniosku.
 *
 * Body: { country_iso, country_name, search_mode, identifier_value, company_name, city, street, street_no }
 * Response: { success, items: [...], durationMs }
 */
app.post('/foreign-search', async (req, res) => {
    const countryIso      = (req.body.country_iso      || '').trim().toUpperCase();
    const countryName     = (req.body.country_name     || '').trim();
    const searchMode      = (req.body.search_mode      || 'id').trim();
    const identifierValue = (req.body.identifier_value || '').trim();
    const companyName     = (req.body.company_name     || '').trim();
    const city            = (req.body.city             || '').trim();
    const street          = (req.body.street           || '').trim();
    const streetNo        = (req.body.street_no        || '').trim();

    if (!countryIso) {
        return res.status(400).json({ success: false, error: 'Wymagany country_iso' });
    }
    if (!identifierValue && !companyName) {
        return res.status(400).json({ success: false, error: 'Wymagany identifier_value lub company_name' });
    }

    const startedAt = Date.now();
    try {
        const items = await scraper.foreignSearch({
            country_iso: countryIso, country_name: countryName,
            search_mode: searchMode, identifier_value: identifierValue,
            company_name: companyName, city, street, street_no: streetNo,
        });
        res.json({ success: true, items, durationMs: Date.now() - startedAt });
    } catch (e) {
        res.status(500).json({ success: false, error: e.message, durationMs: Date.now() - startedAt });
    }
});

/**
 * DELETE /session
 * Usuwa plik sesji Puppeteer — wymusza ponowne logowanie przy kolejnym /sync.
 */
app.delete('/session', (_req, res) => {
    session.clear();
    res.json({ success: true, message: 'Session cleared — next sync will re-login' });
});

// ─── 404 catch-all ───────────────────────────────────────────────────────────

app.use((_req, res) => {
    res.status(404).json({ success: false, error: 'Not found' });
});

// ─── Start ───────────────────────────────────────────────────────────────────

app.listen(PORT, () => {
    const apiKeyInfo = API_KEY
        ? `API Key: set (${API_KEY.length} chars)`
        : 'API Key: NOT SET (open access — set API_KEY in .env!)';
    process.stderr.write(
        `[server] Syntesys Credit Check microservice started\n` +
        `[server] http://localhost:${PORT}\n` +
        `[server] ${apiKeyInfo}\n`
    );
});
