-- filepath: /workspaces/Axia4/public_html/_incl/migrations/003_organizaciones.sql
-- Axia4 Migration 003: Rename centros to organizaciones
-- Migrates the centros table to organizaciones with org_id and org_name columns.

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- ── Create new organizaciones table ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS organizaciones (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    org_id     TEXT    UNIQUE NOT NULL,
    org_name   TEXT    NOT NULL DEFAULT '',
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ── Migrate data from centros to organizaciones ──────────────────────────────
INSERT INTO organizaciones (org_id, org_name, created_at)
SELECT centro_id, COALESCE(name, centro_id), created_at
FROM centros
WHERE NOT EXISTS (
    SELECT 1 FROM organizaciones WHERE org_id = centros.centro_id
);

-- ── Update foreign key references in user_centros ──────────────────────────────
-- user_centros.centro_id → user_centros.org_id (rename column if needed via recreation)
-- For SQLite, we need to recreate the table due to FK constraint changes

CREATE TABLE user_centros_new (
    user_id   INTEGER NOT NULL REFERENCES users(id)               ON DELETE CASCADE,
    org_id    TEXT    NOT NULL REFERENCES organizaciones(org_id)  ON DELETE CASCADE,
    role      TEXT    NOT NULL DEFAULT '',
    ea_aulas     TEXT    NOT NULL DEFAULT '[]',
    PRIMARY KEY (user_id, org_id)
);

INSERT INTO user_centros_new (user_id, org_id, role, ea_aulas)
SELECT user_id, centro_id, role, aulas FROM user_centros;

DROP TABLE user_centros;
ALTER TABLE user_centros_new RENAME TO user_orgs;

-- ── Update foreign key references in aularios ──────────────────────────────────
CREATE TABLE aularios_new (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    org_id     TEXT    NOT NULL REFERENCES organizaciones(org_id) ON DELETE CASCADE,
    aulario_id TEXT    NOT NULL,
    name       TEXT    NOT NULL DEFAULT '',
    icon       TEXT    NOT NULL DEFAULT '',
    extra      TEXT    NOT NULL DEFAULT '{}',
    UNIQUE (org_id, aulario_id)
);

INSERT INTO aularios_new (id, org_id, aulario_id, name, icon, extra)
SELECT id, centro_id, aulario_id, name, icon, extra FROM aularios;

DROP TABLE aularios;
ALTER TABLE aularios_new RENAME TO aularios;

-- ── Update foreign key references in remaining tables ──────────────────────────
-- supercafe_menu
CREATE TABLE supercafe_menu_new (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    org_id     TEXT    NOT NULL REFERENCES organizaciones(org_id) ON DELETE CASCADE,
    data       TEXT    NOT NULL DEFAULT '{}',
    updated_at TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE (org_id)
);

INSERT INTO supercafe_menu_new (id, org_id, data, updated_at)
SELECT id, centro_id, data, updated_at FROM supercafe_menu;

DROP TABLE supercafe_menu;
ALTER TABLE supercafe_menu_new RENAME TO supercafe_menu;

-- supercafe_orders
CREATE TABLE supercafe_orders_new (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    org_id     TEXT    NOT NULL REFERENCES organizaciones(org_id) ON DELETE CASCADE,
    order_ref  TEXT    NOT NULL,
    fecha      TEXT    NOT NULL,
    persona    TEXT    NOT NULL,
    comanda    TEXT    NOT NULL DEFAULT '',
    notas      TEXT    NOT NULL DEFAULT '',
    estado     TEXT    NOT NULL DEFAULT 'Pedido',
    created_at TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE (org_id, order_ref)
);

INSERT INTO supercafe_orders_new (id, org_id, order_ref, fecha, persona, comanda, notas, estado, created_at)
SELECT id, centro_id, order_ref, fecha, persona, comanda, notas, estado, created_at FROM supercafe_orders;

DROP TABLE supercafe_orders;
ALTER TABLE supercafe_orders_new RENAME TO supercafe_orders;

-- comedor_menu_types
CREATE TABLE comedor_menu_types_new (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    org_id     TEXT    NOT NULL REFERENCES organizaciones(org_id) ON DELETE CASCADE,
    aulario_id TEXT    NOT NULL,
    data       TEXT    NOT NULL DEFAULT '[]',
    UNIQUE (org_id, aulario_id)
);

INSERT INTO comedor_menu_types_new (id, org_id, aulario_id, data)
SELECT id, centro_id, aulario_id, data FROM comedor_menu_types;

DROP TABLE comedor_menu_types;
ALTER TABLE comedor_menu_types_new RENAME TO comedor_menu_types;

-- comedor_entries
CREATE TABLE comedor_entries_new (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    org_id     TEXT    NOT NULL REFERENCES organizaciones(org_id) ON DELETE CASCADE,
    aulario_id TEXT    NOT NULL,
    year_month TEXT    NOT NULL,
    day        TEXT    NOT NULL,
    data       TEXT    NOT NULL DEFAULT '{}',
    UNIQUE (org_id, aulario_id, year_month, day)
);

INSERT INTO comedor_entries_new (id, org_id, aulario_id, year_month, day, data)
SELECT id, centro_id, aulario_id, year_month, day, data FROM comedor_entries;

DROP TABLE comedor_entries;
ALTER TABLE comedor_entries_new RENAME TO comedor_entries;

-- diario_entries
CREATE TABLE diario_entries_new (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    org_id     TEXT    NOT NULL REFERENCES organizaciones(org_id) ON DELETE CASCADE,
    aulario_id TEXT    NOT NULL,
    entry_date TEXT    NOT NULL,
    data       TEXT    NOT NULL DEFAULT '{}',
    UNIQUE (org_id, aulario_id, entry_date)
);

INSERT INTO diario_entries_new (id, org_id, aulario_id, entry_date, data)
SELECT id, centro_id, aulario_id, entry_date, data FROM diario_entries;

DROP TABLE diario_entries;
ALTER TABLE diario_entries_new RENAME TO diario_entries;

-- panel_alumno
CREATE TABLE panel_alumno_new (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    org_id     TEXT    NOT NULL REFERENCES organizaciones(org_id) ON DELETE CASCADE,
    aulario_id TEXT    NOT NULL,
    alumno     TEXT    NOT NULL,
    data       TEXT    NOT NULL DEFAULT '{}',
    UNIQUE (org_id, aulario_id, alumno)
);

INSERT INTO panel_alumno_new (id, org_id, aulario_id, alumno, data)
SELECT id, centro_id, aulario_id, alumno, data FROM panel_alumno;

DROP TABLE panel_alumno;
ALTER TABLE panel_alumno_new RENAME TO panel_alumno;

-- ── Drop old centros table ─────────────────────────────────────────────────────
DROP TABLE IF EXISTS centros;

-- ── Verify migration ───────────────────────────────────────────────────────────
-- SELECT COUNT(*) as total_organizaciones FROM organizaciones;