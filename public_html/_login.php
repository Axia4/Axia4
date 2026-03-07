<?php
require_once "_incl/tools.session.php";
require_once "_incl/tools.security.php";
require_once "_incl/db.php";
if (!isset($AuthConfig)) {
    $AuthConfig = db_get_all_config();
}
$DOMAIN = $_SERVER["HTTP_X_FORWARDED_HOST"] ?? $_SERVER["HTTP_HOST"];

// safe_redir() is provided by _incl/tools.security.php.

if (($_GET["reload_user"] ?? "") === "1") {
    $row = db_get_user($_SESSION["auth_user"] ?? "");
    if (!$row) {
        header("Location: /");
        die();
    }
    $_SESSION['auth_data'] = db_build_auth_data($row);
    init_active_org($_SESSION['auth_data']);
    $redir = safe_redir($_GET["redir"] ?? "/");
    header("Location: $redir");
    die();
}
if (($_GET["google_callback"] ?? "") === "1") {
    if (!isset($AuthConfig["google_client_id"]) || !isset($AuthConfig["google_client_secret"])) {
        die("Error: La autenticación de Google no está configurada.");
    }
    if (!isset($_GET["code"])) {
        die("Error: No se recibió el código de autorización de Google.");
    }

    // Validate CSRF nonce from state parameter
    $state_raw = $_GET["state"] ?? "";
    $state = json_decode(base64_decode($state_raw), true);
    $state_nonce = $state["nonce"] ?? "";
    if (!$state_nonce || !isset($_SESSION["oauth_nonce"]) || !hash_equals($_SESSION["oauth_nonce"], $state_nonce)) {
        die("Error: Estado OAuth inválido. Por favor, inténtalo de nuevo.");
    }
    unset($_SESSION["oauth_nonce"]);
    
    $code = $_GET["code"];
    
    // Intercambiar el código de autorización por un token de acceso
    $token_response = file_get_contents("https://oauth2.googleapis.com/token", false, stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/x-www-form-urlencoded",
            "content" => http_build_query([
                "code" => $code,
                "client_id" => $AuthConfig["google_client_id"],
                "client_secret" => $AuthConfig["google_client_secret"],
                "redirect_uri" => "https://$DOMAIN/_login.php?google_callback=1",
                "grant_type" => "authorization_code"
            ])
        ]
    ]));
    
    $token_data = json_decode($token_response, true);
    
    if (!isset($token_data["access_token"])) {
        die("Error: No se pudo obtener el token de acceso de Google.");
    }
    
    $access_token = $token_data["access_token"];
    
    // Obtener la información del usuario con el token de acceso
    $user_info_response = file_get_contents("https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token=$access_token");
    $user_info = json_decode($user_info_response, true);
    
    if (!isset($user_info["email"])) {
        die("Error: No se pudo obtener la información del usuario de Google.");
    }
    
    $email = $user_info["email"];
    $name  = $user_info["name"] ?? explode("@", $email)[0];
    $username = strtolower($email);
    if ($username === "") {
        die("Error: Dirección de correo inválida.");
    }
    $password  = bin2hex(random_bytes(16));
    $existing  = db_get_user($username);
    if ($existing) {
        $user_row = $existing;
    } else {
        db_upsert_user([
            'username'      => $username,
            'display_name'  => $name,
            'email'         => $email,
            'permissions'   => ['public'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'google_auth'   => true,
            '#'             => 'Este usuario fue creado automáticamente al iniciar sesión con Google por primera vez.',
        ]);
        $user_row = db_get_user($username);
    }

    session_regenerate_id(true);
    $_SESSION['auth_user'] = $username;
    $_SESSION['auth_data'] = db_build_auth_data($user_row);
    $_SESSION['auth_ok']   = true;
    init_active_org($_SESSION['auth_data']);
    $cookie_options = ["expires" => time() + (86400 * 30), "path" => "/", "httponly" => true, "secure" => true, "samesite" => "Lax"];
    setcookie("auth_user",      $username,               $cookie_options);
    setcookie("auth_pass_b64",  base64_encode($password), $cookie_options);

    $redir = safe_redir($state["redir"] ?? "/");

    header("Location: $redir");
    die();
}
if (($_GET["google"] ?? "") === "1") {
    if (!isset($AuthConfig["google_client_id"]) || !isset($AuthConfig["google_client_secret"])) {
        die("Error: La autenticación de Google no está configurada.");
    }
    $url = "https://accounts.google.com/o/oauth2/auth";
    
    // Generate a CSRF nonce and store it in the session
    $oauth_nonce = bin2hex(random_bytes(16));
    $_SESSION["oauth_nonce"] = $oauth_nonce;

    // build the HTTP GET query
    $params = array(
        "response_type" => "code",
        "client_id" => $AuthConfig["google_client_id"],
        "redirect_uri" => "https://$DOMAIN/_login.php?google_callback=1",
        "scope" => "email openid profile",
        "state" => base64_encode(json_encode([
            "redir" => safe_redir($_GET["redir"] ?? "/"),
            "nonce" => $oauth_nonce
        ]))
    );
    
    $request_to = $url . '?' . http_build_query($params);
    
    // forward the user to the login access page on the OAuth 2 server
    header("Location: " . $request_to);
    die();
}
if (($_GET["logout"] ?? "") === "1") {
    $redir = safe_redir($_GET["redir"] ?? "/");
    $cookie_options_expired = ["expires" => time() - 3600, "path" => "/", "httponly" => true, "secure" => true, "samesite" => "Lax"];
    setcookie("auth_user", "", $cookie_options_expired);
    setcookie("auth_pass_b64", "", $cookie_options_expired);
    session_destroy();
    header("Location: $redir");
    die();
}
if (($_GET["clear_session"] ?? "") === "1") {
    session_destroy();
    $redir = safe_redir($_GET["redir"] ?? "/");
    header("Location: $redir");
    die();
}
if (isset($_POST["user"])) {
    $user     = trim(strtolower($_POST["user"]));
    $password = $_POST["password"];
    $row      = db_get_user($user);
    if (!$row || !isset($row["password_hash"])) {
        $_GET["_result"] = "El usuario no existe.";
    } elseif (password_verify($password, $row["password_hash"])) {
        session_regenerate_id(true);
        $_SESSION['auth_user'] = $user;
        $_SESSION['auth_data'] = db_build_auth_data($row);
        $_SESSION['auth_ok']   = true;
        init_active_org($_SESSION['auth_data']);
        $cookie_options = ["expires" => time() + (86400 * 30), "path" => "/", "httponly" => true, "secure" => true, "samesite" => "Lax"];
        setcookie("auth_user",     $user,                    $cookie_options);
        setcookie("auth_pass_b64", base64_encode($password), $cookie_options);
        $redir = safe_redir($_GET["redir"] ?? "/");
        header("Location: $redir");
        die();
    } else {
        $_GET["_result"] = "La contraseña no es correcta.";
    }
}
if (strval(db_get_config('installed')) !== '1') {
    header("Location: /_install.php");
    die();
}
require_once "_incl/pre-body.php"; 
?>

<form method="post" action="?redir=<?= urlencode($_GET["redir"] ?? "/") ?>">
    <div class="card pad" style="max-width: 500px;">
        <h1 style="text-align: center;">Iniciar sesión en Axia4</h1>
        <div>
            <div class="mb-3">
                <label for="user" class="form-label"><b>Usuario o correo electrónico:</b></label>
                <input required type="text" id="user" name="user" class="form-control" placeholder="Ej: pepeflores o pepeflo@gmail.arpa">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label"><b>Contraseña:</b></label>
                <input required type="password" id="password" name="password" class="form-control" placeholder="Ej: PerroPiano482">
            </div>
            <button type="submit" class="btn btn-primary">Iniciar sesión</button>
            <?php if ($AuthConfig["google_client_id"] ?? false && $AuthConfig["google_client_secret"] ?? false): ?>
                <a href="/_login.php?google=1&redir=<?= urlencode($_GET["redir"] ?? "/") ?>" class="btn btn-outline-danger">Google</a>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php require_once "_incl/post-body.php"; ?>