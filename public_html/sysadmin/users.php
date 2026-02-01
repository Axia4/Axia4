<?php
require_once "_incl/auth_redir.php";

switch ($_GET['form'] ?? '') {
  case 'save_edit':
    $username = $_POST['username'] ?? '';
    if (empty($username)) {
      die("Nombre de usuario no proporcionado.");
    }
    $userdata_old = json_decode(file_get_contents("/DATA/Usuarios/$username.json"), true) ?? [];
    $userdata_new = [
      'display_name' => $_POST['display_name'] ?? '',
      'email' => $_POST['email'] ?? '',
      'permissions' => $_POST['permissions'] ?? [],
      'entreaulas' => [
        'centro' => $_POST['centro'] ?? '',
        'role' => $_POST['role'] ?? '',
        'aulas' => $_POST['aulas'] ?? []
      ]
    ];
    // Merge old and new data to preserve any other fields, like password hashes or custom metadata.
    $userdata = array_merge($userdata_old, $userdata_new);
    file_put_contents("/DATA/Usuarios/$username.json", json_encode($userdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header("Location: ?action=edit&user=" . urlencode($username) . "&_result=" . urlencode("Cambios guardados correctamente a las ".date("H:i:s")." (hora servidor)."));
    exit;
    break;
}

switch ($_GET['action'] ?? '') {
  case 'add':
    require_once "_incl/pre-body.php";
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
          <h2 class="accordion-header" id="headingSysadmin">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSysadmin" aria-expanded="true" aria-controls="collapseSysadmin">
              Administración del sistema
            </button>
          </h2>
          <div id="collapseSysadmin" class="accordion-collapse collapse show" aria-labelledby="headingSysadmin" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="sysadmin:access" id="sysadmin-access">
                <label class="form-check-label" for="sysadmin-access">
                  Acceso
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingEntreaulas">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEntreaulas" aria-expanded="false" aria-controls="collapseEntreaulas">
              EntreAulas
            </button>
          </h2>
          <div id="collapseEntreaulas" class="accordion-collapse collapse" aria-labelledby="headingEntreaulas" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:access" id="entreaulas-access">
                <label class="form-check-label" for="entreaulas-access">
                  Acceso
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:docente" id="entreaulas-docente">
                <label class="form-check-label" for="entreaulas-docente">
                  Docente
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:proyectos:delete" id="entreaulas-proyectos-delete">
                <label class="form-check-label" for="entreaulas-proyectos-delete">
                  Eliminar Proyectos
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary mt-3">Crear Usuario</button>
    </div>
  </div>
</form>
<?php
    require_once "_incl/post-body.php";
    break;
  case 'edit':
    require_once "_incl/pre-body.php";
    $username = $_GET['user'] ?? '';
    $userdata = json_decode(file_get_contents("/DATA/Usuarios/$username.json"), true);
?>
<form method="post" action="?form=save_edit">
  <div class="card pad">
    <div>
      <h1>Editar Usuario: <?php echo htmlspecialchars($username); ?></h1>
      <div class="mb-3">
        <label for="display_name" class="form-label">Nombre para mostrar:</label>
        <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($userdata['display_name'] ?? ''); ?>" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Correo electrónico:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userdata['email'] ?? ''); ?>" class="form-control" required>
      </div>
      <b>Permisos:</b>
      <div class="accordion mt-3" id="permissionsAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingSysadmin">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSysadmin" aria-expanded="true" aria-controls="collapseSysadmin">
              Administración del sistema
            </button>
          </h2>
          <div id="collapseSysadmin" class="accordion-collapse collapse show" aria-labelledby="headingSysadmin" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="sysadmin:access" id="sysadmin-access" <?php if (in_array('sysadmin:access', $userdata['permissions'] ?? [])) echo 'checked'; ?>>
                <label class="form-check-label" for="sysadmin-access">
                  Acceso
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingEntreaulas">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEntreaulas" aria-expanded="false" aria-controls="collapseEntreaulas">
              EntreAulas
            </button>
          </h2>
          <div id="collapseEntreaulas" class="accordion-collapse collapse" aria-labelledby="headingEntreaulas" data-bs-parent="#permissionsAccordion">
            <div class="accordion-body">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:access" id="entreaulas-access" <?php if (in_array('entreaulas:access', $userdata['permissions'] ?? [])) echo 'checked'; ?>>
                <label class="form-check-label" for="entreaulas-access">
                  Acceso
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:docente" id="entreaulas-docente" <?php if (in_array('entreaulas:docente', $userdata['permissions'] ?? [])) echo 'checked'; ?>>
                <label class="form-check-label" for="entreaulas-docente">
                  Docente
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="entreaulas:proyectos:delete" id="entreaulas-proyectos-delete" <?php if (in_array('entreaulas:proyectos:delete', $userdata['permissions'] ?? [])) echo 'checked'; ?>>
                <label class="form-check-label" for="entreaulas-proyectos-delete">
                  Eliminar Proyectos
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
      <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
      <button type="submit" class="btn btn-primary mt-3">Guardar Cambios</button>
    </div>
  </div>
  <div class="card pad">
    <div>
      <h2>EntreAulas: Configuración</h2>
      <div class="mb-3">
        <label for="centro" class="form-label">Centro asociado:</label>
        <select id="centro" name="centro" class="form-select" required>
          <option value="" <?php if (empty($userdata["entreaulas"]['centro'] ?? '')) echo 'selected'; ?>>-- Selecciona un centro --</option>
          <?php
          $centros_folders = glob("/DATA/entreaulas/Centros/*", GLOB_ONLYDIR);
          foreach ($centros_folders as $centro_folder) {
            $centro_id = basename($centro_folder);
            echo '<option value="' . htmlspecialchars($centro_id) . '"';
            if (($userdata["entreaulas"]['centro'] ?? '') === $centro_id) {
              echo ' selected';
            }
            echo '>' . htmlspecialchars($centro_id) . '</option>';
          }
          ?>
        </select>
      </div>
      <div class="mb-3">
        <label for="role" class="form-label">Rol en EntreAulas:</label>
        <select id="role" name="role" class="form-select" required>
          <option value="" <?php if (empty($userdata["entreaulas"]['role'] ?? '')) echo 'selected'; ?>>-- Selecciona un rol --</option>
          <option value="teacher" <?php if (($userdata["entreaulas"]['role'] ?? '') === 'teacher') echo 'selected'; ?>>Profesor</option>
          <option value="student" <?php if (($userdata["entreaulas"]['role'] ?? '') === 'student') echo 'selected'; ?>>Estudiante</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Aulas asignadas: <small>(Guarda primero para actualizar la lista)</small></label><br>
        <?php
        $aulas_filelist = glob("/DATA/entreaulas/Centros/" . ($userdata["entreaulas"]['centro'] ?? '') . "/Aularios/*.json");
        foreach ($aulas_filelist as $aula_file) {
          $aula_data = json_decode(file_get_contents($aula_file), true);
          $aula_id = basename($aula_file, ".json");
          $is_assigned = in_array($aula_id, $userdata["entreaulas"]['aulas'] ?? []);
          echo '<div class="form-check form-check-inline">';
          echo '<input class="form-check-input" type="checkbox" name="aulas[]" value="' . htmlspecialchars($aula_id) . '" id="aula-' . htmlspecialchars($aula_id) . '" ' . ($is_assigned ? 'checked' : '') . '>';
          echo '<label class="form-check-label" for="aula-' . htmlspecialchars($aula_id) . '">' . htmlspecialchars($aula_data['name'] ?? $aula_id) . '</label>';
          echo '</div>';
        }
        ?>
      </div>
    </div>
  </div>
  <div class="card pad">
    <div>
      <h2>Cambiar contraseña</h2>
      <p>Para cambiar la contraseña de este usuario, utiliza la herramienta de restablecimiento de contraseñas disponible en el siguiente enlace:</p>
      <a href="/sysadmin/reset_password.php?user=<?php echo urlencode($username); ?>" class="btn btn-secondary">Restablecer Contraseña</a>
    </div>
  </div>
</form>
  <?php
    require_once "_incl/post-body.php";
    break;
  case "index":
  default:
    require_once "_incl/pre-body.php";
  ?>
    <div class="card pad">
      <div>
        <h1>Gestión de Usuarios</h1>
        <p>Desde esta sección puedes gestionar los usuarios del sistema. Puedes agregar, editar o eliminar usuarios según sea necesario.</p>
        <table class="table table-striped table-hover">
          <thead class="table-dark">
            <tr>
              <th>Usuario</th>
              <th>Nombre</th>
              <th>Correo</th>
              <th>
                <a href="?action=add" class="btn btn-success">+ Nuevo</a>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php
            $users_filelist = glob("/DATA/Usuarios/*.json");
            foreach ($users_filelist as $user_file) {
              $userdata = json_decode(file_get_contents($user_file), true);
              // Username is the filename without path and extension
              $username = basename($user_file, ".json");
              echo "<tr>";
              echo "<td>" . htmlspecialchars($username) . "</td>";
              echo "<td>" . htmlspecialchars($userdata['display_name'] ?? 'N/A') . "</td>";
              echo "<td>" . htmlspecialchars($userdata['email'] ?? 'N/A') . "</td>";
              echo "<td>";
              echo '<a href="?action=edit&user=' . urlencode($username) . '" class="btn btn-primary">Editar</a> ';
              echo '<a href="?action=delete&user=' . urlencode($username) . '" class="btn btn-danger">Eliminar</a>';
              echo "</td>";
              echo "</tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
<?php
    require_once "_incl/post-body.php";
    break;
}
?>