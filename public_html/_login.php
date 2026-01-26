<?php
session_start();
if ($_GET["reload_user"] == "1") {
    $user = $_SESSION['auth_user'];
    $userdata = json_decode(file_get_contents("/DATA/Usuarios/$user.json"), true);
    $_SESSION['auth_data'] = $userdata;
    header("Location: /");
    die();
}
if ($_GET["logout"] == "1") {
    session_destroy();
    header("Location: /_login.php");
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
        header("Location: /");
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

    <h1>Iniciar sesión en Axia4</h1>
    
    <form method="post">
        <fieldset class="card" style="border: 2px solid black; border-radius: 6.5px; padding: 10px 25px; max-width: 500px;">
            <label>
                <b>Usuario:</b><br>
                <input required type="text" name="user" placeholder="Ej: PepitoFlores3">
            </label><br><br>
            <label>
                <b>Contraseña:</b><br>
                <input required type="password" name="password" placeholder="Ej: PerroArbolPianoPizza">
            </label>
            <button type="submit">Iniciar sesión</button>
        </fieldset>
    </form>
</div>
<?php require_once "_incl/post-body.php"; ?>