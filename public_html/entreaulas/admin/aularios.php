<?php
require_once "../_incl/auth_redir.php";
require_once "../_incl/pre-body.php"; 
switch ($_GET["form"]) {
    case "create":
        $user_data = $_SESSION["entreaulas_auth_data"];
        $centro_id = $user_data["centro"];
        $aulario_id = uniqid("aulario_");
        $aulario_data = [
            "name" => $_POST["name"],
            "icon" => $_POST["icon"] ?? "/static/logo-entreaulas.png"
        ];
        // Make path recursive (mkdir -p equivalent)
        @mkdir("/DATA/entreaulas/Centros/$centro_id/Aularios/", 0777, true);
        file_put_contents("/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json", json_encode($aulario_data));
        // Update user data
        $_SESSION["entreaulas_auth_data"]["aulas"][] = $aulario_id;
        header("Location: ?action=index");
        exit();
        break;
}

switch ($_GET["action"]) {
    case "new":
        ?>
<div class="card pad">
    <h1>Nuevo Aulario</h1>
    <span>
        Aquí puedes crear un nuevo aulario para el centro que administras.
    </span>
    <form method="post" action="?form=create">
        <label>
            Nombre del Aulario:<br>
            <input required type="text" name="name" placeholder="Ej: Aulario Principal">
        </label><br><br>
        <label>
            Icono del Aulario (URL):<br>
            <input type="url" name="icon" placeholder="Ej: https://example.com/icon.png" value="/static/logo-entreaulas.png">
        </label><br><br>
        <button type="submit">Crear Aulario</button>
    </form>
</div>

<?php
        break;
    case "index":
    default:
?>
<div class="card pad">
    <h1>Gestión de Aularios</h1>
    <span>
        Desde esta sección puedes administrar los aularios asociados al centro que estás administrando.
    </span>
    <a href="?action=new" class="button">Nuevo Aulario</a>
</div>
<?php 
        break;
}



require_once "../_incl/post-body.php"; ?>