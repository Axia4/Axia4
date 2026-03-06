<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/tools.security.php";
require_once "../_incl/db.php";

function safe_path_segment($value)
{
    $value = trim((string) $value);
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
        // Check uniqueness in DB
        $existing = db()->prepare("SELECT id FROM centros WHERE centro_id = ?");
        $existing->execute([$centro_id]);
        if ($existing->fetch()) {
            die("El centro ya existe.");
        }
        // Create DB record
        db()->prepare("INSERT INTO centros (centro_id) VALUES (?)")->execute([$centro_id]);
        // Keep filesystem directory for activity photos (Panel/Actividades)
        $centro_path = "/DATA/entreaulas/Centros/$centro_id";
        if (!is_dir($centro_path)) {
            mkdir($centro_path, 0755, true);
        }
        header("Location: ?action=index");
        exit();
        break;
    case "create_activity":
        ini_set('memory_limit', '512M');
        ini_set('upload_max_filesize', '256M');
        ini_set('post_max_size', '256M');
        $centro_id = safe_path_segment(Sf($_GET['centro'] ?? ''));
        // Validate centro exists in DB
        $stmt = db()->prepare("SELECT id FROM centros WHERE centro_id = ?");
        $stmt->execute([$centro_id]);
        if (!$stmt->fetch()) {
            die("Centro no válido.");
        }
        $activity_name  = safe_path_segment(Sf($_POST["name"] ?? ''));
        if (empty($activity_name)) {
            die("Nombre de la actividad no proporcionado.");
        }
        $activity_photo = $_FILES["photo"] ?? null;
        if ($activity_photo === null || $activity_photo["error"] !== UPLOAD_ERR_OK) {
            die("Error al subir la foto.");
        }
        $activity_path = "/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/$activity_name";
        if (is_dir($activity_path)) {
            die("La actividad ya existe.");
        }
        mkdir($activity_path, 0755, true);
        move_uploaded_file($activity_photo["tmp_name"], "$activity_path/photo.jpg");
        header("Location: ?action=edit&centro=" . urlencode($centro_id));
        exit();
        break;
    case "edit_activity":
        ini_set('memory_limit', '512M');
        ini_set('upload_max_filesize', '256M');
        ini_set('post_max_size', '256M');
        $centro_id     = safe_path_segment(Sf($_GET['centro'] ?? ''));
        $activity_name = safe_path_segment(Sf($_GET['activity'] ?? ''));
        $activity_path = "/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/$activity_name";
        if (!is_dir($activity_path)) {
            die("Actividad no válida.");
        }
        $activity_photo = $_FILES["file"] ?? null;
        if ($activity_photo !== null && $activity_photo["error"] === UPLOAD_ERR_OK) {
            move_uploaded_file($activity_photo["tmp_name"], "$activity_path/photo.jpg");
        }
        $new_name = safe_path_segment(Sf($_POST['nombre'] ?? ''));
        if ($new_name !== $activity_name && $new_name !== '') {
            $new_path = "/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/$new_name";
            if (is_dir($new_path)) {
                die("Ya existe una actividad con ese nombre.");
            }
            rename($activity_path, $new_path);
        }
        header("Location: ?action=edit&centro=" . urlencode($centro_id));
        exit();
        break;
}

require_once "_incl/pre-body.php";
$view_action = $_GET["action"] ?? "index";
switch ($view_action) {
    case "edit_activity":
        $centro_id     = safe_path_segment(Sf($_GET['centro'] ?? ''));
        $activity_name = safe_path_segment(Sf($_GET['activity'] ?? ''));
        $activity_path = "/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/$activity_name";
        if (!is_dir($activity_path)) {
            die("Actividad no válida.");
        }
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Gestión de la Actividad: <?= htmlspecialchars($activity_name) ?></h1>
        <form method="post" action="?form=edit_activity&centro=<?= urlencode($centro_id) ?>&activity=<?= urlencode($activity_name) ?>" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre de la actividad:</label>
                <input required type="text" id="nombre" name="nombre" class="form-control" value="<?= htmlspecialchars($activity_name) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Foto (pulsa para cambiarla):</label><br>
                <div style="width: 200px;">
                    <label class="dropimage" style="background-image: url('<?php
                        $img = file_exists("$activity_path/photo.jpg")
                            ? "/entreaulas/_filefetch.php?type=panel_actividades&centro=" . urlencode($centro_id) . "&activity=" . urlencode($activity_name)
                            : '/static/logo-entreaulas.png';
                        echo htmlspecialchars($img);
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
        $stmt = db()->prepare("SELECT id FROM centros WHERE centro_id = ?");
        $stmt->execute([$centro_id]);
        if (!$stmt->fetch()) {
            die("Centro no válido.");
        }
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Nueva Actividad del Panel</h1>
        <form method="post" action="?form=create_activity&centro=<?= urlencode($centro_id) ?>" enctype="multipart/form-data">
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
        $stmt = db()->prepare("SELECT id FROM centros WHERE centro_id = ?");
        $stmt->execute([$centro_id]);
        if (!$stmt->fetch()) {
            die("Centro no válido.");
        }
        $aularios   = db_get_aularios($centro_id);
        $activities = glob("/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/*", GLOB_ONLYDIR) ?: [];
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Gestión del Centro: <?= htmlspecialchars($centro_id) ?></h1>
    </div>
</div>
<div class="card pad">
    <div>
        <h2>Aularios</h2>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Icono</th><th>Nombre</th>
                    <th><a href="/sysadmin/aularios.php?action=new&centro=<?= urlencode($centro_id) ?>" class="btn btn-success">+ Nuevo</a></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aularios as $aula_id => $aula): ?>
                <tr>
                    <td><img src="<?= htmlspecialchars($aula['icon'] ?: '/static/logo-entreaulas.png') ?>" alt="Icono" style="height: 50px;"></td>
                    <td><?= htmlspecialchars($aula['name'] ?: $aula_id) ?></td>
                    <td><a href="/sysadmin/aularios.php?action=edit&aulario=<?= urlencode($aula_id) ?>&centro=<?= urlencode($centro_id) ?>" class="btn btn-primary">Gestionar</a></td>
                </tr>
                <?php endforeach; ?>
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
                    <th>Foto</th><th>Nombre</th>
                    <th><a href="?action=new_activity&centro=<?= urlencode($centro_id) ?>" class="btn btn-success">+ Nuevo</a></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $ap): ?>
                <?php $an = basename($ap); $img_path = "$ap/photo.jpg"; ?>
                <tr>
                    <td><img src="<?= file_exists($img_path) ? htmlspecialchars("/entreaulas/_filefetch.php?type=panel_actividades&centro=" . urlencode($centro_id) . "&activity=" . urlencode($an)) : '/static/logo-entreaulas.png' ?>" style="height: 50px;"></td>
                    <td><?= htmlspecialchars($an) ?></td>
                    <td><a href="?action=edit_activity&centro=<?= urlencode($centro_id) ?>&activity=<?= urlencode($an) ?>" class="btn btn-primary">Gestionar</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
        break;
    case "index":
    default:
        $all_centros = db_get_centros();
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Gestión de Centros</h1>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Centro</th>
                    <th><a href="?action=new" class="btn btn-success">+ Nuevo</a></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_centros as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['centro_id']) ?></td>
                    <td><a href="?action=edit&centro=<?= urlencode($c['centro_id']) ?>" class="btn btn-primary">Gestionar</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
        break;
}

require_once "_incl/post-body.php";
