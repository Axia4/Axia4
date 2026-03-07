<?php
require_once "tools.session.php";
require_once "tools.security.php";
require_once __DIR__ . "/db.php";

// Load auth config from DB (replaces /DATA/AuthConfig.json)
if (!isset($AuthConfig)) {
    $AuthConfig = db_get_all_config();
}

// ── Header-based auth (Axia4Auth/{user}/{pass}) ───────────────────────────────
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (str_starts_with($ua, "Axia4Auth/")) {
    $parts    = explode("/", $ua);
    $username = $parts[1] ?? '';
    $userpass = $parts[2] ?? '';
    $row      = db_get_user($username);
    if (!$row || !password_verify($userpass, $row['password_hash'])) {
        header("HTTP/1.1 403 Forbidden");
        die();
    }
    $_SESSION["auth_user"]          = $username;
    $_SESSION["auth_data"]          = db_build_auth_data($row);
    $_SESSION["auth_ok"]            = true;
    $_SESSION["auth_external_lock"] = "header";
    init_active_org($_SESSION["auth_data"]);
}

// ── Remember-token auto-login ─────────────────────────────────────────────────
// Restores the session from the opaque auth_token cookie (no password stored).
if (($_SESSION["auth_ok"] ?? false) != true && isset($_COOKIE["auth_token"])) {
    $expired    = ["expires" => time() - 3600, "path" => "/", "httponly" => true,
                   "secure" => true, "samesite" => "Lax"];
    $raw_token  = $_COOKIE["auth_token"];
    $token_hash = hash('sha256', $raw_token);
    $sess_row   = db_restore_session_by_remember_token($token_hash);
    if ($sess_row) {
        $username = $sess_row['username'];
        $row      = db_get_user($username);
        if ($row) {
            $_SESSION["auth_user"] = $username;
            $_SESSION["auth_data"] = db_build_auth_data($row);
            $_SESSION["auth_ok"]   = true;
            if (empty($_SESSION["session_created"])) {
                $_SESSION["session_created"] = time();
            }
            init_active_org($_SESSION["auth_data"]);
        } else {
            // User no longer exists — clear the stale cookie
            setcookie("auth_token", "", $expired);
        }
    } else {
        // Token not found (revoked or expired) — clear the stale cookie
        setcookie("auth_token", "", $expired);
    }
}

// ── Validate session is still active in user_sessions (enables revocation) ───
if (!empty($_SESSION["auth_ok"]) && !empty($_SESSION["auth_user"])
    && empty($_SESSION["auth_external_lock"])
) {
    if (!db_session_is_valid($_SESSION["auth_user"])) {
        session_unset();
        session_destroy();
        header("Location: /_login.php?_result=" . urlencode("Tu sesión fue revocada. Inicia sesión de nuevo."));
        die();
    }
}

// ── Periodic session reload from DB ──────────────────────────────────────────
if (!empty($_SESSION["auth_ok"]) && !empty($_SESSION["auth_user"])) {
    $load_mode = $AuthConfig["session_load_mode"] ?? '';
    if ($load_mode === "force") {
        $row = db_get_user($_SESSION["auth_user"]);
        if ($row) {
            $_SESSION["auth_data"] = db_build_auth_data($row);
            init_active_org($_SESSION["auth_data"]);
        }
        $_SESSION["last_reload_time"] = time();
        db_touch_session();
    } elseif ($load_mode !== "never") {
        $last = $_SESSION["last_reload_time"] ?? 0;
        if (time() - $last > 300) {
            $row = db_get_user($_SESSION["auth_user"]);
            if ($row) {
                $_SESSION["auth_data"] = db_build_auth_data($row);
                init_active_org($_SESSION["auth_data"]);
            }
            $_SESSION["last_reload_time"] = time();
            db_touch_session();
        }
        if (!isset($_SESSION["last_reload_time"])) {
            $_SESSION["last_reload_time"] = time();
        }
    }
}

function user_is_authenticated(): bool
{
    return isset($_SESSION["auth_ok"]) && $_SESSION["auth_ok"] === true;
}

function user_has_permission(string $perm): bool
{
    return in_array($perm, $_SESSION["auth_data"]["permissions"] ?? [], true);
}
