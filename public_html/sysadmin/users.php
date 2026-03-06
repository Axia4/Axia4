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

switch ($_GET['form'] ?? '') {
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

        db_upsert_user([
            'username'     => $username,
            'display_name' => $_POST['display_name'] ?? '',
            'email'        => $_POST['email'] ?? '',
            'permissions'  => $permissions,
            'entreaulas'   => [
                'centro' => safe_centro_id($_POST['centro'] ?? ''),
                'role'   => $_POST['role'] ?? '',
                'aulas'  => $aulas,
            ],
        ]);
        header("Location: ?action=edit&user=" . urlencode($username) . "&_result=" . urlencode("Cambios guardados correctamente a las " . date("H:i:s") . " (hora servidor)."));
        exit;
        break;
}

switch ($_GET['action'] ?? '') {
    case 'add':
        require_once "_incl/pre-body.php";
        $all_centros = db_get_centro_ids();
?>
<form method="post" action="?form=save_edit">
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
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEntreaulas">EntreAulas</button>
          </h2>
          <div id="collapseEntreaulas" class="accordion-collapse collapse" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:access" id="entreaulas-access">
                <label class="form-check-label" for="entreaulas-access">Acceso</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:docente" id="entreaulas-docente">
                <label class="form-check-label" for="entreaulas-docente">Docente</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:proyectos:delete" id="entreaulas-proyectos-delete">
                <label class="form-check-label" for="entreaulas-proyectos-delete">Eliminar Proyectos</label>
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
      <h2>EntreAulas: Configuración</h2>
      <div class="mb-3">
        <label for="centro" class="form-label">Centro asociado:</label>
        <select id="centro" name="centro" class="form-select">
          <option value="">-- Selecciona un centro --</option>
          <?php foreach ($all_centros as $cid): ?>
            <option value="<?= htmlspecialchars($cid) ?>"><?= htmlspecialchars($cid) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label for="role" class="form-label">Rol en EntreAulas:</label>
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
        $username = safe_username($_GET['user'] ?? '');
        if (empty($username)) {
            die("Nombre de usuario inválido.");
        }
        $row = db_get_user($username);
        if (!$row) {
            die("Usuario no encontrado.");
        }
        $userdata    = db_build_auth_data($row);
        $all_centros = db_get_centro_ids();
        $user_centro = safe_centro_id($userdata['entreaulas']['centro'] ?? '');
        $aularios    = $user_centro !== '' ? db_get_aularios($user_centro) : [];
?>
<form method="post" action="?form=save_edit">
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
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEntreaulas">EntreAulas</button>
          </h2>
          <div id="collapseEntreaulas" class="accordion-collapse collapse" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:access" id="entreaulas-access" <?= in_array('entreaulas:access', $userdata['permissions'] ?? []) ? 'checked' : '' ?>>
                <label class="form-check-label" for="entreaulas-access">Acceso</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:docente" id="entreaulas-docente" <?= in_array('entreaulas:docente', $userdata['permissions'] ?? []) ? 'checked' : '' ?>>
                <label class="form-check-label" for="entreaulas-docente">Docente</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:proyectos:delete" id="entreaulas-proyectos-delete" <?= in_array('entreaulas:proyectos:delete', $userdata['permissions'] ?? []) ? 'checked' : '' ?>>
                <label class="form-check-label" for="entreaulas-proyectos-delete">Eliminar Proyectos</label>
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
      <h2>EntreAulas: Configuración</h2>
      <div class="mb-3">
        <label for="centro" class="form-label">Centro asociado:</label>
        <select id="centro" name="centro" class="form-select" required>
          <option value="" <?= empty($user_centro) ? 'selected' : '' ?>>-- Selecciona un centro --</option>
          <?php foreach ($all_centros as $cid): ?>
            <option value="<?= htmlspecialchars($cid) ?>" <?= $user_centro === $cid ? 'selected' : '' ?>><?= htmlspecialchars($cid) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label for="role" class="form-label">Rol en EntreAulas:</label>
        <select id="role" name="role" class="form-select" required>
          <option value="" <?= empty($userdata['entreaulas']['role'] ?? '') ? 'selected' : '' ?>>-- Selecciona un rol --</option>
          <option value="teacher" <?= ($userdata['entreaulas']['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>Profesor</option>
          <option value="student" <?= ($userdata['entreaulas']['role'] ?? '') === 'student' ? 'selected' : '' ?>>Estudiante</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Aulas asignadas: <small>(Guarda primero para actualizar la lista)</small></label><br>
        <?php foreach ($aularios as $aula_id => $aula_data): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="aulas[]"
                   value="<?= htmlspecialchars($aula_id) ?>"
                   id="aula-<?= htmlspecialchars($aula_id) ?>"
                   <?= in_array($aula_id, $userdata['entreaulas']['aulas'] ?? []) ? 'checked' : '' ?>>
            <label class="form-check-label" for="aula-<?= htmlspecialchars($aula_id) ?>">
              <?= htmlspecialchars($aula_data['name'] ?? $aula_id) ?>
            </label>
          </div>
        <?php endforeach; ?>
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
<?php
        require_once "_incl/post-body.php";
        break;

    case 'index':
    default:
        require_once "_incl/pre-body.php";
        $all_users = db_get_all_users();
?>
<div class="card pad">
  <div>
    <h1>Gestión de Usuarios</h1>
    <p>Desde esta sección puedes gestionar los usuarios del sistema.</p>
    <table class="table table-striped table-hover">
      <thead class="table-dark">
        <tr>
          <th>Usuario</th>
          <th>Nombre</th>
          <th>Correo</th>
          <th><a href="?action=add" class="btn btn-success">+ Nuevo</a></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($all_users as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['display_name'] ?: 'N/A') ?></td>
            <td><?= htmlspecialchars($u['email'] ?: 'N/A') ?></td>
            <td>
              <a href="?action=edit&user=<?= urlencode($u['username']) ?>" class="btn btn-primary">Editar</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
        require_once "_incl/post-body.php";
        break;
}
