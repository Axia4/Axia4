<?php
require_once "_incl/auth_redir.php";
if (in_array("entreaulas:docente", $_SESSION["auth_data"]["permissions"] ?? []) === false) {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied");
}


$aulario_id = $_GET["aulario"] ?? "";
$centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"] ?? "";

if ($aulario_id === "" || $centro_id === "") {
	require_once "_incl/pre-body.php";
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
	mkdir($proyectos_dir, 0755, true);
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
	$result = file_put_contents($project_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	if ($result === false) {
		error_log("Failed to save project file: $project_file");
	}
	return $result;
}

function get_project_breadcrumb($proyectos_dir, $project_id) {
	$breadcrumb = [];
	$current_id = $project_id;
	
	while ($current_id) {
		$project = load_project($proyectos_dir, $current_id);
		if (!$project) break;
		
		array_unshift($breadcrumb, $project);
		$current_id = $project["parent_id"] ?? null;
	}
	
	return $breadcrumb;
}

function list_projects($proyectos_dir, $parent_id = null) {
	$projects = [];
	if (!is_dir($proyectos_dir)) {
		return $projects;
	}
	$files = glob("$proyectos_dir/*.json");
	foreach ($files as $file) {
		$data = json_decode(file_get_contents($file), true);
		if ($data) {
			// Filter by parent_id
			$project_parent = $data["parent_id"] ?? null;
			if ($parent_id === null && $project_parent === null) {
				// Root level projects (no parent)
				$projects[] = $data;
			} elseif ($parent_id !== null && $project_parent === $parent_id) {
				// Sub-projects of specified parent
				$projects[] = $data;
			}
		} else {
			error_log("Failed to decode JSON from file: $file");
		}
	}
	// Sort by creation date (newest first)
	usort($projects, function($a, $b) {
		return ($b["created_at"] ?? 0) <=> ($a["created_at"] ?? 0);
	});
	return $projects;
}

/**
 * Get linked projects from other aularios based on aulario configuration
 * 
 * @param array $aulario The aulario configuration containing linked_projects array
 * @param string $centro_id The centro ID for constructing file paths
 * @return array Array of project data arrays with is_linked and source_aulario fields added
 */
function get_linked_projects($aulario, $centro_id) {
	$linked = [];
	$linked_projects = $aulario["linked_projects"] ?? [];
	
	foreach ($linked_projects as $link) {
		$source_aulario = $link["source_aulario"] ?? "";
		$project_id = $link["project_id"] ?? "";
		
		if (empty($source_aulario) || empty($project_id)) {
			continue;
		}
		
		$source_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios/$source_aulario/Proyectos";
		$project_file = "$source_dir/$project_id.json";
		
		if (file_exists($project_file)) {
			$project = json_decode(file_get_contents($project_file), true);
			if ($project && ($project["parent_id"] ?? null) === null) {
				// Mark as linked and add source info
				$project["is_linked"] = true;
				$project["source_aulario"] = $source_aulario;
				$linked[] = $project;
			}
		}
	}
	
	return $linked;
}

// Handle actions
$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	$action = $_POST["action"] ?? "";
	
	if ($action === "create_project") {
		$name = trim($_POST["name"] ?? "");
		$description = trim($_POST["description"] ?? "");
		$parent_id = trim($_POST["parent_id"] ?? "");
		
		if ($name !== "") {
			// Determine level based on parent
			$level = 1;
			if ($parent_id !== "") {
				$parent = load_project($proyectos_dir, $parent_id);
				if ($parent) {
					$level = ($parent["level"] ?? 1) + 1;
					// Enforce max 3 levels
					if ($level > 3) {
						$error = "No se pueden crear más de 3 niveles de sub-proyectos.";
					}
				} else {
					$error = "Proyecto padre no encontrado.";
				}
			}
			
			if (empty($error)) {
				$project_id = generate_id($name);
				$project_data = [
					"id" => $project_id,
					"name" => $name,
					"description" => $description,
					"created_at" => time(),
					"updated_at" => time(),
					"items" => [],
					"subprojects" => [],
					"parent_id" => $parent_id !== "" ? $parent_id : null,
					"level" => $level
				];
				
				save_project($proyectos_dir, $project_id, $project_data);
				
				// Create project directory
				$project_dir = "$proyectos_dir/$project_id";
				if (!is_dir($project_dir)) {
					mkdir($project_dir, 0755, true);
				}
				
				// Update parent's subprojects list
				if ($parent_id !== "") {
					$parent = load_project($proyectos_dir, $parent_id);
					if ($parent) {
						if (!isset($parent["subprojects"])) {
							$parent["subprojects"] = [];
						}
						$parent["subprojects"][] = $project_id;
						$parent["updated_at"] = time();
						save_project($proyectos_dir, $parent_id, $parent);
					}
				}
				
				header("Location: /entreaulas/proyectos.php?aulario=" . urlencode($aulario_id) . "&project=" . urlencode($project_id));
				exit;
			}
		} else {
			$error = "El nombre del proyecto es obligatorio.";
		}
	}
	
	if ($action === "delete_project") {
		$project_id = $_POST["project_id"] ?? "";
		if ($project_id !== "") {
			$project_file = "$proyectos_dir/$project_id.json";
			if (file_exists($project_file)) {
				// Load project to get parent_id
				$project = load_project($proyectos_dir, $project_id);
				
				// Remove from parent's subprojects list
				if ($project && !empty($project["parent_id"])) {
					$parent = load_project($proyectos_dir, $project["parent_id"]);
					if ($parent && isset($parent["subprojects"])) {
						$parent["subprojects"] = array_values(array_filter($parent["subprojects"], function($id) use ($project_id) {
							return $id !== $project_id;
						}));
						$parent["updated_at"] = time();
						save_project($proyectos_dir, $project["parent_id"], $parent);
					}
				}
				
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
		$source_aulario_param = $_POST["source_aulario"] ?? "";
		
		// Determine which directory to use and permission level
		$working_dir = $proyectos_dir;
		$needs_approval = false;
		$source_aulario_id_for_save = "";
		
		if (!empty($source_aulario_param)) {
			// Validate the link
			$linked_projects = $aulario["linked_projects"] ?? [];
			foreach ($linked_projects as $link) {
				if (($link["source_aulario"] ?? "") === $source_aulario_param && 
				    ($link["project_id"] ?? "") === $project_id) {
					$permission = $link["permission"] ?? "read_only";
					if ($permission === "full_edit") {
						$working_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios/$source_aulario_param/Proyectos";
					} elseif ($permission === "request_edit") {
						// Changes need approval - save as pending
						$needs_approval = true;
						$source_aulario_id_for_save = $source_aulario_param;
					}
					break;
				}
			}
		}
		
		if ($project_id !== "" && $item_name !== "") {
			if ($needs_approval) {
				// Create pending change request
				$pending_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios/$source_aulario_id_for_save/Proyectos/$project_id/pending_changes";
				if (!is_dir($pending_dir)) {
					mkdir($pending_dir, 0755, true);
				}
				
				$change_id = uniqid("change_");
				$change_data = [
					"id" => $change_id,
					"type" => "add_item",
					"requested_by_aulario" => $aulario_id,
					"requested_at" => time(),
					"status" => "pending",
					"item_type" => $item_type,
					"item_name" => $item_name,
					"item_url" => $item_url
				];
				
				// Handle file upload for pending changes
				if ($item_type === "file" && isset($_FILES["item_file"]) && $_FILES["item_file"]["error"] === UPLOAD_ERR_OK) {
					$ext = strtolower(pathinfo($_FILES["item_file"]["name"], PATHINFO_EXTENSION));
					$allowed_extensions = ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "jpg", "jpeg", "png", "gif", "webp", "txt", "zip", "mp4", "mp3"];
					
					if (in_array($ext, $allowed_extensions, true)) {
						$safe_name = safe_filename($_FILES["item_file"]["name"]);
						$temp_file_path = "$pending_dir/{$change_id}_$safe_name";
						
						if (move_uploaded_file($_FILES["item_file"]["tmp_name"], $temp_file_path)) {
							$change_data["pending_filename"] = basename($temp_file_path);
							$change_data["original_filename"] = $_FILES["item_file"]["name"];
						}
					}
				}
				
				$change_file = "$pending_dir/$change_id.json";
				file_put_contents($change_file, json_encode($change_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
				
				$message = "Solicitud de cambio enviada. El aulario origen debe aprobarla.";
				$redirect_params = "aulario=" . urlencode($aulario_id) . "&project=" . urlencode($project_id);
				if (!empty($source_aulario_param)) {
					$redirect_params .= "&source=" . urlencode($source_aulario_param);
				}
				// Don't exit yet, let the view render with the message
			} else {
				// Direct edit (full_edit permission or local project)
				$project = load_project($working_dir, $project_id);
				if ($project) {
					$item_id = generate_id($item_name);
					$item = [
						"id" => $item_id,
						"name" => $item_name,
						"type" => $item_type,
						"created_at" => time()
					];
					
					$can_add_item = true;
					
					if ($item_type === "link" && $item_url !== "") {
						$item["url"] = $item_url;
					} elseif ($item_type === "file" && isset($_FILES["item_file"]) && $_FILES["item_file"]["error"] === UPLOAD_ERR_OK) {
						// Handle file upload with validation
						$project_dir = "$working_dir/$project_id";
						if (!is_dir($project_dir)) {
							mkdir($project_dir, 0755, true);
						}
						
						// Validate file size (max 500MB as configured in PHP)
						$max_size = 500 * 1024 * 1024; // 500MB
						if ($_FILES["item_file"]["size"] > $max_size) {
							$error = "El archivo es demasiado grande. Tamaño máximo: 500MB.";
							$can_add_item = false;
						}
						
						// Validate file type
						if ($can_add_item) {
							$original_name = $_FILES["item_file"]["name"];
							$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
							$allowed_extensions = ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "jpg", "jpeg", "png", "gif", "webp", "txt", "zip", "mp4", "mp3"];
							
							if (!in_array($ext, $allowed_extensions, true)) {
								$error = "Tipo de archivo no permitido. Extensiones permitidas: " . implode(", ", $allowed_extensions);
								$can_add_item = false;
							}
						}
						
						if ($can_add_item) {
							$safe_name = safe_filename($original_name);
							$target_path = "$project_dir/$safe_name";
							
							// Make filename unique if exists
							$counter = 1;
							$basename = pathinfo($safe_name, PATHINFO_FILENAME);
							while (file_exists($target_path)) {
								$safe_name = safe_filename($basename . "_" . $counter . "." . $ext);
								$target_path = "$project_dir/$safe_name";
								$counter++;
							}
							
							if (move_uploaded_file($_FILES["item_file"]["tmp_name"], $target_path)) {
								$item["filename"] = $safe_name;
								$item["original_name"] = $original_name;
							} else {
								$error = "No se pudo subir el archivo.";
								$can_add_item = false;
							}
						}
					}
					
					if ($can_add_item) {
						if (!isset($project["items"])) {
							$project["items"] = [];
						}
						$project["items"][] = $item;
						$project["updated_at"] = time();
						
						save_project($working_dir, $project_id, $project);
						
						$redirect_params = "aulario=" . urlencode($aulario_id) . "&project=" . urlencode($project_id);
						if (!empty($source_aulario_param)) {
							$redirect_params .= "&source=" . urlencode($source_aulario_param);
						}
						header("Location: /entreaulas/proyectos.php?" . $redirect_params);
						exit;
					}
				}
			}
		}
	}

	if ($action === "approve_change" || $action === "reject_change") {
		$change_id = $_POST["change_id"] ?? "";
		$project_id = $_POST["project_id"] ?? "";
		
		if (!empty($change_id) && !empty($project_id)) {
			$pending_dir = "$proyectos_dir/$project_id/pending_changes";
			$change_file = "$pending_dir/$change_id.json";
			
			if (file_exists($change_file)) {
				$change_data = json_decode(file_get_contents($change_file), true);
				
				if ($action === "approve_change") {
					// Apply the change
					$project = load_project($proyectos_dir, $project_id);
					if ($project) {
						$item_id = generate_id($change_data["item_name"]);
						$item = [
							"id" => $item_id,
							"name" => $change_data["item_name"],
							"type" => $change_data["item_type"],
							"created_at" => time()
						];
						
						if ($change_data["item_type"] === "link") {
							$item["url"] = $change_data["item_url"];
						} elseif ($change_data["item_type"] === "file" && !empty($change_data["pending_filename"])) {
							// Move file from pending to project directory
							$pending_file = "$pending_dir/" . $change_data["pending_filename"];
							$project_dir = "$proyectos_dir/$project_id";
							$target_file = "$project_dir/" . $change_data["pending_filename"];
							
							if (file_exists($pending_file)) {
								if (!is_dir($project_dir)) {
									mkdir($project_dir, 0755, true);
								}
								rename($pending_file, $target_file);
								$item["filename"] = $change_data["pending_filename"];
								$item["original_name"] = $change_data["original_filename"] ?? $change_data["pending_filename"];
							}
						}
						
						if (!isset($project["items"])) {
							$project["items"] = [];
						}
						$project["items"][] = $item;
						$project["updated_at"] = time();
						save_project($proyectos_dir, $project_id, $project);
						
						$message = "Cambio aprobado y aplicado.";
					}
				} else {
					// Reject - just delete pending file if exists
					if (!empty($change_data["pending_filename"])) {
						$pending_file = "$pending_dir/" . $change_data["pending_filename"];
						if (file_exists($pending_file)) {
							unlink($pending_file);
						}
					}
					$message = "Cambio rechazado.";
				}
				
				// Delete the change request file
				unlink($change_file);
			}
		}
	}

	if ($action === "delete_item") {
		$project_id = $_POST["project_id"] ?? "";
		$item_id = $_POST["item_id"] ?? "";
		$source_aulario_param = $_POST["source_aulario"] ?? "";
		
		// Determine which directory to use based on whether this is a linked project
		$working_dir = $proyectos_dir;
		if (!empty($source_aulario_param)) {
			// Validate the link
			$linked_projects = $aulario["linked_projects"] ?? [];
			foreach ($linked_projects as $link) {
				if (($link["source_aulario"] ?? "") === $source_aulario_param && 
				    ($link["project_id"] ?? "") === $project_id &&
				    (($link["permission"] ?? "read_only") === "full_edit" || ($link["permission"] ?? "read_only") === "request_edit")) {
					$working_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios/$source_aulario_param/Proyectos";
					break;
				}
			}
		}
		
		if ($project_id !== "" && $item_id !== "") {
			$project = load_project($working_dir, $project_id);
			if ($project && isset($project["items"])) {
				$new_items = [];
				foreach ($project["items"] as $item) {
					if ($item["id"] !== $item_id) {
						$new_items[] = $item;
					} else {
						// Delete file if it's a file type
						if ($item["type"] === "file" && isset($item["filename"])) {
							$file_path = "$working_dir/$project_id/" . $item["filename"];
							if (file_exists($file_path)) {
								unlink($file_path);
							}
						}
					}
				}
				$project["items"] = $new_items;
				$project["updated_at"] = time();
				save_project($working_dir, $project_id, $project);
				
				$redirect_params = "aulario=" . urlencode($aulario_id) . "&project=" . urlencode($project_id);
				if (!empty($source_aulario_param)) {
					$redirect_params .= "&source=" . urlencode($source_aulario_param);
				}
				header("Location: /entreaulas/proyectos.php?" . $redirect_params);
				exit;
			}
		}
	}
}
require_once "_incl/pre-body.php";
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
			<img src="/static/arasaac/carpeta.png" height="40" style="vertical-align: middle; background: white; padding: 5px; border-radius: 10px;">
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
	// Get local projects and linked projects
	$local_projects = list_projects($proyectos_dir);
	$linked_projects = get_linked_projects($aulario, $centro_id);
	$projects = array_merge($local_projects, $linked_projects);
	
	// Sort by creation date
	usort($projects, function($a, $b) {
		return ($b["created_at"] ?? 0) <=> ($a["created_at"] ?? 0);
	});
	
	if (count($projects) > 0):
	?>
	<div id="grid">
		<?php foreach ($projects as $project): 
			$is_linked = $project["is_linked"] ?? false;
			$source_aulario = $project["source_aulario"] ?? "";
		?>
		<div class="card grid-item" style="width: 300px;">
			<div class="card-body">
				<h5 class="card-title">
					<img src="/static/arasaac/carpeta.png" height="30" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px;">
					<?= htmlspecialchars($project["name"]) ?>
					<?php if ($is_linked): ?>
						<span class="badge bg-info" style="font-size: 0.7rem;">Compartido</span>
					<?php endif; ?>
				</h5>
				<?php if (!empty($project["description"])): ?>
				<p class="card-text"><?= htmlspecialchars($project["description"]) ?></p>
				<?php endif; ?>
				<p class="card-text">
					<small class="text-muted">
						<?= count($project["items"] ?? []) ?> elementos
						<?php if (!empty($project["subprojects"])): ?>
							· <?= count($project["subprojects"]) ?> sub-proyectos
						<?php endif; ?>
					</small>
				</p>
				<div class="d-flex gap-2">
					<?php if ($is_linked): ?>
						<a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>&project=<?= urlencode($project["id"]) ?>&source=<?= urlencode($source_aulario) ?>" class="btn btn-primary">
							<img src="/static/iconexperience/find.png" height="20" style="vertical-align: middle;">
							Abrir
						</a>
					<?php else: ?>
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
					<?php endif; ?>
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
	// Check if this is a linked project from another aulario
	$source_aulario_for_project = $_GET["source"] ?? "";
	$is_linked_project = false;
	$linked_permission = "read_only";
	
	if (!empty($source_aulario_for_project)) {
		// Validate that this project is actually linked in the configuration
		$linked_projects = $aulario["linked_projects"] ?? [];
		$valid_link = false;
		foreach ($linked_projects as $link) {
			if (($link["source_aulario"] ?? "") === $source_aulario_for_project && 
			    ($link["project_id"] ?? "") === $current_project) {
				$valid_link = true;
				$linked_permission = $link["permission"] ?? "read_only";
				break;
			}
		}
		
		if ($valid_link) {
			$is_linked_project = true;
			$project_source_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios/$source_aulario_for_project/Proyectos";
			$project = load_project($project_source_dir, $current_project);
		} else {
			// Invalid link configuration, treat as local project
			$project = load_project($proyectos_dir, $current_project);
		}
	} else {
		// Load from local aulario
		$project = load_project($proyectos_dir, $current_project);
	}
	
	// Determine if editing is allowed
	$can_edit_linked = false;
	if ($is_linked_project) {
		if ($linked_permission === "full_edit" || $linked_permission === "request_edit") {
			// For now, treat both full_edit and request_edit as allowing edits
			// In the future, request_edit could implement an approval workflow
			$can_edit_linked = true;
		}
	}
	
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
	
	// Get breadcrumb path
	$breadcrumb = get_project_breadcrumb($proyectos_dir, $current_project);
	$project_level = $project["level"] ?? 1;
	?>
	
	<!-- Breadcrumb Navigation -->
	<nav aria-label="breadcrumb">
		<ol class="breadcrumb">
			<li class="breadcrumb-item">
				<a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>">Proyectos</a>
			</li>
			<?php foreach ($breadcrumb as $idx => $crumb): ?>
				<?php if ($idx < count($breadcrumb) - 1): ?>
					<li class="breadcrumb-item">
						<a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>&project=<?= urlencode($crumb["id"]) ?>">
							<?= htmlspecialchars($crumb["name"]) ?>
						</a>
					</li>
				<?php else: ?>
					<li class="breadcrumb-item active" aria-current="page">
						<?= htmlspecialchars($crumb["name"]) ?>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ol>
	</nav>
	
	<?php if ($is_linked_project): ?>
		<div class="card pad" style="background: <?= $can_edit_linked ? '#d1e7dd' : '#cfe2ff' ?>; color: <?= $can_edit_linked ? '#0f5132' : '#084298' ?>;">
			<?php if ($can_edit_linked): ?>
				<strong>✏️ Proyecto compartido con permisos de edición:</strong> Este es un proyecto compartido desde otro aulario. Puedes ver y editar su contenido. Los cambios se guardarán en el aulario origen.
			<?php else: ?>
				<strong>ℹ️ Proyecto compartido (solo lectura):</strong> Este es un proyecto compartido desde otro aulario. Solo puedes ver su contenido, pero no editarlo.
			<?php endif; ?>
		</div>
	<?php endif; ?>
	
	<div class="card pad">
		<div class="d-flex justify-content-between align-items-start">
			<div>
				<h1 class="card-title">
					<img src="/static/arasaac/carpeta.png" height="40" style="vertical-align: middle; background: white; padding: 5px; border-radius: 10px;">
					<?= htmlspecialchars($project["name"]) ?>
					<span class="badge bg-secondary">Nivel <?= $project_level ?></span>
					<?php if ($is_linked_project): ?>
						<span class="badge bg-info">Compartido</span>
					<?php endif; ?>
				</h1>
				<?php if (!empty($project["description"])): ?>
				<p><?= htmlspecialchars($project["description"]) ?></p>
				<?php endif; ?>
			</div>
			<?php if (!empty($project["parent_id"])): ?>
				<a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>&project=<?= urlencode($project["parent_id"]) ?>" class="btn btn-secondary">
					← Volver al Proyecto Padre
				</a>
			<?php else: ?>
				<a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary">
					← Volver a Proyectos
				</a>
			<?php endif; ?>
		</div>
	</div>
	
	<!-- Action Buttons -->
	<?php if (!$is_linked_project || $can_edit_linked): ?>
	<div class="card pad">
		<div class="d-flex gap-2 flex-wrap">
			<button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addItemModal">
				<img src="/static/iconexperience/add.png" height="30" style="vertical-align: middle;">
				Añadir Enlace o Archivo
			</button>
			<?php if ($project_level < 3): ?>
				<button type="button" class="btn btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#createSubProjectModal">
					<img src="/static/iconexperience/add.png" height="30" style="vertical-align: middle;">
					Crear Sub-Proyecto
				</button>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>
	
	<!-- Sub-Projects Section -->
	<?php
	$subprojects = list_projects($proyectos_dir, $current_project);
	if (count($subprojects) > 0):
	?>
	<div class="card pad">
		<h3>
			<img src="/static/arasaac/carpeta.png" height="25" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px;">
			Sub-Proyectos
		</h3>
	</div>
	<div id="grid-subprojects">
		<?php foreach ($subprojects as $subproject): ?>
		<div class="card grid-item" style="width: 300px;">
			<div class="card-body">
				<h5 class="card-title">
					<img src="/static/arasaac/carpeta.png" height="25" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px;">
					<?= htmlspecialchars($subproject["name"]) ?>
					<span class="badge bg-info">Nivel <?= $subproject["level"] ?? 2 ?></span>
				</h5>
				<?php if (!empty($subproject["description"])): ?>
				<p class="card-text"><?= htmlspecialchars($subproject["description"]) ?></p>
				<?php endif; ?>
				<p class="card-text">
					<small class="text-muted">
						<?= count($subproject["items"] ?? []) ?> elementos
						<?php if (!empty($subproject["subprojects"])): ?>
							· <?= count($subproject["subprojects"]) ?> sub-proyectos
						<?php endif; ?>
					</small>
				</p>
				<div class="d-flex gap-2">
					<a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>&project=<?= urlencode($subproject["id"]) ?>" class="btn btn-primary">
						<img src="/static/iconexperience/find.png" height="20" style="vertical-align: middle;">
						Abrir
					</a>
					<form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este sub-proyecto?');">
						<input type="hidden" name="action" value="delete_project">
						<input type="hidden" name="project_id" value="<?= htmlspecialchars($subproject["id"]) ?>">
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
	<?php endif; ?>
	
	<!-- Items List -->
	<?php if (count($subprojects) > 0): ?>
	<div class="card pad">
		<h3>
			<img src="/static/arasaac/documento.png" height="25" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px;">
			Archivos y Enlaces
		</h3>
	</div>
	<?php endif; ?>
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
						<img src="/static/arasaac/documento.png" height="30" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px;">
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
						<?php
						// Use source aulario for file fetch if linked project
						$fetch_aulario = $is_linked_project ? $source_aulario_for_project : $aulario_id;
						?>
						<a href="/entreaulas/_filefetch.php?type=proyecto_file&centro=<?= urlencode($centro_id) ?>&aulario=<?= urlencode($fetch_aulario) ?>&project=<?= urlencode($current_project) ?>&file=<?= urlencode($item["filename"]) ?>" target="_blank" class="btn btn-primary btn-lg">
							<img src="/static/iconexperience/find.png" height="25" style="vertical-align: middle;">
							Abrir Archivo
						</a>
					<?php endif; ?>
					<?php if (!$is_linked_project || $can_edit_linked): ?>
					<form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este elemento?');">
						<input type="hidden" name="action" value="delete_item">
						<input type="hidden" name="project_id" value="<?= htmlspecialchars($current_project) ?>">
						<input type="hidden" name="item_id" value="<?= htmlspecialchars($item["id"]) ?>">
						<?php if ($is_linked_project && $can_edit_linked): ?>
						<input type="hidden" name="source_aulario" value="<?= htmlspecialchars($source_aulario_for_project) ?>">
						<?php endif; ?>
						<button type="submit" class="btn btn-danger">
							<img src="/static/iconexperience/garbage.png" height="20" style="vertical-align: middle;">
						</button>
					</form>
					<?php endif; ?>
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
	
	<!-- Pending Changes Section (only for local projects with pending changes) -->
	<?php
	if (!$is_linked_project) {
		$pending_dir = "$proyectos_dir/$current_project/pending_changes";
		$pending_changes = [];
		if (is_dir($pending_dir)) {
			$pending_files = glob("$pending_dir/*.json");
			foreach ($pending_files as $file) {
				$change = json_decode(file_get_contents($file), true);
				if ($change && ($change["status"] ?? "") === "pending") {
					$pending_changes[] = $change;
				}
			}
		}
		
		if (count($pending_changes) > 0):
	?>
	<div class="card pad" style="background: #fff3cd;">
		<h3 style="color: #856404;">
			⏳ Cambios Pendientes de Aprobación (<?= count($pending_changes) ?>)
		</h3>
		<p style="color: #856404;">Los siguientes cambios fueron solicitados por otros aularios y requieren tu aprobación:</p>
	</div>
	<div id="grid">
		<?php foreach ($pending_changes as $change): 
			$requesting_aulario = $change["requested_by_aulario"] ?? "Desconocido";
			// Get requesting aulario name
			$req_aul_path = "/DATA/entreaulas/Centros/$centro_id/Aularios/$requesting_aulario.json";
			$req_aul_data = file_exists($req_aul_path) ? json_decode(file_get_contents($req_aul_path), true) : null;
			$req_aul_name = $req_aul_data["name"] ?? $requesting_aulario;
		?>
		<div class="card grid-item" style="width: 300px; border: 2px solid #ffc107;">
			<div class="card-body">
				<h5 class="card-title">
					<?php if ($change["item_type"] === "link"): ?>
						<img src="/static/arasaac/actividad.png" height="30" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px;">
					<?php else: ?>
						<img src="/static/arasaac/documento.png" height="30" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px;">
					<?php endif; ?>
					<?= htmlspecialchars($change["item_name"]) ?>
					<span class="badge bg-warning text-dark">Pendiente</span>
				</h5>
				<p class="card-text">
					<small class="text-muted">
						Tipo: <?= $change["item_type"] === "link" ? "Enlace" : "Archivo" ?><br>
						Solicitado por: <strong><?= htmlspecialchars($req_aul_name) ?></strong><br>
						Fecha: <?= date("d/m/Y H:i", $change["requested_at"]) ?>
					</small>
				</p>
				<div class="d-flex gap-2 flex-wrap">
					<form method="post" style="display: inline;">
						<input type="hidden" name="action" value="approve_change">
						<input type="hidden" name="change_id" value="<?= htmlspecialchars($change["id"]) ?>">
						<input type="hidden" name="project_id" value="<?= htmlspecialchars($current_project) ?>">
						<button type="submit" class="btn btn-success">
							✓ Aprobar
						</button>
					</form>
					<form method="post" style="display: inline;">
						<input type="hidden" name="action" value="reject_change">
						<input type="hidden" name="change_id" value="<?= htmlspecialchars($change["id"]) ?>">
						<input type="hidden" name="project_id" value="<?= htmlspecialchars($current_project) ?>">
						<button type="submit" class="btn btn-danger">
							✗ Rechazar
						</button>
					</form>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php
		endif;
	}
	?>
	
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
						<?php if ($is_linked_project && $can_edit_linked): ?>
						<input type="hidden" name="source_aulario" value="<?= htmlspecialchars($source_aulario_for_project) ?>">
						<?php endif; ?>
						
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
	
	<!-- Create Sub-Project Modal -->
	<div class="modal fade" id="createSubProjectModal" tabindex="-1" aria-labelledby="createSubProjectModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="createSubProjectModalLabel">Crear Sub-Proyecto</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<form method="post">
					<div class="modal-body">
						<input type="hidden" name="action" value="create_project">
						<input type="hidden" name="parent_id" value="<?= htmlspecialchars($current_project) ?>">
						<div class="alert alert-info">
							Este sub-proyecto se creará dentro de <strong><?= htmlspecialchars($project["name"]) ?></strong> (Nivel <?= $project_level ?>)
						</div>
						<div class="mb-3">
							<label for="subproject_name" class="form-label">Nombre del Sub-Proyecto *</label>
							<input type="text" class="form-control form-control-lg" id="subproject_name" name="name" required>
						</div>
						<div class="mb-3">
							<label for="subproject_description" class="form-label">Descripción</label>
							<textarea class="form-control" id="subproject_description" name="description" rows="3"></textarea>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-info">Crear Sub-Proyecto</button>
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
	.breadcrumb {
		background-color: #f8f9fa;
		padding: 10px 15px;
		border-radius: 5px;
		margin-bottom: 15px;
	}
</style>

<script>
	// Initialize Masonry for main grid
	var grids = ['#grid', '#grid-subprojects'];
	grids.forEach(function(gridId) {
		var gridElement = document.querySelector(gridId);
		if (gridElement) {
			var msnry = new Masonry(gridId, {
				"columnWidth": 280,
				"itemSelector": ".grid-item",
				"gutter": 10,
				"transitionDuration": 0
			});
			setTimeout(() => {msnry.layout()}, 150);
			window.addEventListener('resize', function(event) {
				msnry.layout()
			}, true);
		}
	});
</script>

<?php require_once "_incl/post-body.php"; ?>
