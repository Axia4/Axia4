-- Axia4 Migration 005: Add remember_token_hash to user_sessions
-- Replaces the auth_user + auth_pass_b64 cookie pair with a secure opaque token.
-- The raw token lives only in the browser cookie; only its SHA-256 hash is stored.

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

ALTER TABLE user_sessions ADD COLUMN remember_token_hash TEXT DEFAULT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_user_sessions_remember
    ON user_sessions (remember_token_hash)
    WHERE remember_token_hash IS NOT NULL;
