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
        $org_id = safe_path_segment(Sf($_POST["org_id"] ?? ""));
        $org_name = Ssql($_POST["org_name"] ?? "");
        if (empty($org_id)) {
            die("Nombre de la organización no proporcionado.");
        }
        // Check uniqueness in DB
        $existing = db()->prepare("SELECT id FROM organizaciones WHERE org_id = ?");
        $existing->execute([$org_id]);
        if ($existing->fetch()) {
            die("La organización ya existe.");
        }
        // Create DB record
        db()->prepare("INSERT INTO organizaciones (org_id, org_name) VALUES (?, ?)")->execute([$org_id, $org_name !== '' ? $org_name : $org_id]);
        // Keep filesystem directory for activity photos (Panel/Actividades)
        $org_path = aulatek_orgs_base_path() . "/$org_id";
        if (!is_dir($org_path) && !mkdir($org_path, 0755, true) && !is_dir($org_path)) {
            error_log("orgs.php: failed to create directory $org_path");
        }
        header("Location: ?action=index");
        exit();
        break;
    case "edit":
        $org_id = safe_path_segment(Sf($_GET['org'] ?? ''));
        $org_name = Ssql($_POST['org_name'] ?? '');
        if ($org_id === '' || $org_name === '') {
            die("Datos inválidos para actualizar la organización.");
        }
        db()->prepare("UPDATE organizaciones SET org_name = ? WHERE org_id = ?")->execute([$org_name, $org_id]);
        header("Location: ?action=edit&org=" . urlencode($org_id) . "&_result=" . urlencode("Cambios guardados."));
        exit();
        break;
    case "create_activity":
        ini_set('memory_limit', '512M');
        ini_set('upload_max_filesize', '256M');
        ini_set('post_max_size', '256M');
        $org_id = safe_path_segment(Sf($_GET['org'] ?? ''));
        // Validate organization exists in DB
        $stmt = db()->prepare("SELECT id FROM organizaciones WHERE org_id = ?");
        $stmt->execute([$org_id]);
        if (!$stmt->fetch()) {
            die("Organización no válida.");
        }
        $activity_name  = safe_path_segment(Sf($_POST["name"] ?? ''));
        if (empty($activity_name)) {
            die("Nombre de la actividad no proporcionado.");
        }
        $activity_photo = $_FILES["photo"] ?? null;
        if ($activity_photo === null || $activity_photo["error"] !== UPLOAD_ERR_OK) {
            die("Error al subir la foto.");
        }
        $activity_path = aulatek_orgs_base_path() . "/$org_id/Panel/Actividades/$activity_name";
        if (is_dir($activity_path)) {
            die("La actividad ya existe.");
        }
        mkdir($activity_path, 0755, true);
        move_uploaded_file($activity_photo["tmp_name"], "$activity_path/photo.jpg");
        header("Location: ?action=edit&org=" . urlencode($org_id));
        exit();
        break;
    case "edit_activity":
        ini_set('memory_limit', '512M');
        ini_set('upload_max_filesize', '256M');
        ini_set('post_max_size', '256M');
        $org_id        = safe_path_segment(Sf($_GET['org'] ?? ''));
        $activity_name = safe_path_segment(Sf($_GET['activity'] ?? ''));
        $activity_path = aulatek_orgs_base_path() . "/$org_id/Panel/Actividades/$activity_name";
        if (!is_dir($activity_path)) {
            die("Actividad no válida.");
        }
        $activity_photo = $_FILES["file"] ?? null;
        if ($activity_photo !== null && $activity_photo["error"] === UPLOAD_ERR_OK) {
            move_uploaded_file($activity_photo["tmp_name"], "$activity_path/photo.jpg");
        }
        $new_name = safe_path_segment(Sf($_POST['nombre'] ?? ''));
        if ($new_name !== $activity_name && $new_name !== '') {
            $new_path = aulatek_orgs_base_path() . "/$org_id/Panel/Actividades/$new_name";
            if (is_dir($new_path)) {
                die("Ya existe una actividad con ese nombre.");
            }
            rename($activity_path, $new_path);
        }
        header("Location: ?action=edit&org=" . urlencode($org_id));
        exit();
        break;
}

