<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php";

function safe_id_segment($value)
{
    $value = basename((string)$value);
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value);
}

function safe_centro_id($value)
{
    return preg_replace('/[^0-9]/', '', (string)$value);
}

function safe_aulario_config_path($centro_id, $aulario_id)
{
    $centro = safe_centro_id($centro_id);
    $aulario = safe_id_segment($aulario_id);
    if ($centro === '' || $aulario === '') {
        return null;
    }
    return "/DATA/entreaulas/Centros/$centro/Aularios/$aulario.json";
}
?>
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
    $centro_id = safe_centro_id($user_data["entreaulas"]["centro"] ?? "");
    foreach ($user_data["entreaulas"]["aulas"] as $aulario_id) {
        $aulario_id = safe_id_segment($aulario_id);
        if ($aulario_id === "") {
            continue;
        }
        $aulario_path = safe_aulario_config_path($centro_id, $aulario_id);
        if (!$aulario_path || !file_exists($aulario_path)) {
            continue;
        }
        $aulario = json_decode(file_get_contents($aulario_path), true);
        if (!is_array($aulario)) {
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
