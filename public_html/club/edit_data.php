<?php
ini_set("display_errors", 0);
$file = str_replace('/', '_', $_GET["f"]);
$date = implode("/", array_reverse(explode("-", $file)));
$val = json_decode(file_get_contents("/DATA/club/IMG/$file/data.json"), true);
$config = json_decode(file_get_contents("/DATA/club/config.json"), true);
if(strtoupper($_POST["adminpw"]) == strtoupper($config["adminpw"] ?? "")) {
    $data = [
            "title" => $_POST["title"],
            "note" => $_POST["note"],
            "mapa" => [
                "url" => $_POST["mapa_url"]
            ]
        ];
    $file = $_POST["date"];
    $val = file_put_contents("/DATA/club/IMG/$file/data.json", json_encode($data, JSON_UNESCAPED_SLASHES));
    header("Location: /club/");
    die();
}

$APP_CODE = "club";
$APP_NAME = "La web del Club<sup>3</sup>";
$APP_TITLE = "La web del Club";
$PAGE_TITLE = "Editar datos - $date - Club";
require_once "../_incl/pre-body.php"; ?>
<div class="card">
    <div>
        <h1 class="card-title">Editar datos</h1>
        
        <form method="post">
            <div class="card" style="max-width: 500px;">
                <div>
                    <div class="mb-3">
                        <label for="adminpw" class="form-label"><b>Contraseña de administración:</b></label>
                        <input required type="text" id="adminpw" name="adminpw" class="form-control" placeholder="Contraseña admin">
                    </div>
                    <div class="mb-3">
                        <label for="date" class="form-label"><b>Fecha:</b></label>
                        <input required type="date" id="date" name="date" class="form-control" value="<?php echo $file;?>" placeholder="Fecha">
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label for="title" class="form-label"><b>Titulo:</b></label>
                        <input required type="text" id="title" name="title" class="form-control" value="<?php echo $val["title"] ?: "";?>" placeholder="Titulo">
                    </div>
                    <div class="mb-3">
                        <label for="note" class="form-label"><b>Descripción:</b></label>
                        <textarea rows="5" id="note" name="note" class="form-control" placeholder="Descripción"><?php echo $val["note"] ?: "";?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="mapa_url" class="form-label"><b>Enlace ruta mapa:</b></label>
                        <input type="url" id="mapa_url" name="mapa_url" class="form-control" value="<?php echo $val["mapa"]["url"] ?: "";?>" placeholder="Enlace Mapa">
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php require_once "../_incl/post-body.php"; ?>