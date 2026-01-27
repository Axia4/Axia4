<?php
session_start();
if ($_GET["reload_user"] == "1") {
    $user = $_SESSION['auth_user'];
    $userdata = json_decode(file_get_contents("/DATA/Usuarios/$user.json"), true);
    $_SESSION['auth_data'] = $userdata;
    $redir = $_GET["redir"] ?? "/";
    header("Location: $redir");
    die();
}
if ($_GET["logout"] == "1") {
    $redir = $_GET["redir"] ?? "/";
    unset($_COOKIE["auth_user"]);
    unset($_COOKIE["auth_pass_b64"]);
    session_destroy();
    header("Location: $redir");
    die();
}
if ($_GET["clear_session"] == "1") {
    session_destroy();
    $redir = $_GET["redir"] ?? "/";
    header("Location: $redir");
    die();
}
if (isset($_POST["user"])) {
    $valid = "";
    $user = trim(strtolower($_POST["user"]));
    $password = $_POST["password"];
    $userdata = json_decode(file_get_contents("/DATA/Usuarios/$user.json"), true);
    if (!isset($userdata["password_hash"])) {
        $_GET["_result"] = "El usuario no existe.";
    }

    $hash = $userdata["password_hash"];
    if (password_verify($password, $hash)) {
        $_SESSION['auth_user'] = $user;
        $_SESSION['auth_data'] = $userdata;
        $_SESSION['auth_ok'] = true;
        setcookie("auth_user", $user, time() + (86400 * 30), "/");
        setcookie("auth_pass_b64", base64_encode($password), time() + (86400 * 30), "/");
        $redir = $_GET["redir"] ?? "/";
        header("Location: $redir");
        die();
    } else {
        $_GET["_result"] = "La contraseña no es correcta.";
    }

}
if (!file_exists("/DATA/SISTEMA_INSTALADO.txt")) {
    header("Location: /_install.php");
    die();
}
require_once "_incl/pre-body.php"; ?>
<div class="card pad">
    <div class="card-body">
        <h1 class="card-title">Iniciar sesión en Axia4</h1>
        
        <form method="post">
            <div class="card pad" style="max-width: 500px;">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="user" class="form-label"><b>Usuario:</b></label>
                        <input required type="text" id="user" name="user" class="form-control" placeholder="Ej: PepitoFlores3">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><b>Contraseña:</b></label>
                        <input required type="password" id="password" name="password" class="form-control" placeholder="Ej: PerroArbolPianoPizza">
                    </div>
                    <button type="submit" class="btn btn-primary">Iniciar sesión</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php require_once "_incl/post-body.php"; ?>