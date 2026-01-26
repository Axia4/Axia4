<?php
session_start([ 'cookie_lifetime' => 604800 ]);
session_regenerate_id();
ini_set("session.use_only_cookies", "true");
ini_set("session.use_trans_sid", "false");

$ua = $_SERVER['HTTP_USER_AGENT'];
if (str_starts_with($ua, "Axia4Auth/")) {
    $username = explode("/", $ua)[1];
    $userpass = explode("/", $ua)[2];
    $userdata = json_decode(file_get_contents("/DATA/Usuarios/$username.json"), true);
    if (!$userdata) {
        header("HTTP/1.1 403 Forbidden");
        die();
    }
    if (password_verify($userpass, $userdata["password"])) {
        header("HTTP/1.1 403 Forbidden");
        die();
    }
    $_SESSION["auth_user"] = $username;
    $_SESSION["auth_data"] = $userdata;
    $_SESSION["auth_ok"] = true;
}

// ¿Is user authenticated?
if (!$_SESSION["auth_ok"]) {
    header("Location: /_login.php");
    die();
}

// Check if "$APP_CODE" inside user's permissions, and $AUTH_NOPERMS is not set
if (!in_array("$APP_CODE:access", $_SESSION["auth_data"]["permissions"]) && !$AUTH_NOPERMS) {
    header("Location: /index.php?_resultcolor=red&_result=" . urlencode("No tienes permisos para acceder a $APP_NAME."));
    die();
}
