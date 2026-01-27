<?php
require_once "tools.session.php";
require_once "tools.auth.php";

ini_set("display_errors", 0);



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
  <link rel="stylesheet" href="/static/bootstrap.min.css" />
  <link rel="icon" type="image/png" href="/static/<?php echo $APP_ICON ?? "logo.png"; ?>" />
</head>

<body>
  <style>
    /* fieldset label {
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
    } */

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

    /* 
    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
      margin: 0;
      padding: 0;
    }


    a.grid-item {
      margin-bottom: 10px !important;
      padding: 15px;
      width: 250px;
      text-align: center;
    }

    a.grid-item img {
      margin: 0 auto;
    } */
    .card.pad {
      padding: 10px;
      margin-bottom: 10px;
    }
    details summary {
      cursor: pointer;
      display: list-item;
    }
    .text-black {
      color: black !important;
    }
    .btn {
      margin-bottom: 5px;
    }
    .navbar-nav > a.btn {
      margin-right: 10px;
    }
    .bg-custom {
      background-color: #9013FE;
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
        <nav class="navbar navbar-expand-lg bg-<?= $APP_COLOR ?? ($APP_CODE == "ax4" ? "custom" : "primary") ?>" data-bs-theme="dark">
          <div class="container-fluid">
            <a href="<?php echo $APP_ROOT ?? ""; ?>" class="navbar-brand">
              <img height="30" class="logo" loading="lazy" src="/static/<?php echo $APP_ICON ?? "logo.png"; ?>" style="<?=  $APP_ICON != "logo.png" ? '' : 'filter: invert(1);' ?>" />
              <?php echo $APP_NAME ?? "Axia<sup>4</sup>"; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarColor01" aria-controls="navbarColor01" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarColor01">
              <ul class="navbar-nav me-auto">
                <a class="btn btn-secondary" href="/<?= $APP_CODE ?>/">Inicio</a>
                <?php if (file_exists("../$APP_CODE/__menu.php")) { ?>
                  <?php require_once "../$APP_CODE/__menu.php"; ?>
                <?php } ?>
                
              </ul>
              <?php if ($APP_CODE != "ax4") { ?>
                <a href="/" class="btn btn-secondary pseudo" style="background: #9013FE; color: white;">Salir a Axia4</a>
              <?php } ?>
            </div>
          </div>
        </nav>
        <main style="padding: 20px; ">
        <?php } ?>
        <?php if (isset($_GET["_result"])) { ?>
          <div class="card pad"
            style="padding: 10px; background-color: <?php echo $_GET["_resultcolor"] ?? 'lightgreen'; ?>; text-align: center;">
            <h3><?php echo $_GET["_result"]; ?></h3>
          </div>
        <?php } ?>
        <!-- <div class="card pad" style="padding: 15px; background: #ffcc00; color: #000;">
      <h2>Alerta Meteorologica</h2>
      <span>Viento fuerte en Portugalete.</span>
    </div> -->