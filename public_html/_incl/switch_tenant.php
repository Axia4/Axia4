<?php
/**
 * switch_organization.php
 * POST endpoint to switch the active organization for the current user session.
 * Validates the requested organization against the user's allowed organizations before applying.
 */
require_once "tools.session.php";
require_once "tools.security.php";
require_once "db.php";

if (!isset($_SESSION["auth_ok"]) || $_SESSION["auth_ok"] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    die("No autenticado.");
}

$requested = safe_organization_id(
    $_POST['organization']
    ?? $_POST['organizacion']
    ?? $_POST['org']
    ?? $_POST['centro']
    ?? ''
);
$redir     = safe_redir($_POST['redir'] ?? '/');

$organizations = get_user_organizations($_SESSION['auth_data'] ?? []);

if ($requested !== '' && in_array($requested, $organizations, true)) {
    $_SESSION['active_organization'] = $requested;
    $_SESSION['active_organizacion'] = $requested;
    $_SESSION['active_centro'] = $requested;
    // Also update session auth_data so it reflects immediately
    $_SESSION['auth_data']['active_organization'] = $requested;
    $_SESSION['auth_data']['aulatek']['organizacion'] = $requested;
    $_SESSION['auth_data']['aulatek']['organization'] = $requested;
    $_SESSION['auth_data']['aulatek']['centro'] = $requested;
    $_SESSION['auth_data']['entreaulas']['organizacion'] = $requested;
    $_SESSION['auth_data']['entreaulas']['organization'] = $requested;
    $_SESSION['auth_data']['entreaulas']['centro'] = $requested;
}

header("Location: $redir");
exit;
