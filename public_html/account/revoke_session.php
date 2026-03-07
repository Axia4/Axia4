<?php
/**
 * Revoke a connected device session.
 * POST-only. Requires the user to be authenticated.
 * Accepts: token (session_token hash), redir (safe redirect URL).
 */
require_once "_incl/auth_redir.php";
require_once "../_incl/db.php";
require_once "../_incl/tools.security.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    die();
}

$username = $_SESSION['auth_user'] ?? '';
if ($username === '') {
    header('HTTP/1.1 401 Unauthorized');
    die();
}

$token = preg_replace('/[^a-f0-9]/', '', strtolower($_POST['token'] ?? ''));
$redir = safe_redir($_POST['redir'] ?? '/account/');

if ($token === '') {
    header("Location: $redir");
    die();
}

$current_token = hash('sha256', session_id());

// Prevent revoking the current session through this endpoint
// (users should use the regular logout for that)
if (hash_equals($current_token, $token)) {
    header("Location: $redir");
    die();
}

db_revoke_session($token, $username);

header("Location: $redir");
die();
