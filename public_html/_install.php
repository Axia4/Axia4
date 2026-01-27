<?php
if (file_exists("/DATA/SISTEMA_INSTALADO.txt")) {
    header("Location: /");
    die();
}

switch ($_GET['form'] ?? '') {
    case 'create_admin':
        $admin_user = trim(strtolower($_POST['admin_user'] ?? ''));
        $admin_password = $_POST['admin_password'] ?? '';
        if (empty($admin_user) || empty($admin_password)) {
            die("El nombre de usuario y la contraseña son obligatorios.");
        }
        $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $admin_userdata = [
            'display_name' => 'Administrador',
            'email' => "$admin_user@nomail.arpa",
            'permissions' => ['*', 'sysadmin:access', 'entreaulas:access'],
            'password_hash' => $password_hash
        ];
        if (!is_dir("/DATA/Usuarios")) {
            mkdir("/DATA/Usuarios", 0755, true);
        }
        file_put_contents("/DATA/Usuarios/$admin_user.json", json_encode($admin_userdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents("/DATA/SISTEMA_INSTALADO.txt", "Sistema instalado el ".date("Y-m-d H:i:s")."\n");
        header("Location: /_login.php");
        exit;
        break;
}

switch ($_GET["step"]) {
    case "0":
    default:
        require_once "_incl/pre-body.php";
?>
<div class="card pad">
    <div class="card-body">
        <h1 class="card-title">Instalación de Axia4</h1>
        <span>Bienvenidx al asistente de instalación de Axia4. Por favor, sigue los pasos para completar la configuración inicial del sistema.</span>
        <ol>
            <li>Crear el usuario administrador.</li>
            <!--<li>Configurar los ajustes básicos del sistema.</li>-->
            <li>Finalizar la instalación y acceder al sistema.</li>
        </ol>
        <a href="/_install.php?step=1" class="btn btn-primary">Comenzar instalación</a>
    </div>
</div>
<?php 
        require_once "_incl/post-body.php";
        break;
    case "1":
        require_once "_incl/pre-body.php";
?>
<div class="card pad">
    <div class="card-body">
        <h1 class="card-title">Crear usuario administrador</h1>
        <form method="post" action="?form=create_admin">
            <div class="mb-3">
                <label for="admin_user" class="form-label"><b>Nombre de usuario:</b></label>
                <input required type="text" id="admin_user" name="admin_user" class="form-control" placeholder="Ej: AdminUser">
            </div>
            <div class="mb-3">
                <label for="admin_password" class="form-label"><b>Contraseña:</b></label>
                <input required type="password" id="admin_password" name="admin_password" class="form-control" placeholder="Ej: StrongPassword123">
            </div>
            <button type="submit" class="btn btn-primary">Crear usuario administrador</button>
        </form>
    </div>
</div>
<?php 
        require_once "_incl/post-body.php";
        break;
}

?>