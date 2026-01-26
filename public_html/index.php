<?php require_once "_incl/pre-body.php"; ?>
<div class="card pad">
    <h1>¡Bienvenidx a Axia4!</h1>
    <span>Axia4 es la plataforma unificada de EuskadiTech y Sketaria.</span>
</div>
<div id="grid">
    <div class="card grid-item">
        <img src="/static/logo-club.png" alt="Logo Club">
        <b>La web del club</b>
        <a href="/club/" class="button">Acceso publico</a>
    </div>
    <div class="card grid-item">
        <img src="/static/logo-entreaulas.png" alt="Logo EntreAulas">
        <b>EntreAulas</b>
        <span>Gestión de aularios conectados.</span>
        <a href="/entreaulas/" class="button">Tengo cuenta</a>
    </div>
    <div class="card grid-item" style="opacity: 0.5;">
        <img src="/static/logo-oscar.png" alt="Logo OSCAR">
        <b>OSCAR</b>
        <span>Red de IA Absoluta.</span>
    </div>
    <div class="card grid-item" style="opacity: 0.5;">
        <img src="/static/logo-media.png" alt="Logo ET Media">
        <b>ET Media</b>
        <span>Streaming de pelis y series.</span>
    </div>
    <div class="card grid-item" style="opacity: 0.5;">
        <img src="/static/logo-hyper.png" alt="Logo Hyper">
        <b>Hyper</b>
        <span>Plataforma de gestión empresarial.</span>
    </div>
    <div class="card grid-item" style="opacity: 0.5;">
        <img src="/static/logo-mail.png" alt="Logo Comunicaciones">
        <b>Comunicaciones</b>
        <span>Correos electrónicos y mensajería.</span>
    </div>
    <div class="card grid-item" style="opacity: 0.5;">
        <img src="/static/logo-account.png" alt="Logo Account">
        <b>Cuenta y identidad</b>
        <span>Acceso a la plataforma y pagos.</span>
    </div>
    <div class="card grid-item" style="opacity: 0.5;">
        <img src="/static/logo-malla.png" alt="Logo Malla">
        <b>Malla Meshtastic</b>
        <span>Red de comunicación por radio.</span>
    </div>
    <div class="card grid-item" style="opacity: 0.5;">
        <img src="/static/logo-aularios.png" alt="Logo Aularios">
        <b>Aularios<sup>2</sup></b>
        <span>Visita virtual a los aularios.</span>
        <!--<a href="https://aularios.tech.eus" class="button">Tengo cuenta</a>-->
        <small>Solo lectura - Migrando a Axia4</small>
    </div>
    <div class="card grid-item" style="opacity: 0.5;">
        <img src="/static/logo-nube.png" alt="Logo Axia4 Cloud">
        <b>Nube Axia4.NET</b>
        <span>Almacenamiento central de datos.</span>
        <!--<a href="https://axia4.net" class="button">Tengo cuenta</a>-->
        <small>Cerrado por migración a Axia4</small>
    </div>
    <div class="card grid-item" style="opacity: 0.5;">
        <img src="/static/logo-nk4.png" alt="Logo Nube Kasa">
        <b>Nube Kasa</b>
        <span>Nube personal con domotica.</span>
        <!--<a href="https://nk4.tech.eus/_familia/" class="button">Acceso privado</a>-->
        <small>Cerrado por mantenimiento</small>
    </div>
    <div class="card grid-item">
        <img src="/static/logo-sysadmin.png" alt="Logo SysAdmin">
        <b>SysAdmin</b>
        <span>Panel de administración de sistemas.</span>
        <a href="/sysadmin/" class="button">Acceso privado</a>
    </div>
</div>

<style>
    .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 235px;
        text-align: center;
    }

    .grid-item img {
        margin: 0 auto;
        height: 100px;
    }
</style>


<script>
    var msnry = new Masonry('#grid', {
        "columnWidth": 235,
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

