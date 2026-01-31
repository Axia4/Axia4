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
		<h1>Proyectos</h1>
		<p>No se ha indicado un aulario válido.</p>
	</div>
	<?php
	require_once "_incl/post-body.php";
	exit;
}

$aulario_path = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json";
$aulario = file_exists($aulario_path) ? json_decode(file_get_contents($aulario_path), true) : null;

$proyectos_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id/Proyectos";
if (!is_dir($proyectos_dir)) {
	mkdir($proyectos_dir, 0777, true);
}

// Helper functions
function safe_filename($name) {
	$name = basename($name);
	return preg_replace("/[^a-zA-Z0-9._-]/", "_", $name);
}

function generate_id($name) {
	return strtolower(preg_replace("/[^a-zA-Z0-9]/", "_", $name)) . "_" . substr(md5(uniqid()), 0, 8);
}

function load_project($proyectos_dir, $project_id) {
	$project_file = "$proyectos_dir/$project_id.json";
	if (file_exists($project_file)) {
		return json_decode(file_get_contents($project_file), true);
	}
	return null;
}

function save_project($proyectos_dir, $project_id, $data) {
	$project_file = "$proyectos_dir/$project_id.json";
	return file_put_contents($project_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function list_projects($proyectos_dir) {
	$projects = [];
	if (!is_dir($proyectos_dir)) {
		return $projects;
	}
	$files = glob("$proyectos_dir/*.json");
	foreach ($files as $file) {
		$data = json_decode(file_get_contents($file), true);
		if ($data) {
			$projects[] = $data;
		}
	}
	// Sort by creation date (newest first)
	usort($projects, function($a, $b) {
		return ($b["created_at"] ?? 0) <=> ($a["created_at"] ?? 0);
	});
	return $projects;
}

// Handle actions
$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	$action = $_POST["action"] ?? "";
	
	if ($action === "create_project") {
		$name = trim($_POST["name"] ?? "");
		$description = trim($_POST["description"] ?? "");
		
		if ($name !== "") {
			$project_id = generate_id($name);
			$project_data = [
				"id" => $project_id,
				"name" => $name,
				"description" => $description,
				"created_at" => time(),
				"updated_at" => time(),
				"items" => []
			];
			
			save_project($proyectos_dir, $project_id, $project_data);
			
			// Create project directory
			$project_dir = "$proyectos_dir/$project_id";
			if (!is_dir($project_dir)) {
				mkdir($project_dir, 0777, true);
			}
			
			header("Location: /entreaulas/proyectos.php?aulario=" . urlencode($aulario_id) . "&project=" . urlencode($project_id));
			exit;
		} else {
			$error = "El nombre del proyecto es obligatorio.";
		}
	}
	
	if ($action === "delete_project") {
		$project_id = $_POST["project_id"] ?? "";
		if ($project_id !== "") {
			$project_file = "$proyectos_dir/$project_id.json";
			if (file_exists($project_file)) {
				unlink($project_file);
				// Also delete project directory
				$project_dir = "$proyectos_dir/$project_id";
				if (is_dir($project_dir)) {
					// Delete all files in directory
					$files = glob("$project_dir/*");
					foreach ($files as $file) {
						if (is_file($file)) {
							unlink($file);
						}
					}
					rmdir($project_dir);
				}
				$message = "Proyecto eliminado correctamente.";
			}
		}
	}
	
	if ($action === "add_item") {
		$project_id = $_POST["project_id"] ?? "";
		$item_type = $_POST["item_type"] ?? "link";
		$item_name = trim($_POST["item_name"] ?? "");
		$item_url = trim($_POST["item_url"] ?? "");
		
		if ($project_id !== "" && $item_name !== "") {
			$project = load_project($proyectos_dir, $project_id);
			if ($project) {
				$item_id = generate_id($item_name);
				$item = [
					"id" => $item_id,
					"name" => $item_name,
					"type" => $item_type,
					"created_at" => time()
				];
				
				if ($item_type === "link" && $item_url !== "") {
					$item["url"] = $item_url;
				} elseif ($item_type === "file" && isset($_FILES["item_file"]) && $_FILES["item_file"]["error"] === UPLOAD_ERR_OK) {
					// Handle file upload
					$project_dir = "$proyectos_dir/$project_id";
					if (!is_dir($project_dir)) {
						mkdir($project_dir, 0777, true);
					}
					
					$original_name = $_FILES["item_file"]["name"];
					$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
					$safe_name = safe_filename($original_name);
					$target_path = "$project_dir/$safe_name";
					
					// Make filename unique if exists
					$counter = 1;
					while (file_exists($target_path)) {
						$safe_name = pathinfo($original_name, PATHINFO_FILENAME) . "_" . $counter . "." . $ext;
						$safe_name = safe_filename($safe_name);
						$target_path = "$project_dir/$safe_name";
						$counter++;
					}
					
					if (move_uploaded_file($_FILES["item_file"]["tmp_name"], $target_path)) {
						$item["filename"] = $safe_name;
						$item["original_name"] = $original_name;
					} else {
						$error = "No se pudo subir el archivo.";
					}
				}
				
				if (!isset($project["items"])) {
					$project["items"] = [];
				}
				$project["items"][] = $item;
				$project["updated_at"] = time();
				
				save_project($proyectos_dir, $project_id, $project);
				
				header("Location: /entreaulas/proyectos.php?aulario=" . urlencode($aulario_id) . "&project=" . urlencode($project_id));
				exit;
			}
		}
	}
	
	if ($action === "delete_item") {
		$project_id = $_POST["project_id"] ?? "";
		$item_id = $_POST["item_id"] ?? "";
		
		if ($project_id !== "" && $item_id !== "") {
			$project = load_project($proyectos_dir, $project_id);
			if ($project && isset($project["items"])) {
				$new_items = [];
				foreach ($project["items"] as $item) {
					if ($item["id"] !== $item_id) {
						$new_items[] = $item;
					} else {
						// Delete file if it's a file type
						if ($item["type"] === "file" && isset($item["filename"])) {
							$file_path = "$proyectos_dir/$project_id/" . $item["filename"];
							if (file_exists($file_path)) {
								unlink($file_path);
							}
						}
					}
				}
				$project["items"] = $new_items;
				$project["updated_at"] = time();
				save_project($proyectos_dir, $project_id, $project);
				
				header("Location: /entreaulas/proyectos.php?aulario=" . urlencode($aulario_id) . "&project=" . urlencode($project_id));
				exit;
			}
		}
	}
}

// Determine current view
$current_project = $_GET["project"] ?? null;
$view = $current_project ? "project" : "list";

?>

<?php if ($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($view === "list"): ?>
	<!-- Project List View -->
	<div class="card pad">
		<h1 class="card-title">
			<img src="/static/iconexperience/shelf.png" height="40" style="vertical-align: middle;">
			Proyectos
		</h1>
		<p>Gestiona proyectos con enlaces y archivos para tu aulario.</p>
	</div>
	
	<!-- Create New Project Button -->
	<div class="card pad">
		<button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createProjectModal">
			<img src="/static/iconexperience/add.png" height="30" style="vertical-align: middle;">
			Crear Nuevo Proyecto
		</button>
	</div>
	
	<!-- Project List -->
	<?php
	$projects = list_projects($proyectos_dir);
	if (count($projects) > 0):
	?>
	<div id="grid">
		<?php foreach ($projects as $project): ?>
		<div class="card grid-item" style="width: 300px;">
			<div class="card-body">
				<h5 class="card-title">
					<img src="/static/iconexperience/shelf.png" height="30" style="vertical-align: middle;">
					<?= htmlspecialchars($project["name"]) ?>
				</h5>
				<?php if (!empty($project["description"])): ?>
				<p class="card-text"><?= htmlspecialchars($project["description"]) ?></p>
				<?php endif; ?>
				<p class="card-text">
					<small class="text-muted">
						<?= count($project["items"] ?? []) ?> elementos
					</small>
				</p>
				<div class="d-flex gap-2">
					<a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>&project=<?= urlencode($project["id"]) ?>" class="btn btn-primary">
						<img src="/static/iconexperience/find.png" height="20" style="vertical-align: middle;">
						Abrir
					</a>
					<form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este proyecto?');">
						<input type="hidden" name="action" value="delete_project">
						<input type="hidden" name="project_id" value="<?= htmlspecialchars($project["id"]) ?>">
						<button type="submit" class="btn btn-danger">
							<img src="/static/iconexperience/garbage.png" height="20" style="vertical-align: middle;">
							Eliminar
						</button>
					</form>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php else: ?>
	<div class="card pad">
		<p>No hay proyectos creados aún. ¡Crea tu primer proyecto!</p>
	</div>
	<?php endif; ?>
	
	<!-- Create Project Modal -->
	<div class="modal fade" id="createProjectModal" tabindex="-1" aria-labelledby="createProjectModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="createProjectModalLabel">Crear Nuevo Proyecto</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form method="post">
					<div class="modal-body">
						<input type="hidden" name="action" value="create_project">
						<div class="mb-3">
							<label for="project_name" class="form-label">Nombre del Proyecto *</label>
							<input type="text" class="form-control form-control-lg" id="project_name" name="name" required>
						</div>
						<div class="mb-3">
							<label for="project_description" class="form-label">Descripción</label>
							<textarea class="form-control" id="project_description" name="description" rows="3"></textarea>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary">Crear Proyecto</button>
					</div>
				</form>
			</div>
		</div>
	</div>

<?php elseif ($view === "project"): ?>
	<!-- Project Detail View -->
	<?php
	$project = load_project($proyectos_dir, $current_project);
	if (!$project):
	?>
	<div class="card pad">
		<h1>Error</h1>
		<p>Proyecto no encontrado.</p>
		<a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-primary">Volver a Proyectos</a>
	</div>
	<?php
	require_once "_incl/post-body.php";
	exit;
	endif;
	?>
	
	<div class="card pad">
		<div class="d-flex justify-content-between align-items-start">
			<div>
				<h1 class="card-title">
					<img src="/static/iconexperience/shelf.png" height="40" style="vertical-align: middle;">
					<?= htmlspecialchars($project["name"]) ?>
				</h1>
				<?php if (!empty($project["description"])): ?>
				<p><?= htmlspecialchars($project["description"]) ?></p>
				<?php endif; ?>
			</div>
			<a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary">
				← Volver a Proyectos
			</a>
		</div>
	</div>
	
	<!-- Add Item Button -->
	<div class="card pad">
		<button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addItemModal">
			<img src="/static/iconexperience/add.png" height="30" style="vertical-align: middle;">
			Añadir Enlace o Archivo
		</button>
	</div>
	
	<!-- Items List -->
	<?php
	$items = $project["items"] ?? [];
	if (count($items) > 0):
	?>
	<div id="grid">
		<?php foreach ($items as $item): ?>
		<div class="card grid-item" style="width: 280px;">
			<div class="card-body">
				<h5 class="card-title">
					<?php if ($item["type"] === "link"): ?>
						<img src="/static/arasaac/actividad.png" height="30" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px;">
					<?php else: ?>
						<img src="/static/iconexperience/contract.png" height="30" style="vertical-align: middle;">
					<?php endif; ?>
					<?= htmlspecialchars($item["name"]) ?>
				</h5>
				<p class="card-text">
					<small class="text-muted">
						<?= $item["type"] === "link" ? "Enlace" : "Archivo" ?>
						<?php if ($item["type"] === "file" && isset($item["original_name"])): ?>
							<br>(<?= htmlspecialchars($item["original_name"]) ?>)
						<?php endif; ?>
					</small>
				</p>
				<div class="d-flex gap-2 flex-wrap">
					<?php if ($item["type"] === "link"): ?>
						<a href="<?= htmlspecialchars($item["url"]) ?>" target="_blank" class="btn btn-primary btn-lg">
							<img src="/static/iconexperience/find.png" height="25" style="vertical-align: middle;">
							Abrir Enlace
						</a>
					<?php else: ?>
						<a href="/entreaulas/_filefetch.php?aulario=<?= urlencode($aulario_id) ?>&path=Proyectos/<?= urlencode($current_project) ?>/<?= urlencode($item["filename"]) ?>" target="_blank" class="btn btn-primary btn-lg">
							<img src="/static/iconexperience/find.png" height="25" style="vertical-align: middle;">
							Abrir Archivo
						</a>
					<?php endif; ?>
					<form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este elemento?');">
						<input type="hidden" name="action" value="delete_item">
						<input type="hidden" name="project_id" value="<?= htmlspecialchars($current_project) ?>">
						<input type="hidden" name="item_id" value="<?= htmlspecialchars($item["id"]) ?>">
						<button type="submit" class="btn btn-danger">
							<img src="/static/iconexperience/garbage.png" height="20" style="vertical-align: middle;">
						</button>
					</form>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php else: ?>
	<div class="card pad">
		<p>Este proyecto aún no tiene elementos. ¡Añade tu primer enlace o archivo!</p>
	</div>
	<?php endif; ?>
	
	<!-- Add Item Modal -->
	<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="addItemModalLabel">Añadir Elemento al Proyecto</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form method="post" enctype="multipart/form-data" id="addItemForm">
					<div class="modal-body">
						<input type="hidden" name="action" value="add_item">
						<input type="hidden" name="project_id" value="<?= htmlspecialchars($current_project) ?>">
						
						<div class="mb-3">
							<label for="item_type" class="form-label">Tipo de Elemento *</label>
							<select class="form-select form-select-lg" id="item_type" name="item_type" required>
								<option value="link">Enlace (URL, Videollamada, etc.)</option>
								<option value="file">Archivo (PDF, imagen, etc.)</option>
							</select>
						</div>
						
						<div class="mb-3">
							<label for="item_name" class="form-label">Nombre del Elemento *</label>
							<input type="text" class="form-control form-control-lg" id="item_name" name="item_name" required>
						</div>
						
						<div class="mb-3" id="url_field">
							<label for="item_url" class="form-label">URL *</label>
							<input type="url" class="form-control form-control-lg" id="item_url" name="item_url" placeholder="https://...">
							<small class="form-text text-muted">
								Ejemplo: https://meet.google.com/abc-defg-hij para Google Meet
							</small>
						</div>
						
						<div class="mb-3" id="file_field" style="display: none;">
							<label for="item_file" class="form-label">Archivo *</label>
							<input type="file" class="form-control form-control-lg" id="item_file" name="item_file">
							<small class="form-text text-muted">
								Formatos soportados: PDF, imágenes, documentos, etc.
							</small>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-success">Añadir Elemento</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	
	<script>
		document.getElementById('item_type').addEventListener('change', function() {
			var type = this.value;
			var urlField = document.getElementById('url_field');
			var fileField = document.getElementById('file_field');
			var urlInput = document.getElementById('item_url');
			var fileInput = document.getElementById('item_file');
			
			if (type === 'link') {
				urlField.style.display = 'block';
				fileField.style.display = 'none';
				urlInput.required = true;
				fileInput.required = false;
			} else {
				urlField.style.display = 'none';
				fileField.style.display = 'block';
				urlInput.required = false;
				fileInput.required = true;
			}
		});
	</script>
	
<?php endif; ?>

<style>
	.grid-item {
		margin-bottom: 10px !important;
	}
	.btn-lg {
		font-size: 1.25rem;
		padding: 0.75rem 1.5rem;
	}
	.modal-lg {
		max-width: 800px;
	}
</style>

<script>
	var msnry = new Masonry('#grid', {
		"columnWidth": 280,
		"itemSelector": ".grid-item",
		"gutter": 10,
		"transitionDuration": 0
	});
	setTimeout(() => {msnry.layout()}, 150);
	window.addEventListener('resize', function(event) {
		msnry.layout()
	}, true);
</script>

<?php require_once "_incl/post-body.php"; ?>
