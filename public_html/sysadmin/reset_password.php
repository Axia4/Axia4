<?php
require_once "_incl/auth_redir.php";
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
    case 'save_password':
        $username         = safe_username($_POST['username'] ?? '');
        $new_password     = $_POST['new_password'] ?? '';
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
        $row = db_get_user($username);
        if (!$row) {
            die("Usuario no encontrado.");
        }
        db()->prepare("UPDATE users SET password_hash = ?, updated_at = datetime('now') WHERE id = ?")
            ->execute([password_hash($new_password, PASSWORD_DEFAULT), $row['id']]);

        header("Location: users.php?action=edit&user=" . urlencode($username) . "&_result=" . urlencode("Contraseña restablecida correctamente a las " . date("H:i:s") . " (hora servidor)."));
        exit;
        break;
}

require_once "_incl/pre-body.php";
$username = safe_username($_GET['user'] ?? '');
if (empty($username)) {
    die("Usuario no especificado.");
}
$row = db_get_user($username);
if (!$row) {
    die("Usuario no encontrado.");
}
?>
<form method="post" action="?form=save_password">
  <div class="card pad">
    <div>
      <h1>Restablecer Contraseña: <?= htmlspecialchars($username) ?></h1>
      <div class="mb-3">
        <label for="new_password" class="form-label">Nueva Contraseña:</label>
        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
        <small class="form-text text-muted">Mínimo 6 caracteres</small>
      </div>
      <div class="mb-3">
        <label for="confirm_password" class="form-label">Confirmar Contraseña:</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
      </div>
      <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
      <button type="submit" class="btn btn-primary">Restablecer Contraseña</button>
      <a href="users.php?action=edit&user=<?= urlencode($username) ?>" class="btn btn-secondary">Cancelar</a>
    </div>
  </div>
</form>
<?php
require_once "_incl/post-body.php";
