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
header('Cache-Control: max-age=7200');
header("X-Accel-Redirect: $uripath");

// // stream the file
// $fp = fopen($path, 'rb');
// fpassthru($fp);
exit;