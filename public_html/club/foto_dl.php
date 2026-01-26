<?php
ini_set("display_errors", 0);
ob_implicit_flush(true);
ob_end_flush();
ini_set('memory_limit', '1G'); 
$file = str_replace('..', '_', $_GET["f"]);
header("Access-Control-Allow-Origin: *");

$path = "/DATA/club/IMG/$file";
$uripath = "/club/IMG/$file";
if (!file_exists($path) || !is_file($path)) {
    header("HTTP/1.1 404 Not Found");
    die("File not found");
}
if (file_exists($path . ".thumbnail") && $_GET["thumbnail"] == "1") {
    $path .= ".thumbnail";
    $uripath .= ".thumbnail";
    // die();
}
header("Content-Type: " . mime_content_type($path));
header('Content-Length: ' . filesize($path));
header('Cache-Control: max-age=7200');
header("X-Accel-Redirect: $uripath");

// // stream the file
// $fp = fopen($path, 'rb');
// fpassthru($fp);
exit;