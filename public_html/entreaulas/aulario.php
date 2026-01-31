<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php"; 
$aulario_id = $_GET["id"];
$centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"];
$aulario = json_decode(file_get_contents("/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json"), true);
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Aulario: <?= htmlspecialchars($aulario["name"]) ?></h1>
        <span>
            Bienvenidx al aulario <?= htmlspecialchars($aulario["name"]) ?>. Aquí podrás gestionar las funcionalidades específicas de este aulario.
        </span>
    </div>
</div>

<div id="grid">
    <a href="/entreaulas/paneldiario.php?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-primary grid-item">
        <img src="/static/arasaac/pdi.png" height="125">
        </br>
        Panel Diario
    </a>
    <?php if (in_array("sysadmin:access", $_SESSION["auth_data"]["permissions"] ?? [])): ?>
    <a href="/sysadmin/aularios.php?action=edit&aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary grid-item">
        <img src="/static/iconexperience/gear_edit.png" height="125">
        <br>
        Cambiar Ajustes
    </a>
    <?php endif; ?>
    <!-- Menú del comedor -->
    <a href="/entreaulas/comedor.php?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-success grid-item">
        <img src="/static/arasaac/comedor.png" height="125" style="background: white; padding: 5px; border-radius: 10px;">
        <br>
        Menú del Comedor
    </a>
    <!-- Proyectos -->
    <a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-info grid-item">
        <img src="/static/iconexperience/shelf.png" height="125">
        <br>
        Proyectos
    </a>
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
        height: 125px;
    }
</style>
<script>
    var msnry = new Masonry('#grid', {
        "columnWidth": 250,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
    });
    setTimeout(() => {msnry.layout()}, 150)
//    setInterval(() => {msnry.layout()}, 10000);
window.addEventListener('resize', function(event) {
    msnry.layout()
}, true);

</script>

<?php require_once "_incl/post-body.php"; ?>
