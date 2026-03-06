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
    case "delete":
        $aulario_id = safe_path_segment(Sf($_POST["aulario_id"] ?? ""));
        $centro_id  = safe_path_segment(Sf($_POST["centro_id"]  ?? ""));
        if ($aulario_id === "" || $centro_id === "") {
            die("Parámetros inválidos.");
        }
        // Remove from DB
        db()->prepare("DELETE FROM aularios WHERE centro_id = ? AND aulario_id = ?")
            ->execute([$centro_id, $aulario_id]);
        // Remove comedor, diario, panel data
        db()->prepare("DELETE FROM comedor_menu_types WHERE centro_id = ? AND aulario_id = ?")
            ->execute([$centro_id, $aulario_id]);
        db()->prepare("DELETE FROM comedor_entries WHERE centro_id = ? AND aulario_id = ?")
            ->execute([$centro_id, $aulario_id]);
        db()->prepare("DELETE FROM diario_entries WHERE centro_id = ? AND aulario_id = ?")
            ->execute([$centro_id, $aulario_id]);
        db()->prepare("DELETE FROM panel_alumno WHERE centro_id = ? AND aulario_id = ?")
            ->execute([$centro_id, $aulario_id]);
        // Remove filesystem directory with student photos
        $aulario_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id";
        function rrmdir($dir)
        {
            if (is_dir($dir)) {
                foreach (scandir($dir) as $object) {
                    if ($object !== "." && $object !== "..") {
                        $p = "$dir/$object";
                        is_dir($p) ? rrmdir($p) : unlink($p);
                    }
                }
                rmdir($dir);
            }
        }
        rrmdir($aulario_dir);
        header("Location: ?action=index");
        exit();
        break;
    case "create":
        $centro_id  = safe_path_segment(Sf($_POST["centro"]  ?? ""));
        $aulario_id = strtolower(preg_replace("/[^a-zA-Z0-9_-]/", "_", Sf($_POST["name"] ?? "")));
        if (empty($centro_id) || empty($aulario_id)) {
            die("Datos incompletos.");
        }
        // Ensure centro exists in DB
        $stmt = db()->prepare("SELECT id FROM centros WHERE centro_id = ?");
        $stmt->execute([$centro_id]);
        if (!$stmt->fetch()) {
            die("Centro no válido.");
        }
        db()->prepare(
            "INSERT OR IGNORE INTO aularios (centro_id, aulario_id, name, icon) VALUES (?, ?, ?, ?)"
        )->execute([
            $centro_id, $aulario_id,
            Sf($_POST["name"] ?? ""),
            Sf($_POST["icon"] ?? "/static/logo-entreaulas.png"),
        ]);
        // Create Alumnos directory for photo-based features
        @mkdir("/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id/Proyectos/", 0755, true);
        header("Location: ?action=index");
        exit();
        break;
    case "save_edit":
        $aulario_id = safe_path_segment(Sf($_POST["aulario_id"] ?? ""));
        $centro_id  = safe_path_segment(Sf($_POST["centro_id"]  ?? ""));
        if ($aulario_id === "" || $centro_id === "") {
            die("Parámetros inválidos.");
        }
        // Fetch existing extra data
        $existing = db_get_aulario($centro_id, $aulario_id);
        if ($existing === null) {
            die("Aulario no encontrado.");
        }
        // Build extra JSON preserving any existing extra fields
        $extra_skip = ['name', 'icon'];
        $extra = [];
        foreach ($existing as $k => $v) {
            if (!in_array($k, $extra_skip, true)) {
                $extra[$k] = $v;
            }
        }
        // Update shared_comedor_from if posted
        if (isset($_POST['shared_comedor_from'])) {
            $extra['shared_comedor_from'] = Sf($_POST['shared_comedor_from']);
        }
        db()->prepare(
            "UPDATE aularios SET name = ?, icon = ?, extra = ? WHERE centro_id = ? AND aulario_id = ?"
        )->execute([
            Sf($_POST["name"] ?? ""),
            Sf($_POST["icon"] ?? "/static/logo-entreaulas.png"),
            json_encode($extra),
            $centro_id,
            $aulario_id,
        ]);
        header("Location: ?action=edit&aulario=" . urlencode($aulario_id) . "&centro=" . urlencode($centro_id) . "&_result=" . urlencode("Cambios guardados."));
        exit();
        break;
}

