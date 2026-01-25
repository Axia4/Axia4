<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php"; 
$aulario_id = $_GET["id"];
$centro_id = $_SESSION["entreaulas_auth_data"]["centro"];
$aulario = json_decode(file_get_contents("/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json"), true);
?>
<div class="card pad">
    <h1>Aulario: <?= htmlspecialchars($aulario["name"]) ?></h1>
    <span>
        Bienvenidx al aulario <?= htmlspecialchars($aulario["name"]) ?>. Aquí podrás gestionar las funcionalidades específicas de este aulario.
    </span>
</div>

<div id="grid">
    <a href="/entreaulas/paneldiario.php?aulario=<?= urlencode($aulario_id) ?>" class="button grid-item">
        <img src="/static/iconexperience/calendar_preferences.png" height="125">
        </br>
        Panel Diario
    </a>
    <a href="/entreaulas/admin/aularios.php?action=edit&aulario=<?= urlencode($aulario_id) ?>" class="button grid-item">
        <img src="/static/iconexperience/gear_edit.png" height="125">
        <br>
        Administración del Aulario
    </a>

</div>

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
