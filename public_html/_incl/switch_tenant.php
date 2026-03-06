<?php
/**
 * switch_tenant.php
 * POST endpoint to switch the active tenant/centro for the current user session.
 * Validates the requested centro against the user's allowed centros before applying.
 */
require_once "tools.session.php";
require_once "tools.security.php";
require_once "db.php";

if (!isset($_SESSION["auth_ok"]) || $_SESSION["auth_ok"] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    die("No autenticado.");
}

$requested = Sf($_POST['centro'] ?? '');
$redir     = safe_redir($_POST['redir'] ?? '/');

$centros = get_user_centros($_SESSION['auth_data'] ?? []);

if ($requested !== '' && in_array($requested, $centros, true)) {
    $_SESSION['active_centro'] = $requested;
    // Also update session auth_data so it reflects immediately
    $_SESSION['auth_data']['entreaulas']['centro'] = $requested;
}

header("Location: $redir");
exit;
