<?php
require_once "_incl/auth_redir.php";

switch ($_GET['form'] ?? '') {
  case 'save_password':
    $username = $_POST['username'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username)) {
      die("Nombre de usuario no proporcionado.");
    }

    if (empty($new_password)) {
      die("La contraseña no puede estar vacía.");
    }

    if ($new_password !== $confirm_password) {
      die("Las contraseñas no coinciden.");
    }

    if (strlen($new_password) < 6) {
      die("La contraseña debe tener al menos 6 caracteres.");
    }

    $userfile = "/DATA/Usuarios/$username.json";
    if (!file_exists($userfile)) {
      die("Usuario no encontrado.");
    }

    $userdata = json_decode(file_get_contents($userfile), true);
    $userdata['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
    
    file_put_contents($userfile, json_encode($userdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    header("Location: users.php?action=edit&user=" . urlencode($username) . "&_result=" . urlencode("Contraseña restablecida correctamente a las " . date("H:i:s") . " (hora servidor)."));
    exit;
    break;
}

require_once "_incl/pre-body.php";

$username = $_GET['user'] ?? '';
if (empty($username)) {
  die("Usuario no especificado.");
}

$userfile = "/DATA/Usuarios/$username.json";
if (!file_exists($userfile)) {
  die("Usuario no encontrado.");
}

$userdata = json_decode(file_get_contents($userfile), true);
?>

<form method="post" action="?form=save_password">
  <div class="card pad">
    <div>
      <h1>Restablecer Contraseña: <?php echo htmlspecialchars($username); ?></h1>
      
      <div class="mb-3">
        <label for="new_password" class="form-label">Nueva Contraseña:</label>
        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
        <small class="form-text text-muted">Mínimo 6 caracteres</small>
      </div>
      
      <div class="mb-3">
        <label for="confirm_password" class="form-label">Confirmar Contraseña:</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
      </div>
      
      <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
      
      <button type="submit" class="btn btn-primary">Restablecer Contraseña</button>
      <a href="users.php?action=edit&user=<?php echo urlencode($username); ?>" class="btn btn-secondary">Cancelar</a>
    </div>
  </div>
</form>

<?php
require_once "_incl/post-body.php";
?>
