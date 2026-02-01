<?php
ini_set("display_errors", 1);
$config = json_decode(file_get_contents("/DATA/club/config.json"), true);
if (strtoupper($_GET["pw"]) != $config["uploadpw"]) {
    header("HTTP/1.1 401 Unauthorized");
    die();
}
//remove files with error
$error_files = array();
foreach ($_FILES["file"]["error"] as $key => $error) {
    if ($error != UPLOAD_ERR_OK) {
        $error_files[] = $_FILES["file"]["name"][$key];
    }
}
foreach ($error_files as $file) {
    $key = array_search($file, $_FILES["file"]["name"]);
    unset($_FILES["file"]["name"][$key]);
    unset($_FILES["file"]["type"][$key]);
    unset($_FILES["file"]["tmp_name"][$key]);
    unset($_FILES["file"]["error"][$key]);
    unset($_FILES["file"]["size"][$key]);
}
// Reindex arrays to avoid gaps after unsetting
$_FILES["file"]["name"] = array_values($_FILES["file"]["name"]);
$_FILES["file"]["type"] = array_values($_FILES["file"]["type"]);
$_FILES["file"]["tmp_name"] = array_values($_FILES["file"]["tmp_name"]);
$_FILES["file"]["error"] = array_values($_FILES["file"]["error"]);
$_FILES["file"]["size"] = array_values($_FILES["file"]["size"]);

$file_count = sizeof($_FILES["file"]["name"]);

$all_ok = true;

for ($i = 0; $i < $file_count; $i++) {
    $file_name = $_FILES["file"]["name"][$i];
    $folder = $_GET["folder"];
    $location = "/DATA/club$folder" . $file_name;
    if (!is_dir("/DATA/club$folder")) {
        mkdir("/DATA/club$folder", 777, recursive: true);
    }
    if (move_uploaded_file($_FILES["file"]["tmp_name"][$i], $location)) {
        // Generate thumbnail
        require_once "../_incl/tools.photos.php";
        $thumbnail_path = $location . ".thumbnail";
        #if (!file_exists($thumbnail_path)) {
        #    generatethumbnail($location, $thumbnail_path, 240, 0);
        #}
    } else {
        $all_ok = false;
    }
}

if ($all_ok) {
    header("HTTP/1.1 200 OK");
} else {
    header("HTTP/1.1 500 Internal Server Error");
}
