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
        <b>EntreAulas</b>
        <a href="/sysadmin/centros.php" class="btn btn-primary">Gestionar Centros</a>
        <a href="/sysadmin/aularios.php" class="btn btn-primary">Gestionar Aularios</a>
    </div>
    <div class="card grid-item">
        <img src="/static/logo-account.png" alt="Logo Mi Cuenta">
        <b>Mi Cuenta</b>
        <a href="/sysadmin/users.php" class="btn btn-primary">Gestionar Usuarios</a>
        <a href="/sysadmin/invitations.php" class="btn btn-primary">Gestionar Invitaciones</a>
    </div>
    <div class="card grid-item">
        <img src="/static/logo-club.png" alt="Logo Club">
        <b>La web del Club</b>
        <a href="/sysadmin/club_mkthumb.php" class="btn btn-primary">Generar Miniaturas</a>
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