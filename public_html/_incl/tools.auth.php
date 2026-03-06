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
    $_COOKIE["auth_user"]           = $username;
    $_COOKIE["auth_pass_b64"]       = base64_encode($userpass);
    $_SESSION["auth_external_lock"] = "header";
    init_active_centro($_SESSION["auth_data"]);
}

// ── Cookie-based auto-login ───────────────────────────────────────────────────
if (($_SESSION["auth_ok"] ?? false) != true
    && isset($_COOKIE["auth_user"], $_COOKIE["auth_pass_b64"])
) {
    $username = $_COOKIE["auth_user"];
    $userpass = base64_decode($_COOKIE["auth_pass_b64"]);
    $row      = db_get_user($username);
    if ($row && password_verify($userpass, $row['password_hash'])) {
        $_SESSION["auth_user"] = $username;
        $_SESSION["auth_data"] = db_build_auth_data($row);
        $_SESSION["auth_ok"]   = true;
        init_active_centro($_SESSION["auth_data"]);
    }
}

// ── Periodic session reload from DB ──────────────────────────────────────────
if (!empty($_SESSION["auth_ok"]) && !empty($_SESSION["auth_user"])) {
    $load_mode = $AuthConfig["session_load_mode"] ?? '';
    if ($load_mode === "force") {
        $row = db_get_user($_SESSION["auth_user"]);
        if ($row) {
            $_SESSION["auth_data"] = db_build_auth_data($row);
            init_active_centro($_SESSION["auth_data"]);
        }
        $_SESSION["last_reload_time"] = time();
    } elseif ($load_mode !== "never") {
        $last = $_SESSION["last_reload_time"] ?? 0;
        if (time() - $last > 300) {
            $row = db_get_user($_SESSION["auth_user"]);
            if ($row) {
                $_SESSION["auth_data"] = db_build_auth_data($row);
                init_active_centro($_SESSION["auth_data"]);
            }
            $_SESSION["last_reload_time"] = time();
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
