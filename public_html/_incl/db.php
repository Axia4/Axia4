<?php
/**
 * Axia4 Database Layer
 *
 * Provides a PDO SQLite connection and a lightweight migration runner.
 * All application data previously stored as JSON files under /DATA is now
 * persisted in /DATA/axia4.sqlite.
 *
 * Usage:  db()  → returns the shared PDO instance (auto-migrates on first call).
 */

define('DB_PATH',        '/DATA/axia4.sqlite');
define('MIGRATIONS_DIR', __DIR__ . '/migrations');

// ── Connection ────────────────────────────────────────────────────────────────

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (!is_dir('/DATA')) {
        mkdir('/DATA', 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA synchronous   = NORMAL');

    db_migrate($pdo);

    return $pdo;
}

// ── Migration runner ──────────────────────────────────────────────────────────

function db_migrate(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version    INTEGER PRIMARY KEY,
            applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )'
    );

    $applied = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')
                   ->fetchAll(PDO::FETCH_COLUMN);

    $files = glob(MIGRATIONS_DIR . '/*.{sql,php}', GLOB_BRACE) ?: [];
    sort($files);

    foreach ($files as $file) {
        if (!preg_match('/^(\d+)/', basename($file), $m)) {
            continue;
        }
        $version = (int) $m[1];
        if (in_array($version, $applied, true)) {
            continue;
        }

        if (str_ends_with($file, '.sql')) {
            $pdo->exec((string) file_get_contents($file));
        } elseif (str_ends_with($file, '.php')) {
            // PHP migration receives the connection as $db
            $db = $pdo;
            require $file;
        }

        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
    }
}

// ── Config helpers ────────────────────────────────────────────────────────────

function db_get_config(string $key, $default = null)
{
    $stmt = db()->prepare('SELECT value FROM config WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if ($row === false) {
        return $default;
    }
    $decoded = json_decode($row['value'], true);
    return $decoded !== null ? $decoded : $row['value'];
}

function db_set_config(string $key, $value): void
{
    db()->prepare('INSERT OR REPLACE INTO config (key, value) VALUES (?, ?)')
       ->execute([$key, is_string($value) ? $value : json_encode($value)]);
}

function db_get_all_config(): array
{
    $rows   = db()->query('SELECT key, value FROM config')->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $decoded = json_decode($row['value'], true);
        $result[$row['key']] = ($decoded !== null) ? $decoded : $row['value'];
    }
    return $result;
}

// ── User helpers ──────────────────────────────────────────────────────────────

