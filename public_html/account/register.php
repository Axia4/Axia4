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
    <div class="card-body">
        <h1 class="card-title">¡Crea una cuenta!</h1>
        <form method="post">
            <div class="card pad" style="max-width: 500px;">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="invitation_code" class="form-label"><b>Codigo de invitación:</b></label>
                        <input type="text" id="invitation_code" name="invitation_code" class="form-control" required />
                        <small>Codigo de invitación proporcionado por un administrador.<br>Formato: 123456-ABCDEF</small>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label"><b>Usuario:</b></label>
                        <input type="text" id="username" name="username" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label"><b>Contraseña:</b></label>
                        <input type="password" id="password" name="password" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label for="display_name" class="form-label"><b>Nombre:</b></label>
                        <input type="text" id="display_name" name="display_name" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label"><b>Correo electronico:</b></label>
                        <input type="email" id="email" name="email" class="form-control" required />
                    </div>
                    <button type="submit" class="btn btn-primary">Crear cuenta</button>
                </div>
            </div>
        </form>
    </div>
</div>