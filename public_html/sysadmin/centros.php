<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/tools.security.php";

function safe_path_segment($value)
{
    $value = trim((string)$value);
    $value = str_replace(["\0", "/", "\\"], "", $value);
    $value = str_replace("..", "", $value);
    $value = basename($value);
    if ($value === "." || $value === "..") {
        return "";
    }
    return $value;
}

$form_action = $_GET["form"] ?? "";
switch ($form_action) {
    case "create":
        $centro_id = safe_path_segment(Sf($_POST["name"] ?? ""));
        if (empty($centro_id)) {
            die("Nombre del centro no proporcionado.");
        }
        $centro_path = "/DATA/entreaulas/Centros/$centro_id";
        if (is_dir($centro_path)) {
            die("El centro ya existe.");
        }
        mkdir($centro_path, 0777, true);
        header("Location: ?action=index");
        exit();
        break;
    case "create_activity":
        ini_set('memory_limit', '512M');
        ini_set("display_errors", 1);
        ini_set('upload_max_filesize', '256M');
        ini_set('post_max_size', '256M');
        $centro_id = safe_path_segment(Sf($_GET['centro'] ?? ''));
        $centro_path = "/DATA/entreaulas/Centros/$centro_id";
        if (!is_dir($centro_path)) {
            die("Centro no válido.");
        }
        $activity_name = safe_path_segment(Sf($_POST["name"] ?? ''));
        if (empty($activity_name)) {
            die("Nombre de la actividad no proporcionado.");
        }
        $activity_photo = $_FILES["photo"] ?? null;
        if ($activity_photo === null || $activity_photo["error"] !== UPLOAD_ERR_OK) {
            die("Error al subir la foto.");
        }
        $activity_path = "$centro_path/Panel/Actividades/$activity_name";
        if (is_dir($activity_path)) {
            die("La actividad ya existe.");
        }
        mkdir($activity_path, 0777, true);
        $photo_path = "$activity_path/photo.jpg";
        move_uploaded_file($activity_photo["tmp_name"], $photo_path);
        header("Location: ?action=edit&centro=" . urlencode($centro_id));
        exit();
        break;
    case "edit_activity":
        ini_set('memory_limit', '512M');
        ini_set("display_errors", 1);
        ini_set('upload_max_filesize', '256M');
        ini_set('post_max_size', '256M');
        $centro_id = safe_path_segment(Sf($_GET['centro'] ?? ''));
        $activity_name = safe_path_segment(Sf($_GET['activity'] ?? ''));
        $activity_path = "/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/$activity_name";
        if (!is_dir($activity_path)) {
            die("Actividad no válida.");
        }
        $activity_photo = $_FILES["file"] ?? null;
        if ($activity_photo !== null && $activity_photo["error"] === UPLOAD_ERR_OK) {
            $photo_path = "$activity_path/photo.jpg";
            move_uploaded_file($activity_photo["tmp_name"], $photo_path);
        }
        if (safe_path_segment(Sf($_POST['nombre'] ?? '')) != $activity_name) {
            $new_activity_name = safe_path_segment(Sf($_POST['nombre'] ?? ''));
            $new_activity_path = "/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/$new_activity_name";
            if (is_dir($new_activity_path)) {
                die("Ya existe una actividad con ese nombre.");
            }
            rename($activity_path, $new_activity_path);
        }
        header("Location: ?action=edit&centro=" . urlencode($centro_id));;
        exit();
        break;
}

