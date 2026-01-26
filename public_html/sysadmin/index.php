<?php 
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php"; ?>
<div class="card pad">
    <h1>Administración del Sistema</h1>
    <p>Bienvenido a la sección de administración del sistema. Aquí puedes gestionar las configuraciones y usuarios del sistema.</p>
</div>
<div id="grid">
    <div class="card grid-item">
        <img src="/static/logo-entreaulas.png" alt="Logo EntreAulas">
        <b>Gestión de Centros</b>
        <span>Administra los centros del sistema EntreAulas.</span>
        <a href="/sysadmin/centros.php" class="button">Gestionar Centros</a>
    </div>
    <div class="card grid-item">
        <img src="/static/logo-entreaulas.png" alt="Logo EntreAulas">
        <b>Gestión de Aularios</b>
        <span>Administra los aularios dentro de los centros.</span>
        <a href="/sysadmin/aularios.php" class="button">Gestionar Aularios</a>
    </div>
    <div class="card grid-item">
        <img src="/static/logo.png" alt="Logo Usuarios">
        <b>Gestión de Usuarios</b>
        <span>Administra los usuarios del sistema.</span>
        <a href="/sysadmin/users.php" class="button">Gestionar Usuarios</a>
    </div>
</div>
<style>
    .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 250px;
        text-align: center;
    }
    .grid-item img {
        margin: 0 auto;
        height: 100px;
    }
</style>
<script>
    var msnry = new Masonry('#grid', {
        "columnWidth": 250,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
    });
    setTimeout(() => {msnry.layout()}, 250)
    setInterval(() => {
        msnry.layout()
    }, 1000);
</script>
<?php require_once "_incl/post-body.php"; ?>