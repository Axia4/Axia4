<?php
ini_set("display_errors", 0);
if (strtoupper($_GET["p"]) != "ELPEPE") {
    header("Location: unauth.php");
    die();
}
$APP_CODE = "club";
$APP_NAME = "La web del Club<sup>3</sup>";
$APP_TITLE = "La web del Club";
require_once "../../_incl/pre-body.php"; ?>
<div class="card">
    <div class="card-body">
        <h1 class="card-title">Subir fotos</h1>
        <form id="upload" encType="multipart/form-data">
            <div class="mb-3">
                <label for="uploaderfileinp" class="form-label"><b>Elegir los archivos a subir (max 10)</b></label>
                <input type="file" id="uploaderfileinp" name="file[]" multiple="true" class="form-control" />
            </div>
            <hr>
            <div class="mb-3">
                <span><b>Progreso:</b> <span id="alert">Por subir...</span></span>
                <div class="progress">
                    <div id="fileuploaderprog" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Subir fotos</button>
        </form>
    </div>
</div>


    <script src="js/plugin/jquery-3.6.0.min.js"></script>
    <script src="js/main.js"></script>

<?php require_once "../../_incl/post-body.php"; ?>