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

$proyectos_dir = "/DATA/entreaulas/Centros/$centro_id/Proyectos";
if (!is_dir($proyectos_dir)) {
  mkdir($proyectos_dir, 0755, true);
}

// Helper functions
function safe_filename($name)
{
  $name = basename($name);
  return preg_replace("/[^a-zA-Z0-9._-]/", "_", $name);
}

function sanitize_html($html)
{
  $html = trim($html ?? "");
  if ($html === "") {
    return "";
  }

  $allowed = "<b><strong><i><em><u><br><p><ul><ol><li><a><span><div>";
  $clean = strip_tags($html, $allowed);

  // Remove event handlers and style attributes
  $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);
  $clean = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);

  // Sanitize href/src attributes (no javascript: or data:)
  $clean = preg_replace_callback('/\s(href|src)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', function ($matches) {
    $attr = strtolower($matches[1]);
    $value = trim($matches[2], "\"' ");
    $lower = strtolower($value);
    if (strpos($lower, 'javascript:') === 0 || strpos($lower, 'data:') === 0) {
      return '';
    }
    return " $attr=\"" . htmlspecialchars($value, ENT_QUOTES) . "\"";
  }, $clean);

  return $clean;
}

function build_videocall_url($platform, $room, $custom_url, &$error)
{
  $platform = strtolower(trim((string)$platform));
  if ($platform === "jitsi") {
    $room = trim((string)$room);
    if ($room === "") {
      $error = "El nombre de la sala es obligatorio para la videollamada.";
      return ["", ""];
    }
    $safe_room = preg_replace("/[^a-zA-Z0-9_-]/", "-", $room);
    return ["https://meet.jit.si/" . $safe_room, $safe_room];
  }
  if ($platform === "google_meet") {
    $room = trim((string)$room);
    if ($room === "") {
      $error = "El código de Google Meet es obligatorio para la videollamada.";
      return ["", ""];
    }
    $safe_room = preg_replace("/[^a-zA-Z0-9-]/", "", $room);
    return ["https://meet.google.com/" . $safe_room, $safe_room];
  }

  $custom_url = trim((string)$custom_url);
  if ($custom_url === "" || filter_var($custom_url, FILTER_VALIDATE_URL) === false) {
    $error = "La URL de videollamada no es válida.";
    return ["", ""];
  }
  return [$custom_url, ""];
}

function generate_id($name)
{
  return strtolower(preg_replace("/[^a-zA-Z0-9]/", "_", $name)) . "_" . substr(md5(uniqid()), 0, 8);
}

function project_meta_path($project_dir)
{
  return "$project_dir/_data_.eadat";
}

function find_project_path($projects_base, $project_id)
{
  if (!is_dir($projects_base)) {
    return null;
  }

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projects_base, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );

  foreach ($iterator as $fileinfo) {
    if (!$fileinfo->isDir()) {
      continue;
    }
    $meta_file = $fileinfo->getPathname() . "/_data_.eadat";
    if (!file_exists($meta_file)) {
      continue;
    }
    $data = json_decode(file_get_contents($meta_file), true);
    if (($data["id"] ?? "") === $project_id) {
      return $fileinfo->getPathname();
    }
  }

  return null;
}

function delete_dir_recursive($dir)
{
  if (!is_dir($dir)) {
    return;
  }
  $items = array_diff(scandir($dir), [".", ".."]);
  foreach ($items as $item) {
    $path = "$dir/$item";
    if (is_dir($path)) {
      delete_dir_recursive($path);
    } else {
      unlink($path);
    }
  }
  rmdir($dir);
}

