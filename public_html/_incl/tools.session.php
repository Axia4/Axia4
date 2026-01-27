<?php
session_start([ 'cookie_lifetime' => 604800 ]);
session_regenerate_id();
ini_set("session.use_only_cookies", "true");
ini_set("session.use_trans_sid", "false");
