<?php
require_once "tools.session.php";
require_once "tools.security.php";
require_once "db.php";

$redir = safe_redir($_GET["redir"] ?? "/");
$cookie_options_expired = ["expires" => time() - 3600, "path" => "/", "httponly" => true, "secure" => true, "samesite" => "Lax"];
setcookie("auth_token", "", $cookie_options_expired);
db_delete_session();
session_unset();
session_destroy();
header("Location: $redir");
die();
