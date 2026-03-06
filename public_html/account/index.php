<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/db.php";
require_once "_incl/pre-body.php";

$authData   = $_SESSION["auth_data"] ?? [];
$username   = $_SESSION["auth_user"] ?? '';
$displayName = $authData["display_name"] ?? 'Invitado';
$email      = $authData["email"] ?? '';
$permissions = $authData["permissions"] ?? [];

// Tenant / centro management
$userCentros   = get_user_centros($authData);
$activeCentro  = $_SESSION['active_centro'] ?? ($authData['entreaulas']['centro'] ?? '');
$aularios      = ($activeCentro !== '') ? db_get_aularios($activeCentro) : [];
$userAulas     = $authData['entreaulas']['aulas'] ?? [];
$role          = $authData['entreaulas']['role'] ?? '';

// Initials for avatar
$parts    = preg_split('/\s+/', trim($displayName));
$initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
if ($initials === '') {
    $initials = '?';
}
?>
<style>
.account-grid { display: flex; flex-wrap: wrap; gap: 16px; padding: 16px; }
.account-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 24px; min-width: 280px; flex: 1 1 280px; }
.account-card h2 { font-size: 1rem; font-weight: 600; color: var(--gw-text-secondary, #5f6368); text-transform: uppercase; letter-spacing: .05em; margin: 0 0 16px; }
.avatar-lg { width: 80px; height: 80px; border-radius: 50%; background: var(--gw-blue, #1a73e8); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; margin: 0 auto 12px; }
.info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f3f4; font-size: .9rem; }
.info-row:last-child { border-bottom: none; }
.info-row .label { color: var(--gw-text-secondary, #5f6368); }
.badge-pill { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: .78rem; font-weight: 600; margin: 2px; }
.badge-active { background: #e6f4ea; color: #137333; }
.badge-perm { background: #e8f0fe; color: #1a73e8; }
.tenant-btn { display: block; width: 100%; text-align: left; padding: 8px 12px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 8px; background: #f8f9fa; cursor: pointer; font-size: .9rem; transition: background .15s; }
.tenant-btn:hover { background: #e8f0fe; }
.tenant-btn.active-tenant { border-color: var(--gw-blue, #1a73e8); background: #e8f0fe; font-weight: 600; }
</style>

<div class="account-grid">

  <!-- Profile Card -->
  <div class="account-card" style="text-align:center;">
    <h2>Mi Perfil</h2>
    <div class="avatar-lg"><?= htmlspecialchars($initials) ?></div>
    <div style="font-size:1.2rem; font-weight:700; margin-bottom:4px;"><?= htmlspecialchars($displayName) ?></div>
    <div style="color:var(--gw-text-secondary,#5f6368); margin-bottom:16px;"><?= htmlspecialchars($email ?: 'Sin correo') ?></div>
    <div class="info-row"><span class="label">Usuario</span><span><?= htmlspecialchars($username) ?></span></div>
    <?php if ($role): ?>
    <div class="info-row"><span class="label">Rol</span><span><?= htmlspecialchars($role) ?></span></div>
    <?php endif; ?>
    <div style="margin-top:16px;">
      <a href="/account/change_password.php" class="btn btn-secondary btn-sm">Cambiar contraseña</a>
    </div>
  </div>

  <!-- QR Card -->
  <div class="account-card" style="text-align:center;">
    <h2>Código QR de Acceso</h2>
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($username) ?>"
         alt="QR Code" style="margin:0 auto 12px; display:block; width:150px; height:150px;">
    <small style="color:var(--gw-text-secondary,#5f6368);">Escanea este código para iniciar sesión rápidamente.</small>
  </div>

  <!-- Tenant / Centro Card -->
  <?php if (!empty($userCentros)): ?>
  <div class="account-card">
    <h2>Organizaciones</h2>
    <?php foreach ($userCentros as $cid): ?>
      <form method="post" action="/_incl/switch_tenant.php" style="margin:0;">
        <input type="hidden" name="redir" value="/account/">
        <button type="submit" name="centro" value="<?= htmlspecialchars($cid) ?>"
                class="tenant-btn <?= ($activeCentro === $cid) ? 'active-tenant' : '' ?>">
          <?php if ($activeCentro === $cid): ?>
            <span style="color:var(--gw-blue,#1a73e8);">✓ </span>
          <?php endif; ?>
          <?= htmlspecialchars($cid) ?>
          <?php if ($activeCentro === $cid): ?>
            <span class="badge-pill badge-active" style="float:right;">Activo</span>
          <?php endif; ?>
        </button>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Aulas Card -->
  <?php if (!empty($userAulas)): ?>
  <div class="account-card">
    <h2>Mis Aulas (<?= htmlspecialchars($activeCentro) ?>)</h2>
    <?php foreach ($userAulas as $aula_id): ?>
      <?php $aula = $aularios[$aula_id] ?? null; ?>
      <div class="info-row">
        <?php if ($aula && !empty($aula['icon'])): ?>
          <img src="<?= htmlspecialchars($aula['icon']) ?>" style="height:20px;vertical-align:middle;margin-right:6px;">
        <?php endif; ?>
        <span><?= htmlspecialchars($aula['name'] ?? $aula_id) ?></span>
        <span class="badge-pill badge-active">Asignada</span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Permissions Card -->
  <?php if (!empty($permissions)): ?>
  <div class="account-card">
    <h2>Permisos</h2>
    <div>
      <?php foreach ($permissions as $p): ?>
        <span class="badge-pill badge-perm"><?= htmlspecialchars($p) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Session Info Card -->
  <div class="account-card">
    <h2>Sesión Activa</h2>
    <div class="info-row"><span class="label">ID Sesión</span><span style="font-family:monospace;font-size:.75rem;"><?= htmlspecialchars(substr(session_id(), 0, 12)) ?>…</span></div>
    <div class="info-row"><span class="label">Org. activa</span><span><?= htmlspecialchars($activeCentro ?: '–') ?></span></div>
    <div class="info-row"><span class="label">Autenticación</span><span><?= empty($authData['google_auth']) ? 'Contraseña' : 'Google' ?></span></div>
    <div style="margin-top:16px;">
      <a href="/_incl/logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
    </div>
  </div>

</div>

<?php require_once "_incl/post-body.php"; ?>
