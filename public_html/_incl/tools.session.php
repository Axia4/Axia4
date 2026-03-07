<?php
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");
session_start([
    'cookie_lifetime' => 604800,
    'cookie_httponly' => true,
    'cookie_secure'   => true,
    'cookie_samesite' => 'Lax',
]);
