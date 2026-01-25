<?php require_once "_incl/pre-body.php"; ?>
<div class="card pad">
    <h1>¡Bienvenidx a Axia4!</h1>
    <span>Axia4 es la plataforma unificada de EuskadiTech y Sketaria.</span>
</div>
<div id="grid">
    <div class="card grid-item">
        <img src="/static/logo-club.png" alt="Logo Club">
        <b>La web del club</b>
        <b>No disponible</b>
        <a href="/club/" class="button">Acceso publico</a>
    </div>
    <div class="card grid-item">
        <img src="/static/logo-entreaulas.png" alt="Logo EntreAulas">
        <b>EntreAulas</b>
        <span>Gestión de aularios conectados.</span>
        <a href="/entreaulas/" class="button">Tengo cuenta</a>
    </div>
    <!--<div class="card grid-item">
        <img src="/static/logo-oscar.png" alt="Logo OSCAR">
        <b>OSCAR</b>
        <span>Red de IA Absoluta.</span>
        <a href="/oscar/" disabled class="button">No disponible</a>
    </div>-->
    <div class="card grid-item">
        <img src="/static/logo-aularios.png" alt="Logo Aularios">
        <b>Aularios<sup>2</sup></b>
        <span>Acceso centralizado a los Aularios.</span>
        <a href="https://aularios.tech.eus" class="button">Tengo cuenta</a>
        <small>Externo</small>
    </div>
    <div class="card grid-item">
        <img src="/static/logo.png" alt="Logo Axia4 Cloud">
        <b>Nube Axia4</b>
        <span>Almacenamiento central de datos.</span>
        <a href="https://axia4.net" class="button">Tengo cuenta</a>
        <small>Externo</small>
    </div>
    <div class="card grid-item">
        <img src="/static/logo-nk4.svg" alt="Logo NK5">
        <b>Nube Kasa</b>
        <span>Nube personal con domotica.</span>
        <a href="https://nk4.tech.eus/_familia/" class="button">Acceso privado</a>
        <small>Externo</small>
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

