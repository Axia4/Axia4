<?php
// UserAgent
$ua = $_SERVER['HTTP_USER_AGENT'];
if (str_starts_with($ua, "EntreAulasAuth/")) {
    // Bypass authentication for this specific user agent (used by Ortuella tablets)
    session_start([ 'cookie_lifetime' => 604800 ]);
    $username = explode("/", $ua)[1];
    $userpass = explode("/", $ua)[2];
    $_SESSION["entreaulas_auth_user"] = $username;
    $_SESSION["entreaulas_auth_data"] = json_decode(file_get_contents("/srv/storage/entreaulas/Usuarios/$username.json"), true);
    $_SESSION["entreaulas_auth_ok"] = true;
    session_regenerate_id();
    ini_set("session.use_only_cookies", "true");
    ini_set("session.use_trans_sid", "false");
}
session_start([ 'cookie_lifetime' => 604800 ]);
session_regenerate_id();
ini_set("session.use_only_cookies", "true");
ini_set("session.use_trans_sid", "false");
if (!$_SESSION["entreaulas_auth_ok"]) {
    header("Location: /entreaulas/_login.php");
    die();
}