require_once "_incl/pre-body.php";
$view_action = $_GET["action"] ?? "index";
switch ($view_action) {
    case "edit_activity":
        $org_id        = safe_path_segment(Sf($_GET['org'] ?? ''));
        $activity_name = safe_path_segment(Sf($_GET['activity'] ?? ''));
        $activity_path = aulatek_orgs_base_path() . "/$org_id/Panel/Actividades/$activity_name";
        if (!is_dir($activity_path)) {
            die("Actividad no válida.");
        }
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Gestión de la Actividad: <?= htmlspecialchars($activity_name) ?></h1>
        <form method="post" action="?form=edit_activity&org=<?= urlencode($org_id) ?>&activity=<?= urlencode($activity_name) ?>" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre de la actividad:</label>
                <input required type="text" id="nombre" name="nombre" class="form-control" value="<?= htmlspecialchars($activity_name) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Foto (pulsa para cambiarla):</label><br>
                <div style="width: 200px;">
                    <label class="dropimage" style="background-image: url('<?php
                        $img = file_exists("$activity_path/photo.jpg")
                            ? "/aulatek/_filefetch.php?type=panel_actividades&org=" . urlencode($org_id) . "&activity=" . urlencode($activity_name)
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
        $org_id = safe_path_segment(Sf($_GET['org'] ?? ''));
        $stmt = db()->prepare("SELECT id FROM organizaciones WHERE org_id = ?");
        $stmt->execute([$org_id]);
        if (!$stmt->fetch()) {
            die("Organización no válida.");
        }
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Nueva Actividad del Panel</h1>
        <form method="post" action="?form=create_activity&org=<?= urlencode($org_id) ?>" enctype="multipart/form-data">
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
        <h1 class="card-title">Nueva Organización</h1>
        <form method="post" action="?form=create">
            <div class="mb-3">
                <label for="org_id" class="form-label">ID de la organización:</label>
                <input required type="text" id="org_id" name="org_id" class="form-control" placeholder="Ej: Organizacion-Principal-001">
            </div>
            <div class="mb-3">
                <label for="org_name" class="form-label">Nombre de la organización:</label>
                <input required type="text" id="org_name" name="org_name" class="form-control" placeholder="Ej: Organización Principal">
            </div>
            <button type="submit" class="btn btn-primary">Crear Organización</button>
        </form>
    </div>
</div>
<?php
        break;
    case "edit":
        $org_id = safe_path_segment(Sf($_GET['org'] ?? ''));
        $stmt = db()->prepare("SELECT org_name FROM organizaciones WHERE org_id = ?");
        $stmt->execute([$org_id]);
        $org_row = $stmt->fetch();
        if (!$org_row) {
            die("Organización no válida.");
        }
        $org_name = $org_row['org_name'] ?? $org_id;
        $aularios   = db_get_aularios($org_id);
        $activities = glob(aulatek_orgs_base_path() . "/$org_id/Panel/Actividades/*", GLOB_ONLYDIR) ?: [];
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Gestión de la Organización: <?= htmlspecialchars($org_name) ?></h1>
    </div>
    <form method="post" action="?form=edit&org=<?= urlencode($org_id) ?>">
        <div class="mb-3">
            <label for="org_name" class="form-label">Nombre de la organización:</label>
            <input required type="text" id="org_name" name="org_name" class="form-control" value="<?= htmlspecialchars($org_name) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
</div>
<div class="card pad">
    <div>
        <h2>Aularios</h2>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Icono</th><th>Nombre</th>
                    <th><a href="/sysadmin/aularios.php?action=new&org=<?= urlencode($org_id) ?>" class="btn btn-success">+ Nuevo</a></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aularios as $aula_id => $aula): ?>
                <tr>
                    <td><img src="<?= htmlspecialchars($aula['icon'] ?: '/static/logo-entreaulas.png') ?>" alt="Icono" style="height: 50px;"></td>
                    <td><?= htmlspecialchars($aula['name'] ?: $aula_id) ?></td>
                    <td><a href="/sysadmin/aularios.php?action=edit&aulario=<?= urlencode($aula_id) ?>&org=<?= urlencode($org_id) ?>" class="btn btn-primary">Gestionar</a></td>
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
                    <th><a href="?action=new_activity&org=<?= urlencode($org_id) ?>" class="btn btn-success">+ Nuevo</a></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $ap): ?>
                <?php $an = basename($ap); $img_path = "$ap/photo.jpg"; ?>
                <tr>
                    <td><img src="<?= file_exists($img_path) ? htmlspecialchars("/aulatek/_filefetch.php?type=panel_actividades&org=" . urlencode($org_id) . "&activity=" . urlencode($an)) : '/static/logo-entreaulas.png' ?>" style="height: 50px;"></td>
                    <td><?= htmlspecialchars($an) ?></td>
                    <td><a href="?action=edit_activity&org=<?= urlencode($org_id) ?>&activity=<?= urlencode($an) ?>" class="btn btn-primary">Gestionar</a></td>
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
        $all_organizaciones = db_get_organizaciones();
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Gestión de Organizaciones</h1>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Organización</th>
                    <th style="text-align: right;"><a href="?action=new" class="btn btn-success">+ Nuevo</a></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_organizaciones as $o): ?>
                <tr>
                    <td><?= htmlspecialchars($o['org_name']) ?><br><small><?= htmlspecialchars($o['org_id']) ?></small></td>
                    <td><a href="?action=edit&org=<?= urlencode($o['org_id']) ?>" class="btn btn-primary">Gestionar</a></td>
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
