<?php
session_start();
if ($_GET["reload_users"] == "1") {
    $user = $_SESSION['entreaulas_auth_user'];
    $userdata = json_decode(file_get_contents("/DATA/entreaulas/Usuarios/$user.json"), true);
    $_SESSION['entreaulas_auth_data'] = $userdata;
    header("Location: /entreaulas/");
    die();
}
if ($_GET["logout"] == "1") {
    session_destroy();
    header("Location: /entreaulas/_login.php");
    die();
}
if (isset($_POST["user"])) {
    $valid = "";
    $user = trim(strtolower($_POST["user"]));
    $password = $_POST["password"];
    $userdata = json_decode(file_get_contents("/DATA/entreaulas/Usuarios/$user.json"), true);
    if (!isset($userdata["password_hash"])) {
        $valid = "El usuario no existe.";
    }

    $hash = $userdata["password_hash"];
    if (password_verify($password, $hash)) {
        $_SESSION['entreaulas_auth_user'] = $user;
        $_SESSION['entreaulas_auth_data'] = $userdata;
        $_SESSION['entreaulas_auth_ok'] = true;
        header("Location: /entreaulas/");
        die();
    } else {
        $valid = "La contraseña no es correcta.";
    }

}
require_once "_incl/pre-body.php"; ?>
<div class="card pad">

    <h1>Iniciar sesión</h1>
    
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