require_once "_incl/pre-body.php"; 
$view_action = $_GET["action"] ?? "index";
switch ($view_action) {
    case "edit_activity":
        $centro_id = safe_path_segment(Sf($_GET['centro'] ?? ''));
        $activity_name = safe_path_segment(Sf($_GET['activity'] ?? ''));
        $activity_path = "/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/$activity_name";
        if (!is_dir($activity_path)) {
            die("Actividad no válida.");
        }
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Gestión de la Actividad: <?php echo htmlspecialchars($activity_name); ?></h1>
        <span>
            Desde esta sección puedes administrar la actividad seleccionada del panel del centro <?php echo htmlspecialchars($centro_id); ?>.
        </span>
        <form method="post" action="?form=edit_activity&centro=<?php echo urlencode($centro_id); ?>&activity=<?php echo urlencode($activity_name); ?>" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre de la actividad:</label>
                <input required type="text" id="nombre" name="nombre" class="form-control" value="<?php echo htmlspecialchars($activity_name); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Foto (pulsa para cambiarla):</label><br>
                <div style="width: 200px;">
                    <label class="dropimage" style="background-image: url('<?php
                        $image_path = "$activity_path/photo.jpg";
                        $image_fetchpath = file_exists($image_path) ? "/entreaulas/_filefetch.php?type=panel_actividades&centro=" . urlencode($centro_id) . "&activity=" . urlencode($activity_name) : '/static/logo-entreaulas.png';
                        echo htmlspecialchars($image_fetchpath);
                    ?>');">
                        <input title="Drop image or click me" type="file" name="file" accept="image/*">
                    </label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </form>
    </div>
</div>
<?php
        break;
    case "new_activity":
        $centro_id = safe_path_segment(Sf($_GET['centro'] ?? ''));
        $centro_path = "/DATA/entreaulas/Centros/$centro_id";
        if (!is_dir($centro_path)) {
            die("Centro no válido.");
        }
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Nueva Actividad del Panel</h1>
        <span>
            Aquí puedes crear una nueva actividad para el panel del centro <?php echo htmlspecialchars($centro_id); ?>.
        </span>
        <form method="post" action="?form=create_activity&centro=<?php echo urlencode($centro_id); ?>" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="name" class="form-label">Nombre de la actividad:</label>
                <input required type="text" id="name" name="name" class="form-control" placeholder="Ej: Biblioteca">
            </div>
            <div class="mb-3">
                <label for="photo" class="form-label">Foto:</label>
                <input required type="file" id="photo" name="photo" class="form-control" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary">Crear Actividad</button>
        </form>
    </div>
</div>
<?php
        break;
    case "new":
        ?>
<div class="card pad">
    <div>
        <h1 class="card-title">Nuevo Centro</h1>
        <span>
            Aquí puedes crear un nuevo centro para el sistema.
        </span>
        <form method="post" action="?form=create">
            <div class="mb-3">
                <label for="name" class="form-label">ID del centro:</label>
                <input required type="text" id="name" name="name" class="form-control" placeholder="Ej: Centro-Principal-001">
            </div>
            <button type="submit" class="btn btn-primary">Crear Centro</button>
        </form>
    </div>
</div>

<?php
        break;
    case "edit":
        $centro_id = safe_path_segment(Sf($_GET['centro'] ?? ''));
        $centro_path = "/DATA/entreaulas/Centros/$centro_id";
        if (!is_dir($centro_path)) {
            die("Centro no válido.");
        }
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Gestión del Centro: <?php echo htmlspecialchars($centro_id); ?></h1>
        <span>
            Desde esta sección puedes administrar el centro seleccionado.
        </span>
    </div>
</div>
<div class="card pad">
    <div>
        <h2>Aularios</h2>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Icono</th>
                    <th>Nombre</th>
                    <th>
                        <a href="/sysadmin/aularios.php?action=new&centro=<?php echo urlencode($centro_id); ?>" class="btn btn-success">+ Nuevo</a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                $aulas_filelist = glob("/DATA/entreaulas/Centros/$centro_id/Aularios/*.json");
                foreach ($aulas_filelist as $aula_file) {
                    $aula_data = json_decode(file_get_contents($aula_file), true);
                    echo '<tr>';
                    echo '<td><img src="' . htmlspecialchars($aula_data['icon'] ?? '/static/logo-entreaulas.png') . '" alt="Icono" style="height: 50px;"></td>';
                    echo '<td>' . htmlspecialchars($aula_data['name'] ?? 'Sin Nombre') . '</td>';
                    echo '<td><a href="/sysadmin/aularios.php?action=edit&aulario=' . urlencode(basename($aula_file, ".json")) . '&centro=' . urlencode($centro_id) . '" class="btn btn-primary">Gestionar</a></td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<div class="card pad">
    <div>
        <h2>Actividades del panel</h2>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Foto</th>
                    <th>Nombre</th>
                    <th>
                        <a href="?action=new_activity&centro=<?php echo urlencode($centro_id); ?>" class="btn btn-success">+ Nuevo</a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                $activities = glob("/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/*", GLOB_ONLYDIR);
                foreach ($activities as $activity_path) {
                    $activity_name = basename($activity_path);
                    $image_path = "/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/" . basename($activity_name) . "/photo.jpg";
                    $image_fetchpath = file_exists($image_path) ? "/entreaulas/_filefetch.php?type=panel_actividades&centro=" . urlencode($centro_id) . "&activity=" . urlencode($activity_name) : '/static/logo-entreaulas.png';
                    echo '<tr>';
                    echo '<td><img src="' . htmlspecialchars($image_fetchpath) . '" alt="Foto" style="height: 50px;"></td>';
                    echo '<td>' . htmlspecialchars($activity_name) . '</td>';
                    echo '<td><a href="?action=edit_activity&centro=' . urlencode($centro_id) . '&activity=' . urlencode($activity_name) . '" class="btn btn-primary">Gestionar</a></td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

    <?php
        break;
    case "index":
    default:
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Gestión de Centros</h1>
        <span>
            Desde esta sección puedes administrar los centros asociados al sistema.
        </span>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Nombre</th>
                    <th>
                        <a href="?action=new" class="btn btn-success">+ Nuevo</a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                $user_data = $_SESSION["auth_data"];
                $centros_filelist = glob("/DATA/entreaulas/Centros/*");
                foreach ($centros_filelist as $centro_folder) {
                    $centro_id = basename($centro_folder);
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($centro_id) . '</td>';
                    echo '<td><a href="?action=edit&centro=' . urlencode($centro_id) . '" class="btn btn-primary">Gestionar</a></td>';
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