$view_action = $_GET["action"] ?? "index";
switch ($view_action) {
    case "new":
        require_once "_incl/pre-body.php";
        $centro_id   = safe_path_segment(Sf($_GET["centro"] ?? ""));
        $all_centros = db_get_centro_ids();
?>
<div class="card pad">
    <div>
        <h1>Nuevo Aulario</h1>
        <form method="post" action="?form=create">
            <div class="mb-3">
                <label for="centro" class="form-label">Centro:</label>
                <select id="centro" name="centro" class="form-select" required>
                    <option value="">-- Selecciona un centro --</option>
                    <?php foreach ($all_centros as $cid): ?>
                    <option value="<?= htmlspecialchars($cid) ?>" <?= $cid === $centro_id ? 'selected' : '' ?>><?= htmlspecialchars($cid) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="name" class="form-label">Nombre:</label>
                <input required type="text" id="name" name="name" class="form-control" placeholder="Ej: Aula 1">
            </div>
            <div class="mb-3">
                <label for="icon" class="form-label">URL del icono:</label>
                <input type="url" id="icon" name="icon" class="form-control" value="/static/logo-entreaulas.png">
            </div>
            <button type="submit" class="btn btn-primary">Crear Aulario</button>
        </form>
    </div>
</div>
<?php
        require_once "_incl/post-body.php";
        break;
    case "edit":
        require_once "_incl/pre-body.php";
        $aulario_id = safe_path_segment(Sf($_GET["aulario"] ?? ""));
        $centro_id  = safe_path_segment(Sf($_GET["centro"]  ?? ""));
        $aulario    = db_get_aulario($centro_id, $aulario_id);
        if (!$aulario) {
            die("Aulario no encontrado.");
        }
        $other_aularios = db_get_aularios($centro_id);
?>
<div class="card pad">
    <div>
        <h1>Aulario: <?= htmlspecialchars($aulario['name'] ?? $aulario_id) ?></h1>
        <form method="post" action="?form=save_edit">
            <input type="hidden" name="aulario_id" value="<?= htmlspecialchars($aulario_id) ?>">
            <input type="hidden" name="centro_id"  value="<?= htmlspecialchars($centro_id) ?>">
            <div class="mb-3">
                <label for="name" class="form-label">Nombre:</label>
                <input required type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($aulario['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="icon" class="form-label">URL del icono:</label>
                <input type="text" id="icon" name="icon" class="form-control" value="<?= htmlspecialchars($aulario['icon'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="shared_comedor_from" class="form-label">Compartir comedor de:</label>
                <select id="shared_comedor_from" name="shared_comedor_from" class="form-select">
                    <option value="">-- Sin compartir --</option>
                    <?php foreach ($other_aularios as $aid => $adata): if ($aid === $aulario_id) continue; ?>
                    <option value="<?= htmlspecialchars($aid) ?>" <?= ($aulario['shared_comedor_from'] ?? '') === $aid ? 'selected' : '' ?>><?= htmlspecialchars($adata['name'] ?? $aid) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </form>
        <hr>
        <form method="post" action="?form=delete" onsubmit="return confirm('¿Eliminar este aulario? Se borrarán todos sus datos.')">
            <input type="hidden" name="aulario_id" value="<?= htmlspecialchars($aulario_id) ?>">
            <input type="hidden" name="centro_id"  value="<?= htmlspecialchars($centro_id) ?>">
            <button type="submit" class="btn btn-danger">Eliminar Aulario</button>
        </form>
    </div>
</div>
<?php
        require_once "_incl/post-body.php";
        break;
    case "index":
    default:
        require_once "_incl/pre-body.php";
        $all_centros = db_get_centros();
?>
<div class="card pad">
    <div>
        <h1>Gestión de Aularios</h1>
        <?php foreach ($all_centros as $c): ?>
            <?php $aularios = db_get_aularios($c['centro_id']); ?>
            <h2><?= htmlspecialchars($c['centro_id']) ?></h2>
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Icono</th><th>Nombre</th>
                        <th><a href="?action=new&centro=<?= urlencode($c['centro_id']) ?>" class="btn btn-success">+ Nuevo</a></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aularios as $aid => $adata): ?>
                    <tr>
                        <td><img src="<?= htmlspecialchars($adata['icon'] ?: '/static/logo-entreaulas.png') ?>" style="height: 50px;"></td>
                        <td><?= htmlspecialchars($adata['name'] ?: $aid) ?></td>
                        <td><a href="?action=edit&aulario=<?= urlencode($aid) ?>&centro=<?= urlencode($c['centro_id']) ?>" class="btn btn-primary">Editar</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>
</div>
<?php
        require_once "_incl/post-body.php";
        break;
}
