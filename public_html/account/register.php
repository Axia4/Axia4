<?php
require_once "_incl/pre-body.php";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Handle form submission
    $invitations = json_decode(file_get_contents("/DATA/Invitaciones_de_usuarios.json"), true);
    $invi_code = strtoupper($_POST['invitation_code'] ?? '');
    if (!isset($invitations[$invi_code])) {
        header("Location: /?_resultcolor=red&_result=" . urlencode("Código de invitación no válido."));
        exit;
    }
    $userdata = [
        'display_name' => $_POST['display_name'],
        'email' => $_POST['email'],
        'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        '_meta_signup' => [
            'invitation_code' => $invi_code
        ],
        'permissions' => []
    ];
    if ($invitations[$invi_code]["active"] != true) {
        header("Location: /?_resultcolor=red&_result=" . urlencode("Código de invitación no válido."));
        exit;
    }
    $username = $_POST['username'];
    if (file_exists("/DATA/Usuarios/$username.json")) {
        header("Location: /?_resultcolor=red&_result=" . urlencode("El nombre de usuario ya existe. Por favor, elige otro."));
        exit;
    }
    file_put_contents("/DATA/Usuarios/$username.json", json_encode($userdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    // Deactivate invitation code if it's single-use
    if ($invitations[$invi_code]["single_use"] === true) {
        $invitations[$invi_code]["active"] = false;
        file_put_contents("/DATA/Invitaciones_de_usuarios.json", json_encode($invitations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    header("Location: /?_result=" . urlencode("Cuenta creada correctamente. Ya puedes iniciar sesión."));
    exit;
}
?>
<div class="card pad">
    <h1>¡Crea una cuenta!</h1>
    <form method="post">
        <fieldset class="card pad" style="border: 2px solid black; border-radius: 6.5px; max-width: 500px;">
            <label>
                <b>Codigo de invitación:</b>
                <input type="text" name="invitation_code" required />
                <small>Codigo de invitación proporcionado por un administrador.<br>Formato: 123456-ABCDEF</small>
            </label>
            <label>
                <b>Usuario:</b>
                <input type="text" name="username" required />
            </label>
            <label>
                <b>Contraseña:</b>
                <input type="password" name="password" required />
            </label>
            <label>
                <b>Nombre:</b>
                <input type="text" name="display_name" required />
            </label>
            <label>
                <b>Correo electronico:</b>
                <input type="email" name="email" required />
            </label>
            <button type="submit">Crear cuenta</button>
            <br><br>
        </fieldset>
    </form>
</div>