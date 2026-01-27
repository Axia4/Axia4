<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php"; 
$aulario_id = $_GET["id"];
$centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"];
$aulario = json_decode(file_get_contents("/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json"), true);
?>
<div class="card pad">
    <div class="card-body">
        <h1 class="card-title">Aulario: <?= htmlspecialchars($aulario["name"]) ?></h1>
        <span>
            Bienvenidx al aulario <?= htmlspecialchars($aulario["name"]) ?>. Aquí podrás gestionar las funcionalidades específicas de este aulario.
        </span>
    </div>
</div>

<div id="grid">
    <a href="/entreaulas/paneldiario.php?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-primary grid-item">
        <img src="/static/iconexperience/calendar_preferences.png" height="125">
        </br>
        Panel Diario
    </a>
    <?php if (in_array("sysadmin:access", $_SESSION["auth_data"]["permissions"] ?? [])): ?>
    <a href="/sysadmin/aularios.php?action=edit&aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary grid-item">
        <img src="/static/iconexperience/gear_edit.png" height="125">
        <br>
        Administración del Aulario
    </a>
    <?php endif; ?>
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
