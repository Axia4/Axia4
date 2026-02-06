<?php
require_once "_incl/auth_redir.php";
if (in_array("entreaulas:docente", $_SESSION["auth_data"]["permissions"] ?? []) === false) {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied");
}
require_once "_incl/pre-body.php";

$aulario_id = $_GET["aulario"] ?? "";
$centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"] ?? "";

if ($aulario_id === "" || $centro_id === "") {
	?>
	<div class="card pad">
		<h1>Menú del Comedor</h1>
		<p>No se ha indicado un aulario válido.</p>
	</div>
	<?php
	require_once "_incl/post-body.php";
	exit;
}

$aulario_path = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json";
$aulario = file_exists($aulario_path) ? json_decode(file_get_contents($aulario_path), true) : null;

// Check if this aulario shares comedor data from another aulario
$source_aulario_id = $aulario_id; // Default to current aulario
$is_shared = false;
if ($aulario && !empty($aulario["shared_comedor_from"])) {
	$shared_from = $aulario["shared_comedor_from"];
	$shared_aulario_path = "/DATA/entreaulas/Centros/$centro_id/Aularios/$shared_from.json";
	if (file_exists($shared_aulario_path)) {
		$source_aulario_id = $shared_from;
		$is_shared = true;
	}
}

$menuTypesPath = "/DATA/entreaulas/Centros/$centro_id/Aularios/$source_aulario_id/Comedor-MenuTypes.json";
$defaultMenuTypes = [
	["id" => "basal", "label" => "Menú basal", "color" => "#0d6efd"],
	["id" => "vegetariano", "label" => "Menú vegetariano", "color" => "#198754"],
	["id" => "alergias", "label" => "Menú alergias", "color" => "#dc3545"],
];
if (!file_exists($menuTypesPath)) {
	if (!is_dir(dirname($menuTypesPath))) {
		mkdir(dirname($menuTypesPath), 0777, true);
	}
	file_put_contents($menuTypesPath, json_encode($defaultMenuTypes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$menuTypes = json_decode(@file_get_contents($menuTypesPath), true);
if (!is_array($menuTypes) || count($menuTypes) === 0) {
	$menuTypes = $defaultMenuTypes;
}

$menuTypeIds = [];
foreach ($menuTypes as $t) {
	if (!empty($t["id"])) {
		$menuTypeIds[] = $t["id"];
	}
}

$dateParam = $_GET["date"] ?? date("Y-m-d");
$dateObj = DateTime::createFromFormat("Y-m-d", $dateParam) ?: new DateTime();
$date = $dateObj->format("Y-m-d");
$menuTypeId = $_GET["menu"] ?? ($menuTypeIds[0] ?? "basal");
if (!in_array($menuTypeId, $menuTypeIds, true)) {
	$menuTypeId = $menuTypeIds[0] ?? "basal";
}

$ym = $dateObj->format("Y-m");
$day = $dateObj->format("d");
$baseDir = "/DATA/entreaulas/Centros/$centro_id/Aularios/$source_aulario_id/Comedor/$ym/$day";
$dataPath = "$baseDir/_datos.json";

function blank_menu()
{
	return [
		"plates" => [
			"primero" => ["name" => "", "pictogram" => ""],
			"segundo" => ["name" => "", "pictogram" => ""],
			"postre" => ["name" => "", "pictogram" => ""],
		]
	];
}

$menuData = [
	"date" => $date,
	"menus" => []
];
if (file_exists($dataPath)) {
	$existing = json_decode(file_get_contents($dataPath), true);
	if (is_array($existing)) {
		$menuData = array_merge($menuData, $existing);
	}
}
if (!isset($menuData["menus"][$menuTypeId])) {
	$menuData["menus"][$menuTypeId] = blank_menu();
}

$canEdit = in_array("sysadmin:access", $_SESSION["auth_data"]["permissions"] ?? []) && !$is_shared;
$saveNotice = "";
$uploadErrors = [];

function safe_filename($name)
{
	$name = basename($name);
	return preg_replace("/[^a-zA-Z0-9._-]/", "_", $name);
}

function handle_image_upload($fieldName, $targetBaseName, $baseDir, &$uploadErrors)
{
	if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]["error"] !== UPLOAD_ERR_OK) {
		return null;
	}
	$ext = strtolower(pathinfo($_FILES[$fieldName]["name"], PATHINFO_EXTENSION));
	$allowed = ["jpg", "jpeg", "png", "webp", "gif"]; 
	if (!in_array($ext, $allowed, true)) {
		$uploadErrors[] = "El archivo " . htmlspecialchars($_FILES[$fieldName]["name"]) . " no es una imagen válida.";
		return null;
	}
	if (!is_dir($baseDir)) {
		mkdir($baseDir, 0777, true);
	}
	$target = "$targetBaseName.$ext";
	$targetPath = $baseDir . "/" . safe_filename($target);
	if (move_uploaded_file($_FILES[$fieldName]["tmp_name"], $targetPath)) {
		return basename($targetPath);
	}
	$uploadErrors[] = "No se pudo guardar " . htmlspecialchars($_FILES[$fieldName]["name"]) . ".";
	return null;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $canEdit) {
	$action = $_POST["action"] ?? "";

	if ($action === "add_type") {
		$newId = strtolower(trim($_POST["new_type_id"] ?? ""));
		$newLabel = trim($_POST["new_type_label"] ?? "");
		$newColor = trim($_POST["new_type_color"] ?? "#0d6efd");
		if ($newId !== "" && $newLabel !== "") {
			$exists = false;
			foreach ($menuTypes as $t) {
				if (($t["id"] ?? "") === $newId) {
					$exists = true;
					break;
				}
			}
			if (!$exists) {
				$menuTypes[] = ["id" => $newId, "label" => $newLabel, "color" => $newColor];
				file_put_contents($menuTypesPath, json_encode($menuTypes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
				header("Location: /entreaulas/comedor.php?aulario=" . urlencode($aulario_id) . "&date=" . urlencode($date) . "&menu=" . urlencode($newId));
				exit;
			}
		}
	}

	if ($action === "delete_type") {
		$deleteId = trim($_POST["delete_type_id"] ?? "");
		if ($deleteId !== "") {
			$deleted = false;
			$newMenuTypes = [];
			foreach ($menuTypes as $t) {
				if (($t["id"] ?? "") === $deleteId) {
					$deleted = true;
				} else {
					$newMenuTypes[] = $t;
				}
			}
			if ($deleted) {
				$menuTypes = $newMenuTypes;
				file_put_contents($menuTypesPath, json_encode($menuTypes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
				// Redirect to the first available menu type or default
				$redirectMenuId = !empty($menuTypes) ? $menuTypes[0]["id"] : "basal";
				header("Location: /entreaulas/comedor.php?aulario=" . urlencode($aulario_id) . "&date=" . urlencode($date) . "&menu=" . urlencode($redirectMenuId));
				exit;
			}
		}
	}

	if ($action === "rename_type") {
		$renameId = trim($_POST["rename_type_id"] ?? "");
		$newLabel = trim($_POST["rename_type_label"] ?? "");
		$newColor = trim($_POST["rename_type_color"] ?? "");
		if ($renameId !== "" && $newLabel !== "") {
			foreach ($menuTypes as &$t) {
				if (($t["id"] ?? "") === $renameId) {
					$t["label"] = $newLabel;
					if ($newColor !== "") {
						$t["color"] = $newColor;
					}
					break;
				}
			}
			// Clean up the reference to avoid accidental usage after the loop
			unset($t);
			file_put_contents($menuTypesPath, json_encode($menuTypes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
			header("Location: /entreaulas/comedor.php?aulario=" . urlencode($aulario_id) . "&date=" . urlencode($date) . "&menu=" . urlencode($renameId));
			exit;
		}
	}

	if ($action === "save") {
		$menuTypeId = $_POST["menu_type"] ?? $menuTypeId;
		if (!isset($menuData["menus"][$menuTypeId])) {
			$menuData["menus"][$menuTypeId] = blank_menu();
		}

		$plates = ["primero", "segundo", "postre"];
		foreach ($plates as $plate) {
			$name = trim($_POST["name_" . $plate] ?? "");
			$menuData["menus"][$menuTypeId]["plates"][$plate]["name"] = $name;

			$pictUpload = handle_image_upload("pictogram_file_" . $plate, $menuTypeId . "_" . $plate . "_pict", $baseDir, $uploadErrors);

			if ($pictUpload !== null) {
				$menuData["menus"][$menuTypeId]["plates"][$plate]["pictogram"] = $pictUpload;
			}

		}

		if (!is_dir($baseDir)) {
			mkdir($baseDir, 0777, true);
		}
		file_put_contents($dataPath, json_encode($menuData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		$saveNotice = "Menú guardado correctamente.";
	}
}

$menuForType = $menuData["menus"][$menuTypeId] ?? blank_menu();
function image_src($value, $centro_id, $source_aulario_id, $date)
{
	if (!$value) {
		return "";
	}
	if (filter_var($value, FILTER_VALIDATE_URL)) {
		return $value;
	}
	return "/entreaulas/_filefetch.php?type=comedor_image&centro=" . urlencode($centro_id) . "&aulario=" . urlencode($source_aulario_id) . "&date=" . urlencode($date) . "&file=" . urlencode($value);
}

$prevDate = (clone $dateObj)->modify("-1 day")->format("Y-m-d");
$nextDate = (clone $dateObj)->modify("+1 day")->format("Y-m-d");

$userAulas = $_SESSION["auth_data"]["entreaulas"]["aulas"] ?? [];
$aulaOptions = [];
foreach ($userAulas as $aulaId) {
	$aulaPath = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulaId.json";
	$aulaData = file_exists($aulaPath) ? json_decode(file_get_contents($aulaPath), true) : null;
	$aulaOptions[] = [
		"id" => $aulaId,
		"name" => $aulaData["name"] ?? $aulaId
	];
}
?>



<?php if ($is_shared): ?>
	<div class="card pad" style="background: #cfe2ff; color: #084298;">
		<strong>ℹ️ Datos compartidos:</strong> Este aulario está mostrando los menús del aulario origen. Para editar, debes acceder al aulario origen o desactivar el compartir en la configuración.
	</div>
<?php endif; ?>

<?php if ($saveNotice !== ""): ?>
	<div class="card pad" style="background: #d1e7dd; color: #0f5132;">
		<?= htmlspecialchars($saveNotice) ?>
	</div>
<?php endif; ?>

<?php if (count($uploadErrors) > 0): ?>
	<div class="card pad" style="background: #f8d7da; color: #842029;">
		<ul style="margin: 0;">
			<?php foreach ($uploadErrors as $err): ?>
				<li><?= $err ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<!-- Navigation Buttons - Single row -->
<div class="card pad">
    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: center; flex-direction: row;">
        <a class="btn btn-outline-dark" href="/entreaulas/comedor.php?aulario=<?= urlencode($aulario_id) ?>&date=<?= urlencode($prevDate) ?>&menu=<?= urlencode($menuTypeId) ?>">⟵ Día anterior</a>
        <input type="date" id="datePicker" class="form-control form-control-lg" value="<?= htmlspecialchars($date) ?>" style="max-width: 200px;">
        <a class="btn btn-outline-dark" href="/entreaulas/comedor.php?aulario=<?= urlencode($aulario_id) ?>&date=<?= urlencode($nextDate) ?>&menu=<?= urlencode($menuTypeId) ?>">Día siguiente ⟶</a>
    </div>
    <div style="margin-top: 10px; text-align: center;">
        <label for="aularioPicker" class="form-label" style="margin-right: 10px;">Aulario:</label>
        <select id="aularioPicker" class="form-select form-select-lg" style="max-width: 300px; display: inline-block;">
            <?php foreach ($aulaOptions as $option): 
                $isSelected = ($option["id"] ?? "") === $aulario_id;
                ?>
                <option value="<?= htmlspecialchars($option["id"] ?? "") ?>" <?= $isSelected ? "selected" : "" ?>>
                    <?= htmlspecialchars($option["name"] ?? $option["id"]) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="card pad">
	<h2 style="margin-bottom: 10px;">Tipos de menú</h2>
	<div style="display: flex; gap: 10px; flex-wrap: wrap;">
		<?php foreach ($menuTypes as $type):
			$isActive = ($type["id"] ?? "") === $menuTypeId;
			$color = $type["color"] ?? "#0d6efd";
			?>
			<a href="/entreaulas/comedor.php?aulario=<?= urlencode($aulario_id) ?>&date=<?= urlencode($date) ?>&menu=<?= urlencode($type["id"]) ?>"
			   class="btn btn-lg" style="background: <?= htmlspecialchars($color) ?>; color: white; border: 3px solid <?= $isActive ? "#000" : "transparent" ?>;">
				<?= htmlspecialchars($type["label"] ?? $type["id"]) ?>
			</a>
		<?php endforeach; ?>
	</div>
</div>

<div class="menu-grid">
	<?php
	$plates = [
		"primero" => "Primer plato",
		"segundo" => "Segundo plato",
		"postre" => "Postre"
	];
	foreach ($plates as $plateKey => $plateLabel):
		$plate = $menuForType["plates"][$plateKey] ?? ["name" => "", "pictogram" => ""];
		$pictSrc = image_src($plate["pictogram"] ?? "", $centro_id, $source_aulario_id, $date);
		?>
		<div class="card pad menu-card">
			<h3 class="menu-title"><?= htmlspecialchars($plateLabel) ?></h3>
			<div class="menu-images">
				<div class="menu-img-block">
					<div class="menu-img-label">Pictograma</div>
					<?php if ($pictSrc !== ""): ?>
						<img class="menu-img" src="<?= htmlspecialchars($pictSrc) ?>" alt="Pictograma de <?= htmlspecialchars($plateLabel) ?>">
					<?php else: ?>
						<div class="menu-placeholder">Sin pictograma</div>
					<?php endif; ?>
				</div>
			</div>
			<div class="menu-name">
				<?= $plate["name"] !== "" ? htmlspecialchars($plate["name"]) : "Sin nombre" ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<?php if ($canEdit): ?>
	<details class="card pad" open>
		<summary><strong>Editar menú</strong></summary>
		<form method="post" enctype="multipart/form-data" style="margin-top: 10px;">
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="menu_type" value="<?= htmlspecialchars($menuTypeId) ?>">
			<div class="edit-grid">
				<?php foreach ($plates as $plateKey => $plateLabel):
					$plate = $menuForType["plates"][$plateKey] ?? ["name" => "", "pictogram" => ""];
					?>
					<div class="card pad" style="background: #f8f9fa;">
						<h4><?= htmlspecialchars($plateLabel) ?></h4>
						<label class="form-label">Nombre del plato</label>
						<input type="text" class="form-control form-control-lg" name="name_<?= $plateKey ?>" value="<?= htmlspecialchars($plate["name"] ?? "") ?>" placeholder="Ej. Lentejas">

						<label class="form-label" style="margin-top: 10px;">Pictograma (archivo)</label>
						<input type="file" class="form-control form-control-lg" name="pictogram_file_<?= $plateKey ?>" accept="image/*">
					</div>
				<?php endforeach; ?>
			</div>
			<button type="submit" class="btn btn-success btn-lg" style="margin-top: 10px;">Guardar menú</button>
		</form>
	</details>

	<details class="card pad">
		<summary><strong>Administrar tipos de menú</strong></summary>
		
		<!-- Add new menu type -->
		<div style="margin-top: 15px; padding: 15px; background: #e7f1ff; border-radius: 8px;">
			<h4 style="margin-bottom: 10px;">Añadir nuevo tipo de menú</h4>
			<form method="post">
				<input type="hidden" name="action" value="add_type">
				<div class="row g-2">
					<div class="col-md-4">
						<label class="form-label">ID</label>
						<input type="text" name="new_type_id" class="form-control form-control-lg" placeholder="basal" required>
					</div>
					<div class="col-md-5">
						<label class="form-label">Nombre</label>
						<input type="text" name="new_type_label" class="form-control form-control-lg" placeholder="Menú basal" required>
					</div>
					<div class="col-md-3">
						<label class="form-label">Color</label>
						<input type="color" name="new_type_color" class="form-control form-control-lg" value="#0d6efd">
					</div>
				</div>
				<button type="submit" class="btn btn-primary btn-lg" style="margin-top: 10px;">Añadir tipo</button>
			</form>
		</div>

		<!-- List existing menu types with edit/delete options -->
		<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px;">
			<h4 style="margin-bottom: 15px;">Tipos de menú existentes</h4>
			<?php foreach ($menuTypes as $type): ?>
				<div style="margin-bottom: 15px; padding: 12px; background: white; border-radius: 6px; border: 2px solid <?= htmlspecialchars($type["color"] ?? "#ccc") ?>;">
					<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
						<div style="flex: 1; min-width: 200px;">
							<strong style="font-size: 1.2rem;"><?= htmlspecialchars($type["label"] ?? $type["id"]) ?></strong>
							<br>
							<small style="color: #666;">ID: <?= htmlspecialchars($type["id"] ?? "") ?></small>
							<br>
							<small style="color: #666;">Color: <span style="display: inline-block; width: 20px; height: 20px; background: <?= htmlspecialchars($type["color"] ?? "#ccc") ?>; border-radius: 3px; vertical-align: middle;"></span> <?= htmlspecialchars($type["color"] ?? "") ?></small>
						</div>
						<div style="display: flex; gap: 8px; flex-wrap: wrap;">
							<!-- Rename form -->
							<button type="button" class="btn btn-warning" onclick="toggleRenameForm('<?= htmlspecialchars($type["id"] ?? "") ?>')">Renombrar</button>
							
							<!-- Delete form -->
							<form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar este tipo de menú?');">
								<input type="hidden" name="action" value="delete_type">
								<input type="hidden" name="delete_type_id" value="<?= htmlspecialchars($type["id"] ?? "") ?>">
								<button type="submit" class="btn btn-danger">Eliminar</button>
							</form>
						</div>
					</div>
					<!-- Rename form (hidden by default) -->
					<div id="rename-form-<?= htmlspecialchars($type["id"] ?? "") ?>" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
						<form method="post">
							<input type="hidden" name="action" value="rename_type">
							<input type="hidden" name="rename_type_id" value="<?= htmlspecialchars($type["id"] ?? "") ?>">
							<div class="row g-2">
								<div class="col-md-8">
									<label class="form-label">Nuevo nombre</label>
									<input type="text" name="rename_type_label" class="form-control" value="<?= htmlspecialchars($type["label"] ?? "") ?>" required>
								</div>
								<div class="col-md-4">
									<label class="form-label">Nuevo color</label>
									<input type="color" name="rename_type_color" class="form-control" value="<?= htmlspecialchars($type["color"] ?? "#0d6efd") ?>">
								</div>
							</div>
							<div style="margin-top: 8px;">
								<button type="submit" class="btn btn-success">Guardar cambios</button>
								<button type="button" class="btn btn-secondary" onclick="toggleRenameForm('<?= htmlspecialchars($type["id"] ?? "") ?>')">Cancelar</button>
							</div>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</details>

	<script>
		function toggleRenameForm(typeId) {
			// Sanitize typeId to prevent potential XSS
			const sanitizedId = typeId.replace(/[^a-zA-Z0-9_-]/g, '');
			const formDiv = document.getElementById('rename-form-' + sanitizedId);
			if (formDiv) {
				formDiv.style.display = formDiv.style.display === 'none' ? 'block' : 'none';
			}
		}
	</script>
<?php endif; ?>

<style>
	.menu-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
		gap: 12px;
	}
	.menu-card {
		min-height: 320px;
	}
	.menu-title {
		font-size: 1.6rem;
	}
	.menu-images {
		display: grid;
		grid-template-columns: 1fr;
		gap: 10px;
		margin: 10px 0;
	}
	.menu-img-block {
		text-align: center;
	}
	.menu-img-label {
		font-weight: bold;
		margin-bottom: 6px;
	}
	.menu-img {
		max-width: 100%;
		height: 140px;
		object-fit: contain;
		background: #fff;
		border-radius: 12px;
		padding: 6px;
		border: 2px solid #ddd;
	}
	.menu-placeholder {
		height: 140px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: #f1f1f1;
		border-radius: 12px;
		border: 2px dashed #aaa;
		color: #666;
		font-weight: bold;
	}
	.menu-name {
		font-size: 1.4rem;
		font-weight: bold;
		text-align: center;
		padding: 6px;
		background: #fff3cd;
		border-radius: 8px;
	}
	.edit-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
		gap: 12px;
	}
	.form-control-lg {
		font-size: 1.1rem;
	}
</style>

<script>
	const datePicker = document.getElementById("datePicker");
	const aularioPicker = document.getElementById("aularioPicker");
	function goToSelection() {
		const dateValue = datePicker ? datePicker.value : "";
		const aularioValue = aularioPicker ? aularioPicker.value : "";
		if (!dateValue || !aularioValue) return;
		const params = new URLSearchParams(window.location.search);
		params.set("date", dateValue);
		params.set("aulario", aularioValue);
		params.set("menu", "<?= htmlspecialchars($menuTypeId) ?>");
		window.location.href = "/entreaulas/comedor.php?" + params.toString();
	}
	if (datePicker) {
		datePicker.addEventListener("change", goToSelection);
	}
	if (aularioPicker) {
		aularioPicker.addEventListener("change", goToSelection);
	}
</script>

<?php require_once "_incl/post-body.php"; ?>
