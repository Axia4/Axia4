<?php
ini_set("display_errors", 0);
$files = glob("/DATA/club/IMG/*/");
sort($files);
$files = array_reverse($files);
$APP_CODE = "club";
$APP_NAME = "La web del Club<sup>3</sup>";
$APP_TITLE = "La web del Club";
require_once "../_incl/pre-body.php"; ?>
<div class="card pad">
    <h2>Calendario:</h2>
    <ul>
        <?php foreach ($files as $file) {
            $filenam = str_replace("/", "", str_replace("/DATA/club/IMG/", "", $file));
            $date = implode("/", array_reverse(explode("-", $filenam)));
            $val = json_decode(file_get_contents($file . "data.json"), true)
                ?>

            <li><a href="cal.php?f=<?php echo $filenam; ?>"><b><?php echo $date; ?></b></a> -
                <?php echo $val["title"] ?: "Por nombrar"; ?>
            </li>
        <?php } ?>
    </ul>
</div>
<?php require_once "../_incl/post-body.php"; ?>