<?php
session_start([ 'cookie_lifetime' => 604800 ]);
session_regenerate_id();
ini_set("session.use_only_cookies", "true");
ini_set("session.use_trans_sid", "false");

if (!isset($APP_CODE)) {
  $APP_CODE = "ax4";
  $APP_ROOT = "/";
  $APP_ICON = "logo.png";
  $APP_NAME = "Axia<sup>4</sup>";
  $APP_TITLE = "Axia4";
} else {
  $APP_ROOT = "/$APP_CODE";
  $APP_ICON = "logo-$APP_CODE.png";
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $APP_TITLE ?? "Axia4"; ?></title>
  <link rel="stylesheet" href="/static/picnic.min.css" />
  <link rel="icon" type="image/png" href="/static/<?php echo $APP_ICON ?? "logo.png"; ?>" />
</head>

<body>
  <style>
    fieldset label {
      margin-bottom: 15px;
    }

    .actbutton,
    .actbutton-half {
      padding: 5px 10px;
      padding-left: 5px;
      width: 200px;
      text-align: right;
      vertical-align: top;
    }

    .actbutton-half {
      width: 167.5px;

    }

    .actbutton img,
    .actbutton-half img {
      float: left;
      margin: 0;
      height: 55px;
      width: 55px;
      margin-right: 10px;
    }

    td,
    th {
      padding: 0.3em 0.6em;
      border-left: 1px solid #ccc;
      border-right: 1px solid #ccc;
    }

    th {
      text-align: center;
    }

    @media print {
      .no-print {
        display: none;
      }
    }

    input[readonly],
    textarea[readonly],
    .select select[readonly] {
      background-color: lightgray;
    }

    fieldset input,
    fieldset textarea,
    fieldset .select select {
      width: calc(100% - 1s5px);
      box-sizing: border-box;
    }

    input.nonumscroll::-webkit-outer-spin-button,
    input.nonumscroll::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    input.nonumscroll[type=number] {
      appearance: textfield;
      -moz-appearance: textfield;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
      margin: 0;
      padding: 0;
    }

    .card.pad {
      padding: 15px 25px;
    }

    a.grid-item {
      margin-bottom: 10px !important;
      padding: 15px;
      width: 250px;
      text-align: center;
    }

    a.grid-item img {
      margin: 0 auto;
      /*height: 100px;*/
    }
    details summary {
      cursor: pointer;
      display: list-item;
    }
  </style>

  <script src="/static/masonry.pkgd.min.js"></script>
  <script src="//code.iconify.design/1/1.0.6/iconify.min.js"></script>
  <?php if ($_GET["_hidenav"] == "yes") { ?>
    <main style="padding: 10px;">
  <?php } elseif ($_GET["_hidenav"] == "widget") { ?>
    <main style="padding: 0px;">
  <?php } else { ?>
    <style>
      body {
        height: calc(100% - 3em);
        background: #ddd;
      }
    </style>
    <nav>
      <a href="<?php echo $APP_ROOT ?? ""; ?>" class="brand">
        <img class="logo" loading="lazy" src="/static/<?php echo $APP_ICON ?? "logo.png"; ?>" />
        <span><?php echo $APP_NAME ?? "Axia<sup>4</sup>"; ?></span>
      </a>
      <?php if ($APP_CODE == "ax4") { ?>
        <a href="/lazo.php" class="brand" style="padding: 0;">
          <img style="margin: 0;" class="logo" title="Nuestra solidaridad con las víctimas y familiares del grave accidente de Adamuz"
            alt="Nuestra solidaridad con las víctimas y familiares del grave accidente de Adamuz" src="/static/lazo_negro.png" />
        </a>
      <?php } ?>
      <input id="bmenub" type="checkbox" class="show" />
      <label for="bmenub" class="burger button">menú</label>
      <div class="menu">
        <?php if (file_exists(__DIR__ . "/.." . $APP_ROOT . "/__menu.php")) { ?>
          <?php require_once __DIR__ . "/.." . $APP_ROOT . "/__menu.php"; ?>
        <?php } ?>
        <?php if ($APP_CODE != "ax4") { ?>
          <a href="/" class="button pseudo" style="background: #9013FE; color: white;">Ax<sup>4</sup></a>
        <?php } ?>
      </div>
    </nav>
    <main style="margin-top: 3em; padding: 20px; ">
    <?php } ?>
    <?php if (isset($_GET["_result"])) { ?>
      <div class="card"
        style="padding: 10px; background-color: <?php echo $_GET["_resultcolor"] ?? 'lightgreen'; ?>; text-align: center;">
        <h3><?php echo $_GET["_result"]; ?></h3>
      </div>
    <?php } ?>
    <!-- <div class="card" style="padding: 15px; background: #ffcc00; color: #000;">
      <h2>Alerta Meteorologica</h2>
      <span>Viento fuerte en Portugalete.</span>
    </div> -->
