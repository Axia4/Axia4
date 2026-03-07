<?php
require_once "tools.session.php";
require_once "tools.security.php";

$redir = safe_redir($_GET["redir"] ?? "/");
$cookie_options_expired = ["expires" => time() - 3600, "path" => "/", "httponly" => true, "secure" => true, "samesite" => "Lax"];
setcookie("auth_user", "", $cookie_options_expired);
setcookie("auth_pass_b64", "", $cookie_options_expired);
session_unset();
session_destroy();
header("Location: $redir");
die();
