<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php";?>
<div class="card pad">
    <div>
        <h1 class="card-title">¡Hola, <?php echo $_SESSION["auth_data"]["display_name"];?>!</h1>
        <span>
            Bienvenidx a la plataforma de gestión de aularios conectados. Desde aquí podrás administrar los aularios asociados a tu cuenta.
        </span>
    </div>
</div>
<div id="grid">
    <?php $user_data = $_SESSION["auth_data"];
    $centro_id = $user_data["entreaulas"]["centro"];
    foreach ($user_data["entreaulas"]["aulas"] as $aulario_id) {
        $aulario = json_decode(file_get_contents("/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json"), true);
        echo '<a href="/entreaulas/aulario.php?id=' . $aulario_id . '" class="btn btn-primary grid-item">
            <img style="height: 125px;" src="' . $aulario["icon"] . '" alt="' . htmlspecialchars($aulario["name"]) . ' Icono">
            <br>
            ' . htmlspecialchars($aulario["name"]) . '
        </a>';
    } ?>
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
    setInterval(() => {
        msnry.layout()
    }, 1000);
    msnry.layout()
</script>

<?php require_once "_incl/post-body.php"; ?>
