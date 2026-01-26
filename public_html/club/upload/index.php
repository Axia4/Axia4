<?php ini_set("display_errors", "off");

$APP_CODE = "club";
$APP_NAME = "La web del Club<sup>3</sup>";
$APP_TITLE = "La web del Club";
require_once "/var/www/_incl/pre-body.php"; ?>
<div class="card pad">
    <h1>Subir fotos</h1>
    <form action="form.php" method="get">
        <fieldset class="card" style="border: 2px solid black; border-radius: 6.5px; padding: 5px 25px; max-width: 500px;">
            <label>
                <b>Tu nombre:</b> 
                <input required type="text" name="n" value="<?php echo $_GET["n"] ?: "";?>" placeholder="Nombre...">
            </label>
            <br>
            <label>
                <b>Fecha:</b> 
                <input required type="date" name="f" value="<?php echo $_GET["f"] ?: "";?>" placeholder="Fecha...">
            </label>
            <br>
            <label>
                <b>La contraseña:</b> 
                <input required type="text" name="p" value="" placeholder="Contraseña...">
            </label>
            <br>
            <button type="submit">Continuar...</button>
        </fieldset>
    </form>
</div>
<?php require_once "/var/www/_incl/post-body.php"; ?>