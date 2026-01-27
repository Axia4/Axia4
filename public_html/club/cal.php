<?php
ini_set("display_errors", 0);
$file = str_replace('/', '_', $_GET["f"]);
$date = implode("/", array_reverse(explode("-", $file)));
$val = json_decode(file_get_contents("/DATA/club/IMG/$file/data.json"), true);

$fotos = glob("/DATA/club/IMG/$file/*/");

$APP_CODE = "club";
$APP_NAME = "La web del Club<sup>3</sup>";
$APP_TITLE = "La web del Club";
require_once "../_incl/pre-body.php"; ?>
<div class="card pad">
    <h1><?php echo $date; ?> - <?php echo $val["title"] ?: "Por definir"; ?></h1>
    <span>
        <a href="/club/" class="btn btn-secondary">Volver a Inicio</a>
        <a href="/club/edit_data.php?f=<?php echo $file; ?>" class="btn btn-secondary">Cambiar datos</a>
        <a href="/club/upload/index.php?f=<?php echo $file; ?>" class="btn btn-primary">Subir fotos</a>
        <?php if (isset($val["mapa"]["url"]) and $val["mapa"]["url"] != ""): ?>
            <a class="btn btn-secondary" href="<?php echo $val["mapa"]["url"]; ?>" target="_blank">Abrir ruta interactiva</a>
        <?php endif; ?>
    </span>

    <?php if (isset($val["mapa"]) and $val["mapa"] != ""): ?>
        <h2>Ruta y estadísticas</h2>
        <?php if (isset($val["mapa"]["route"]) and $val["mapa"]["route"] != ""): ?>
            <img height="300" loading="lazy" src="foto_dl.php?f=<?php echo $file . "/" . $val["mapa"]["route"]; ?>" alt="">
        <?php endif; ?>
        <?php if (isset($val["mapa"]["stats"]) and $val["mapa"]["stats"] != ""): ?>
            <img height="300" loading="lazy" src="foto_dl.php?f=<?php echo $file . "/" . $val["mapa"]["stats"]; ?>" alt="">
        <?php endif; ?>
    <?php endif; ?>
    <h2>Fotos</h2>
    <div id="grid">
        <?php foreach ($fotos as $persona): ?>
            <?php $pname = str_replace("/", "", str_replace("/DATA/club/IMG/$file/", "", $persona)); ?>
            <?php foreach (preg_grep('/^([^.])/', scandir($persona)) as $foto): ?>
                <?php if (is_dir($foto)) {
                    continue;
                } ?>
                <?php if (strtolower(pathinfo($foto, PATHINFO_EXTENSION)) == "thumbnail") {
                    continue;
                } ?>
                <div style="width: 240px; display: inline-block; margin-bottom: 10px; border: 3px solid black; border-radius: 6.5px; box-sizing: content-box;"
                    class="grid-item">
                    <?php $dl_url = "foto_dl.php?f=$file/$pname/" . str_replace($persona, "", $foto); ?>
                    <img class="stack" width="240px" loading="lazy" src="<?php echo $dl_url; ?>&thumbnail=1"
                        alt="Foto de <?php echo $pname . " - " . str_replace($persona, "", $foto); ?>">
                    <div style="padding: 5px; text-align: center;">
                        Subido por <?php echo $pname; ?><br>
                        <a href="<?php echo $dl_url; ?>" target="_blank" class="btn btn-secondary">Abrir</a>
                        <a href="<?php echo $dl_url; ?>"
                            download="<?php echo "CLUB-NK5-$file-$pname-" . str_replace($persona, "", $foto); ?>"
                            class="btn btn-secondary">Descargar</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>

<script>
    var msnry = new Masonry('#grid', { "columnWidth": 240, "itemSelector": ".grid-item", "gutter": 10, "transitionDuration": 0 });
    setInterval(() => {
        msnry.layout()
    }, 1000);
</script>
<?php require_once "../_incl/post-body.php"; ?>