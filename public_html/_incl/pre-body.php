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

$displayName = $_SESSION["auth_data"]["display_name"] ?? "Invitado";
$email = $_SESSION["auth_data"]["email"] ?? "Sin sesión";
$initials = "?";
if (!empty($displayName)) {
  $parts = preg_split('/\s+/', trim($displayName));
  $first = mb_substr($parts[0] ?? "", 0, 1);
  $last = mb_substr($parts[1] ?? "", 0, 1);
  $initials = mb_strtoupper($first . $last);
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

    .navbar-nav>a.btn {
      margin-right: 10px;
    }

    .bg-custom {
      background-color: #9013FE;
    }

    .app-shell {
      display: flex;
      min-height: 100vh;
      background: #f5f5f5;
    }

    .sidebar-toggle-input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .sidebar {
      width: 260px;
      background: #ffffff;
      border-right: 1px solid #e5e7eb;
      padding: 20px 16px;
      position: sticky;
      top: 0;
      height: 100vh;
      display: flex;
      flex-direction: column;
      gap: 16px;
      transition: width 0.25s ease, transform 0.25s ease, padding 0.25s ease, opacity 0.2s ease;
    }

    .sidebar-toggle-input:not(:checked)~.app-shell .sidebar {
      width: 0;
      padding-left: 0;
      padding-right: 0;
      border-right: none;
      overflow: hidden;
      opacity: 0;
      pointer-events: none;
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
      color: #202124;
      text-decoration: none;
    }

    .sidebar-brand img {
      height: 34px;
    }

    .sidebar-nav {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 10px;
      border-radius: 12px;
      text-decoration: none;
      color: #202124;
      background: #f8f9fa;
      outline: 1px solid grey;
    }

    .sidebar-link img {
      height: 26px;
    }

    .sidebar-note {
      font-size: 12px;
      color: #5f6368;
    }

    .sidebar-backdrop {
      display: none;
    }

    .app-content {
      flex: 1;
      min-width: 0;
    }

    .axia-home {
      max-width: 1200px;
      margin: 0 auto;
      padding: 10px 16px 40px;
    }

    .axia-header {
      display: flex;
      align-items: center;
      gap: 18px;
      background: #ffffff;
      border-radius: 999px;
      padding: 10px 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      position: sticky;
      top: 10px;
      z-index: 5;
    }

    .logo-area {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
      color: #202124;
      text-decoration: none;
    }

    .brand-logo {
      height: 32px;
    }

    .brand-text {
      font-size: 18px;
    }

    .sidebar-toggle {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      background: #f1f3f4;
      color: #5f6368;
      border: none;
    }

    .search-bar {
      flex: 1;
    }

    .search-bar input {
      width: 100%;
      border: none;
      background: #f1f3f4;
      padding: 10px 16px;
      border-radius: 999px;
      outline: none;
      font-size: 15px;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .axia-header summary {
      list-style: none;
    }

    .axia-header summary::-webkit-details-marker {
      display: none;
    }

    .icon-button {
      list-style: none;
      background: transparent;
      border: none;
      padding: 8px;
      border-radius: 50%;
      cursor: pointer;
    }

    .dot-grid {
      display: grid;
      grid-template-columns: repeat(3, 4px);
      gap: 4px;
      padding: 2px;
    }

    .dot-grid span {
      width: 4px;
      height: 4px;
      background: #5f6368;
      border-radius: 50%;
    }

    details {
      position: relative;
    }

    .menu-card {
      position: absolute;
      right: 0;
      margin-top: 10px;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
      padding: 16px;
      min-width: 240px;
      z-index: 10;
    }

    .menu-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
      gap: 12px;
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 10px;
      border-radius: 12px;
      text-decoration: none;
      color: #202124;
      background: #f8f9fa;
      outline: 1px solid grey;
    }

    .menu-item img {
      height: 28px;
    }

    .avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #1a73e8;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      cursor: pointer;
    }

    .avatar.big {
      width: 48px;
      height: 48px;
      font-size: 24px;
    }

    .account-card {
      min-width: 280px;
    }

    .account-head {
      display: flex;
      gap: 12px;
      align-items: center;
      margin-bottom: 12px;
    }

    .account-name {
      font-weight: 600;
    }

    .account-email {
      font-size: 13px;
      color: #5f6368;
    }

    .account-actions .btn {
      margin-bottom: 8px;
    }

    @media (max-width: 768px) {
      .sidebar-toggle {
        display: inline-flex;
        /* make it more to the left */
        margin-left: -8px;
      }

      .logo-area {
        gap: 6px;
        margin-left: -8px;
        margin-right: -8px;
      }

      .axia-home {
        padding: 10px 8px 40px;
      }

      .app-shell {
        display: block;
      }

      .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        transform: translateX(-100%);
        transition: transform 0.25s ease;
        z-index: 20;
        width: 260px;
        padding: 20px 16px;
        border-right: 1px solid #e5e7eb;
        opacity: 1;
        pointer-events: auto;
      }

      .sidebar-toggle-input:not(:checked)~.app-shell .sidebar {
        width: 260px;
        padding: 20px 16px;
        border-right: 1px solid #e5e7eb;
        opacity: 1;
        pointer-events: auto;
      }

      .sidebar-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.35);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
        z-index: 15;
        display: block;
      }

      .sidebar-toggle-input:checked~.app-shell .sidebar {
        transform: translateX(0);
      }

      .sidebar-toggle-input:checked~.app-shell .sidebar-backdrop {
        opacity: 1;
        pointer-events: auto;
      }

      .axia-header {
        flex-wrap: wrap;
        border-radius: 20px;
      }

      .search-bar {
        width: 100%;
        order: 3;
        display: none;
      }

      /* make other buttons alinged to the right */
      .header-actions {
        margin-left: auto;
        margin-right: -8px;
        gap: 6px;
      }

      .hide-small {
        display: none;
      }
    }

    :root {
      --bs-btn-font-family: Arial, Helvetica, sans-serif;
      --bs-body-font-family: Arial, Helvetica, sans-serif;
      --bs-font-sans-serif: Arial, Helvetica, sans-serif;
      --bs-font-family-base: Arial, Helvetica, sans-serif;
      --bs-heading-font-family: Arial, Helvetica, sans-serif;
    }
  </style>

  <script src="/static/masonry.pkgd.min.js"></script>
  <script src="//code.iconify.design/1/1.0.6/iconify.min.js"></script>
  <?php if ($_GET["_hidenav"] == "yes") { ?>
    <main style="padding: 10px;">
    <?php } elseif ($_GET["_hidenav"] == "widget") { ?>
      <main style="padding: 0px;">
      <?php } else { ?>
        <input type="checkbox" id="sidebarToggle" class="sidebar-toggle-input">
        <script>
          (function() {
            const toggle = document.getElementById('sidebarToggle');
            if (!toggle) return;

            const storageKey = 'axia4.sidebar.open';
            const prefersDesktopOpen = window.matchMedia('(min-width: 769px)').matches;
            const saved = localStorage.getItem(storageKey);

            if (saved === 'true' || saved === 'false') {
              toggle.checked = saved === 'true';
            } else {
              toggle.checked = prefersDesktopOpen;
            }

            toggle.addEventListener('change', function() {
              localStorage.setItem(storageKey, toggle.checked ? 'true' : 'false');
            });
          })();
        </script>
        <div class="app-shell">
          <aside class="sidebar">
            <b>Esta app</b>
            <nav class="sidebar-nav">
              <?php 
              if (file_exists(__DIR__ . "/../$APP_CODE/__menu.php")) {
                include __DIR__ . "/../$APP_CODE/__menu.php";
              }
              ?>
            </nav>
            <b>Axia4</b>
            <nav class="sidebar-nav">
              <a class="sidebar-link" href="/">
                <img src="/static/logo.png" alt="">
                <span>Inicio</span>
              </a>
            </nav>
          </aside>
          <label for="sidebarToggle" class="sidebar-backdrop" aria-hidden="true"></label>
          <div class="app-content">
            <main class="axia-home">
              <header class="axia-header">
                <label for="sidebarToggle" class="sidebar-toggle" aria-label="Abrir menú">☰</label>
                <a class="logo-area" href="<?= $APP_ROOT ?>">
                  <img src="/static/<?= $APP_ICON ?>" alt="<?= htmlspecialchars($APP_NAME) ?>" class="brand-logo">
                  <span class="brand-text"><?= $APP_NAME ?></span>
                </a>
                <form class="search-bar" action="/search.php" method="get">
                  <input type="text" name="q" placeholder="Busca en Axia4" aria-label="Buscar">
                </form>
                <div class="header-actions">
                  <details class="app-menu">
                    <summary class="icon-button" aria-label="Menú de aplicaciones">
                      <span class="dot-grid" aria-hidden="true">
                        <span></span><span></span><span></span>
                        <span></span><span></span><span></span>
                        <span></span><span></span><span></span>
                      </span>
                    </summary>
                    <div class="menu-card">
                      <div class="menu-grid">
                        <a class="menu-item" href="/">
                          <img src="/static/logo.png" alt="">
                          <span>Axia4</span>
                        </a>
                        <a class="menu-item" href="/club/">
                          <img src="/static/logo-club.png" alt="">
                          <span>Club</span>
                        </a>
                        <a class="menu-item" href="/entreaulas/">
                          <img src="/static/logo-entreaulas.png" alt="">
                          <span>EntreAulas</span>
                        </a>
                        <a class="menu-item" href="/account/">
                          <img src="/static/logo-account.png" alt="">
                          <span>Cuenta</span>
                        </a>
                        <a class="menu-item" href="/sysadmin/">
                          <img src="/static/logo-sysadmin.png" alt="">
                          <span>SysAdmin</span>
                        </a>
                      </div>
                    </div>
                  </details>
                  <details class="account-menu">
                    <summary class="avatar" aria-label="Cuenta">
                      <?php echo htmlspecialchars($initials); ?>
                    </summary>
                    <div class="menu-card account-card">
                      <div class="account-head">
                        <div class="avatar big"><?php echo htmlspecialchars($initials); ?></div>
                        <div>
                          <div class="account-name"><?php echo htmlspecialchars($displayName); ?></div>
                          <div class="account-email"><?php echo htmlspecialchars($email); ?></div>
                        </div>
                      </div>
                      <div class="account-actions">
                        <?php if ($_SESSION["auth_ok"]) { ?>
                          <a href="/account/" class="btn btn-primary w-100">Gestionar cuenta</a>
                          <a href="/_login.php?logout=1&redir=/" class="btn btn-outline-secondary w-100">Cerrar sesión</a>
                        <?php } else { ?>
                          <a href="/_login.php?redir=/" class="btn btn-primary w-100">Iniciar sesión</a>
                          <a href="/account/register.php" class="btn btn-outline-primary w-100">Crear cuenta</a>
                        <?php } ?>
                      </div>
                    </div>
                  </details>
                </div>
              </header>
              <div style="margin-top: 20px;">
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