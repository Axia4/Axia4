<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/db.php";

switch ($_GET['form'] ?? '') {
    case "create":
        $code       = strtoupper(trim($_POST['invitation_code'] ?? ''));
        $single_use = isset($_POST['single_use']);
        if (empty($code)) {
            header("Location: /sysadmin/invitations.php?action=new&_resultcolor=red&_result=" . urlencode("Código de invitación vacío."));
            exit;
        }
        if (db_get_invitation($code)) {
            header("Location: /sysadmin/invitations.php?action=new&_resultcolor=red&_result=" . urlencode("El código de invitación ya existe."));
            exit;
        }
        db_upsert_invitation($code, true, $single_use);
        header("Location: /sysadmin/invitations.php?_result=" . urlencode("Código $code creado correctamente."));
        exit;
        break;
    case "delete":
        $code = strtoupper(trim($_POST['invitation_code'] ?? ''));
        db_delete_invitation($code);
        header("Location: /sysadmin/invitations.php?_result=" . urlencode("Código $code borrado."));
        exit;
        break;
}

require_once "_incl/pre-body.php";
switch ($_GET['action'] ?? 'index') {
    case "new":
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Nueva invitación de usuario</h1>
        <form method="post" action="?form=create">
            <div class="card pad" style="max-width: 500px;">
                <div>
                    <div class="mb-3">
                        <label for="invitation_code" class="form-label"><b>Código de invitación:</b></label>
                        <input type="text" id="invitation_code" name="invitation_code" class="form-control" required />
                        <small>Formato: 123456-ABCDEF</small>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="single_use" id="single_use">
                        <label class="form-check-label" for="single_use">Uso único</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Crear invitación</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
        break;
    default:
    case "index":
        $invitations = db_get_all_invitations();
?>
<div class="card pad">
    <div>
        <h1>Invitaciones de usuarios</h1>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <th>Código</th>
                <th>Activo</th>
                <th>Uso único</th>
                <th><a href="?action=new" class="btn btn-success">+ Nuevo</a></th>
            </thead>
            <tbody>
                <?php foreach ($invitations as $inv): ?>
                <tr>
                    <td><?= htmlspecialchars($inv['code']) ?></td>
                    <td><?= $inv['active'] ? 'Sí' : 'No' ?></td>
                    <td><?= $inv['single_use'] ? 'Sí' : 'No' ?></td>
                    <td>
                        <form method="post" action="?form=delete" style="display:inline">
                            <input type="hidden" name="invitation_code" value="<?= htmlspecialchars($inv['code']) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Borrar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
        break;
}
require_once "_incl/post-body.php";
