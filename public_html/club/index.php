<?php
ini_set("display_errors", 0);
require_once "../_incl/db.php";
$files = glob("/DATA/club/IMG/*/");
sort($files);
$files = array_reverse($files);
$APP_CODE = "club";
$APP_NAME = "La web del Club<sup>3</sup>";
$APP_TITLE = "La web del Club";
$PAGE_TITLE = "Club - Inicio";
require_once "../_incl/pre-body.php"; ?>
<div class="card pad">
    <span>
        <a href="/club/upload/" class="btn btn-secondary">+ Nuevo</a>
    </span>
    <h2>Calendario:</h2>
    <ul>
        <?php foreach ($files as $file) {
            $filenam = str_replace("/", "", str_replace("/DATA/club/IMG/", "", $file));
            $date = implode("/", array_reverse(explode("-", $filenam)));
            $val = db_get_club_event($filenam);
                ?>

            <li><a class="btn btn-secondary" href="cal.php?f=<?php echo $filenam; ?>"><b><?php echo $date; ?></b></a> -
                <?php echo $val["title"] ?: "Por nombrar"; ?>
            </li>
        <?php } ?>
    </ul>
</div>
<?php require_once "../_incl/post-body.php"; ?>