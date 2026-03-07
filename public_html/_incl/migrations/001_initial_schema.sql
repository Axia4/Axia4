-- Axia4 Migration 001: Initial Schema
-- Converts all JSON file-based storage to a proper relational schema.

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- ── Application configuration (replaces /DATA/AuthConfig.json) ─────────────
CREATE TABLE IF NOT EXISTS config (
    key        TEXT PRIMARY KEY,
    value      TEXT NOT NULL DEFAULT ''
);

-- ── System installation flag (replaces /DATA/SISTEMA_INSTALADO.txt) ────────
-- Stored as a config row: key='installed', value='1'

-- ── Users (replaces /DATA/Usuarios/*.json) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    UNIQUE NOT NULL,
    display_name  TEXT    NOT NULL DEFAULT '',
    email         TEXT    NOT NULL DEFAULT '',
    password_hash TEXT    NOT NULL DEFAULT '',
    permissions   TEXT    NOT NULL DEFAULT '[]',  -- JSON array
    google_auth   INTEGER NOT NULL DEFAULT 0,
    meta          TEXT    NOT NULL DEFAULT '{}',   -- JSON for extra fields
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ── Invitations (replaces /DATA/Invitaciones_de_usuarios.json) ─────────────
CREATE TABLE IF NOT EXISTS invitations (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    code       TEXT    UNIQUE NOT NULL,
    active     INTEGER NOT NULL DEFAULT 1,
    single_use INTEGER NOT NULL DEFAULT 1,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ── Centros/Organizations ───────────────────────────────────────────────────
-- Replaces directory existence at /DATA/entreaulas/Centros/{centro_id}/
CREATE TABLE IF NOT EXISTS centros (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    centro_id  TEXT    UNIQUE NOT NULL,
    name       TEXT    NOT NULL DEFAULT '',
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ── User ↔ Centro assignments (many-to-many) ───────────────────────────────
-- Replaces entreaulas.centro + entreaulas.aulas fields in user JSON.
-- A single user can belong to multiple centros (multi-tenant).
CREATE TABLE IF NOT EXISTS user_centros (
    user_id   INTEGER NOT NULL REFERENCES users(id)           ON DELETE CASCADE,
    centro_id TEXT    NOT NULL REFERENCES centros(centro_id)  ON DELETE CASCADE,
    role      TEXT    NOT NULL DEFAULT '',
    aulas     TEXT    NOT NULL DEFAULT '[]',   -- JSON array of aulario_ids
    PRIMARY KEY (user_id, centro_id)
);

-- ── Aularios (replaces /DATA/entreaulas/Centros/{}/Aularios/{id}.json) ──────
CREATE TABLE IF NOT EXISTS aularios (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    centro_id  TEXT    NOT NULL REFERENCES centros(centro_id) ON DELETE CASCADE,
    aulario_id TEXT    NOT NULL,
    name       TEXT    NOT NULL DEFAULT '',
    icon       TEXT    NOT NULL DEFAULT '',
    extra      TEXT    NOT NULL DEFAULT '{}',  -- JSON for extra config
    UNIQUE (centro_id, aulario_id)
);

-- ── SuperCafe menu (replaces .../SuperCafe/Menu.json) ──────────────────────
CREATE TABLE IF NOT EXISTS supercafe_menu (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    centro_id  TEXT    NOT NULL REFERENCES centros(centro_id) ON DELETE CASCADE,
    data       TEXT    NOT NULL DEFAULT '{}',  -- JSON matching existing format
    updated_at TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE (centro_id)
);

-- ── SuperCafe orders (replaces .../SuperCafe/Comandas/*.json) ───────────────
CREATE TABLE IF NOT EXISTS supercafe_orders (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    centro_id  TEXT    NOT NULL REFERENCES centros(centro_id) ON DELETE CASCADE,
    order_ref  TEXT    NOT NULL,
    fecha      TEXT    NOT NULL,
    persona    TEXT    NOT NULL,
    comanda    TEXT    NOT NULL DEFAULT '',
    notas      TEXT    NOT NULL DEFAULT '',
    estado     TEXT    NOT NULL DEFAULT 'Pedido',
    created_at TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE (centro_id, order_ref)
);

-- ── Comedor menu types (replaces .../Comedor-MenuTypes.json) ────────────────
CREATE TABLE IF NOT EXISTS comedor_menu_types (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    centro_id  TEXT    NOT NULL REFERENCES centros(centro_id) ON DELETE CASCADE,
    aulario_id TEXT    NOT NULL,
    data       TEXT    NOT NULL DEFAULT '[]',  -- JSON array of menu type objs
    UNIQUE (centro_id, aulario_id)
);

-- ── Comedor daily entries (replaces .../Comedor/{ym}/{day}/_datos.json) ─────
CREATE TABLE IF NOT EXISTS comedor_entries (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    centro_id  TEXT    NOT NULL REFERENCES centros(centro_id) ON DELETE CASCADE,
    aulario_id TEXT    NOT NULL,
    year_month TEXT    NOT NULL,   -- "2024-01"
    day        TEXT    NOT NULL,   -- "15"
    data       TEXT    NOT NULL DEFAULT '{}',
    UNIQUE (centro_id, aulario_id, year_month, day)
);

-- ── Diary entries (replaces .../Diario/*.json) ──────────────────────────────
CREATE TABLE IF NOT EXISTS diario_entries (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    centro_id  TEXT    NOT NULL REFERENCES centros(centro_id) ON DELETE CASCADE,
    aulario_id TEXT    NOT NULL,
    entry_date TEXT    NOT NULL,
    data       TEXT    NOT NULL DEFAULT '{}',
    UNIQUE (centro_id, aulario_id, entry_date)
);

-- ── Panel diario per-student data (replaces .../Alumnos/*/Panel.json) ───────
CREATE TABLE IF NOT EXISTS panel_alumno (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    centro_id  TEXT    NOT NULL REFERENCES centros(centro_id) ON DELETE CASCADE,
    aulario_id TEXT    NOT NULL,
    alumno     TEXT    NOT NULL,
    data       TEXT    NOT NULL DEFAULT '{}',
    UNIQUE (centro_id, aulario_id, alumno)
);

-- ── Club event metadata (replaces /DATA/club/IMG/{date}/data.json) ──────────
CREATE TABLE IF NOT EXISTS club_events (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    date_ref TEXT    UNIQUE NOT NULL,
    data     TEXT    NOT NULL DEFAULT '{}'
);

-- ── Club configuration (replaces /DATA/club/config.json) ────────────────────
CREATE TABLE IF NOT EXISTS club_config (
    id   INTEGER PRIMARY KEY CHECK (id = 1),
    data TEXT    NOT NULL DEFAULT '{}'
);
