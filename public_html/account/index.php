<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php";
?>
<div id="grid">
    <div class="card pad grid-item" style="text-align: center;">
        <h2>¡Hola, <?php echo htmlspecialchars($_SESSION["auth_data"]["display_name"]); ?>!</h2>
        <span><b>Tu Email:</b> <?php echo htmlspecialchars($_SESSION["auth_data"]["email"]); ?></span>
        <span><b>Tu Nombre de Usuario:</b> <?php echo htmlspecialchars($_SESSION["auth_user"]); ?></span>
    </div>
    <div class="card pad grid-item" style="text-align: center;">
        <b>Código QR</b>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($_SESSION["auth_user"]); ?>" alt="QR Code de Nombre de Usuario" style="margin: 0 auto;">
        <small>Escanea este código para iniciar sesión. Es como tu contraseña, pero más fácil.</small>
    </div>
</div>
<style>
    .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 300px;
        text-align: center;
    }

    .grid-item img {
        margin: 0 auto;
        height: 150px;
    }
</style>
<script>
    var msnry = new Masonry('#grid', {
        "columnWidth": 300,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
    });
    setTimeout(() => {
        msnry.layout()
    }, 250)
    setInterval(() => {
        msnry.layout()
    }, 1000);
</script>
<?php require_once "_incl/post-body.php"; ?>