function save_file_metadata($file_path, $data)
{
  $meta_file = $file_path . ".eadat";
  file_put_contents($meta_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function parse_size_to_bytes($size)
{
  $size = trim((string)$size);
  if ($size === "") {
    return 0;
  }
  $last = strtolower(substr($size, -1));
  $num = (float)$size;
  if (in_array($last, ["g", "m", "k"], true)) {
    $num = (float)substr($size, 0, -1);
    switch ($last) {
      case "g":
        $num *= 1024;
      case "m":
        $num *= 1024;
      case "k":
        $num *= 1024;
    }
  }
  return (int)$num;
}

function format_bytes($bytes)
{
  $bytes = (int)$bytes;
  if ($bytes >= 1024 * 1024 * 1024) {
    return round($bytes / (1024 * 1024 * 1024), 1) . "GB";
  }
  if ($bytes >= 1024 * 1024) {
    return round($bytes / (1024 * 1024), 1) . "MB";
  }
  if ($bytes >= 1024) {
    return round($bytes / 1024, 1) . "KB";
  }
  return $bytes . "B";
}

$app_max_upload_bytes = 500 * 1024 * 1024;
$upload_limit = parse_size_to_bytes(ini_get("upload_max_filesize"));
$post_limit = parse_size_to_bytes(ini_get("post_max_size"));
$max_upload_bytes = $app_max_upload_bytes;
foreach ([$upload_limit, $post_limit] as $limit) {
  if ($limit > 0 && $limit < $max_upload_bytes) {
    $max_upload_bytes = $limit;
  }
}
$max_upload_label = format_bytes($max_upload_bytes);

function load_project($proyectos_dir, $project_id)
{
  $project_dir = find_project_path($proyectos_dir, $project_id);
  if (!$project_dir) {
    return null;
  }
  $project_file = project_meta_path($project_dir);
  if (!file_exists($project_file)) {
    return null;
  }
  return json_decode(file_get_contents($project_file), true);
}

function save_project($proyectos_dir, $project_id, $data)
{
  $project_dir = find_project_path($proyectos_dir, $project_id);
  if (!$project_dir && isset($data["_project_dir"])) {
    $project_dir = $data["_project_dir"];
  }
  if (!$project_dir) {
    return false;
  }
  $project_file = project_meta_path($project_dir);
  if (isset($data["_project_dir"])) {
    unset($data["_project_dir"]);
  }
  $result = file_put_contents($project_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  if ($result === false) {
    error_log("Failed to save project file: $project_file");
  }
  return $result;
}

function get_project_breadcrumb($proyectos_dir, $project_id)
{
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

function list_projects($proyectos_dir, $parent_id = null, $owner_aulario = null)
{
  $projects = [];
  if (!is_dir($proyectos_dir)) {
    return $projects;
  }
  $base_dir = $proyectos_dir;
  if ($parent_id !== null) {
    $parent_dir = find_project_path($proyectos_dir, $parent_id);
    if (!$parent_dir) {
      return $projects;
    }
    $base_dir = $parent_dir;
  }

  $entries = array_diff(scandir($base_dir), [".", ".."]);
  foreach ($entries as $entry) {
    $entry_path = "$base_dir/$entry";
    if (!is_dir($entry_path)) {
      continue;
    }
    $meta_file = project_meta_path($entry_path);
    if (!file_exists($meta_file)) {
      continue;
    }
    $data = json_decode(file_get_contents($meta_file), true);
    if ($data) {
      if ($parent_id === null && $owner_aulario !== null) {
        if (($data["owner_aulario"] ?? null) !== $owner_aulario) {
          continue;
        }
      }
      $projects[] = $data;
    }
  }
  // Sort by creation date (newest first)
  usort($projects, function ($a, $b) {
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
function get_linked_projects($aulario, $centro_id)
{
  $linked = [];
  $linked_projects = $aulario["linked_projects"] ?? [];

  foreach ($linked_projects as $link) {
    $source_aulario = $link["source_aulario"] ?? "";
    $project_id = $link["project_id"] ?? "";

    if (empty($source_aulario) || empty($project_id)) {
      continue;
    }

    $projects_base = "/DATA/entreaulas/Centros/$centro_id/Proyectos";
    $project = load_project($projects_base, $project_id);
    if ($project && ($project["parent_id"] ?? null) === null) {
      // Mark as linked and add source info
      $project["is_linked"] = true;
      $project["source_aulario"] = $source_aulario;
      $linked[] = $project;
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
    $description = sanitize_html($_POST["description"] ?? "");
    $parent_id = trim($_POST["parent_id"] ?? "");

    if ($name !== "") {
      // Determine level based on parent
      $level = 1;
      if ($parent_id !== "") {
        $parent = load_project($proyectos_dir, $parent_id);
        if ($parent) {
          $level = ($parent["level"] ?? 1) + 1;
          // Enforce max 6 levels
          if ($level > 6) {
            $error = "No se pueden crear más de 6 niveles de sub-proyectos.";
          }
        } else {
          $error = "Proyecto padre no encontrado.";
        }
      }

      if (empty($error)) {
        $project_id = generate_id($name);
        $parent_dir = $parent_id !== "" ? find_project_path($proyectos_dir, $parent_id) : null;
        if ($parent_id !== "" && !$parent_dir) {
          $error = "Proyecto padre no encontrado.";
        }
      }

      if (empty($error)) {
        $project_dir = $parent_id !== "" ? "$parent_dir/$project_id" : "$proyectos_dir/$project_id";
        if (!is_dir($project_dir)) {
          mkdir($project_dir, 0755, true);
        }
        $project_data = [
          "id" => $project_id,
          "name" => $name,
          "description" => $description,
          "created_at" => time(),
          "updated_at" => time(),
          "items" => [],
          "subprojects" => [],
          "parent_id" => $parent_id !== "" ? $parent_id : null,
          "level" => $level,
          "owner_aulario" => $aulario_id
        ];

        $project_data["_project_dir"] = $project_dir;
        save_project($proyectos_dir, $project_id, $project_data);

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

  if ($action === "share_project") {
    $project_id = $_POST["project_id"] ?? "";
    $target_aulario = $_POST["target_aulario"] ?? "";

    if ($project_id !== "" && $target_aulario !== "" && $target_aulario !== $aulario_id) {
      // Only allow sharing local projects
      $is_local_project = (load_project($proyectos_dir, $project_id) !== null);
      if (!$is_local_project) {
        $error = "No se puede compartir un proyecto ajeno.";
      } else {
        $target_config_path = "/DATA/entreaulas/Centros/$centro_id/Aularios/$target_aulario.json";
        if (!file_exists($target_config_path)) {
          $error = "Aulario de destino no encontrado.";
        } else {
          $target_config = json_decode(file_get_contents($target_config_path), true);
          if (!is_array($target_config)) {
            $target_config = [];
          }
          if (!isset($target_config["linked_projects"]) || !is_array($target_config["linked_projects"])) {
            $target_config["linked_projects"] = [];
          }

          $existing_index = null;
          foreach ($target_config["linked_projects"] as $idx => $link) {
            if (($link["source_aulario"] ?? "") === $aulario_id && ($link["project_id"] ?? "") === $project_id) {
              $existing_index = $idx;
              break;
            }
          }

          if ($existing_index !== null) {
            array_splice($target_config["linked_projects"], $existing_index, 1);
            $message = "Proyecto dejado de compartir.";
          } else {
            $target_config["linked_projects"][] = [
              "source_aulario" => $aulario_id,
              "project_id" => $project_id,
              "permission" => "request_edit"
            ];
            $message = "Proyecto compartido correctamente.";
          }

          file_put_contents($target_config_path, json_encode($target_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
      }
    }
  }

  if ($action === "delete_project") {
    if (in_array("entreaulas:proyectos:delete", $_SESSION["auth_data"]["permissions"] ?? []) === false) {
      $error = "No tienes permisos para borrar proyectos.";
    } else {
      $project_id = $_POST["project_id"] ?? "";
      if ($project_id !== "") {
        $project = load_project($proyectos_dir, $project_id);
        $project_dir = find_project_path($proyectos_dir, $project_id);

        // Remove from parent's subprojects list
        if ($project && !empty($project["parent_id"])) {
          $parent = load_project($proyectos_dir, $project["parent_id"]);
          if ($parent && isset($parent["subprojects"])) {
            $parent["subprojects"] = array_values(array_filter($parent["subprojects"], function ($id) use ($project_id) {
              return $id !== $project_id;
            }));
            $parent["updated_at"] = time();
            save_project($proyectos_dir, $project["parent_id"], $parent);
          }
        }

        if ($project_dir) {
          delete_dir_recursive($project_dir);
          $message = "Proyecto eliminado correctamente.";
        }
      }
    }
  }

  if ($action === "edit_project") {
    $project_id = $_POST["project_id"] ?? "";
    $name = trim($_POST["name"] ?? "");
    $description = sanitize_html($_POST["description"] ?? "");

    if ($project_id !== "" && $name !== "") {
      $project = load_project($proyectos_dir, $project_id);
      if ($project) {
        $project["name"] = $name;
        $project["description"] = $description;
        $project["updated_at"] = time();
        save_project($proyectos_dir, $project_id, $project);
        $message = "Proyecto actualizado correctamente.";
      } else {
        $error = "Proyecto no encontrado.";
      }
    } else {
      $error = "El nombre del proyecto es obligatorio.";
    }
  }

  if ($action === "add_item") {
    $project_id = $_POST["project_id"] ?? "";
    $item_type = $_POST["item_type"] ?? "link";
    $item_name = trim($_POST["item_name"] ?? "");
    $item_url = trim($_POST["item_url"] ?? "");
    $item_content = sanitize_html($_POST["item_content"] ?? "");
    $videocall_platform = $_POST["videocall_platform"] ?? "jitsi";
    $videocall_room = trim($_POST["videocall_room"] ?? "");
    $videocall_url = trim($_POST["videocall_url"] ?? "");
    $source_aulario_param = $_POST["source_aulario"] ?? "";

    // Determine which directory to use and permission level
    $working_dir = $proyectos_dir;
    $project_dir = null;
    $needs_approval = false;
    $source_aulario_id_for_save = "";

    if (!empty($source_aulario_param)) {
      // Validate the link
      $linked_projects = $aulario["linked_projects"] ?? [];
      foreach ($linked_projects as $link) {
        if (($link["source_aulario"] ?? "") === $source_aulario_param &&
          ($link["project_id"] ?? "") === $project_id
        ) {
          $permission = $link["permission"] ?? "read_only";
          if ($permission === "full_edit") {
            $working_dir = $proyectos_dir;
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
        $project_dir = find_project_path($proyectos_dir, $project_id);
        if (!$project_dir) {
          $error = "Proyecto no encontrado.";
        }
      }

      if ($needs_approval && empty($error)) {
        // Create pending change request
        $pending_dir = "$project_dir/pending_changes";
        if (!is_dir($pending_dir)) {
          mkdir($pending_dir, 0755, true);
        }

        $change_id = uniqid("change_");
        $change_data = [
          "id" => $change_id,
          "type" => "add_item",
          "requested_by_aulario" => $aulario_id,
          "requested_by_persona_name" => ($_SESSION["auth_data"]["display_name"] ?? "Desconocido"),
          "requested_at" => time(),
          "status" => "pending",
          "item_type" => $item_type,
          "item_name" => $item_name,
          "item_url" => $item_url,
          "item_content" => $item_content
        ];

        if ($item_type === "videocall") {
          $vc_error = "";
          [$vc_url, $vc_room] = build_videocall_url($videocall_platform, $videocall_room, $videocall_url, $vc_error);
          if ($vc_error !== "") {
            $error = $vc_error;
          } else {
            $change_data["item_url"] = $vc_url;
            $change_data["item_platform"] = $videocall_platform;
            $change_data["item_room"] = $vc_room;
          }
        }

        // Handle file upload for pending changes
        if (($item_type === "file" || $item_type === "pdf_secure") && isset($_FILES["item_file"]) && $_FILES["item_file"]["error"] === UPLOAD_ERR_OK) {
          if ($_FILES["item_file"]["size"] > $max_upload_bytes) {
            $error = "El archivo es demasiado grande. Tamaño máximo: $max_upload_label.";
          }
          $ext = strtolower(pathinfo($_FILES["item_file"]["name"], PATHINFO_EXTENSION));
          $allowed_extensions = $item_type === "pdf_secure" ? ["pdf"] : ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "jpg", "jpeg", "png", "gif", "webp", "txt", "zip", "mp4", "mp3"];

          if (empty($error) && in_array($ext, $allowed_extensions, true)) {
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
        $project_dir = find_project_path($working_dir, $project_id);
        if (!$project_dir) {
          $error = "Proyecto no encontrado.";
        }
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
          } elseif ($item_type === "notepad") {
            $item["content"] = $item_content;
          } elseif ($item_type === "videocall") {
            $vc_error = "";
            [$vc_url, $vc_room] = build_videocall_url($videocall_platform, $videocall_room, $videocall_url, $vc_error);
            if ($vc_error !== "") {
              $error = $vc_error;
              $can_add_item = false;
            } else {
              $item["url"] = $vc_url;
              $item["platform"] = $videocall_platform;
              $item["room"] = $vc_room;
            }
          } elseif (($item_type === "file" || $item_type === "pdf_secure") && isset($_FILES["item_file"]) && $_FILES["item_file"]["error"] === UPLOAD_ERR_OK) {
            // Handle file upload with validation
            if (!is_dir($project_dir)) {
              mkdir($project_dir, 0755, true);
            }

            // Validate file size (max 500MB as configured in PHP)
            if ($_FILES["item_file"]["size"] > $max_upload_bytes) {
              $error = "El archivo es demasiado grande. Tamaño máximo: $max_upload_label.";
              $can_add_item = false;
            }

            // Validate file type
            if ($can_add_item) {
              $original_name = $_FILES["item_file"]["name"];
              $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
              $allowed_extensions = $item_type === "pdf_secure" ? ["pdf"] : ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "jpg", "jpeg", "png", "gif", "webp", "txt", "zip", "mp4", "mp3"];

              if (!in_array($ext, $allowed_extensions, true)) {
                $error = $item_type === "pdf_secure" ? "El PDF seguro solo permite archivos PDF." : "Tipo de archivo no permitido. Extensiones permitidas: " . implode(", ", $allowed_extensions);
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

            if (in_array($item_type, ["file", "pdf_secure"], true) && isset($item["filename"])) {
              $file_meta = [
                "id" => $item_id,
                "name" => $item_name,
                "type" => $item_type,
                "original_name" => $item["original_name"] ?? $item["filename"],
                "created_at" => $item["created_at"]
              ];
              save_file_metadata($target_path, $file_meta);
            }

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
      $project_dir = find_project_path($proyectos_dir, $project_id);
      if (!$project_dir) {
        $error = "Proyecto no encontrado.";
      }
    }

    if (!empty($change_id) && !empty($project_id) && empty($error)) {
      $pending_dir = "$project_dir/pending_changes";
      $change_file = "$pending_dir/$change_id.json";

      if (file_exists($change_file)) {
        $change_data = json_decode(file_get_contents($change_file), true);

        if ($action === "approve_change") {
          $project = load_project($proyectos_dir, $project_id);
          if ($project) {
            if (($change_data["type"] ?? "") === "delete_item") {
              $item_id = $change_data["item_id"] ?? "";
              if ($item_id !== "" && isset($project["items"])) {
                $new_items = [];
                foreach ($project["items"] as $item) {
                  if ($item["id"] !== $item_id) {
                    $new_items[] = $item;
                  } else {
                    if (in_array($item["type"], ["file", "pdf_secure"], true) && isset($item["filename"])) {
                      $file_path = "$project_dir/" . $item["filename"];
                      if (file_exists($file_path)) {
                        unlink($file_path);
                        if (file_exists($file_path . ".eadat")) {
                          unlink($file_path . ".eadat");
                        }
                      }
                    }
                  }
                }
                $project["items"] = $new_items;
              }
              $project["updated_at"] = time();
              save_project($proyectos_dir, $project_id, $project);
              $message = "Cambio aprobado y aplicado.";
            } elseif (($change_data["type"] ?? "") === "edit_item") {
              $item_id = $change_data["item_id"] ?? "";
              if ($item_id !== "" && isset($project["items"])) {
                foreach ($project["items"] as &$item) {
                  if ($item["id"] !== $item_id) {
                    continue;
                  }

                  $item["name"] = $change_data["item_name"] ?? $item["name"];
                  if (($item["type"] ?? "") === "link") {
                    $item["url"] = $change_data["item_url"] ?? $item["url"] ?? "";
                  } elseif (($item["type"] ?? "") === "notepad") {
                    $item["content"] = sanitize_html($change_data["item_content"] ?? "");
                  } elseif (($item["type"] ?? "") === "videocall") {
                    $item["url"] = $change_data["item_url"] ?? "";
                    $item["platform"] = $change_data["item_platform"] ?? "jitsi";
                    $item["room"] = $change_data["item_room"] ?? "";
                  }

                  if (in_array(($item["type"] ?? ""), ["file", "pdf_secure"], true) && !empty($change_data["pending_filename"])) {
                    $pending_file = "$pending_dir/" . $change_data["pending_filename"];
                    $target_file = "$project_dir/" . $change_data["pending_filename"];
                    if (file_exists($pending_file)) {
                      if (!is_dir($project_dir)) {
                        mkdir($project_dir, 0755, true);
                      }
                      rename($pending_file, $target_file);
                      if (!empty($item["filename"])) {
                        $old_path = "$project_dir/" . $item["filename"];
                        if (file_exists($old_path)) {
                          unlink($old_path);
                          if (file_exists($old_path . ".eadat")) {
                            unlink($old_path . ".eadat");
                          }
                        }
                      }
                      $item["filename"] = $change_data["pending_filename"];
                      $item["original_name"] = $change_data["original_filename"] ?? $change_data["pending_filename"];

                      $file_meta = [
                        "id" => $item_id,
                        "name" => $item["name"],
                        "type" => $item["type"],
                        "original_name" => $item["original_name"],
                        "created_at" => $item["created_at"] ?? time()
                      ];
                      save_file_metadata($target_file, $file_meta);
                    }
                  }

                  if (in_array(($item["type"] ?? ""), ["file", "pdf_secure"], true) && !empty($item["filename"])) {
                    $file_path = "$project_dir/" . $item["filename"];
                    if (file_exists($file_path . ".eadat")) {
                      $file_meta = json_decode(file_get_contents($file_path . ".eadat"), true) ?: [];
                      $file_meta["name"] = $item["name"];
                      save_file_metadata($file_path, $file_meta);
                    }
                  }
                  break;
                }
                unset($item);
              }
              $project["updated_at"] = time();
              save_project($proyectos_dir, $project_id, $project);
              $message = "Cambio aprobado y aplicado.";
            } else {
              // Apply add_item change
              $item_id = generate_id($change_data["item_name"]);
              $item = [
                "id" => $item_id,
                "name" => $change_data["item_name"],
                "type" => $change_data["item_type"],
                "created_at" => time()
              ];

              if ($change_data["item_type"] === "link") {
                $item["url"] = $change_data["item_url"];
              } elseif ($change_data["item_type"] === "notepad") {
                $item["content"] = sanitize_html($change_data["item_content"] ?? "");
              } elseif ($change_data["item_type"] === "videocall") {
                $item["url"] = $change_data["item_url"] ?? "";
                $item["platform"] = $change_data["item_platform"] ?? "jitsi";
                $item["room"] = $change_data["item_room"] ?? "";
              } elseif (in_array($change_data["item_type"], ["file", "pdf_secure"], true) && !empty($change_data["pending_filename"])) {
                // Move file from pending to project directory
                $pending_file = "$pending_dir/" . $change_data["pending_filename"];
                $target_file = "$project_dir/" . $change_data["pending_filename"];

                if (file_exists($pending_file)) {
                  if (!is_dir($project_dir)) {
                    mkdir($project_dir, 0755, true);
                  }
                  rename($pending_file, $target_file);
                  $item["filename"] = $change_data["pending_filename"];
                  $item["original_name"] = $change_data["original_filename"] ?? $change_data["pending_filename"];

                  $file_meta = [
                    "id" => $item_id,
                    "name" => $change_data["item_name"],
                    "type" => $change_data["item_type"],
                    "original_name" => $item["original_name"],
                    "created_at" => $item["created_at"]
                  ];
                  save_file_metadata($target_file, $file_meta);
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
    $permission = "full_edit";
    $needs_approval = false;
    if (!empty($source_aulario_param)) {
      // Validate the link
      $linked_projects = $aulario["linked_projects"] ?? [];
      foreach ($linked_projects as $link) {
        if (($link["source_aulario"] ?? "") === $source_aulario_param &&
          ($link["project_id"] ?? "") === $project_id &&
          (($link["permission"] ?? "read_only") === "full_edit" || ($link["permission"] ?? "read_only") === "request_edit")
        ) {
          $working_dir = $proyectos_dir;
          $permission = $link["permission"] ?? "read_only";
          if ($permission === "request_edit") {
            $needs_approval = true;
          }
          break;
        }
      }
    }

    if ($project_id !== "" && $item_id !== "") {
      if (!empty($source_aulario_param) && $permission === "read_only") {
        $error = "No tienes permisos para eliminar elementos en este proyecto.";
      }
    }

    if ($project_id !== "" && $item_id !== "" && empty($error)) {
      $project_dir = find_project_path($working_dir, $project_id);
      $project = load_project($working_dir, $project_id);
      if ($project && isset($project["items"])) {
        if ($needs_approval) {
          $pending_dir = $project_dir ? "$project_dir/pending_changes" : null;
          if ($pending_dir && !is_dir($pending_dir)) {
            mkdir($pending_dir, 0755, true);
          }

          $target_item = null;
          foreach ($project["items"] as $item) {
            if ($item["id"] === $item_id) {
              $target_item = $item;
              break;
            }
          }

          if ($target_item && $pending_dir) {
            $change_id = uniqid("change_");
            $change_data = [
              "id" => $change_id,
              "type" => "delete_item",
              "requested_by_aulario" => $aulario_id,
              "requested_by_persona_name" => ($_SESSION["auth_data"]["display_name"] ?? "Desconocido"),
              "requested_at" => time(),
              "status" => "pending",
              "item_id" => $item_id,
              "item_name" => $target_item["name"] ?? "",
              "item_type" => $target_item["type"] ?? ""
            ];

            $change_file = "$pending_dir/$change_id.json";
            file_put_contents($change_file, json_encode($change_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $message = "Solicitud de eliminación enviada. El aulario origen debe aprobarla.";
            $redirect_params = "aulario=" . urlencode($aulario_id) . "&project=" . urlencode($project_id);
            if (!empty($source_aulario_param)) {
              $redirect_params .= "&source=" . urlencode($source_aulario_param);
            }
            header("Location: /entreaulas/proyectos.php?" . $redirect_params);
            exit;
          }
        }

        $new_items = [];
        foreach ($project["items"] as $item) {
          if ($item["id"] !== $item_id) {
            $new_items[] = $item;
          } else {
            // Delete file if it's a file type
            if (in_array($item["type"], ["file", "pdf_secure"], true) && isset($item["filename"])) {
              $file_path = $project_dir ? "$project_dir/" . $item["filename"] : null;
              if ($file_path && file_exists($file_path)) {
                unlink($file_path);
                if (file_exists($file_path . ".eadat")) {
                  unlink($file_path . ".eadat");
                }
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

  if ($action === "edit_item") {
    $project_id = $_POST["project_id"] ?? "";
    $item_id = $_POST["item_id"] ?? "";
    $item_name = trim($_POST["item_name"] ?? "");
    $item_url = trim($_POST["item_url"] ?? "");
    $item_content = sanitize_html($_POST["item_content"] ?? "");
    $videocall_platform = $_POST["edit_videocall_platform"] ?? "jitsi";
    $videocall_room = trim($_POST["edit_videocall_room"] ?? "");
    $videocall_url = trim($_POST["edit_videocall_url"] ?? "");
    $source_aulario_param = $_POST["source_aulario"] ?? "";

    $working_dir = $proyectos_dir;
    $permission = "full_edit";
    $needs_approval = false;
    if (!empty($source_aulario_param)) {
      $linked_projects = $aulario["linked_projects"] ?? [];
      foreach ($linked_projects as $link) {
        if (($link["source_aulario"] ?? "") === $source_aulario_param &&
          ($link["project_id"] ?? "") === $project_id &&
          (($link["permission"] ?? "read_only") === "full_edit" || ($link["permission"] ?? "read_only") === "request_edit")
        ) {
          $working_dir = $proyectos_dir;
          $permission = $link["permission"] ?? "read_only";
          if ($permission === "request_edit") {
            $needs_approval = true;
          }
          break;
        }
      }
    }

    if ($project_id !== "" && $item_id !== "" && $item_name !== "") {
      $project_dir = find_project_path($working_dir, $project_id);
      $project = load_project($working_dir, $project_id);
      if ($project && isset($project["items"])) {
        if ($needs_approval) {
          $project_dir = find_project_path($proyectos_dir, $project_id);
          if (!$project_dir) {
            $error = "Proyecto no encontrado.";
          }
        }

        if ($needs_approval && empty($error)) {
          $pending_dir = "$project_dir/pending_changes";
          if (!is_dir($pending_dir)) {
            mkdir($pending_dir, 0755, true);
          }

          $target_item = null;
          foreach ($project["items"] as $existing_item) {
            if ($existing_item["id"] === $item_id) {
              $target_item = $existing_item;
              break;
            }
          }

          if (!$target_item) {
            $error = "Elemento no encontrado.";
          } else {
            $change_id = uniqid("change_");
            $change_data = [
              "id" => $change_id,
              "type" => "edit_item",
              "requested_by_aulario" => $aulario_id,
              "requested_by_persona_name" => ($_SESSION["auth_data"]["display_name"] ?? "Desconocido"),
              "requested_at" => time(),
              "status" => "pending",
              "item_id" => $item_id,
              "item_name" => $item_name,
              "item_type" => $target_item["type"] ?? "",
              "item_url" => $item_url,
              "item_content" => $item_content
            ];

            if (($target_item["type"] ?? "") === "videocall") {
              $vc_error = "";
              [$vc_url, $vc_room] = build_videocall_url($videocall_platform, $videocall_room, $videocall_url, $vc_error);
              if ($vc_error !== "") {
                $error = $vc_error;
              } else {
                $change_data["item_url"] = $vc_url;
                $change_data["item_platform"] = $videocall_platform;
                $change_data["item_room"] = $vc_room;
              }
            }

            if (in_array(($target_item["type"] ?? ""), ["file", "pdf_secure"], true) && isset($_FILES["edit_item_file"]) && $_FILES["edit_item_file"]["error"] === UPLOAD_ERR_OK) {
              if ($_FILES["edit_item_file"]["size"] > $max_upload_bytes) {
                $error = "El archivo es demasiado grande. Tamaño máximo: $max_upload_label.";
              }

              $ext = strtolower(pathinfo($_FILES["edit_item_file"]["name"], PATHINFO_EXTENSION));
              $allowed_extensions = ($target_item["type"] ?? "") === "pdf_secure" ? ["pdf"] : ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "jpg", "jpeg", "png", "gif", "webp", "txt", "zip", "mp4", "mp3"];

              if (empty($error) && in_array($ext, $allowed_extensions, true)) {
                $safe_name = safe_filename($_FILES["edit_item_file"]["name"]);
                $temp_file_path = "$pending_dir/{$change_id}_$safe_name";

                if (move_uploaded_file($_FILES["edit_item_file"]["tmp_name"], $temp_file_path)) {
                  $change_data["pending_filename"] = basename($temp_file_path);
                  $change_data["original_filename"] = $_FILES["edit_item_file"]["name"];
                }
              }
            }

            if (empty($error)) {
              $change_file = "$pending_dir/$change_id.json";
              file_put_contents($change_file, json_encode($change_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

              $message = "Solicitud de cambio enviada. El aulario origen debe aprobarla.";
              $redirect_params = "aulario=" . urlencode($aulario_id) . "&project=" . urlencode($project_id);
              if (!empty($source_aulario_param)) {
                $redirect_params .= "&source=" . urlencode($source_aulario_param);
              }
              header("Location: /entreaulas/proyectos.php?" . $redirect_params);
              exit;
            }
          }
        }

        $can_save = true;
        foreach ($project["items"] as &$item) {
          if ($item["id"] === $item_id) {
            $item["name"] = $item_name;
            if ($item["type"] === "link") {
              $item["url"] = $item_url;
            } elseif ($item["type"] === "notepad") {
              $item["content"] = $item_content;
            } elseif ($item["type"] === "videocall") {
              $vc_error = "";
              [$vc_url, $vc_room] = build_videocall_url($videocall_platform, $videocall_room, $videocall_url, $vc_error);
              if ($vc_error !== "") {
                $error = $vc_error;
                $can_save = false;
                break;
              }
              $item["url"] = $vc_url;
              $item["platform"] = $videocall_platform;
              $item["room"] = $vc_room;
            }
            if (in_array($item["type"], ["file", "pdf_secure"], true) && isset($_FILES["edit_item_file"]) && $_FILES["edit_item_file"]["error"] === UPLOAD_ERR_OK) {
              if (!$project_dir) {
                $error = "No se pudo acceder al directorio del proyecto.";
                $can_save = false;
                break;
              }

              if ($_FILES["edit_item_file"]["size"] > $max_upload_bytes) {
                $error = "El archivo es demasiado grande. Tamaño máximo: $max_upload_label.";
                $can_save = false;
                break;
              }

              $original_name = $_FILES["edit_item_file"]["name"];
              $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
              $allowed_extensions = $item["type"] === "pdf_secure" ? ["pdf"] : ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "jpg", "jpeg", "png", "gif", "webp", "txt", "zip", "mp4", "mp3"];

              if (!in_array($ext, $allowed_extensions, true)) {
                $error = $item["type"] === "pdf_secure" ? "El PDF seguro solo permite archivos PDF." : "Tipo de archivo no permitido. Extensiones permitidas: " . implode(", ", $allowed_extensions);
                $can_save = false;
                break;
              }

              if (!is_dir($project_dir)) {
                mkdir($project_dir, 0755, true);
              }

              $safe_name = safe_filename($original_name);
              $target_path = "$project_dir/$safe_name";
              $counter = 1;
              $basename = pathinfo($safe_name, PATHINFO_FILENAME);
              while (file_exists($target_path)) {
                $safe_name = safe_filename($basename . "_" . $counter . "." . $ext);
                $target_path = "$project_dir/$safe_name";
                $counter++;
              }

              if (!move_uploaded_file($_FILES["edit_item_file"]["tmp_name"], $target_path)) {
                $error = "No se pudo subir el archivo.";
                $can_save = false;
                break;
              }

              if (isset($item["filename"])) {
                $old_path = "$project_dir/" . $item["filename"];
                if (file_exists($old_path)) {
                  unlink($old_path);
                  if (file_exists($old_path . ".eadat")) {
                    unlink($old_path . ".eadat");
                  }
                }
              }

              $item["filename"] = $safe_name;
              $item["original_name"] = $original_name;

              $file_meta = [
                "id" => $item_id,
                "name" => $item_name,
                "type" => $item["type"],
                "original_name" => $original_name,
                "created_at" => $item["created_at"] ?? time()
              ];
              save_file_metadata($target_path, $file_meta);
            }
            if (in_array($item["type"], ["file", "pdf_secure"], true) && $project_dir && isset($item["filename"])) {
              $file_path = "$project_dir/" . $item["filename"];
              if (file_exists($file_path . ".eadat")) {
                $file_meta = json_decode(file_get_contents($file_path . ".eadat"), true) ?: [];
                $file_meta["name"] = $item_name;
                save_file_metadata($file_path, $file_meta);
              }
            }
            $project["updated_at"] = time();
            break;
          }
        }
        unset($item);
        if ($can_save) {
          save_project($working_dir, $project_id, $project);
          $message = "Elemento actualizado correctamente.";
        }
      } else {
        $error = "Proyecto no encontrado.";
      }
    } else {
      $error = "El nombre del elemento es obligatorio.";
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
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="32" style="vertical-align: middle; fill: currentColor;">
        <title>folder-multiple</title>
        <path d="M22,4H14L12,2H6A2,2 0 0,0 4,4V16A2,2 0 0,0 6,18H22A2,2 0 0,0 24,16V6A2,2 0 0,0 22,4M2,6H0V11H0V20A2,2 0 0,0 2,22H20V20H2V6Z" />
      </svg>
      Proyectos
    </h1>
    <p>Gestiona proyectos con enlaces y archivos para tu aulario.</p>
    <span>
      <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createProjectModal">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="24" style="vertical-align: middle; fill: white;">
          <title>folder-plus</title>
          <path d="M13 19C13 19.34 13.04 19.67 13.09 20H4C2.9 20 2 19.11 2 18V6C2 4.89 2.89 4 4 4H10L12 6H20C21.1 6 22 6.89 22 8V13.81C21.12 13.3 20.1 13 19 13C15.69 13 13 15.69 13 19M20 18V15H18V18H15V20H18V23H20V20H23V18H20Z" />
        </svg>
        Nuevo proyecto
      </button>
    </span>
  </div>

  <!-- Project List -->
  <?php
  // Get local projects and linked projects
  $local_projects = list_projects($proyectos_dir, null, $aulario_id);
  $linked_projects = get_linked_projects($aulario, $centro_id);
  $projects = array_merge($local_projects, $linked_projects);

  // Sort by creation date
  usort($projects, function ($a, $b) {
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
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="24" style="vertical-align: middle; fill: #f0ad4e;">
                <title>folder-multiple</title>
                <path d="M22,4H14L12,2H6A2,2 0 0,0 4,4V16A2,2 0 0,0 6,18H22A2,2 0 0,0 24,16V6A2,2 0 0,0 22,4M2,6H0V11H0V20A2,2 0 0,0 2,22H20V20H2V6Z" />
              </svg>
              <?= htmlspecialchars($project["name"]) ?>
              <?php if ($is_linked): ?>
                <span class="badge bg-warning" style="font-size: 0.7rem;">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="16" style="vertical-align: middle; fill: white;">
                    <title>share-variant</title>
                    <path d="M18,16.08C17.24,16.08 16.56,16.38 16.04,16.85L8.91,12.7C8.96,12.47 9,12.24 9,12C9,11.76 8.96,11.53 8.91,11.3L15.96,7.19C16.5,7.69 17.21,8 18,8A3,3 0 0,0 21,5A3,3 0 0,0 18,2A3,3 0 0,0 15,5C15,5.24 15.04,5.47 15.09,5.7L8.04,9.81C7.5,9.31 6.79,9 6,9A3,3 0 0,0 3,12A3,3 0 0,0 6,15C6.79,15 7.5,14.69 8.04,14.19L15.16,18.34C15.11,18.55 15.08,18.77 15.08,19C15.08,20.61 16.39,21.91 18,21.91C19.61,21.91 20.92,20.61 20.92,19A2.92,2.92 0 0,0 18,16.08Z" />
                  </svg>
                </span>
              <?php endif; ?>
            </h5>
            <?php if (!empty($project["description"])): ?>
              <div class="card-text project-description"><?= $project["description"] ?></div>
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
                  Abrir
                </a>
              <?php else: ?>
                <a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>&project=<?= urlencode($project["id"]) ?>" class="btn btn-primary">
                  Abrir
                </a>
                <!-- Delete -->
                <form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este proyecto?');">
                  <input type="hidden" name="action" value="delete_project">
                  <input type="hidden" name="project_id" value="<?= htmlspecialchars($project["id"]) ?>">
                  <button type="submit" class="btn btn-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="20" style="vertical-align: middle; fill: white;">
                      <title>delete</title>
                      <path d="M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19V4Z" />
                    </svg>
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
              <label class="form-label">Descripción</label>
              <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Editor">
                <button type="button" class="btn btn-outline-secondary" data-cmd="bold" data-target="project_description_editor"><b>B</b></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="italic" data-target="project_description_editor"><i>I</i></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="underline" data-target="project_description_editor"><u>U</u></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="insertUnorderedList" data-target="project_description_editor">• Lista</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="insertOrderedList" data-target="project_description_editor">1. Lista</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="createLink" data-target="project_description_editor">Link</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="removeFormat" data-target="project_description_editor">Limpiar</button>
              </div>
              <div id="project_description_editor" class="form-control" contenteditable="true" style="min-height: 120px;"></div>
              <input type="hidden" id="project_description" name="description">
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
      if (($link["source_aulario"] ?? "") !== $source_aulario_for_project) {
        continue;
      }
      $link_root_id = $link["project_id"] ?? "";
      if ($link_root_id === "") {
        continue;
      }
      if ($link_root_id === $current_project) {
        $valid_link = true;
        $linked_permission = $link["permission"] ?? "read_only";
        break;
      }
      $project_source_dir = "/DATA/entreaulas/Centros/$centro_id/Proyectos";
      $breadcrumb_check = get_project_breadcrumb($project_source_dir, $current_project);
      foreach ($breadcrumb_check as $crumb) {
        if (($crumb["id"] ?? "") === $link_root_id) {
          $valid_link = true;
          $linked_permission = $link["permission"] ?? "read_only";
          break 2;
        }
      }
    }

    if ($valid_link) {
      $is_linked_project = true;
      $project_source_dir = "/DATA/entreaulas/Centros/$centro_id/Proyectos";
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
  $breadcrumb_dir = $is_linked_project ? $project_source_dir : $proyectos_dir;
  $breadcrumb = get_project_breadcrumb($breadcrumb_dir, $current_project);
  $project_level = $project["level"] ?? 1;
  ?>

  <!-- Breadcrumb Navigation -->
  <nav aria-label="breadcrumb">
  </nav>

  <div class="card pad">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>">Proyectos</a>
      </li>
      <?php foreach ($breadcrumb as $idx => $crumb): ?>
        <?php if ($idx < count($breadcrumb) - 1): ?>
          <li class="breadcrumb-item">
            <a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>&project=<?= urlencode($crumb["id"]) ?><?= $is_linked_project ? "&source=" . urlencode($source_aulario_for_project) : "" ?>">
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
    <?php if ($is_linked_project && $linked_permission === "request_edit"): ?>
      <div class="alert alert-info mt-0 mb-2" style="color: black;">
        Este proyecto está compartido con solicitud de permiso. Los cambios se registrarán y deberán ser aprobados por el propietario.
      </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h1 class="card-title">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="42" style="vertical-align: middle; fill: #f0ad4e;">
            <title>folder-open</title>
            <path d="M19,20H4C2.89,20 2,19.1 2,18V6C2,4.89 2.89,4 4,4H10L12,6H19A2,2 0 0,1 21,8H21L4,8V18L6.14,10H23.21L20.93,18.5C20.7,19.37 19.92,20 19,20Z" />
          </svg>
          <?= htmlspecialchars($project["name"]) ?>
          <!--<span class="badge bg-secondary">Nivel <?= $project_level ?></span>-->
          <?php if ($is_linked_project): ?>
            <span class="badge bg-warning" style="font-size: 1rem;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="16" style="vertical-align: middle; fill: white;">
                <title>share-variant</title>
                <path d="M18,16.08C17.24,16.08 16.56,16.38 16.04,16.85L8.91,12.7C8.96,12.47 9,12.24 9,12C9,11.76 8.96,11.53 8.91,11.3L15.96,7.19C16.5,7.69 17.21,8 18,8A3,3 0 0,0 21,5A3,3 0 0,0 18,2A3,3 0 0,0 15,5C15,5.24 15.04,5.47 15.09,5.7L8.04,9.81C7.5,9.31 6.79,9 6,9A3,3 0 0,0 3,12A3,3 0 0,0 6,15C6.79,15 7.5,14.69 8.04,14.19L15.16,18.34C15.11,18.55 15.08,18.77 15.08,19C15.08,20.61 16.39,21.91 18,21.91C19.61,21.91 20.92,20.61 20.92,19A2.92,2.92 0 0,0 18,16.08Z" />
              </svg>
              <?= htmlspecialchars(json_decode(file_get_contents("/DATA/entreaulas/Centros/$centro_id/Aularios/$source_aulario_for_project.json"), true)["name"] ?? "") ?>
            </span>
          <?php endif; ?>
        </h1>
        <?php if (!empty($project["description"])): ?>
          <div class="project-description"><?= $project["description"] ?></div>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-2">
        <?php if (!empty($project["parent_id"])): ?>
          <a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>&project=<?= urlencode($project["parent_id"]) ?><?= $is_linked_project ? "&source=" . urlencode($source_aulario_for_project) : "" ?>" class="btn btn-secondary">
            ← Volver al Proyecto
          </a>
        <?php else: ?>
          <a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary">
            ← Volver al Listado
          </a>
        <?php endif; ?>
        <?php if (!$is_linked_project || $can_edit_linked): ?>
          <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <!-- <img src="/static/iconexperience/add.png" height="20" style="vertical-align: middle;"> -->
            Añadir
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addItemModal" style="color: black;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="20" style="vertical-align: middle; fill: black;">
                  <title>file-plus</title>
                  <path d="M14 2H6C4.89 2 4 2.89 4 4V20C4 21.11 4.89 22 6 22H13.81C13.28 21.09 13 20.05 13 19C13 15.69 15.69 13 19 13C19.34 13 19.67 13.03 20 13.08V8L14 2M13 9V3.5L18.5 9H13M23 20H20V23H18V20H15V18H18V15H20V18H23V20Z" />
                </svg>
                Elemento
              </a>
            </li>
            <?php if ($project_level < 6): ?>
              <li>
                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#createSubProjectModal" style="color: black;">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="20" style="vertical-align: middle; fill: black;">
                    <title>folder-plus</title>
                    <path d="M13 19C13 19.34 13.04 19.67 13.09 20H4C2.9 20 2 19.11 2 18V6C2 4.89 2.89 4 4 4H10L12 6H20C21.1 6 22 6.89 22 8V13.81C21.12 13.3 20.1 13 19 13C15.69 13 13 15.69 13 19M20 18V15H18V18H15V20H18V23H20V20H23V18H20Z" />
                  </svg>
                  Carpeta
                </a>
              </li>
            <?php endif; ?>
          </ul>
          <!-- Compartir raiz -->
          <?php if ($project_level === 1): ?>
            <button type="button" class="btn btn-primary btn-dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">

              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="16" style="vertical-align: middle; fill: white;">
                <title>share-variant</title>
                <path d="M18,16.08C17.24,16.08 16.56,16.38 16.04,16.85L8.91,12.7C8.96,12.47 9,12.24 9,12C9,11.76 8.96,11.53 8.91,11.3L15.96,7.19C16.5,7.69 17.21,8 18,8A3,3 0 0,0 21,5A3,3 0 0,0 18,2A3,3 0 0,0 15,5C15,5.24 15.04,5.47 15.09,5.7L8.04,9.81C7.5,9.31 6.79,9 6,9A3,3 0 0,0 3,12A3,3 0 0,0 6,15C6.79,15 7.5,14.69 8.04,14.19L15.16,18.34C15.11,18.55 15.08,18.77 15.08,19C15.08,20.61 16.39,21.91 18,21.91C19.61,21.91 20.92,20.61 20.92,19A2.92,2.92 0 0,0 18,16.08Z" />
              </svg>
              Compartir
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php
              function list_aularios($centro_id)
              {
                $aularios_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios";
                $aularios = [];
                if (is_dir($aularios_dir)) {
                  $entries = scandir($aularios_dir);
                  foreach ($entries as $entry) {
                    if ($entry === "." || $entry === "..") {
                      continue;
                    }
                    $aulario_path = "$aularios_dir/$entry";
                    if (is_dir($aulario_path)) {
                      $config_file = "$aulario_path.json";
                      if (file_exists($config_file)) {
                        $config = json_decode(file_get_contents($config_file), true);
                        $aularios[] = [
                          "id" => $entry,
                          "name" => $config["name"] ?? "Aulario Desconocido",
                          "linked_projects" => $config["linked_projects"] ?? []
                        ];
                      }
                    }
                  }
                }
                return $aularios;
              }
              $aularios = list_aularios($centro_id);
              foreach ($aularios as $other_aulario):
                //echo $other_aulario["id"] . "-" . $aulario_id;
                if ($other_aulario["id"] === $aulario_id) {
                  continue;
                }
              ?>
                <li>
                  <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="share_project">
                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($current_project, ENT_QUOTES) ?>">
                    <button type="submit" class="dropdown-item" style="color: black;" name="target_aulario" value="<?= htmlspecialchars($other_aulario["id"], ENT_QUOTES) ?>">
                      <?php // Is Shared checkbox
                      $is_shared = false;
                      $linked_projects = $other_aulario["linked_projects"] ?? [];
                      foreach ($linked_projects as $link) {
                        if (($link["source_aulario"] ?? "") === $aulario_id &&
                          ($link["project_id"] ?? "") === $current_project
                        ) {
                          $is_shared = true;
                          break;
                        }
                      }
                      ?>
                      <?= $is_shared ? "✓" : "X" ?>

                      <?= htmlspecialchars($other_aulario["name"]) ?>
                    </button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Sub-Projects Section -->
  <?php
  $subprojects_dir = $is_linked_project ? $project_source_dir : $proyectos_dir;
  $subprojects = list_projects($subprojects_dir, $current_project);
  if (count($subprojects) > 0):
  ?>
    <div id="grid-subprojects">
      <?php foreach ($subprojects as $subproject): ?>
        <div class="card grid-item" style="width: 300px;">
          <div class="card-body">
            <h5 class="card-title">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="30" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px; fill: #f0ad4e;">
                <title>folder-open</title>
                <path d="M19,20H4C2.89,20 2,19.1 2,18V6C2,4.89 2.89,4 4,4H10L12,6H19A2,2 0 0,1 21,8H21L4,8V18L6.14,10H23.21L20.93,18.5C20.7,19.37 19.92,20 19,20Z" />
              </svg>
              <?= htmlspecialchars($subproject["name"]) ?>
              <!--<span class="badge bg-info">Nivel <?= $subproject["level"] ?? 2 ?></span>-->
            </h5>
            <?php if (!empty($subproject["description"])): ?>
              <div class="card-text project-description"><?= $subproject["description"] ?></div>
            <?php endif; ?>
            <p class="card-text">
              <small class="text-muted">
                <?= count($subproject["items"] ?? []) ?> elementos
                <?php if (!empty($subproject["subprojects"])): ?>
                  · <?= count($subproject["subprojects"]) ?> carpetas
                <?php endif; ?>
              </small>
            </p>
            <div class="d-flex gap-2">
              <a href="/entreaulas/proyectos.php?aulario=<?= urlencode($aulario_id) ?>&project=<?= urlencode($subproject["id"]) ?><?= $is_linked_project ? "&source=" . urlencode($source_aulario_for_project) : "" ?>" class="btn btn-primary">
                Abrir
              </a>
              <?php if (!$is_linked_project || ($is_linked_project && $linked_permission === "full_edit")): ?>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editProjectModal"
                  data-project-id="<?= htmlspecialchars($subproject["id"], ENT_QUOTES) ?>"
                  data-project-name="<?= htmlspecialchars($subproject["name"], ENT_QUOTES) ?>"
                  data-project-description="<?= htmlspecialchars($subproject["description"] ?? "", ENT_QUOTES) ?>">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="20" style="vertical-align: middle; fill: white;">
                    <title>pencil</title>
                    <path d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z" />
                  </svg>
                </button>
              <?php endif; ?>
              <?php if (!$is_linked_project): ?>
                <form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este sub-proyecto?');">
                  <input type="hidden" name="action" value="delete_project">
                  <input type="hidden" name="project_id" value="<?= htmlspecialchars($subproject["id"]) ?>">
                  <button type="submit" class="btn btn-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="20" style="vertical-align: middle; fill: white;">
                      <title>delete</title>
                      <path d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z" />
                    </svg>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Items List -->
  <?php if (count($subprojects) > 0): ?>
    <!--<div class="card pad">
		<h3>
			<img src="/static/arasaac/documento.png" height="25" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px;">
			Archivos y Enlaces
		</h3>
	</div>-->
  <?php endif; ?>
  <?php
  $items = $project["items"] ?? [];
  if (count($items) > 0):
  ?>
    <div id="grid">
      <?php foreach ($items as $item): ?>
        <div class="card grid-item" style="width: 300px;">
          <div class="card-body">
            <h5 class="card-title">
              <?php if ($item["type"] === "link"): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="vertical-align: middle; fill: #5bc0de; background: white; padding: 3px; border-radius: 5px;" height="30">
                  <title>web</title>
                  <path d="M16.36,14C16.44,13.34 16.5,12.68 16.5,12C16.5,11.32 16.44,10.66 16.36,10H19.74C19.9,10.64 20,11.31 20,12C20,12.69 19.9,13.36 19.74,14M14.59,19.56C15.19,18.45 15.65,17.25 15.97,16H18.92C17.96,17.65 16.43,18.93 14.59,19.56M14.34,14H9.66C9.56,13.34 9.5,12.68 9.5,12C9.5,11.32 9.56,10.65 9.66,10H14.34C14.43,10.65 14.5,11.32 14.5,12C14.5,12.68 14.43,13.34 14.34,14M12,19.96C11.17,18.76 10.5,17.43 10.09,16H13.91C13.5,17.43 12.83,18.76 12,19.96M8,8H5.08C6.03,6.34 7.57,5.06 9.4,4.44C8.8,5.55 8.35,6.75 8,8M5.08,16H8C8.35,17.25 8.8,18.45 9.4,19.56C7.57,18.93 6.03,17.65 5.08,16M4.26,14C4.1,13.36 4,12.69 4,12C4,11.31 4.1,10.64 4.26,10H7.64C7.56,10.66 7.5,11.32 7.5,12C7.5,12.68 7.56,13.34 7.64,14M12,4.03C12.83,5.23 13.5,6.57 13.91,8H10.09C10.5,6.57 11.17,5.23 12,4.03M18.92,8H15.97C15.65,6.75 15.19,5.55 14.59,4.44C16.43,5.07 17.96,6.34 18.92,8M12,2C6.47,2 2,6.5 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" />
                </svg>
              <?php elseif ($item["type"] === "pdf_secure"): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="vertical-align: middle; fill: #dc3545; background: white; padding: 3px; border-radius: 5px;" height="30">
                  <title>file-pdf</title>
                  <path d="M14 2H6C4.89 2 4 2.89 4 4V20C4 21.11 4.89 22 6 22H18C19.11 22 20 21.11 20 20V8L14 2M13 9V3.5L18.5 9H13M8 13H10.5C11.33 13 12 13.67 12 14.5C12 15.33 11.33 16 10.5 16H9.5V18H8V13M13 13H15C16.1 13 17 13.9 17 15V16C17 17.1 16.1 18 15 18H13V13M14.5 14.5V16.5H15C15.28 16.5 15.5 16.28 15.5 16V15C15.5 14.72 15.28 14.5 15 14.5H14.5Z" />
                </svg>
              <?php elseif ($item["type"] === "videocall"): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="vertical-align: middle; fill: #6f42c1; background: white; padding: 3px; border-radius: 5px;" height="30">
                  <title>video</title>
                  <path d="M17,10.5V7C17,5.89 16.11,5 15,5H5C3.89,5 3,5.89 3,7V17C3,18.11 3.89,19 5,19H15C16.11,19 17,18.11 17,17V13.5L21,17V7L17,10.5Z" />
                </svg>
              <?php elseif ($item["type"] === "notepad"): ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="vertical-align: middle; fill: #f0ad4e; background: white; padding: 3px; border-radius: 5px;" height="30">
                  <title>notebook</title>
                  <path d="M19,2A2,2 0 0,1 21,4V20A2,2 0 0,1 19,22H5A2,2 0 0,1 3,20V4A2,2 0 0,1 5,2H19M17,4H7V20H17V4M9,6H15V8H9V6M9,10H15V12H9V10M9,14H13V16H9V14Z" />
                </svg>
              <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="vertical-align: middle; fill: #5cb85c; background: white; padding: 3px; border-radius: 5px;" height="30">
                  <title>file</title>
                  <path d="M13,9V3.5L18.5,9M6,2C4.89,2 4,2.89 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6Z" />
                </svg>
              <?php endif; ?>
              <?= htmlspecialchars($item["name"]) ?>
            </h5>
            <p class="card-text">
              <small class="text-muted">
                <?php if ($item["type"] === "link"): ?>
                  Enlace
                <?php elseif ($item["type"] === "pdf_secure"): ?>
                  PDF Seguro
                <?php elseif ($item["type"] === "videocall"): ?>
                  Videollamada
                <?php elseif ($item["type"] === "notepad"): ?>
                  Cuaderno
                <?php else: ?>
                  Archivo
                <?php endif; ?>
                <?php if ($item["type"] === "file" && isset($item["original_name"])): ?>
                  <br>(<?= htmlspecialchars($item["original_name"]) ?>)
                <?php endif; ?>
              </small>
            </p>
            <div class="d-flex gap-2 flex-wrap">
              <?php if ($item["type"] === "link"): ?>
                <a href="<?= htmlspecialchars($item["url"]) ?>" target="_blank" class="btn btn-primary">
                  Abrir
                </a>
              <?php elseif ($item["type"] === "pdf_secure"): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#viewPdfSecureModal"
                  data-item-name="<?= htmlspecialchars($item["name"], ENT_QUOTES) ?>"
                  data-file-url="/entreaulas/_filefetch.php?type=proyecto_file&centro=<?= urlencode($centro_id) ?>&project=<?= urlencode($current_project) ?>&file=<?= urlencode($item["filename"]) ?>">
                  Abrir
                </button>
              <?php elseif ($item["type"] === "videocall"): ?>
                <a href="<?= htmlspecialchars($item["url"]) ?>" target="_blank" class="btn btn-primary">
                  Abrir
                </a>
              <?php elseif ($item["type"] === "notepad"): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#viewNotepadModal"
                  data-item-name="<?= htmlspecialchars($item["name"], ENT_QUOTES) ?>"
                  data-item-content="<?= htmlspecialchars($item["content"] ?? "", ENT_QUOTES) ?>">
                  Abrir
                </button>
              <?php else: ?>
                <a href="/entreaulas/_filefetch.php?type=proyecto_file&centro=<?= urlencode($centro_id) ?>&project=<?= urlencode($current_project) ?>&file=<?= urlencode($item["filename"]) ?>" target="_blank" class="btn btn-primary">
                  Abrir
                </a>
              <?php endif; ?>
              <?php if (!$is_linked_project || ($is_linked_project && ($linked_permission === "full_edit" || $linked_permission === "request_edit"))): ?>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editItemModal"
                  data-item-id="<?= htmlspecialchars($item["id"], ENT_QUOTES) ?>"
                  data-item-name="<?= htmlspecialchars($item["name"], ENT_QUOTES) ?>"
                  data-item-type="<?= htmlspecialchars($item["type"], ENT_QUOTES) ?>"
                  data-item-url="<?= htmlspecialchars($item["url"] ?? "", ENT_QUOTES) ?>"
                  data-item-platform="<?= htmlspecialchars($item["platform"] ?? "", ENT_QUOTES) ?>"
                  data-item-room="<?= htmlspecialchars($item["room"] ?? "", ENT_QUOTES) ?>"
                  data-item-content="<?= htmlspecialchars($item["content"] ?? "", ENT_QUOTES) ?>">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="16" style="vertical-align: middle; fill: white;">
                    <title>pencil</title>
                    <path d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z" />
                  </svg>
                </button>
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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="16" style="vertical-align: middle; fill: white;">
                      <title>delete</title>
                      <path d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z" />
                    </svg>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <!--<em>Este proyecto aún no tiene elementos. ¡Añade tu primer enlace o archivo!</em>-->
  <?php endif; ?>

  <!-- Pending Changes Section (only for local projects with pending changes) -->
  <?php
  if (!$is_linked_project) {
    $current_project_dir = find_project_path($proyectos_dir, $current_project);
    $pending_dir = $current_project_dir ? "$current_project_dir/pending_changes" : "";
    $pending_changes = [];
    if ($pending_dir !== "" && is_dir($pending_dir)) {
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
          $req_persona_name = $change["requested_by_persona_name"] ?? "Desconocido";
        ?>
          <div class="card grid-item" style="width: 300px; border: 2px solid #ffc107;">
            <div class="card-body">
              <h5 class="card-title">
                <?php if ($change["item_type"] === "link"): ?>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="30" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px; fill: #5bc0de;">
                    <title>web</title>
                    <path d="M16.36,14C16.44,13.34 16.5,12.68 16.5,12C16.5,11.32 16.44,10.66 16.36,10H19.74C19.9,10.64 20,11.31 20,12C20,12.69 19.9,13.36 19.74,14M14.59,19.56C15.19,18.45 15.65,17.25 15.97,16H18.92C17.96,17.65 16.43,18.93 14.59,19.56M14.34,14H9.66C9.56,13.34 9.5,12.68 9.5,12C9.5,11.32 9.56,10.65 9.66,10H14.34C14.43,10.65 14.5,11.32 14.5,12C14.5,12.68 14.43,13.34 14.34,14M12,19.96C11.17,18.76 10.5,17.43 10.09,16H13.91C13.5,17.43 12.83,18.76 12,19.96M8,8H5.08C6.03,6.34 7.57,5.06 9.4,4.44C8.8,5.55 8.35,6.75 8,8M5.08,16H8C8.35,17.25 8.8,18.45 9.4,19.56C7.57,18.93 6.03,17.65 5.08,16M4.26,14C4.1,13.36 4,12.69 4,12C4,11.31 4.1,10.64 4.26,10H7.64C7.56,10.66 7.5,11.32 7.5,12C7.5,12.68 7.56,13.34 7.64,14M12,4.03C12.83,5.23 13.5,6.57 13.91,8H10.09C10.5,6.57 11.17,5.23 12,4.03M18.92,8H15.97C15.65,6.75 15.19,5.55 14.59,4.44C16.43,5.07 17.96,6.34 18.92,8M12,2C6.47,2 2,6.5 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" />
                  </svg>
                <?php elseif ($change["item_type"] === "videocall"): ?>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="30" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px; fill: #6f42c1;">
                    <title>video</title>
                    <path d="M17,10.5V7C17,5.89 16.11,5 15,5H5C3.89,5 3,5.89 3,7V17C3,18.11 3.89,19 5,19H15C16.11,19 17,18.11 17,17V13.5L21,17V7L17,10.5Z" />
                  </svg>
                <?php elseif ($change["item_type"] === "notepad"): ?>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="30" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px; fill: #f0ad4e;">
                    <title>notebook</title>
                    <path d="M19,2A2,2 0 0,1 21,4V20A2,2 0 0,1 19,22H5A2,2 0 0,1 3,20V4A2,2 0 0,1 5,2H19M17,4H7V20H17V4M9,6H15V8H9V6M9,10H15V12H9V10M9,14H13V16H9V14Z" />
                  </svg>
                <?php else: ?>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="30" style="vertical-align: middle; background: white; padding: 3px; border-radius: 5px; fill: #5cb85c;">
                    <title>file</title>
                    <path d="M13,9V3.5L18.5,9M6,2C4.89,2 4,2.89 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6Z" />
                  </svg>
                <?php endif; ?>
                <?= htmlspecialchars($change["item_name"]) ?>
                <span class="badge bg-warning text-dark">Pendiente</span>
              </h5>
              <p class="card-text">
                <small class="text-muted">
                  <?php if (($change["type"] ?? "") === "delete_item"): ?>
                    Solicitud: <strong>Eliminar elemento</strong><br>
                    Tipo: <?php if (($change["item_type"] ?? "") === "link"): ?>Enlace<?php elseif (($change["item_type"] ?? "") === "videocall"): ?>Videollamada<?php elseif (($change["item_type"] ?? "") === "notepad"): ?>Cuaderno<?php else: ?>Archivo<?php endif; ?><br>
                  <?php elseif (($change["type"] ?? "") === "edit_item"): ?>
                    Solicitud: <strong>Editar elemento</strong><br>
                    Tipo: <?php if (($change["item_type"] ?? "") === "link"): ?>Enlace<?php elseif (($change["item_type"] ?? "") === "videocall"): ?>Videollamada<?php elseif (($change["item_type"] ?? "") === "notepad"): ?>Cuaderno<?php else: ?>Archivo<?php endif; ?><br>
                  <?php else: ?>
                    Tipo: <?php if (($change["item_type"] ?? "") === "link"): ?>Enlace<?php elseif (($change["item_type"] ?? "") === "videocall"): ?>Videollamada<?php elseif (($change["item_type"] ?? "") === "notepad"): ?>Cuaderno<?php else: ?>Archivo<?php endif; ?><br>
                  <?php endif; ?>
                  Solicitado por: <strong><?= htmlspecialchars($req_persona_name) ?> · <?= htmlspecialchars($req_aul_name) ?></strong><br>
                  Fecha: <?= date("d/m/Y H:i", $change["requested_at"]) ?> GMT
                </small>
              </p>
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewChangeModal"
                  data-change-type="<?= htmlspecialchars($change["type"] ?? "", ENT_QUOTES) ?>"
                  data-item-name="<?= htmlspecialchars($change["item_name"] ?? "", ENT_QUOTES) ?>"
                  data-item-type="<?= htmlspecialchars($change["item_type"] ?? "", ENT_QUOTES) ?>"
                  data-item-url="<?= htmlspecialchars($change["item_url"] ?? "", ENT_QUOTES) ?>"
                  data-item-content="<?= htmlspecialchars($change["item_content"] ?? "", ENT_QUOTES) ?>"
                  data-item-platform="<?= htmlspecialchars($change["item_platform"] ?? "", ENT_QUOTES) ?>"
                  data-item-room="<?= htmlspecialchars($change["item_room"] ?? "", ENT_QUOTES) ?>"
                  data-original-filename="<?= htmlspecialchars($change["original_filename"] ?? "", ENT_QUOTES) ?>"
                  data-pending-filename="<?= htmlspecialchars($change["pending_filename"] ?? "", ENT_QUOTES) ?>">
                  👁 Ver cambios
                </button>
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

  <!-- View Change Modal -->
  <div class="modal fade" id="viewChangeModal" tabindex="-1" aria-labelledby="viewChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewChangeModalLabel">Cambios Solicitados</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><strong>Solicitud:</strong> <span id="change_type_label"></span></p>
          <p><strong>Elemento:</strong> <span id="change_item_name"></span></p>
          <p><strong>Tipo:</strong> <span id="change_item_type"></span></p>
          <div id="change_url_row" style="display: none;">
            <strong>URL:</strong> <a id="change_item_url" href="#" target="_blank" rel="noopener"></a>
          </div>
          <div id="change_videocall_row" style="display: none;" class="mt-2">
            <strong>Plataforma:</strong> <span id="change_item_platform"></span><br>
            <strong>Sala / código:</strong> <span id="change_item_room"></span>
          </div>
          <div id="change_file_row" style="display: none;" class="mt-2">
            <strong>Archivo:</strong> <span id="change_file_name"></span>
          </div>
          <div id="change_content_row" style="display: none;" class="mt-2">
            <strong>Contenido:</strong>
            <div id="change_item_content" class="form-control" style="min-height: 160px;"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

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
                <option value="notepad">Cuaderno (Bloc de notas)</option>
                <option value="videocall">Videollamada (Jitsi / Google Meet)</option>
                <option value="pdf_secure">PDF Seguro (sin descarga/impresión)</option>
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
              <small class="form-text text-muted" id="file_help"
                data-default="Formatos soportados: PDF, imágenes, documentos, etc. Tamaño máximo: <?= htmlspecialchars($max_upload_label) ?>."
                data-pdf="Solo PDF. Tamaño máximo: <?= htmlspecialchars($max_upload_label) ?>.">
                Formatos soportados: PDF, imágenes, documentos, etc. Tamaño máximo: <?= htmlspecialchars($max_upload_label) ?>.
              </small>
            </div>

            <div class="mb-3" id="notepad_field" style="display: none;">
              <label class="form-label">Contenido del Cuaderno</label>
              <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Editor">
                <button type="button" class="btn btn-outline-secondary" data-cmd="bold" data-target="notepad_editor"><b>B</b></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="italic" data-target="notepad_editor"><i>I</i></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="underline" data-target="notepad_editor"><u>U</u></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="insertUnorderedList" data-target="notepad_editor">• Lista</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="insertOrderedList" data-target="notepad_editor">1. Lista</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="createLink" data-target="notepad_editor">Link</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="removeFormat" data-target="notepad_editor">Limpiar</button>
              </div>
              <div id="notepad_editor" class="form-control" contenteditable="true" style="min-height: 160px;"></div>
              <input type="hidden" id="notepad_content" name="item_content">
            </div>

            <div class="mb-3" id="videocall_field" style="display: none;">
              <div class="mb-2">
                <label for="videocall_platform" class="form-label">Plataforma</label>
                <select class="form-select" id="videocall_platform" name="videocall_platform">
                  <option value="google_meet">Google Meet (gratis)</option>
                  <option value="jitsi">Jitsi Meet (gratis)</option>
                  <option value="custom">Otra URL</option>
                </select>
              </div>
              <div class="mb-2" id="videocall_room_field">
                <label for="videocall_room" class="form-label">Nombre de la sala / código</label>
                <input type="text" class="form-control" id="videocall_room" name="videocall_room" placeholder="mi-sala-clase o abc-defg-hij">
              </div>
              <div class="mb-2" id="videocall_url_field" style="display: none;">
                <label for="videocall_url" class="form-label">URL de videollamada</label>
                <input type="url" class="form-control" id="videocall_url" name="videocall_url" placeholder="https://...">
              </div>
              <small class="text-muted">No requiere API. Se genera un enlace gratuito de Jitsi Meet.</small>
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

  <!-- Edit Project Modal -->
  <div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editProjectModalLabel">Editar Carpeta</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post">
          <div class="modal-body">
            <input type="hidden" name="action" value="edit_project">
            <input type="hidden" name="project_id" id="edit_project_id">
            <div class="mb-3">
              <label for="edit_project_name" class="form-label">Nombre *</label>
              <input type="text" class="form-control form-control-lg" id="edit_project_name" name="name" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Descripción</label>
              <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Editor">
                <button type="button" class="btn btn-outline-secondary" data-cmd="bold" data-target="edit_project_description_editor"><b>B</b></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="italic" data-target="edit_project_description_editor"><i>I</i></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="underline" data-target="edit_project_description_editor"><u>U</u></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="insertUnorderedList" data-target="edit_project_description_editor">• Lista</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="insertOrderedList" data-target="edit_project_description_editor">1. Lista</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="createLink" data-target="edit_project_description_editor">Link</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="removeFormat" data-target="edit_project_description_editor">Limpiar</button>
              </div>
              <div id="edit_project_description_editor" class="form-control" contenteditable="true" style="min-height: 120px;"></div>
              <input type="hidden" id="edit_project_description" name="description">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Notepad Modal -->
  <div class="modal fade" id="viewNotepadModal" tabindex="-1" aria-labelledby="viewNotepadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewNotepadModalLabel">Cuaderno</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="view_notepad_content" class="form-control" style="min-height: 160px;"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- View PDF Secure Modal -->
  <div class="modal fade" id="viewPdfSecureModal" tabindex="-1" aria-labelledby="viewPdfSecureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewPdfSecureModalLabel">PDF Seguro</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" style="padding: 0;">
          <!--<div class="alert alert-warning" style="color: black;">
            Este PDF se muestra en modo seguro. No se permite la descarga, impresión o copia del contenido.
          </div>-->
          <iframe id="pdf_secure_frame" style="width: 100%; height: 70vh; border: 1px solid #ddd; margin: 0;" sandbox="allow-same-origin allow-scripts"></iframe>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Item Modal -->
  <div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editItemModalLabel">Editar Elemento</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" enctype="multipart/form-data">
          <div class="modal-body">
            <input type="hidden" name="action" value="edit_item">
            <input type="hidden" name="project_id" value="<?= htmlspecialchars($current_project) ?>">
            <input type="hidden" name="item_id" id="edit_item_id">
            <?php if ($is_linked_project && $can_edit_linked): ?>
              <input type="hidden" name="source_aulario" value="<?= htmlspecialchars($source_aulario_for_project) ?>">
            <?php endif; ?>
            <div class="mb-3">
              <label for="edit_item_name" class="form-label">Nombre *</label>
              <input type="text" class="form-control form-control-lg" id="edit_item_name" name="item_name" required>
            </div>
            <div class="mb-3" id="edit_item_url_field">
              <label for="edit_item_url" class="form-label">URL</label>
              <input type="url" class="form-control form-control-lg" id="edit_item_url" name="item_url" placeholder="https://...">
            </div>
            <div class="mb-3" id="edit_videocall_field" style="display: none;">
              <div class="mb-2">
                <label for="edit_videocall_platform" class="form-label">Plataforma</label>
                <select class="form-select" id="edit_videocall_platform" name="edit_videocall_platform">
                  <option value="jitsi">Jitsi Meet (gratis)</option>
                  <option value="google_meet">Google Meet (gratis)</option>
                  <option value="custom">Otra URL</option>
                </select>
              </div>
              <div class="mb-2" id="edit_videocall_room_field">
                <label for="edit_videocall_room" class="form-label">Nombre de la sala / código</label>
                <input type="text" class="form-control" id="edit_videocall_room" name="edit_videocall_room" placeholder="mi-sala-clase o abc-defg-hij">
              </div>
              <div class="mb-2" id="edit_videocall_url_field" style="display: none;">
                <label for="edit_videocall_url" class="form-label">URL de videollamada</label>
                <input type="url" class="form-control" id="edit_videocall_url" name="edit_videocall_url" placeholder="https://...">
              </div>
            </div>
            <div class="mb-3" id="edit_file_field" style="display: none;">
              <label for="edit_item_file" class="form-label">Reemplazar archivo</label>
              <input type="file" class="form-control form-control-lg" id="edit_item_file" name="edit_item_file">
              <small class="form-text text-muted" id="edit_file_help"
                data-default="Formatos soportados: PDF, imágenes, documentos, etc. Tamaño máximo: <?= htmlspecialchars($max_upload_label) ?>."
                data-pdf="Solo PDF. Tamaño máximo: <?= htmlspecialchars($max_upload_label) ?>.">
                Formatos soportados: PDF, imágenes, documentos, etc. Tamaño máximo: <?= htmlspecialchars($max_upload_label) ?>.
              </small>
            </div>
            <div class="mb-3" id="edit_notepad_field" style="display: none;">
              <label class="form-label">Contenido del Cuaderno</label>
              <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Editor">
                <button type="button" class="btn btn-outline-secondary" data-cmd="bold" data-target="edit_notepad_editor"><b>B</b></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="italic" data-target="edit_notepad_editor"><i>I</i></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="underline" data-target="edit_notepad_editor"><u>U</u></button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="insertUnorderedList" data-target="edit_notepad_editor">• Lista</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="insertOrderedList" data-target="edit_notepad_editor">1. Lista</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="createLink" data-target="edit_notepad_editor">Link</button>
                <button type="button" class="btn btn-outline-secondary" data-cmd="removeFormat" data-target="edit_notepad_editor">Limpiar</button>
              </div>
              <div id="edit_notepad_editor" class="form-control" contenteditable="true" style="min-height: 160px;"></div>
              <input type="hidden" id="edit_notepad_content" name="item_content">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
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
      var notepadField = document.getElementById('notepad_field');
      var videocallField = document.getElementById('videocall_field');
      var urlInput = document.getElementById('item_url');
      var fileInput = document.getElementById('item_file');
      var fileHelp = document.getElementById('file_help');
      var roomInput = document.getElementById('videocall_room');
      var videocallUrlInput = document.getElementById('videocall_url');

      if (type === 'link') {
        urlField.style.display = 'block';
        fileField.style.display = 'none';
        notepadField.style.display = 'none';
        videocallField.style.display = 'none';
        urlInput.required = true;
        fileInput.required = false;
        if (fileInput) fileInput.accept = '';
        if (fileHelp) fileHelp.textContent = fileHelp.dataset.default || fileHelp.textContent;
        if (roomInput) roomInput.required = false;
        if (videocallUrlInput) videocallUrlInput.required = false;
      } else {
        if (type === 'file') {
          urlField.style.display = 'none';
          fileField.style.display = 'block';
          notepadField.style.display = 'none';
          videocallField.style.display = 'none';
          urlInput.required = false;
          fileInput.required = true;
          if (fileInput) fileInput.accept = '';
          if (fileHelp) fileHelp.textContent = fileHelp.dataset.default || fileHelp.textContent;
          if (roomInput) roomInput.required = false;
          if (videocallUrlInput) videocallUrlInput.required = false;
        } else if (type === 'notepad') {
          urlField.style.display = 'none';
          fileField.style.display = 'none';
          notepadField.style.display = 'block';
          videocallField.style.display = 'none';
          urlInput.required = false;
          fileInput.required = false;
          if (fileInput) fileInput.accept = '';
          if (fileHelp) fileHelp.textContent = fileHelp.dataset.default || fileHelp.textContent;
          if (roomInput) roomInput.required = false;
          if (videocallUrlInput) videocallUrlInput.required = false;
        } else if (type === 'pdf_secure') {
          urlField.style.display = 'none';
          fileField.style.display = 'block';
          notepadField.style.display = 'none';
          videocallField.style.display = 'none';
          urlInput.required = false;
          fileInput.required = true;
          if (fileInput) fileInput.accept = '.pdf,application/pdf';
          if (fileHelp) fileHelp.textContent = fileHelp.dataset.pdf || fileHelp.textContent;
          if (roomInput) roomInput.required = false;
          if (videocallUrlInput) videocallUrlInput.required = false;
        } else {
          urlField.style.display = 'none';
          fileField.style.display = 'none';
          notepadField.style.display = 'none';
          videocallField.style.display = 'block';
          urlInput.required = false;
          fileInput.required = false;
          if (fileInput) fileInput.accept = '';
          if (fileHelp) fileHelp.textContent = fileHelp.dataset.default || fileHelp.textContent;
          if (roomInput) roomInput.required = true;
          if (videocallUrlInput) videocallUrlInput.required = false;
        }
      }
    });

    var videocallPlatform = document.getElementById('videocall_platform');
    if (videocallPlatform) {
      videocallPlatform.addEventListener('change', function() {
        var roomField = document.getElementById('videocall_room_field');
        var urlField = document.getElementById('videocall_url_field');
        var roomInput = document.getElementById('videocall_room');
        var urlInput = document.getElementById('videocall_url');
        if (this.value === 'custom') {
          if (roomField) roomField.style.display = 'none';
          if (urlField) urlField.style.display = 'block';
          if (roomInput) roomInput.required = false;
          if (urlInput) urlInput.required = true;
        } else {
          if (roomField) roomField.style.display = 'block';
          if (urlField) urlField.style.display = 'none';
          if (roomInput) roomInput.required = true;
          if (urlInput) urlInput.required = false;
        }
      });
    }

    function decodeHtml(html) {
      var txt = document.createElement('textarea');
      txt.innerHTML = html || '';
      return txt.value;
    }

    function setupEditor(editorId, inputId) {
      var editor = document.getElementById(editorId);
      var input = document.getElementById(inputId);
      if (!editor || !input) return;
      var sync = function() {
        input.value = editor.innerHTML;
      };
      editor.addEventListener('input', sync);
      editor.addEventListener('blur', sync);
      sync();
    }

    document.addEventListener('click', function(e) {
      var btn = e.target.closest('button[data-cmd]');
      if (!btn) return;
      e.preventDefault();
      var cmd = btn.getAttribute('data-cmd');
      var targetId = btn.getAttribute('data-target');
      var editor = document.getElementById(targetId);
      if (!editor) return;
      editor.focus();
      if (cmd === 'createLink') {
        var url = prompt('URL:', 'https://');
        if (url) {
          document.execCommand(cmd, false, url);
        }
      } else {
        document.execCommand(cmd, false, null);
      }
    });

    setupEditor('project_description_editor', 'project_description');
    setupEditor('edit_project_description_editor', 'edit_project_description');
    setupEditor('notepad_editor', 'notepad_content');
    setupEditor('edit_notepad_editor', 'edit_notepad_content');

    var editProjectModal = document.getElementById('editProjectModal');
    if (editProjectModal) {
      editProjectModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        document.getElementById('edit_project_id').value = button.getAttribute('data-project-id');
        document.getElementById('edit_project_name').value = button.getAttribute('data-project-name');
        var desc = decodeHtml(button.getAttribute('data-project-description'));
        var editor = document.getElementById('edit_project_description_editor');
        editor.innerHTML = desc;
        document.getElementById('edit_project_description').value = desc;
      });
    }

    var editItemModal = document.getElementById('editItemModal');
    if (editItemModal) {
      editItemModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var itemType = button.getAttribute('data-item-type');
        document.getElementById('edit_item_id').value = button.getAttribute('data-item-id');
        document.getElementById('edit_item_name').value = button.getAttribute('data-item-name');
        document.getElementById('edit_item_url').value = button.getAttribute('data-item-url');
        var urlField = document.getElementById('edit_item_url_field');
        var fileField = document.getElementById('edit_file_field');
        var notepadField = document.getElementById('edit_notepad_field');
        var videocallField = document.getElementById('edit_videocall_field');
        var fileHelp = document.getElementById('edit_file_help');
        var fileInput = document.getElementById('edit_item_file');
        if (itemType === 'link') {
          urlField.style.display = 'block';
          fileField.style.display = 'none';
          notepadField.style.display = 'none';
          videocallField.style.display = 'none';
          if (fileInput) fileInput.accept = '';
          if (fileHelp) fileHelp.textContent = fileHelp.dataset.default || fileHelp.textContent;
        } else {
          urlField.style.display = 'none';
          if (itemType === 'notepad') {
            notepadField.style.display = 'block';
            fileField.style.display = 'none';
            videocallField.style.display = 'none';
            if (fileInput) fileInput.accept = '';
            if (fileHelp) fileHelp.textContent = fileHelp.dataset.default || fileHelp.textContent;
          } else if (itemType === 'videocall') {
            notepadField.style.display = 'none';
            fileField.style.display = 'none';
            videocallField.style.display = 'block';
            if (fileInput) fileInput.accept = '';
            if (fileHelp) fileHelp.textContent = fileHelp.dataset.default || fileHelp.textContent;
          } else {
            notepadField.style.display = 'none';
            fileField.style.display = 'block';
            videocallField.style.display = 'none';
            if (itemType === 'pdf_secure') {
              if (fileInput) fileInput.accept = '.pdf,application/pdf';
              if (fileHelp) fileHelp.textContent = fileHelp.dataset.pdf || fileHelp.textContent;
            } else {
              if (fileInput) fileInput.accept = '';
              if (fileHelp) fileHelp.textContent = fileHelp.dataset.default || fileHelp.textContent;
            }
          }
        }

        if (itemType === 'notepad') {
          var content = decodeHtml(button.getAttribute('data-item-content'));
          var editor = document.getElementById('edit_notepad_editor');
          editor.innerHTML = content;
          document.getElementById('edit_notepad_content').value = content;
        }

        if (itemType === 'videocall') {
          var platform = button.getAttribute('data-item-platform') || 'jitsi';
          var room = button.getAttribute('data-item-room') || '';
          document.getElementById('edit_videocall_platform').value = platform;
          document.getElementById('edit_videocall_room').value = room;
          document.getElementById('edit_videocall_url').value = button.getAttribute('data-item-url') || '';

          var editRoomField = document.getElementById('edit_videocall_room_field');
          var editUrlField = document.getElementById('edit_videocall_url_field');
          if (platform === 'custom') {
            if (editRoomField) editRoomField.style.display = 'none';
            if (editUrlField) editUrlField.style.display = 'block';
          } else {
            if (editRoomField) editRoomField.style.display = 'block';
            if (editUrlField) editUrlField.style.display = 'none';
          }
        }
      });
    }

    var editVideocallPlatform = document.getElementById('edit_videocall_platform');
    if (editVideocallPlatform) {
      editVideocallPlatform.addEventListener('change', function() {
        var roomField = document.getElementById('edit_videocall_room_field');
        var urlField = document.getElementById('edit_videocall_url_field');
        if (this.value === 'custom') {
          if (roomField) roomField.style.display = 'none';
          if (urlField) urlField.style.display = 'block';
        } else {
          if (roomField) roomField.style.display = 'block';
          if (urlField) urlField.style.display = 'none';
        }
      });
    }

    var viewNotepadModal = document.getElementById('viewNotepadModal');
    if (viewNotepadModal) {
      viewNotepadModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var title = button.getAttribute('data-item-name') || 'Cuaderno';
        var content = decodeHtml(button.getAttribute('data-item-content'));
        document.getElementById('viewNotepadModalLabel').textContent = title;
        document.getElementById('view_notepad_content').innerHTML = content;
      });
    }

    var viewPdfSecureModal = document.getElementById('viewPdfSecureModal');
    if (viewPdfSecureModal) {
      viewPdfSecureModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var title = button.getAttribute('data-item-name') || 'PDF Seguro';
        var url = button.getAttribute('data-file-url') || '';
        document.getElementById('viewPdfSecureModalLabel').textContent = title;
        document.getElementById('pdf_secure_frame').src = url ? ('/entreaulas/pdf_secure_viewer.php?file=' + encodeURIComponent(url)) : '';
      });
      viewPdfSecureModal.addEventListener('hidden.bs.modal', function() {
        document.getElementById('pdf_secure_frame').src = '';
      });
    }

    var viewChangeModal = document.getElementById('viewChangeModal');
    if (viewChangeModal) {
      viewChangeModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var changeType = button.getAttribute('data-change-type') || '';
        var itemName = button.getAttribute('data-item-name') || '';
        var itemType = button.getAttribute('data-item-type') || '';
        var itemUrl = button.getAttribute('data-item-url') || '';
        var itemContent = button.getAttribute('data-item-content') || '';
        var itemPlatform = button.getAttribute('data-item-platform') || '';
        var itemRoom = button.getAttribute('data-item-room') || '';
        var originalFilename = button.getAttribute('data-original-filename') || '';
        var pendingFilename = button.getAttribute('data-pending-filename') || '';

        var typeLabel = 'Solicitud';
        if (changeType === 'add_item') typeLabel = 'Añadir elemento';
        if (changeType === 'edit_item') typeLabel = 'Editar elemento';
        if (changeType === 'delete_item') typeLabel = 'Eliminar elemento';

        var itemTypeLabel = 'Archivo';
        if (itemType === 'link') itemTypeLabel = 'Enlace';
        if (itemType === 'videocall') itemTypeLabel = 'Videollamada';
        if (itemType === 'notepad') itemTypeLabel = 'Cuaderno';
        if (itemType === 'pdf_secure') itemTypeLabel = 'PDF Seguro';

        document.getElementById('change_type_label').textContent = typeLabel;
        document.getElementById('change_item_name').textContent = itemName || '-';
        document.getElementById('change_item_type').textContent = itemTypeLabel;

        var urlRow = document.getElementById('change_url_row');
        var urlEl = document.getElementById('change_item_url');
        if (itemUrl) {
          urlRow.style.display = 'block';
          urlEl.textContent = itemUrl;
          urlEl.href = itemUrl;
        } else {
          urlRow.style.display = 'none';
          urlEl.textContent = '';
          urlEl.href = '#';
        }

        var vcRow = document.getElementById('change_videocall_row');
        if (itemType === 'videocall') {
          vcRow.style.display = 'block';
          document.getElementById('change_item_platform').textContent = itemPlatform || 'jitsi';
          document.getElementById('change_item_room').textContent = itemRoom || '-';
        } else {
          vcRow.style.display = 'none';
          document.getElementById('change_item_platform').textContent = '';
          document.getElementById('change_item_room').textContent = '';
        }

        var fileRow = document.getElementById('change_file_row');
        var fileName = originalFilename || pendingFilename;
        if (fileName) {
          fileRow.style.display = 'block';
          document.getElementById('change_file_name').textContent = fileName;
        } else {
          fileRow.style.display = 'none';
          document.getElementById('change_file_name').textContent = '';
        }

        var contentRow = document.getElementById('change_content_row');
        var contentEl = document.getElementById('change_item_content');
        if (itemType === 'notepad' && itemContent) {
          contentRow.style.display = 'block';
          contentEl.innerHTML = decodeHtml(itemContent);
        } else {
          contentRow.style.display = 'none';
          contentEl.innerHTML = '';
        }
      });
    }
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
    background: transparent;
    box-shadow: none;
    padding: 0;
    border: none;
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
        "columnWidth": 300,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
      });
      setTimeout(() => {
        msnry.layout()
      }, 150);
      window.addEventListener('resize', function(event) {
        msnry.layout()
      }, true);
    }
  });
</script>

<?php require_once "_incl/post-body.php"; ?>