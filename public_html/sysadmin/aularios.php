<?php
require_once "_incl/auth_redir.php";
switch ($_GET["form"]) {
    case "create":
        $user_data = $_SESSION["auth_data"];
        $centro_id = $_POST["centro"];
        if (empty($centro_id) || !is_dir("/DATA/entreaulas/Centros/$centro_id")) {
            die("Centro no válido.");
        }
        $aulario_id = uniqid("aulario_");
        $aulario_data = [
            "name" => $_POST["name"],
            "icon" => $_POST["icon"] ?? "/static/logo-entreaulas.png"
        ];
        // Make path recursive (mkdir -p equivalent)
        @mkdir("/DATA/entreaulas/Centros/$centro_id/Aularios/", 0777, true);
        file_put_contents("/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json", json_encode($aulario_data));
        // Update user data
        $_SESSION["auth_data"]["entreaulas"]["aulas"][] = $aulario_id;
        header("Location: ?action=index");
        exit();
        break;
}

require_once "_incl/pre-body.php"; 
switch ($_GET["action"]) {
    case "new":
        ?>
<div class="card pad">
    <div class="card-body">
        <h1 class="card-title">Nuevo Aulario</h1>
        <span>
            Aquí puedes crear un nuevo aulario para el centro que administras.
        </span>
        <form method="post" action="?form=create">
            <div class="mb-3">
                <label for="centro" class="form-label"><b>Centro:</b></label>
                <select required id="centro" name="centro" class="form-select">
                    <option value="">-- Selecciona un centro --</option>
                    <?php
                    foreach (glob("/DATA/entreaulas/Centros/*", GLOB_ONLYDIR) as $centro_folder) {
                        $centro_id = basename($centro_folder);
                        $selected = ($centro_id == $_SESSION["auth_data"]["entreaulas"]["centro"]) ? "selected" : "";
                        echo '<option value="' . htmlspecialchars($centro_id) . '" ' . $selected . '>' . htmlspecialchars($centro_id) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="name" class="form-label">Nombre del Aulario:</label>
                <input required type="text" id="name" name="name" class="form-control" placeholder="Ej: Aulario Principal">
            </div>
            <div class="mb-3">
                <label for="icon" class="form-label">Icono del Aulario (URL):</label>
                <input type="text" id="icon" name="icon" class="form-control" placeholder="Ej: https://example.com/icon.png" value="/static/logo-entreaulas.png">
            </div>
            <button type="submit" class="btn btn-primary">Crear Aulario</button>
        </form>
    </div>
</div>

<?php
        break;
    case "edit":
        $aulario_id = $_GET["aulario"];
        $centro_id = $_GET["centro"];
        $aulario_file = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json";
        if (!file_exists($aulario_file)) {
            die("Aulario no encontrado.");
        }
        $aulario_data = json_decode(file_get_contents($aulario_file), true);
?>
<div class="card pad">
    <div class="card-body">
        <h1 class="card-title">Editar Aulario: <?php echo htmlspecialchars($aulario_data['name'] ?? 'Sin Nombre'); ?></h1>
        <form method="post" action="?form=save_edit">
            <div class="mb-3">
                <label for="name" class="form-label">Nombre del Aulario:</label>
                <input required type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($aulario_data['name'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label for="icon" class="form-label">Icono del Aulario (URL):</label>
                <input type="text" id="icon" name="icon" class="form-control" value="<?php echo htmlspecialchars($aulario_data['icon'] ?? '/static/logo-entreaulas.png'); ?>">
            </div>
            <input type="hidden" name="aulario_id" value="<?php echo htmlspecialchars($aulario_id); ?>">
            <input type="hidden" name="centro_id" value="<?php echo htmlspecialchars($centro_id); ?>">
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </form>
    </div>
</div>
<?php 
        break;
    case "index":
    default:
?>
<div class="card pad">
    <div class="card-body">
        <h1 class="card-title">Gestión de Aularios</h1>
        <span>
            Desde esta sección puedes administrar los aularios asociados al centro que estás administrando.
        </span>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Icono</th>
                    <th>Nombre</th>
                    <th>
                        <a href="?action=new" class="btn btn-success">+ Nuevo</a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                $user_data = $_SESSION["auth_data"];
                $centro_filter = $_GET['centro'] ?? "*";
                $aulas_filelist = glob("/DATA/entreaulas/Centros/$centro_filter/Aularios/*.json");
                foreach ($aulas_filelist as $aula_file) {
                    $aula_data = json_decode(file_get_contents($aula_file), true);
                    $centro_id = basename(dirname(dirname($aula_file)));
                    echo '<tr>';
                    echo '<td><img src="' . htmlspecialchars($aula_data['icon'] ?? '/static/logo-entreaulas.png') . '" alt="Icono" style="height: 50px;"></td>';
                    echo '<td>' . htmlspecialchars($aula_data['name'] ?? 'Sin Nombre') . '<br><small>' . $centro_id . '</small></td>';
                    echo '<td><a href="?action=edit&aulario=' . urlencode(basename($aula_file, ".json")) . '&centro=' . urlencode($centro_id) . '" class="btn btn-primary">Gestionar</a></td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<?php 
        break;
}



require_once "_incl/post-body.php"; ?>