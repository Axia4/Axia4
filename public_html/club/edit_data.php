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
require_once "../_incl/pre-body.php"; ?>
<div class="card pad">

    <h1>Editar datos</h1>
    
    <form method="post">
        <fieldset class="card" style="border: 2px solid black; border-radius: 6.5px; padding: 5px 25px; max-width: 500px;">
            <label>
                <b>Contraseña de administración:</b><br>
                <input required type="text" name="adminpw" placeholder="Contraseña admin">
            </label><br><br>
            <label>
                <b>Fecha:</b><br>
                <input required type="date" name="date" value="<?php echo $file;?>" placeholder="Fecha">
            </label><br><br><hr>
            <label>
                <b>Titulo:</b><br>
                <input required type="text" name="title" value="<?php echo $val["title"] ?: "";?>" placeholder="Titulo">
            </label><br><br>
            <label>
                <b>Descripción:</b><br>
                <textarea rows="5" name="note" placeholder="Descripción"><?php echo $val["note"] ?: "";?></textarea>
            </label><br><br>
            <label>
                <b>Enlace ruta mapa:</b><br>
                <input type="url" name="mapa_url" value="<?php echo $val["mapa"]["url"] ?: "";?>" placeholder="Enlace Mapa">
            </label><br><br>
            <button type="submit">Guardar cambios</button>
            <br><br>
        </fieldset>
    </form>
</div>
<?php require_once "../_incl/post-body.php"; ?>