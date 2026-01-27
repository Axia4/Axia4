<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php"; 
require_once "../_incl/tools.photos.php";
switch ($_GET["action"]) {
    case "generate_thumbs":
        ini_set("max_execution_time", 300);
        ini_set("memory_limit", "1024M");
        // ini_set("display_errors", 1);
        echo "<div class='card pad'><h1>Generando Miniaturas...</h1>";
        // Iterate over all club photos and generate thumbnails if they don't exist
        $club_cal_folders = array_filter(glob("/DATA/club/IMG/*"), 'is_dir');
        foreach ($club_cal_folders as $cal_folder) {
            $personas = array_filter(glob("$cal_folder/*"), 'is_dir');
            foreach ($personas as $persona) {
                $fotos = preg_grep('/^([^.])/', scandir($persona));
                foreach ($fotos as $foto) {
                    $foto_path = "$persona/$foto";
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