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
    $pageTitle = "Agregar Usuario";
    break;
  case 'edit':
    require_once "_incl/pre-body.php";
    $username = $_GET['user'] ?? '';
    $userdata = json_decode(file_get_contents("/DATA/Usuarios/$username.json"), true);
?>
<form method="post" action="?form=save_edit">
  <div class="card pad">
    <h1>Editar Usuario: <?php echo htmlspecialchars($username); ?></h1>
    <label>
      Nombre para mostrar:<br>
      <input type="text" name="display_name" value="<?php echo htmlspecialchars($userdata['display_name'] ?? ''); ?>" required>
    </label><br><br>
    <label>
      Correo electrónico:<br>
      <input type="email" name="email" value="<?php echo htmlspecialchars($userdata['email'] ?? ''); ?>" required>
    </label><br><br>
    <b>Permisos:</b>
    <details open>
      <summary>Administración del sistema</summary>
      <label style="padding: 5px; border: 1.5px solid #000; display: inline-block; margin-bottom: 5px; border-radius: 5px;">
        <input type="checkbox" name="permissions[]" value="sysadmin:access" <?php if (in_array('sysadmin:access', $userdata['permissions'] ?? [])) echo 'checked'; ?>>
        <span class="checkable">Acceso</span>
      </label>
    </details>
    <details open>
      <summary>EntreAulas</summary>
      <label style="padding: 5px; border: 1.5px solid #000; display: inline-block; margin-bottom: 5px; border-radius: 5px;">
        <input type="checkbox" name="permissions[]" value="entreaulas:access" <?php if (in_array('entreaulas:access', $userdata['permissions'] ?? [])) echo 'checked'; ?>>
        <span class="checkable">Acceso</span>
      </label>
    </details>
    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
    <button type="submit">Guardar Cambios</button>
  </div>
  <div class="card pad">
    <h2>EntreAulas: Configuración</h2>
    <label>
      Centro asociado:<br>
      <select name="centro" required>
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
    </label>
    <br><br>
    <label>
      Rol en EntreAulas:<br>
      <select name="role" required>
        <option value="" <?php if (empty($userdata["entreaulas"]['role'] ?? '')) echo 'selected'; ?>>-- Selecciona un rol --</option>
        <option value="teacher" <?php if (($userdata["entreaulas"]['role'] ?? '') === 'teacher') echo 'selected'; ?>>Profesor</option>
        <option value="student" <?php if (($userdata["entreaulas"]['role'] ?? '') === 'student') echo 'selected'; ?>>Estudiante</option>
      </select>
    </label>
    <br><br>
    <span>Aulas asignadas: <small>(Guarda primero para actualizar la lista)</small></span><br>
    <?php
    $aulas_filelist = glob("/DATA/entreaulas/Centros/" . ($userdata["entreaulas"]['centro'] ?? '') . "/Aularios/*.json");
    foreach ($aulas_filelist as $aula_file) {
      $aula_data = json_decode(file_get_contents($aula_file), true);
      $aula_id = basename($aula_file, ".json");
      $is_assigned = in_array($aula_id, $userdata["entreaulas"]['aulas'] ?? []);
      echo '<label style="padding: 5px; border: 1.5px solid #000; display: inline-block; margin-bottom: 5px; border-radius: 5px;">';
      echo '<input type="checkbox" name="aulas[]" value="' . htmlspecialchars($aula_id) . '" ' . ($is_assigned ? 'checked' : '') . '>';
      echo '<span class="checkable">' . htmlspecialchars($aula_data['name'] ?? $aula_id) . '</span>';
      echo '</label> ';
    }
    ?>
  </div>
  <div class="card pad">
    <h2>Cambiar contraseña</h2>
    <p>Para cambiar la contraseña de este usuario, utiliza la herramienta de restablecimiento de contraseñas disponible en el siguiente enlace:</p>
    <a href="/sysadmin/reset_password.php?user=<?php echo urlencode($username); ?>" class="button">Restablecer Contraseña</a>
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
      <h1>Gestión de Usuarios</h1>
      <p>Desde esta sección puedes gestionar los usuarios del sistema. Puedes agregar, editar o eliminar usuarios según sea necesario.</p>
      <a href="?action=add" class="button">Agregar Nuevo Usuario</a>
      <table>
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Acciones</th>
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
            echo '<a href="?action=edit&user=' . urlencode($username) . '" class="button">Editar</a> ';
            echo '<a href="?action=delete&user=' . urlencode($username) . '" class="button danger">Eliminar</a>';
            echo "</td>";
            echo "</tr>";
          }
          ?>
        </tbody>
      </table>
    </div>
<?php
    require_once "_incl/post-body.php";
    break;
}
?>