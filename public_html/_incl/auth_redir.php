<?php
require_once "tools.session.php";
require_once "tools.auth.php";

// ¿Is user authenticated?
if (!user_is_authenticated()) {
    header("Location: /_login.php");
    die();
}

// Check if "$APP_CODE" inside user's permissions, and $AUTH_NOPERMS is not set
if (!user_has_permission("$APP_CODE:access") && !$AUTH_NOPERMS) {
    header("Location: /index.php?_resultcolor=red&_result=" . urlencode("No tienes permisos para acceder a $APP_NAME."));
    die();
}
