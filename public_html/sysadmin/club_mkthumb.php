<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php"; 
require_once "../_incl/tools.photos.php";
$action = $_GET["action"] ?? "index";
switch ($action) {
    case "generate_thumbs":
        ini_set("max_execution_time", 300);
        ini_set("memory_limit", "1024M");
        // ini_set("display_errors", 1);
        echo "<div class='card pad'><h1>Generando Miniaturas...</h1>";
        // Iterate over all club photos and generate thumbnails if they don't exist
        $club_base = realpath("/DATA/club/IMG");
        if ($club_base === false) {
            echo "No se encontró el directorio base de imágenes.<br>";
            echo "<h2>Proceso completado.</h2></div>";
            break;
        }
        $club_cal_folders = array_filter(glob("/DATA/club/IMG/*") ?: [], 'is_dir');
        foreach ($club_cal_folders as $cal_folder) {
            $real_cal_folder = realpath($cal_folder);
            if ($real_cal_folder === false || strpos($real_cal_folder, rtrim($club_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0) {
                continue;
            }
            $personas = array_filter(glob("$real_cal_folder/*") ?: [], 'is_dir');
            foreach ($personas as $persona) {
                $real_persona = realpath($persona);
                if ($real_persona === false || strpos($real_persona, rtrim($club_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0) {
                    continue;
                }
                $fotos = preg_grep('/^([^.])/', scandir($real_persona));
                foreach ($fotos as $foto) {
                    $foto_path = "$real_persona/$foto";
                    if (!is_file($foto_path)) {
                        continue;
                    }
                    $thumbnail_path = "$foto_path.thumbnail";
                    if (file_exists($thumbnail_path)) {
                        continue;
                    }
                    // Extension is not thumbnail
                    if (strtolower(pathinfo($foto_path, PATHINFO_EXTENSION)) == "thumbnail") {
                        continue;
                    }
                    generatethumbnail($foto_path, $thumbnail_path, 240, 0);
                    echo "Generated thumbnail for $foto_path<br>";
                    flush();
                }
            }
        }
        echo "<h2>Proceso completado.</h2></div>";
        break;
    case "index":
    default:
?>
<div class="card pad">
    <h1>Generar Miniaturas para Fotos del Club</h1>
    <span>
        Desde esta sección puedes generar miniaturas para las fotos subidas al Club que aún no las tengan.
    </span>
    <form method="get" action="">
        <input type="hidden" name="action" value="generate_thumbs">
        <button type="submit" class="btn btn-primary">Generar Miniaturas</button>
    </form>
</div>
<?php 
        break;
}



require_once "_incl/post-body.php"; ?>