/** Find a user by username (always lower-cased). Returns DB row or null. */
function db_get_user(string $username): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([strtolower($username)]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/** Return all user rows ordered by username. */
function db_get_all_users(): array
{
    return db()->query('SELECT * FROM users ORDER BY username')->fetchAll();
}

/**
 * Build the auth_data session array from a DB user row.
 * Preserves the same format existing code expects:
 *   auth_data.permissions, auth_data.entreaulas.centro, .role, .aulas, .centros
 */
function db_build_auth_data(array $row): array
{
    $permissions = json_decode($row['permissions'] ?? '[]', true) ?: [];
    $meta        = json_decode($row['meta']        ?? '{}', true) ?: [];

    // Fetch all centro assignments for this user
    $stmt = db()->prepare(
        'SELECT centro_id, role, aulas
           FROM user_centros
          WHERE user_id = ?
          ORDER BY centro_id'
    );
    $stmt->execute([$row['id']]);
    $centro_rows = $stmt->fetchAll();

    $ea = ['centro' => '', 'centros' => [], 'role' => '', 'aulas' => []];
    if (!empty($centro_rows)) {
        $first        = $centro_rows[0];
        $ea['centro'] = $first['centro_id'];          // legacy compat
        $ea['role']   = $first['role'];
        $ea['aulas']  = json_decode($first['aulas'] ?? '[]', true) ?: [];
        $ea['centros']      = array_column($centro_rows, 'centro_id');
        $ea['centros_data'] = $centro_rows;
    }

    return array_merge($meta, [
        'display_name'  => $row['display_name'],
        'email'         => $row['email'],
        'password_hash' => $row['password_hash'],
        'permissions'   => $permissions,
        'entreaulas'    => $ea,
        'google_auth'   => (bool) $row['google_auth'],
    ]);
}

/**
 * Create or update a user.
 * $data keys: username, display_name, email, password_hash, permissions[],
 *             google_auth, entreaulas{centro,centros[],role,aulas[]}, + any extra meta.
 * Returns the user ID.
 */
function db_upsert_user(array $data): int
{
    $pdo      = db();
    $username = strtolower((string) ($data['username'] ?? ''));

    $existing = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $existing->execute([$username]);
    $existing_row = $existing->fetch();

    $permissions = json_encode($data['permissions'] ?? []);
    $meta_skip   = ['username', 'display_name', 'email', 'password_hash',
                    'permissions', 'entreaulas', 'google_auth'];
    $meta = [];
    foreach ($data as $k => $v) {
        if (!in_array($k, $meta_skip, true)) {
            $meta[$k] = $v;
        }
    }

    if ($existing_row) {
        $user_id = (int) $existing_row['id'];
        $upd = $pdo->prepare(
            "UPDATE users SET
                display_name  = ?,
                email         = ?,
                permissions   = ?,
                google_auth   = ?,
                meta          = ?,
                updated_at    = datetime('now')
             WHERE id = ?"
        );
        $upd->execute([
            $data['display_name'] ?? '',
            $data['email']        ?? '',
            $permissions,
            (int) ($data['google_auth'] ?? 0),
            json_encode($meta),
            $user_id,
        ]);
        if (!empty($data['password_hash'])) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([$data['password_hash'], $user_id]);
        }
    } else {
        $pdo->prepare(
            'INSERT INTO users (username, display_name, email, password_hash, permissions, google_auth, meta)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $username,
            $data['display_name'] ?? '',
            $data['email']        ?? '',
            $data['password_hash'] ?? '',
            $permissions,
            (int) ($data['google_auth'] ?? 0),
            json_encode($meta),
        ]);
        $user_id = (int) $pdo->lastInsertId();
    }

    // Update centro assignments when entreaulas data is provided
    if (array_key_exists('entreaulas', $data)) {
        $ea = $data['entreaulas'] ?? [];
        $pdo->prepare('DELETE FROM user_centros WHERE user_id = ?')->execute([$user_id]);

        // Support both legacy single centro and new multi-centro
        $centros = [];
        if (!empty($ea['centros']) && is_array($ea['centros'])) {
            $centros = $ea['centros'];
        } elseif (!empty($ea['centro'])) {
            $centros = [$ea['centro']];
        }
        $role  = $ea['role']  ?? '';
        $aulas = json_encode($ea['aulas'] ?? []);

        $ins_centro = $pdo->prepare('INSERT OR IGNORE INTO centros (centro_id) VALUES (?)');
        $ins_uc = $pdo->prepare(
            'INSERT OR REPLACE INTO user_centros (user_id, centro_id, role, aulas) VALUES (?, ?, ?, ?)'
        );
        foreach ($centros as $cid) {
            if ($cid === '') {
                continue;
            }
            $ins_centro->execute([$cid]);
            $ins_uc->execute([$user_id, $cid, $role, $aulas]);
        }
    }

    return $user_id;
}

/** Delete a user and their centro assignments. */
function db_delete_user(string $username): void
{
    db()->prepare('DELETE FROM users WHERE username = ?')->execute([strtolower($username)]);
}

// ── Centro helpers ────────────────────────────────────────────────────────────

function db_get_centros(): array
{
    return db()->query('SELECT centro_id, name FROM centros ORDER BY centro_id')->fetchAll();
}

function db_get_centro_ids(): array
{
    return db()->query('SELECT centro_id FROM centros ORDER BY centro_id')->fetchAll(PDO::FETCH_COLUMN);
}

// ── Aulario helpers ───────────────────────────────────────────────────────────

/** Get a single aulario config. Returns merged array (name, icon, + extra fields) or null. */
function db_get_aulario(string $centro_id, string $aulario_id): ?array
{
    $stmt = db()->prepare(
        'SELECT name, icon, extra FROM aularios WHERE centro_id = ? AND aulario_id = ?'
    );
    $stmt->execute([$centro_id, $aulario_id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }
    $extra = json_decode($row['extra'] ?? '{}', true) ?: [];
    return array_merge($extra, ['name' => $row['name'], 'icon' => $row['icon']]);
}

/** Get all aularios for a centro as aulario_id → config array. */
function db_get_aularios(string $centro_id): array
{
    $stmt = db()->prepare(
        'SELECT aulario_id, name, icon, extra FROM aularios WHERE centro_id = ? ORDER BY aulario_id'
    );
    $stmt->execute([$centro_id]);
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $extra = json_decode($row['extra'] ?? '{}', true) ?: [];
        $result[$row['aulario_id']] = array_merge($extra, [
            'name' => $row['name'],
            'icon' => $row['icon'],
        ]);
    }
    return $result;
}

// ── SuperCafe helpers ─────────────────────────────────────────────────────────

