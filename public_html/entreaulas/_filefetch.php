<?php
ini_set("display_errors", 0);
ob_implicit_flush(true);
ob_end_flush();
ini_set('memory_limit', '1G'); 
header("Access-Control-Allow-Origin: *");

switch ($_GET["type"]) {
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
}
$path = "/DATA/$relpath";
$uripath = "/$relpath";
if (!file_exists($path) || !is_file($path)) {
    header("HTTP/1.1 404 Not Found");
    die("File not found");
}
$mime = mime_content_type($path);

// Check if thumbnail is requested
if (file_exists($path . ".thumbnail") && $_GET["thumbnail"] == "1") {
    $path .= ".thumbnail";
    $uripath .= ".thumbnail";
    $mime = "image/jpeg";
}
header("Content-Type: " . $mime);
header('Content-Length: ' . filesize($path));
//header('Cache-Control: max-age=7200');
header("X-Accel-Redirect: $uripath");

// // stream the file
// $fp = fopen($path, 'rb');
// fpassthru($fp);
exit;