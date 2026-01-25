<?php
session_start();

if (isset($_POST["user"])) {
    $valid = "";
    $user = trim(strtolower($_POST["user"]));
    $password = $_POST["password"];
    $users = json_decode(file_get_contents("/DATA/Usuarios.json"), true);
    if (!isset($users)) {
        $valid = "Fallo del sistema: No hay cuentas.";
    }

    $userdata = $users[$user];
    if (!isset($userdata["password_hash"])) {
        $valid = "El usuario no existe.";
    }

    $hash = $userdata["password_hash"];
    if (password_verify($password, $hash)) {
        $_SESSION['auth_user'] = $user;
        $_SESSION['auth_data'] = $userdata;
        $_SESSION['auth_ok'] = "yes";
        header("Location: /");
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
