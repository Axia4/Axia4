<?php
require_once "tools.session.php";
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
    $_COOKIE["auth_user"] = $username;
    $_COOKIE["auth_pass_b64"] = base64_encode($userpass);
    $_SESSION["auth_external_lock"] = "header"; // Cannot logout because auth is done via header
}

// If $_SESSION is empty, check for cookies "auth_user" and "auth_pass_b64"
if ($_SESSION["auth_ok"] != true && isset($_COOKIE["auth_user"]) && isset($_COOKIE["auth_pass_b64"])) {
    $username = $_COOKIE["auth_user"];
    $userpass_b64 = $_COOKIE["auth_pass_b64"];
    $userpass = base64_decode($userpass_b64);
    $userdata = json_decode(file_get_contents("/DATA/Usuarios/$username.json"), true);
    if ($userdata && password_verify($userpass, $userdata["password_hash"])) {
        $_SESSION["auth_user"] = $username;
        $_SESSION["auth_data"] = $userdata;
        $_SESSION["auth_ok"] = true;
    }
}

// If session is older than 5min, reload user data
if (isset($_SESSION["auth_ok"]) && $_SESSION["auth_ok"] && isset($_SESSION["auth_user"])) {
    if (isset($_SESSION["last_reload_time"])) {
        $last_reload = $_SESSION["last_reload_time"];
        if (time() - $last_reload > 300) {
            $username = $_SESSION["auth_user"];
            $userdata = json_decode(file_get_contents("/DATA/Usuarios/$username.json"), true);
            $_SESSION["auth_data"] = $userdata;
            $_SESSION["last_reload_time"] = time();
        }
    } else {
        $_SESSION["last_reload_time"] = time();
    }
}

function user_is_authenticated() {
    return isset($_SESSION["auth_ok"]) && $_SESSION["auth_ok"] === true;
}
function user_has_permission($perm) {
    return in_array($perm, $_SESSION["auth_data"]["permissions"] ?? []);
}