function db_get_supercafe_menu(string $centro_id): array
{
    $stmt = db()->prepare('SELECT data FROM supercafe_menu WHERE centro_id = ?');
    $stmt->execute([$centro_id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return [];
    }
    return json_decode($row['data'], true) ?: [];
}

function db_set_supercafe_menu(string $centro_id, array $menu): void
{
    db()->prepare('INSERT OR REPLACE INTO supercafe_menu (centro_id, data, updated_at) VALUES (?, ?, datetime(\'now\'))')
       ->execute([$centro_id, json_encode($menu, JSON_UNESCAPED_UNICODE)]);
}

/** Return all SC orders for a centro as an array of rows. */
function db_get_supercafe_orders(string $centro_id): array
{
    $stmt = db()->prepare(
        'SELECT * FROM supercafe_orders WHERE centro_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$centro_id]);
    return $stmt->fetchAll();
}

/** Return a single SC order by ref, or null. */
function db_get_supercafe_order(string $centro_id, string $order_ref): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM supercafe_orders WHERE centro_id = ? AND order_ref = ?'
    );
    $stmt->execute([$centro_id, $order_ref]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/** Create or update an SC order. */
function db_upsert_supercafe_order(
    string $centro_id,
    string $order_ref,
    string $fecha,
    string $persona,
    string $comanda,
    string $notas,
    string $estado
): void {
    db()->prepare(
        'INSERT INTO supercafe_orders (centro_id, order_ref, fecha, persona, comanda, notas, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?)
         ON CONFLICT(centro_id, order_ref) DO UPDATE SET
             fecha    = excluded.fecha,
             persona  = excluded.persona,
             comanda  = excluded.comanda,
             notas    = excluded.notas,
             estado   = excluded.estado'
    )->execute([$centro_id, $order_ref, $fecha, $persona, $comanda, $notas, $estado]);
}

/** Generate the next order_ref for a centro (sc001, sc002, …). */
function db_next_supercafe_ref(string $centro_id): string
{
    $stmt = db()->prepare(
        "SELECT order_ref FROM supercafe_orders WHERE centro_id = ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$centro_id]);
    $last = $stmt->fetchColumn();
    $n = 0;
    if ($last && preg_match('/^sc(\d+)$/', $last, $m)) {
        $n = (int) $m[1];
    }
    return 'sc' . str_pad($n + 1, 3, '0', STR_PAD_LEFT);
}

/** Count 'Deuda' orders for a persona in a centro. */
function db_supercafe_count_debts(string $centro_id, string $persona_key): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM supercafe_orders WHERE centro_id = ? AND persona = ? AND estado = 'Deuda'"
    );
    $stmt->execute([$centro_id, $persona_key]);
    return (int) $stmt->fetchColumn();
}

// ── Comedor helpers ───────────────────────────────────────────────────────────

function db_get_comedor_menu_types(string $centro_id, string $aulario_id): array
{
    $stmt = db()->prepare(
        'SELECT data FROM comedor_menu_types WHERE centro_id = ? AND aulario_id = ?'
    );
    $stmt->execute([$centro_id, $aulario_id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return [];
    }
    return json_decode($row['data'], true) ?: [];
}

function db_set_comedor_menu_types(string $centro_id, string $aulario_id, array $types): void
{
    db()->prepare(
        'INSERT OR REPLACE INTO comedor_menu_types (centro_id, aulario_id, data) VALUES (?, ?, ?)'
    )->execute([$centro_id, $aulario_id, json_encode($types, JSON_UNESCAPED_UNICODE)]);
}

function db_get_comedor_entry(string $centro_id, string $aulario_id, string $ym, string $day): array
{
    $stmt = db()->prepare(
        'SELECT data FROM comedor_entries WHERE centro_id = ? AND aulario_id = ? AND year_month = ? AND day = ?'
    );
    $stmt->execute([$centro_id, $aulario_id, $ym, $day]);
    $row = $stmt->fetch();
    if ($row === false) {
        return [];
    }
    return json_decode($row['data'], true) ?: [];
}

function db_set_comedor_entry(string $centro_id, string $aulario_id, string $ym, string $day, array $data): void
{
    db()->prepare(
        'INSERT OR REPLACE INTO comedor_entries (centro_id, aulario_id, year_month, day, data) VALUES (?, ?, ?, ?, ?)'
    )->execute([$centro_id, $aulario_id, $ym, $day, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

// ── Diario helpers ────────────────────────────────────────────────────────────

function db_get_diario_entry(string $centro_id, string $aulario_id, string $entry_date): array
{
    $stmt = db()->prepare(
        'SELECT data FROM diario_entries WHERE centro_id = ? AND aulario_id = ? AND entry_date = ?'
    );
    $stmt->execute([$centro_id, $aulario_id, $entry_date]);
    $row = $stmt->fetch();
    if ($row === false) {
        return [];
    }
    return json_decode($row['data'], true) ?: [];
}

function db_set_diario_entry(string $centro_id, string $aulario_id, string $entry_date, array $data): void
{
    db()->prepare(
        'INSERT OR REPLACE INTO diario_entries (centro_id, aulario_id, entry_date, data) VALUES (?, ?, ?, ?)'
    )->execute([$centro_id, $aulario_id, $entry_date, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

// ── Panel alumno helpers ──────────────────────────────────────────────────────

function db_get_panel_alumno(string $centro_id, string $aulario_id, string $alumno): array
{
    $stmt = db()->prepare(
        'SELECT data FROM panel_alumno WHERE centro_id = ? AND aulario_id = ? AND alumno = ?'
    );
    $stmt->execute([$centro_id, $aulario_id, $alumno]);
    $row = $stmt->fetch();
    if ($row === false) {
        return [];
    }
    return json_decode($row['data'], true) ?: [];
}

function db_set_panel_alumno(string $centro_id, string $aulario_id, string $alumno, array $data): void
{
    db()->prepare(
        'INSERT OR REPLACE INTO panel_alumno (centro_id, aulario_id, alumno, data) VALUES (?, ?, ?, ?)'
    )->execute([$centro_id, $aulario_id, $alumno, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

// ── Invitation helpers ────────────────────────────────────────────────────────

function db_get_all_invitations(): array
{
    return db()->query('SELECT * FROM invitations ORDER BY code')->fetchAll();
}

function db_get_invitation(string $code): ?array
{
    $stmt = db()->prepare('SELECT * FROM invitations WHERE code = ?');
    $stmt->execute([strtoupper($code)]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

function db_upsert_invitation(string $code, bool $active, bool $single_use): void
{
    db()->prepare(
        'INSERT OR REPLACE INTO invitations (code, active, single_use) VALUES (?, ?, ?)'
    )->execute([strtoupper($code), (int) $active, (int) $single_use]);
}

function db_deactivate_invitation(string $code): void
{
    db()->prepare('UPDATE invitations SET active = 0 WHERE code = ?')->execute([strtoupper($code)]);
}

function db_delete_invitation(string $code): void
{
    db()->prepare('DELETE FROM invitations WHERE code = ?')->execute([strtoupper($code)]);
}

// ── Club helpers ──────────────────────────────────────────────────────────────

function db_get_club_config(): array
{
    $stmt = db()->query('SELECT data FROM club_config WHERE id = 1');
    $row  = $stmt->fetch();
    if ($row === false) {
        return [];
    }
    return json_decode($row['data'], true) ?: [];
}

function db_set_club_config(array $config): void
{
    db()->prepare('INSERT OR REPLACE INTO club_config (id, data) VALUES (1, ?)')
       ->execute([json_encode($config, JSON_UNESCAPED_UNICODE)]);
}

function db_get_all_club_events(): array
{
    return db()->query('SELECT date_ref, data FROM club_events ORDER BY date_ref DESC')->fetchAll();
}

function db_get_club_event(string $date_ref): array
{
    $stmt = db()->prepare('SELECT data FROM club_events WHERE date_ref = ?');
    $stmt->execute([$date_ref]);
    $row = $stmt->fetch();
    if ($row === false) {
        return [];
    }
    return json_decode($row['data'], true) ?: [];
}

function db_set_club_event(string $date_ref, array $data): void
{
    db()->prepare('INSERT OR REPLACE INTO club_events (date_ref, data) VALUES (?, ?)')
       ->execute([$date_ref, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

// ── Multi-tenant helpers ──────────────────────────────────────────────────────

/** Return all centro IDs the authenticated user belongs to. */
function get_user_centros(?array $auth_data = null): array
{
    $data = $auth_data ?? $_SESSION['auth_data'] ?? [];
    $ea   = $data['entreaulas'] ?? [];

    if (!empty($ea['centros']) && is_array($ea['centros'])) {
        return array_values($ea['centros']);
    }
    if (!empty($ea['centro'])) {
        return [$ea['centro']];
    }
    return [];
}

/** Ensure $_SESSION['active_centro'] is set to a valid centro. */
function init_active_centro(?array $auth_data = null): void
{
    $centros = get_user_centros($auth_data);
    if (empty($centros)) {
        $_SESSION['active_centro'] = null;
        return;
    }
    if (!empty($_SESSION['active_centro']) && in_array($_SESSION['active_centro'], $centros, true)) {
        return;
    }
    $_SESSION['active_centro'] = $centros[0];
}
