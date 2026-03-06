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

// Tenant (centro) management
$userCentros  = get_user_centros($_SESSION["auth_data"] ?? []);
$activeCentro = $_SESSION['active_centro'] ?? ($_SESSION["auth_data"]["entreaulas"]["centro"] ?? '');


<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $PAGE_TITLE ?? $APP_TITLE ?? "Axia4"; ?></title>
  <link rel="stylesheet" href="/static/bootstrap.min.css" />
  <link rel="icon" type="image/png" href="/static/<?php echo $APP_ICON ?? "logo.png"; ?>" />
  <link rel="manifest" href="/static/manifest.json">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
</head>

<body>
  <style>
    /* ─── Google Workspace Design System ─────────────────────────────── */
    :root {
      --gw-font: 'Google Sans', 'Roboto', 'Arial', sans-serif;
      --gw-blue: #1a73e8;
      --gw-blue-hover: #1765cc;
      --gw-blue-light: #e8f0fe;
      --gw-text-primary: #202124;
      --gw-text-secondary: #5f6368;
      --gw-bg: #f0f4f9;
      --gw-surface: #ffffff;
      --gw-border: #dadce0;
      --gw-hover: #f1f3f4;
      --gw-header-h: 64px;
      --gw-sidebar-w: 256px;
      --gw-brand: #9013FE;
      --bs-btn-font-family: 'Google Sans', 'Roboto', Arial, sans-serif;
      --bs-body-font-family: 'Google Sans', 'Roboto', Arial, sans-serif;
      --bs-font-sans-serif: 'Google Sans', 'Roboto', Arial, sans-serif;
      --bs-link-color: var(--gw-blue);
      --bs-link-hover-color: var(--gw-blue-hover);
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
      font-family: var(--gw-font);
      background: var(--gw-bg);
      color: var(--gw-text-primary);
      margin: 0;
    }

    /* ─── Print ───────────────────────────────────────────────────────── */
    @media print { .no-print { display: none; } }

    /* ─── Form helpers ────────────────────────────────────────────────── */
    input[readonly], textarea[readonly], .select select[readonly] {
      background-color: #f1f3f4;
    }
    fieldset input, fieldset textarea, fieldset .select select {
      width: 100%;
      box-sizing: border-box;
    }
    input.nonumscroll::-webkit-outer-spin-button,
    input.nonumscroll::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    input.nonumscroll[type=number] { appearance: textfield; -moz-appearance: textfield; }

    /* ─── Utility ─────────────────────────────────────────────────────── */
    .card.pad { padding: 12px; margin-bottom: 12px; border: 1px solid var(--gw-border); border-radius: 8px; box-shadow: none; }
    details summary { cursor: pointer; display: list-item; }
    .text-black { color: black !important; }
    .bg-custom { background-color: var(--gw-brand); }
    .btn { margin-bottom: 4px; border-radius: 4px; font-family: var(--gw-font); font-weight: 500; letter-spacing: 0.01em; }
    .btn-primary { background-color: var(--gw-blue); border-color: var(--gw-blue); }
    .btn-primary:hover { background-color: var(--gw-blue-hover); border-color: var(--gw-blue-hover); }
    .navbar-nav > a.btn { margin-right: 10px; }

    /* ─── App shell ───────────────────────────────────────────────────── */
    .app-shell {
      display: flex;
      min-height: calc(100vh - var(--gw-header-h));
      background: var(--gw-bg);
    }

    /* ─── Sidebar toggle (hidden checkbox) ───────────────────────────── */
    .sidebar-toggle-input { position: absolute; opacity: 0; pointer-events: none; }

    /* ─── Sidebar ─────────────────────────────────────────────────────── */
    .sidebar {
      width: var(--gw-sidebar-w);
      background: var(--gw-surface);
      padding: 8px 0;
      position: sticky;
      top: var(--gw-header-h);
      height: calc(100vh - var(--gw-header-h));
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 0;
      transition: width 0.3s cubic-bezier(0.4,0,0.2,1),
                  padding 0.3s cubic-bezier(0.4,0,0.2,1),
                  opacity 0.2s ease;
      flex-shrink: 0;
    }

    .sidebar-toggle-input:not(:checked) ~ .app-shell .sidebar {
      width: 0;
      overflow: hidden;
      opacity: 0;
      pointer-events: none;
    }

    .sidebar-section-label {
      font-size: 11px;
      font-weight: 500;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--gw-text-secondary);
      padding: 16px 16px 4px;
      white-space: nowrap;
    }

    .sidebar-nav {
      display: flex;
      flex-direction: column;
      gap: 2px;
      padding: 0 8px;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 0 16px;
      height: 48px;
      border-radius: 24px;
      text-decoration: none;
      color: var(--gw-text-primary);
      font-size: 14px;
      font-weight: 400;
      white-space: nowrap;
      transition: background 0.15s ease;
    }
    .sidebar-link:hover { background: var(--gw-hover); color: var(--gw-text-primary); text-decoration: none; }
    .sidebar-link.active, .sidebar-link:focus-visible { background: var(--gw-blue-light); color: var(--gw-blue); font-weight: 500; }

    .sidebar-link img { height: 20px; width: 20px; object-fit: contain; flex-shrink: 0; }

    .sidebar-divider { height: 1px; background: var(--gw-border); margin: 8px 16px; }

    .sidebar-backdrop { display: none; }

    /* ─── App content ─────────────────────────────────────────────────── */
    .app-content { flex: 1; min-width: 0; }

    /* ─── Top header ──────────────────────────────────────────────────── */
    .axia-header {
      display: flex;
      align-items: center;
      gap: 4px;
      background: var(--gw-surface);
      border-bottom: 1px solid var(--gw-border);
      padding: 0 8px;
      height: var(--gw-header-h);
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 1px 2px 0 rgba(60,64,67,0.1);
    }

    .logo-area {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 18px;
      font-weight: 400;
      color: var(--gw-text-secondary);
      text-decoration: none;
      padding: 0 8px;
      white-space: nowrap;
    }
    .logo-area:hover { color: var(--gw-text-primary); text-decoration: none; }

    .brand-logo { height: 30px; }

    .brand-text { font-size: 18px; font-weight: 400; letter-spacing: -0.01em; }

    /* Sidebar toggle button */
    .sidebar-toggle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      background: transparent;
      color: var(--gw-text-secondary);
      border: none;
      transition: background 0.15s ease;
      flex-shrink: 0;
    }
    .sidebar-toggle:hover { background: var(--gw-hover); }

    /* Search bar */
    .search-bar { flex: 1; max-width: 720px; margin: 0 auto; }

    .search-bar form,
    .search-bar > form { display: flex; }

    .search-bar input {
      width: 100%;
      border: 1px solid var(--gw-border);
      background: var(--gw-hover);
      padding: 8px 20px;
      border-radius: 24px;
      outline: none;
      font-size: 16px;
      font-family: var(--gw-font);
      color: var(--gw-text-primary);
      transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .search-bar input:focus {
      background: var(--gw-surface);
      border-color: var(--gw-blue);
      box-shadow: 0 1px 6px rgba(32,33,36,0.28);
    }
    .search-bar input::placeholder { color: var(--gw-text-secondary); }

    /* Header action area */
    .header-actions { display: flex; align-items: center; gap: 4px; margin-left: auto; }

    .axia-header summary { list-style: none; }
    .axia-header summary::-webkit-details-marker { display: none; }

    /* Icon button (for waffle, etc.) */
    .icon-button {
      list-style: none;
      background: transparent;
      border: none;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: var(--gw-text-secondary);
      transition: background 0.15s ease;
    }
    .icon-button:hover { background: var(--gw-hover); }

    /* Waffle 3×3 dot grid */
    .dot-grid {
      display: grid;
      grid-template-columns: repeat(3, 5px);
      gap: 3px;
    }
    .dot-grid span {
      width: 5px;
      height: 5px;
      background: var(--gw-text-secondary);
      border-radius: 50%;
    }

    /* ─── Dropdown cards ──────────────────────────────────────────────── */
    details { position: relative; }

    header .menu-card {
      position: absolute;
      right: 0;
      top: calc(100% + 4px);
      background: var(--gw-surface);
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.06);
      padding: 12px 4px;
      min-width: 280px;
      z-index: 200;
    }

    header .menu-card-title {
      font-size: 13px;
      font-weight: 500;
      color: var(--gw-text-secondary);
      padding: 4px 16px 12px;
      letter-spacing: 0.01em;
    }

    header .menu-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0;
    }

    header .menu-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      padding: 12px 8px;
      border-radius: 8px;
      text-decoration: none;
      color: var(--gw-text-primary);
      font-size: 12px;
      text-align: center;
      margin: 2px;
      transition: background 0.15s ease;
    }
    header .menu-item:hover { background: var(--gw-hover); text-decoration: none; color: var(--gw-text-primary); }

    header .menu-item img {
      height: 40px;
      width: 40px;
      border-radius: 8px;
      object-fit: contain;
    }

    header .menu-item span { line-height: 1.3; }

    /* ─── Avatar ──────────────────────────────────────────────────────── */
    .avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--gw-blue);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 500;
      font-size: 13px;
      cursor: pointer;
      letter-spacing: 0.03em;
      flex-shrink: 0;
    }
    .avatar.big { width: 56px; height: 56px; font-size: 22px; }

    /* ─── Account card ────────────────────────────────────────────────── */
    .account-card { min-width: 300px; }

    .account-head {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      padding: 16px 16px 12px;
      border-bottom: 1px solid var(--gw-border);
      margin-bottom: 8px;
      text-align: center;
    }

    .account-name { font-weight: 500; font-size: 16px; }
    .account-email { font-size: 13px; color: var(--gw-text-secondary); }

    .account-actions { padding: 0 12px 8px; }
    .account-actions .btn { margin-bottom: 6px; border-radius: 20px; font-size: 14px; }

    /* ─── Main content ────────────────────────────────────────────────── */
    .axia-home {
      max-width: 1200px;
      margin: 0 auto;
      padding: 24px 24px 48px;
    }

    /* ─── Mobile ──────────────────────────────────────────────────────── */
    @media (max-width: 768px) {
      .axia-home { padding: 16px 12px 48px; }

      .app-shell { display: block; }

      .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        transform: translateX(-100%);
        transition: transform 0.25s cubic-bezier(0.4,0,0.2,1);
        z-index: 200;
        width: var(--gw-sidebar-w);
        padding: 8px 0;
        opacity: 1;
        pointer-events: auto;
      }

      .sidebar-toggle-input:not(:checked) ~ .app-shell .sidebar {
        width: var(--gw-sidebar-w);
        opacity: 1;
        pointer-events: auto;
      }

      .sidebar-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.32);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
        z-index: 150;
        display: block;
      }

      .sidebar-toggle-input:checked ~ .app-shell .sidebar { transform: translateX(0); }
      .sidebar-toggle-input:checked ~ .app-shell .sidebar-backdrop { opacity: 1; pointer-events: auto; }

      .search-bar { display: none; }
      .header-actions { gap: 2px; }
      .hide-small { display: none; }

      .logo-area { padding: 0 4px; }
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

        <!-- ── Google Workspace-style top header ──────────────────── -->
        <header class="axia-header">
          <label for="sidebarToggle" class="sidebar-toggle" aria-label="Abrir menú">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
            </svg>
          </label>
          <a class="logo-area" href="<?= $APP_ROOT ?>">
            <img src="/static/<?= $APP_ICON ?>" alt="<?= htmlspecialchars($APP_NAME) ?>" class="brand-logo">
            <span class="brand-text hide-small"><?= $APP_NAME ?></span>
          </a>
          <div class="search-bar">
            <form action="https://search.tech.eus/s/" method="get">
              <input type="text" name="q" placeholder="Búsqueda global" aria-label="Buscar">
            </form>
          </div>
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
                <div class="menu-card-title">Aplicaciones de Axia4</div>
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
                  <div class="account-name"><?php echo htmlspecialchars($displayName); ?></div>
                  <div class="account-email"><?php echo htmlspecialchars($email); ?></div>
                </div>
                <?php if (!empty($userCentros) && $_SESSION["auth_ok"]): ?>
                <div style="padding: 8px 16px; border-top: 1px solid #e0e0e0;">
                  <div style="font-size:.75rem;font-weight:600;color:#5f6368;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">
                    Organización activa
                  </div>
                  <div style="font-size:.9rem;font-weight:600;color:#1a73e8;margin-bottom:<?= count($userCentros) > 1 ? '8px' : '0' ?>;">
                    <?= htmlspecialchars($activeCentro ?: '–') ?>
                  </div>
                  <?php if (count($userCentros) > 1): ?>
                    <div style="font-size:.75rem;color:#5f6368;margin-bottom:4px;">Cambiar organización:</div>
                    <?php foreach ($userCentros as $cid): if ($cid === $activeCentro) continue; ?>
                      <form method="post" action="/_incl/switch_tenant.php" style="margin:0 0 4px;">
                        <input type="hidden" name="redir" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
                        <button type="submit" name="centro" value="<?= htmlspecialchars($cid) ?>"
                                style="display:block;width:100%;text-align:left;padding:5px 8px;border:1px solid #e0e0e0;border-radius:6px;background:#f8f9fa;font-size:.85rem;cursor:pointer;">
                          <?= htmlspecialchars($cid) ?>
                        </button>
                      </form>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="account-actions">
                  <?php if ($_SESSION["auth_ok"]) { ?>
                    <a href="/account/" class="btn btn-outline-secondary w-100">Gestionar cuenta</a>
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

        <!-- ── App shell (sidebar + content) ──────────────────────── -->
        <div class="app-shell">
          <aside class="sidebar">
            <div class="sidebar-section-label">Esta app</div>
            <nav class="sidebar-nav">
              <?php
              if (file_exists(__DIR__ . "/../$APP_CODE/__menu.php")) {
                include __DIR__ . "/../$APP_CODE/__menu.php";
              }
              ?>
            </nav>
            <div class="sidebar-divider"></div>
            <div class="sidebar-section-label">Axia4</div>
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
              <?php } ?>
              <?php if (isset($_GET["_result"])) { ?>
                <div class="card pad"
                  style="padding: 10px; background-color: <?php echo Si($_GET["_resultcolor"] ?? 'lightgreen'); ?>; text-align: center;">
                  <h3><?php echo htmlspecialchars($_GET["_result"]); ?></h3>
                </div>
              <?php } ?>
              <!-- <div class="card pad" style="padding: 15px; background: #ffcc00; color: #000;">
      <h2>Alerta Meteorologica</h2>
      <span>Viento fuerte en Portugalete.</span>
    </div> -->
