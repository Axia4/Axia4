-- Axia4 Migration 004: User Sessions (Dispositivos conectados)
-- Tracks active authenticated sessions so users can see and revoke connected devices.

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS user_sessions (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    session_token TEXT   UNIQUE NOT NULL,   -- SHA-256 hash of the PHP session_id()
    username      TEXT   NOT NULL,
    ip_address    TEXT   NOT NULL DEFAULT '',
    user_agent    TEXT   NOT NULL DEFAULT '',
    created_at    TEXT   NOT NULL DEFAULT (datetime('now')),
    last_active   TEXT   NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_user_sessions_username ON user_sessions (username);
