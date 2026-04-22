'use strict';

const Database = require('node-sqlite3-wasm');
const fs        = require('fs');
const path      = require('path');

const DB_PATH = process.env.DB_PATH || './data/credit_checks.db';

// Utwórz katalog jeśli nie istnieje
const dir = path.dirname(DB_PATH);
if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

const db = new Database(DB_PATH);

// WAL dla lepszej wydajności przy równoległych odczytach
db.exec('PRAGMA journal_mode = WAL');
db.exec('PRAGMA foreign_keys = ON');

// ─── Schema ────────────────────────────────────────────────────────────────────

db.exec(`
CREATE TABLE IF NOT EXISTS credit_checks (
    id                          INTEGER PRIMARY KEY AUTOINCREMENT,
    external_id                 INTEGER NOT NULL UNIQUE,
    list_status                 TEXT    NOT NULL,
    identifier                  TEXT,
    identifier_type_code        TEXT,
    country                     TEXT,
    advice_type_code            TEXT,
    advice_reason_code          TEXT,
    advice_json                 TEXT,
    client_json                 TEXT,
    status_code                 TEXT,
    error_type_code             TEXT,
    advice_created_at           TEXT,
    created_by                  TEXT,
    latest_advice_with_opinion  INTEGER NOT NULL DEFAULT 0,
    automatic_renewal_excluded  INTEGER NOT NULL DEFAULT 0,
    created_by_automatic_renewal INTEGER NOT NULL DEFAULT 0,
    synced_at                   TEXT    NOT NULL,
    created_at                  TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at                  TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_cc_list_status  ON credit_checks (list_status);
CREATE INDEX IF NOT EXISTS idx_cc_identifier   ON credit_checks (identifier);
CREATE INDEX IF NOT EXISTS idx_cc_synced_at    ON credit_checks (synced_at);
`);

// ─── Prepared statements ───────────────────────────────────────────────────────

const stmtUpsert = db.prepare(`
INSERT INTO credit_checks (
    external_id, list_status, identifier, identifier_type_code, country,
    advice_type_code, advice_reason_code, advice_json, client_json,
    status_code, error_type_code, advice_created_at, created_by,
    latest_advice_with_opinion, automatic_renewal_excluded,
    created_by_automatic_renewal, synced_at, updated_at
) VALUES (
    @external_id, @list_status, @identifier, @identifier_type_code, @country,
    @advice_type_code, @advice_reason_code, @advice_json, @client_json,
    @status_code, @error_type_code, @advice_created_at, @created_by,
    @latest_advice_with_opinion, @automatic_renewal_excluded,
    @created_by_automatic_renewal, @synced_at, @synced_at
)
ON CONFLICT(external_id) DO UPDATE SET
    list_status                  = excluded.list_status,
    identifier                   = excluded.identifier,
    identifier_type_code         = excluded.identifier_type_code,
    country                      = excluded.country,
    advice_type_code             = excluded.advice_type_code,
    advice_reason_code           = excluded.advice_reason_code,
    advice_json                  = excluded.advice_json,
    client_json                  = excluded.client_json,
    status_code                  = excluded.status_code,
    error_type_code              = excluded.error_type_code,
    advice_created_at            = excluded.advice_created_at,
    created_by                   = excluded.created_by,
    latest_advice_with_opinion   = excluded.latest_advice_with_opinion,
    automatic_renewal_excluded   = excluded.automatic_renewal_excluded,
    created_by_automatic_renewal = excluded.created_by_automatic_renewal,
    synced_at                    = excluded.synced_at,
    updated_at                   = excluded.updated_at
`);

// ─── Public API ────────────────────────────────────────────────────────────────

/**
 * Upsert batch of items from Syntesys API.
 * @param {Object[]} items
 * @param {string}   listStatus
 * @returns {{inserted: number, updated: number}}
 */
function upsertBatch(items, listStatus) {
    const now      = new Date().toISOString();
    let inserted   = 0;
    let updated    = 0;

    const upsertMany = db.transaction((rows) => {
        for (const item of rows) {
            const req    = item.request    || {};
            const advice = item.advice     || null;
            const client = item.client     || null;

            const existing = db.prepare('SELECT id FROM credit_checks WHERE external_id = ?')
                .get(item.id);

            stmtUpsert.run({
                external_id:                  item.id,
                list_status:                  listStatus,
                identifier:                   req.identifier                || null,
                identifier_type_code:         req.identifierTypeCode        || null,
                country:                      req.country                   || null,
                advice_type_code:             advice?.typeCode              || null,
                advice_reason_code:           advice?.reasonCode            || null,
                advice_json:                  advice ? JSON.stringify(advice) : null,
                client_json:                  client ? JSON.stringify(client) : null,
                status_code:                  item.statusCode               || null,
                error_type_code:              item.errorTypeCode            || null,
                advice_created_at:            item.created                  || null,
                created_by:                   item.createdBy                || null,
                latest_advice_with_opinion:   item.latestAdviceWithOpinion  ? 1 : 0,
                automatic_renewal_excluded:   item.automaticRenewalExcluded ? 1 : 0,
                created_by_automatic_renewal: item.createdByAutomaticRenewal ? 1 : 0,
                synced_at:                    now,
            });

            if (existing) { updated++; } else { inserted++; }
        }
    });

    upsertMany(items);
    return { inserted, updated };
}

/**
 * Query credit checks.
 * @param {Object} opts
 * @returns {{items: Object[], total: number}}
 */
function query({ status, search, page = 1, pageSize = 50 } = {}) {
    const conditions = [];
    const params     = [];

    if (status)  { conditions.push('list_status = ?'); params.push(status); }
    if (search)  { conditions.push('identifier LIKE ?'); params.push('%' + search + '%'); }

    const where    = conditions.length ? 'WHERE ' + conditions.join(' AND ') : '';
    const offset   = (page - 1) * pageSize;

    const total = db.prepare(`SELECT COUNT(*) as n FROM credit_checks ${where}`).get(...params).n;
    const items = db.prepare(
        `SELECT * FROM credit_checks ${where} ORDER BY advice_created_at DESC, id DESC LIMIT ? OFFSET ?`
    ).all(...params, pageSize, offset);

    return { items, total, page, pageSize };
}

/**
 * Get single record by external_id.
 */
function getByExternalId(externalId) {
    return db.prepare('SELECT * FROM credit_checks WHERE external_id = ?').get(externalId) || null;
}

/**
 * Delete by external_id.
 * @returns {boolean}
 */
function deleteByExternalId(externalId) {
    const result = db.prepare('DELETE FROM credit_checks WHERE external_id = ?').run(externalId);
    return result.changes > 0;
}

/**
 * Counts per list_status.
 */
function counts() {
    const rows = db.prepare(
        `SELECT list_status, COUNT(*) as n FROM credit_checks GROUP BY list_status`
    ).all();
    const map = {};
    for (const r of rows) map[r.list_status] = r.n;
    return map;
}

/**
 * Last sync time.
 */
function lastSyncedAt() {
    const row = db.prepare('SELECT synced_at FROM credit_checks ORDER BY synced_at DESC LIMIT 1').get();
    return row?.synced_at ?? null;
}

module.exports = { upsertBatch, query, getByExternalId, deleteByExternalId, counts, lastSyncedAt };
