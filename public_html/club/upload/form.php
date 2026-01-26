<?php
ini_set("display_errors", 0);
if (strtoupper($_GET["p"]) != "ELPEPE") {
    header("Location: unauth.php");
    die();
}
$APP_CODE = "club";
$APP_NAME = "La web del Club<sup>3</sup>";
$APP_TITLE = "La web del Club";
require_once "/var/www/_incl/pre-body.php"; ?>
<div class="card pad">
    <h1>Subir fotos</h1>
    <form id="upload" encType="multipart/form-data">
        <fieldset class="card"
            style="border: 2px solid black; border-radius: 6.5px; padding: 5px 25px; max-width: 500px;">

            <label id="uploader0">
                <b>Elegir los archivos a subir (max 10)</b>
                <input type="file" name="file[]" multiple="true" id="uploaderfileinp" />
            </label>
            <hr>
            <span>
                <b>Progreso:</b> <span id="alert">Por subir...</span>
            </span>
            <progress id="fileuploaderprog" max="100" value="0" style="width: 100%;">0%</progress>
            <br>
            <button type="submit" class="button">Subir fotos</button>
        </fieldset>
    </form>


    <script src="js/plugin/jquery-3.6.0.min.js"></script>
    <script src="js/main.js"></script>

</div>
<?php require_once "/var/www/_incl/post-body.php"; ?>