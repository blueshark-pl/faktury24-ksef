'use strict';

const fs   = require('fs');
const path = require('path');

const SESSION_PATH   = process.env.SESSION_PATH   || './data/syntesys-session.json';
const SESSION_TTL_MS = parseInt(process.env.SESSION_TTL_MS || '28800000', 10); // 8h

function _ensureDir() {
    const dir = path.dirname(SESSION_PATH);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

/**
 * Load session data from file. Returns null if not found or expired.
 * @returns {{ token?: string }|null}
 */
function load() {
    try {
        if (!fs.existsSync(SESSION_PATH)) return null;
        const { savedAt, ...data } = JSON.parse(fs.readFileSync(SESSION_PATH, 'utf8'));
        if (!savedAt) return null;
        if (Date.now() - savedAt > SESSION_TTL_MS) {
            clear();
            return null;
        }
        return data;
    } catch {
        return null;
    }
}

/**
 * Save session data to file.
 * @param {{ token?: string }} data
 */
function save(data) {
    _ensureDir();
    fs.writeFileSync(SESSION_PATH, JSON.stringify({ savedAt: Date.now(), ...data }), 'utf8');
}

/**
 * Delete session file.
 */
function clear() {
    try {
        if (fs.existsSync(SESSION_PATH)) fs.unlinkSync(SESSION_PATH);
    } catch { /* ignore */ }
}

/**
 * Info about current session (for health endpoint).
 */
function info() {
    try {
        if (!fs.existsSync(SESSION_PATH)) return { active: false };
        const data = JSON.parse(fs.readFileSync(SESSION_PATH, 'utf8'));
        if (!data.savedAt) return { active: false };
        const ageMs     = Date.now() - data.savedAt;
        const remaining = SESSION_TTL_MS - ageMs;
        if (remaining <= 0) return { active: false };
        return {
            active:           true,
            savedAt:          new Date(data.savedAt).toISOString(),
            expiresAt:        new Date(data.savedAt + SESSION_TTL_MS).toISOString(),
            remainingMinutes: Math.floor(remaining / 60000),
            hasToken:         !!data.token,
        };
    } catch {
        return { active: false };
    }
}

module.exports = { load, save, clear, info };
