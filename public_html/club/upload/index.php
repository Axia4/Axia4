<?php 
$APP_CODE = "club";
$APP_NAME = "La web del Club<sup>3</sup>";
$APP_TITLE = "La web del Club";
require_once "../../_incl/pre-body.php"; ?>
<div class="card pad">
    <h1>Subir fotos</h1>
    <form action="form.php" method="get">
        <div class="mb-3">
            <label for="n" class="form-label"><b>Tu nombre:</b></label>
            <input required type="text" id="n" name="n" class="form-control" value="<?php echo $_GET["n"] ?: "";?>" placeholder="Nombre...">
        </div>
        <div class="mb-3">
            <label for="f" class="form-label"><b>Fecha:</b></label>
            <input required type="date" id="f" name="f" class="form-control" value="<?php echo $_GET["f"] ?: "";?>" placeholder="Fecha...">
        </div>
        <div class="mb-3">
            <label for="p" class="form-label"><b>La contraseña:</b></label>
            <input required type="text" id="p" name="p" class="form-control" value="" placeholder="Contraseña...">
        </div>
        <button type="submit" class="btn btn-primary">Continuar...</button>
    </form>
</div>
<?php require_once "../../_incl/post-body.php"; ?>