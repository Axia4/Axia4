<?php
ini_set("display_errors", 0);
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
$file_count = sizeof($_FILES["file"]["name"]);

for ($i = 0; $i < $file_count; $i++) {
    $file_name = $_FILES["file"]["name"][$i];
    $folder = $_GET["folder"];
    $location = "/DATA/club$folder" . $file_name;
    if (!is_dir("/DATA/club$folder")) {
        mkdir("/DATA/club$folder", recursive: true);
    }
    if (move_uploaded_file($_FILES["file"]["tmp_name"][$i], $location)) {
        // Generate thumbnail
        require_once "../_incl/tools.photos.php";
        $thumbnail_path = $location . ".thumbnail";
        if (!file_exists($thumbnail_path)) {
            generatethumbnail($location, $thumbnail_path, 240, 0);
        }
        header("HTTP/1.1 200 OK");
    } else {
        header("HTTP/1.1 500 Internal Server Error");
    }
}
