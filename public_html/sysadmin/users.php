<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/tools.security.php";
require_once "../_incl/db.php";

function safe_username($value)
{
    $value = strtolower(basename((string) $value));
    $value = preg_replace('/[^a-zA-Z0-9._@-]/', '', $value);
    if (strpos($value, '..') !== false) {
        return '';
    }
    return $value;
}

function render_users_mobile_styles()
{
    ?>
    <style>
      .users-mobile-stack .btn {
        width: 100%;
      }
      .tenant-list {
        max-height: 210px;
        overflow: auto;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
      }
      .tenant-list .form-check {
        margin-bottom: 0.45rem;
      }
      .aulas-list {
        max-height: 220px;
        overflow: auto;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
      }
      .aulas-list .form-check {
        margin-right: 0.6rem;
        margin-bottom: 0.5rem;
      }
      @media (max-width: 767.98px) {
        .card.pad {
          padding: 1rem !important;
        }
        .users-mobile-stack h1 {
          font-size: 1.4rem;
        }
        .users-mobile-stack .accordion-button {
          padding-top: 0.7rem;
          padding-bottom: 0.7rem;
        }
        .users-mobile-stack .btn {
          width: 100%;
        }
      }
    </style>
    <?php
}

switch ($_GET['form'] ?? '') {
    case 'delete_user':
        $username = safe_username($_POST['username'] ?? '');
        if (empty($username)) {
            die("Nombre de usuario no proporcionado.");
        }
        db_delete_user($username);
        db_delete_user_sessions($username);
        header("Location: ?action=index&_result=" . urlencode("Usuario \"$username\" eliminado correctamente."));
        exit;

    case 'save_edit':
        $username = safe_username($_POST['username'] ?? '');
        if (empty($username)) {
            die("Nombre de usuario no proporcionado.");
        }
        $permissions = $_POST['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }
        $aulas = $_POST['aulas'] ?? [];
        if (!is_array($aulas)) {
            $aulas = [];
        }
        $aulas = array_values(array_filter(array_map('safe_aulario_id', $aulas)));

        $organization_input = $_POST['organization'] ?? [];
        if (!is_array($organization_input)) {
          $organization_input = [$organization_input];
        }
        $organizations = array_values(array_unique(array_filter(array_map('safe_organization_id', $organization_input))));

        db_upsert_user([
            'username'     => $username,
            'display_name' => $_POST['display_name'] ?? '',
            'email'        => $_POST['email'] ?? '',
            'permissions'  => $permissions,
            'orgs'         => $organizations,
          'role'         => $_POST['role'] ?? '',
          'aulas'        => $aulas,
        ]);
        header("Location: ?action=edit&user=" . urlencode($username) . "&_result=" . urlencode("Cambios guardados correctamente a las " . date("H:i:s") . " (hora servidor)."));
        exit;
        break;
}

switch ($_GET['action'] ?? '') {
    case 'add':
        require_once "_incl/pre-body.php";
        render_users_mobile_styles();
        $all_organizations = db_get_organizations();
?>
<form method="post" action="?form=save_edit" class="users-mobile-stack">
  <div class="card pad">
    <div>
      <h1 class="card-title">Agregar Nuevo Usuario</h1>
      <div class="mb-3">
        <label for="username" class="form-label">Nombre de usuario:</label>
        <input type="text" id="username" name="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="display_name" class="form-label">Nombre para mostrar:</label>
        <input type="text" id="display_name" name="display_name" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Correo electrónico:</label>
        <input type="email" id="email" name="email" class="form-control" required>
      </div>
      <b>Permisos:</b>
      <div class="accordion mt-3" id="permissionsAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSysadmin">Administración del sistema</button>
          </h2>
          <div id="collapseSysadmin" class="accordion-collapse collapse show" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="sysadmin:access" id="sysadmin-access">
                <label class="form-check-label" for="sysadmin-access">Acceso</label>
              </div>
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAulatek">AulaTek</button>
          </h2>
          <div id="collapseAulatek" class="accordion-collapse collapse" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="aulatek:access" id="aulatek-access">
                <label class="form-check-label" for="aulatek-access">Acceso</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="aulatek:docente" id="aulatek-docente">
                <label class="form-check-label" for="aulatek-docente">Docente</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="aulatek:proyectos:delete" id="aulatek-proyectos-delete">
                <label class="form-check-label" for="aulatek-proyectos-delete">Eliminar Proyectos</label>
              </div>
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSupercafe">SuperCafe</button>
          </h2>
          <div id="collapseSupercafe" class="accordion-collapse collapse" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="supercafe:access" id="supercafe-access">
                <label class="form-check-label" for="supercafe-access">Acceso</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="supercafe:edit" id="supercafe-edit">
                <label class="form-check-label" for="supercafe-edit">Editar comandas</label>
              </div>
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-3">Crear Usuario</button>
    </div>
  </div>
  <div class="card pad">
    <div>
      <h2>AulaTek: Configuración</h2>
      <div class="mb-3">
        <label class="form-label">Tenant asociado:</label>
        <div class="tenant-list">
          <?php foreach ($all_organizations as $orgRow): $cid = $orgRow['org_id']; ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="organization[]" value="<?= htmlspecialchars($cid) ?>" id="tenant-<?= htmlspecialchars($cid) ?>">
              <label class="form-check-label" for="tenant-<?= htmlspecialchars($cid) ?>"><?= htmlspecialchars($cid) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <small class="text-muted">Marca uno o varios tenants.</small>
      </div>
      <div class="mb-3">
        <label for="role" class="form-label">Rol en AulaTek:</label>
        <select id="role" name="role" class="form-select">
          <option value="">-- Selecciona un rol --</option>
          <option value="teacher">Profesor</option>
          <option value="student">Estudiante</option>
        </select>
      </div>
      <p class="text-muted"><small>Las aulas podrán asignarse tras guardar el usuario.</small></p>
    </div>
  </div>
</form>
<?php
        require_once "_incl/post-body.php";
        break;

    case 'edit':
        require_once "_incl/pre-body.php";
      render_users_mobile_styles();
        $username = safe_username($_GET['user'] ?? '');
        if (empty($username)) {
            die("Nombre de usuario inválido.");
        }
        $row = db_get_user($username);
        if (!$row) {
            die("Usuario no encontrado.");
        }
        $userdata    = db_build_auth_data($row);
        $all_organizations = db_get_organizations();
        $user_organizations = $userdata['orgs'] ?? [];
        if (!is_array($user_organizations)) {
          $user_organizations = [];
        }
        $user_organizations = array_values(array_unique(array_filter(array_map('safe_organization_id', $user_organizations))));
        if (empty($user_organizations)) {
          $legacy_organization = safe_organization_id($userdata['orgs'] ?? '');
          if ($legacy_organization !== '') {
            $user_organizations = [$legacy_organization];
          }
        }

        $aularios_by_organization = [];
        foreach ($user_organizations as $org_id) {
          $aularios_by_organization[$org_id] = db_get_aularios($org_id);
        }
        $assigned_aulas = $userdata['aulatek']['aulas'] ?? ($userdata['entreaulas']['aulas'] ?? []);
?>
<form method="post" action="?form=save_edit" class="users-mobile-stack">
  <div class="card pad">
    <div>
      <h1>Editar Usuario: <?= htmlspecialchars($username) ?></h1>
      <div class="mb-3">
        <label for="display_name" class="form-label">Nombre para mostrar:</label>
        <input type="text" id="display_name" name="display_name" value="<?= htmlspecialchars($userdata['display_name'] ?? '') ?>" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Correo electrónico:</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($userdata['email'] ?? '') ?>" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Organizaciones asociadas:</label>
        <div class="organization-list">
          <?php foreach ($all_organizations as $orgRow): $org_id = $orgRow['org_id']; $org_name = $orgRow['org_name']; ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="organization[]" value="<?= htmlspecialchars($org_id) ?>" id="organization-<?= htmlspecialchars($org_id) ?>" <?= in_array($org_id, $user_organizations, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="organization-<?= htmlspecialchars($org_id) ?>"><?= htmlspecialchars($org_name) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <small class="text-muted">Marca una o varias organizaciones.</small>
      </div>
      <b>Permisos:</b>
      <div class="accordion mt-3" id="permissionsAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSysadmin">Administración del sistema</button>
          </h2>
          <div id="collapseSysadmin" class="accordion-collapse collapse show" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="sysadmin:access" id="sysadmin-access" <?= in_array('sysadmin:access', $userdata['permissions'] ?? []) ? 'checked' : '' ?>>
                <label class="form-check-label" for="sysadmin-access">Acceso</label>
              </div>
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAulatek">AulaTek</button>
          </h2>
          <div id="collapseAulatek" class="accordion-collapse collapse" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="aulatek:access" id="aulatek-access" <?= in_array('aulatek:access', $userdata['permissions'] ?? []) ? 'checked' : '' ?>>
                <label class="form-check-label" for="aulatek-access">Acceso</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="aulatek:docente" id="aulatek-docente" <?= in_array('aulatek:docente', $userdata['permissions'] ?? []) ? 'checked' : '' ?>>
                <label class="form-check-label" for="aulatek-docente">Docente</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="aulatek:proyectos:delete" id="aulatek-proyectos-delete" <?= in_array('aulatek:proyectos:delete', $userdata['permissions'] ?? []) ? 'checked' : '' ?>>
                <label class="form-check-label" for="aulatek-proyectos-delete">Eliminar Proyectos</label>
              </div>
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSupercafe">SuperCafe</button>
          </h2>
          <div id="collapseSupercafe" class="accordion-collapse collapse" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="supercafe:access" id="supercafe-access" <?= in_array('supercafe:access', $userdata['permissions'] ?? []) ? 'checked' : '' ?>>
                <label class="form-check-label" for="supercafe-access">Acceso</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="supercafe:edit" id="supercafe-edit" <?= in_array('supercafe:edit', $userdata['permissions'] ?? []) ? 'checked' : '' ?>>
                <label class="form-check-label" for="supercafe-edit">Editar comandas</label>
              </div>
            </div>
          </div>
        </div>
      </div>
      <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
      <button type="submit" class="btn btn-primary mt-3">Guardar Cambios</button>
    </div>
  </div>
  <div class="card pad">
    <div>
      <h2>AulaTek: Configuración</h2>
      <div class="mb-3">
        <label for="role" class="form-label">Rol en AulaTek:</label>
        <select id="role" name="role" class="form-select" required>
          <option value="" <?= empty($userdata['aulatek']['role'] ?? '') ? 'selected' : '' ?>>-- Selecciona un rol --</option>
          <option value="teacher" <?= ($userdata['aulatek']['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>Profesor</option>
          <option value="student" <?= ($userdata['aulatek']['role'] ?? '') === 'student' ? 'selected' : '' ?>>Estudiante</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Aulas asignadas: <small>(Guarda primero para actualizar la lista)</small></label><br>
        <div class="aulas-list">
          <?php if (empty($aularios_by_organization)): ?>
            <small class="text-muted">No hay organizaciones asociadas para mostrar aulas.</small>
          <?php endif; ?>
          <?php foreach ($aularios_by_organization as $org_id => $org_aularios): ?>
            <div style="margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e9ecef;">
              <div style="font-weight: 600; margin-bottom: 0.4rem;"><?= htmlspecialchars($org_id) ?></div>
              <?php if (empty($org_aularios)): ?>
                <small class="text-muted">Sin aulas en esta organización.</small>
              <?php else: ?>
                <?php foreach ($org_aularios as $aula_id => $aula_data): ?>
                  <?php $checkbox_id = 'aula-' . md5($org_id . '-' . $aula_id); ?>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="aulas[]"
                           value="<?= htmlspecialchars($aula_id) ?>"
                           id="<?= htmlspecialchars($checkbox_id) ?>"
                           <?= in_array($aula_id, $assigned_aulas, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= htmlspecialchars($checkbox_id) ?>">
                      <?= htmlspecialchars($aula_data['name'] ?? $aula_id) ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="card pad">
    <div>
      <h2>Cambiar contraseña</h2>
      <a href="/sysadmin/reset_password.php?user=<?= urlencode($username) ?>" class="btn btn-secondary">Restablecer Contraseña</a>
    </div>
  </div>
</form>
<form method="post" action="?form=delete_user" class="users-mobile-stack mt-2" onsubmit="return confirm('¿Seguro que quieres eliminar la cuenta de <?= htmlspecialchars($username, ENT_QUOTES) ?>? Esta acción no se puede deshacer.');">
  <div class="card pad border-danger">
    <div>
      <h2 class="text-danger">Zona de peligro</h2>
      <p>Eliminar la cuenta borrará permanentemente al usuario y todas sus sesiones activas.</p>
      <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
      <button type="submit" class="btn btn-danger">Eliminar cuenta</button>
    </div>
  </div>
</form>
<?php
        require_once "_incl/post-body.php";
        break;

    case 'index':
    default:
        require_once "_incl/pre-body.php";
        render_users_mobile_styles();
        $all_users = db_get_all_users();
?>
<div class="card pad users-mobile-stack">
  <div>
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2 mb-2">
      <h1 class="mb-0">Gestión de Usuarios</h1>
      <a href="?action=add" class="btn btn-success">+ Nuevo</a>
    </div>
    <p>Desde esta sección puedes gestionar los usuarios del sistema.</p>
    <div class="d-none d-md-block table-responsive">
      <table class="table table-striped table-hover">
        <thead class="table-dark">
          <tr>
            <th>Usuario</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all_users as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td><?= htmlspecialchars($u['display_name'] ?: 'N/A') ?></td>
              <td><?= htmlspecialchars($u['email'] ?: 'N/A') ?></td>
              <td>
                <a href="?action=edit&user=<?= urlencode($u['username']) ?>" class="btn btn-primary btn-sm">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="d-md-none">
      <?php foreach ($all_users as $u): ?>
        <div class="border rounded p-3 mb-2 bg-white">
          <div><strong><?= htmlspecialchars($u['display_name'] ?: 'N/A') ?></strong></div>
          <div class="text-muted small"><?= htmlspecialchars($u['username']) ?></div>
          <div class="small"><?= htmlspecialchars($u['email'] ?: 'N/A') ?></div>
          <a href="?action=edit&user=<?= urlencode($u['username']) ?>" class="btn btn-primary btn-sm mt-2">Editar</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php
        require_once "_incl/post-body.php";
        break;
}
