<?php
ini_set("display_errors", 0);
ob_implicit_flush(true);
ob_end_flush();
ini_set('memory_limit', '1G'); 
header("Access-Control-Allow-Origin: *");

function safe_id_segment($value)
{
    $value = basename((string)$value);
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value);
}

function safe_centro_id($value)
{
    return preg_replace('/[^0-9]/', '', (string)$value);
}

function safe_filename($name)
{
    $name = basename((string)$name);
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    $name = ltrim($name, '.');
    return $name;
}

$type = $_GET["type"] ?? "";
switch ($type) {
    case "alumno_photo":
        $centro = safe_centro_id($_GET["centro"] ?? '');
        $aulario = safe_id_segment($_GET["aulario"] ?? '');
        $alumno = safe_id_segment($_GET["alumno"] ?? '');
        // Additional validation to prevent empty names
        if (empty($centro) || empty($aulario) || empty($alumno)) {
            header("HTTP/1.1 403 Forbidden");
            die("Invalid parameters");
        }
        $relpath = "entreaulas/Centros/$centro/Aularios/$aulario/Alumnos/$alumno/photo.jpg";
        break;
    case "panel_actividades":
        $centro = safe_centro_id($_GET["centro"] ?? '');
        $activity = safe_id_segment($_GET["activity"] ?? '');
        if (empty($centro) || empty($activity)) {
            header("HTTP/1.1 400 Bad Request");
            die("Invalid parameters");
        }
        $relpath = "entreaulas/Centros/$centro/Panel/Actividades/$activity/photo.jpg";
        break;
    case "comedor_image":
        $centro = safe_centro_id($_GET["centro"] ?? '');
        $aulario = safe_id_segment($_GET["aulario"] ?? '');
        $date = preg_replace('/[^0-9-]/', '', $_GET["date"] ?? '');
        $file = safe_filename($_GET["file"] ?? '');
        if (empty($centro) || empty($aulario) || empty($file)) {
            header("HTTP/1.1 400 Bad Request");
            die("Invalid parameters");
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            header("HTTP/1.1 400 Bad Request");
            die("Invalid date");
        }
        $ym = substr($date, 0, 7);
        $day = substr($date, 8, 2);
        $relpath = "entreaulas/Centros/$centro/Aularios/$aulario/Comedor/$ym/$day/$file";
        break;
    case "proyecto_file":
        $centro = safe_centro_id($_GET["centro"] ?? '');
        $project = safe_id_segment($_GET["project"] ?? '');
        $file = safe_filename($_GET["file"] ?? '');
        if (empty($centro) || empty($project) || empty($file)) {
            header("HTTP/1.1 400 Bad Request");
            die("Invalid parameters");
        }
        // Ensure no directory traversal
        if (strpos($file, '..') !== false || strpos($file, '/') !== false || strpos($file, '\\') !== false) {
            header("HTTP/1.1 400 Bad Request");
            die("Invalid file name");
        }
        $projects_base = "/DATA/entreaulas/Centros/$centro/Proyectos";
        $project_dir = null;
        if (is_dir($projects_base)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($projects_base, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $fileinfo) {
                if (!$fileinfo->isDir()) {
                    continue;
                }
                $meta = $fileinfo->getPathname() . "/_data_.eadat";
                if (!file_exists($meta)) {
                    continue;
                }
                $data = json_decode(file_get_contents($meta), true);
                if (($data["id"] ?? "") === $project) {
                    $project_dir = $fileinfo->getPathname();
                    break;
                }
            }
        }
        if (!$project_dir) {
            header("HTTP/1.1 404 Not Found");
            die("Project not found");
        }
        $path = $project_dir . "/" . $file;
        $uripath = str_replace("/DATA", "", $path);
        break;
    default:
        header("HTTP/1.1 400 Bad Request");
        die("Invalid type");
}
if (!isset($path)) {
    $path = "/DATA/$relpath";
}
if (!isset($uripath)) {
    $uripath = "/$relpath";
}

// Validate that the resolved path is within /DATA directory
$real_path = realpath($path);
$real_base = realpath("/DATA");
$real_base_prefix = $real_base !== false ? rtrim($real_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : null;
if ($real_path === false || $real_base === false || $real_base_prefix === null || strpos($real_path, $real_base_prefix) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied");
}

if (!file_exists($real_path) || !is_file($real_path)) {
    header("HTTP/1.1 404 Not Found");
    die("File not found");
}
$mime = mime_content_type($real_path);

// Check if thumbnail is requested
if (file_exists($real_path . ".thumbnail") && (($_GET["thumbnail"] ?? "") === "1")) {
    $real_path .= ".thumbnail";
    $uripath .= ".thumbnail";
    $mime = "image/jpeg";
}
header("Content-Type: " . $mime);
header('Content-Length: ' . filesize($real_path));
//header('Cache-Control: max-age=7200');
header("X-Accel-Redirect: $uripath");

// // stream the file
// $fp = fopen($path, 'rb');
// fpassthru($fp);
exit;