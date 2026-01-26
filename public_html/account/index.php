<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php";
?>
<div class="card pad">
    <h1>Cuenta y identidad</h1>
    <p>Bienvenido a la sección de gestión de tu cuenta e identidad. Aquí puedes actualizar tu información personal, cambiar tu contraseña y gestionar tus preferencias de seguridad.</p>
</div>
<div class="card pad" style="text-align: center;">
    <!--QR Code - Username -->
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($_SESSION["auth_user"]); ?>" alt="QR Code de Nombre de Usuario" style="margin: 0 auto;">
    <h2>¡Hola, <?php echo htmlspecialchars($_SESSION["auth_data"]["display_name"]); ?>!</h2>
    <span><b>Tu Email:</b> <?php echo htmlspecialchars($_SESSION["auth_data"]["email"]); ?></span>
    <span><b>Tu Nombre de Usuario:</b> <?php echo htmlspecialchars($_SESSION["auth_user"]); ?></span>
</div>
<?php require_once "_incl/post-body.php"; ?>