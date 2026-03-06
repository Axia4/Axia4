<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php";
require_once "../_incl/tools.security.php";
require_once "../_incl/db.php";

?>
<div class="card pad">
    <div>
        <h1 class="card-title">¡Hola, <?php echo htmlspecialchars($_SESSION["auth_data"]["display_name"]); ?>!</h1>
        <span>
            Bienvenidx a la plataforma de gestión de aularios conectados. Desde aquí podrás administrar los aularios asociados a tu cuenta.
        </span>
    </div>
</div>
<div id="grid">
    <?php
    $user_data = $_SESSION["auth_data"];
    $centro_id = safe_centro_id($user_data["entreaulas"]["centro"] ?? "");
    $user_aulas = $user_data["entreaulas"]["aulas"] ?? [];
    foreach ($user_aulas as $aulario_id) {
        $aulario_id = safe_id_segment($aulario_id);
        if ($aulario_id === "") {
            continue;
        }
        $aulario = db_get_aulario($centro_id, $aulario_id);
        if (!$aulario) {
            continue;
        }
        $aulario_name = $aulario["name"] ?? $aulario_id;
        $aulario_icon = $aulario["icon"] ?? "/static/arasaac/aulario.png";
        echo '<a href="/entreaulas/aulario.php?id=' . urlencode($aulario_id) . '" class="btn btn-primary grid-item">
            <img style="height: 125px;" src="' . htmlspecialchars($aulario_icon, ENT_QUOTES) . '" alt="' . htmlspecialchars($aulario_name) . ' Icono">
            <br>
            ' . htmlspecialchars($aulario_name) . '
        </a>';
    } ?>
    <?php if (in_array('supercafe:access', $_SESSION['auth_data']['permissions'] ?? [])): ?>
        <a href="/entreaulas/supercafe.php" class="btn btn-warning grid-item">
            <img src="/static/iconexperience/purchase_order_cart.png" height="125"
                 style="background: white; padding: 5px; border-radius: 10px;"
                 alt="Icono SuperCafe">
            <br>
            SuperCafe
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
    setTimeout(() => { msnry.layout() }, 250);
    setInterval(() => { msnry.layout() }, 1000);
</script>
<?php require_once "_incl/post-body.php"; ?>
