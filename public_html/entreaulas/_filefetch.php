<?php
ini_set("display_errors", 0);
ob_implicit_flush(true);
ob_end_flush();
ini_set('memory_limit', '1G'); 
header("Access-Control-Allow-Origin: *");

switch ($_GET["type"]) {
    case "alumno_photo":
        $centro = basename($_GET["centro"] ?? '');
        $aulario = basename($_GET["aulario"] ?? '');
        $alumno = basename($_GET["alumno"] ?? '');
        // Additional validation to prevent empty names
        if (empty($centro) || empty($aulario) || empty($alumno)) {
            header("HTTP/1.1 400 Bad Request");
            die("Invalid parameters");
        }
        $relpath = "entreaulas/Centros/$centro/Aularios/$aulario/Alumnos/$alumno/photo.jpg";
        break;
    case "panel_actividades":
        $centro = str_replace('..', '_', $_GET["centro"] ?? '');
        $activity = str_replace('..', '_', $_GET["activity"] ?? '');
        $relpath = "entreaulas/Centros/$centro/Panel/Actividades/$activity/photo.jpg";
        break;
    case "comedor_image":
        $centro = str_replace('..', '_', $_GET["centro"] ?? '');
        $aulario = str_replace('..', '_', $_GET["aulario"] ?? '');
        $date = preg_replace('/[^0-9-]/', '', $_GET["date"] ?? '');
        $file = basename($_GET["file"] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            header("HTTP/1.1 400 Bad Request");
            die("Invalid date");
        }
        $ym = substr($date, 0, 7);
        $day = substr($date, 8, 2);
        $relpath = "entreaulas/Centros/$centro/Aularios/$aulario/Comedor/$ym/$day/$file";
        break;
    case "proyecto_file":
        $centro = str_replace('..', '_', $_GET["centro"] ?? '');
        $project = str_replace('..', '_', $_GET["project"] ?? '');
        $file = basename($_GET["file"] ?? '');
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
if ($real_path === false || $real_base === false || strpos($real_path, $real_base) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied");
}

if (!file_exists($real_path) || !is_file($real_path)) {
    header("HTTP/1.1 404 Not Found");
    die("File not found");
}
$mime = mime_content_type($real_path);

// Check if thumbnail is requested
if (file_exists($real_path . ".thumbnail") && $_GET["thumbnail"] == "1") {
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