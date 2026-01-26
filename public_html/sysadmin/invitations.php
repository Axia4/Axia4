<?php
require_once "_incl/auth_redir.php";

switch ($_GET['form']) {
    case "create":
        // Handle creation logic here
        $invitations = json_decode(file_get_contents("/DATA/Invitaciones_de_usuarios.json"), true) ?? [];
        $invitation_code = strtoupper($_POST['invitation_code'] ?? '');
        $single_use = isset($_POST['single_use']) ? true : false;
        if (isset($invitations[$invitation_code])) {
            header("Location: /sysadmin/invitations.php?action=new&_resultcolor=red&_result=" . urlencode("El código de invitación ya existe."));
            exit;
        }
        $invitations[$invitation_code] = [
            "active" => true,
            "single_use" => $single_use
        ];
        file_put_contents("/DATA/Invitaciones_de_usuarios.json", json_encode($invitations, JSON_PRETTY_PRINT));
        header("Location: /sysadmin/invitations.php?_result=" . urlencode("Código $invitation_code creado correctamente."));
        exit;
        break;
    case "delete":
        // Handle deletion logic here
        $invitations = json_decode(file_get_contents("/DATA/Invitaciones_de_usuarios.json"), true) ?? [];
        $invitation_code = strtoupper($_POST['invitation_code'] ?? '');
        if (isset($invitations[$invitation_code])) {
            unset($invitations[$invitation_code]);
            file_put_contents("/DATA/Invitaciones_de_usuarios.json", json_encode($invitations, JSON_PRETTY_PRINT));
        }
        header("Location: /sysadmin/invitations.php?_result=" . urlencode("Codigo $invitation_code borrado"));
        exit;
        break;
}

require_once "_incl/pre-body.php";
switch ($_GET['action']) {
    case "new":
        ?>
        <div class="card pad">
            <h1>Nueva invitación de usuario</h1>
            <form method="post" action="?form=create">
                <fieldset class="card pad" style="border: 2px solid black; border-radius: 6.5px; max-width: 500px;">
                    <label>
                        <b>Código de invitación:</b>
                        <input type="text" name="invitation_code" required />
                        <small>Formato: 123456-ABCDEF</small>
                    </label>
                    <label>
                        <input type="checkbox" name="single_use" />
                        <span class="checkable">Uso único</span>
                    </label>
                    <button type="submit">Crear invitación</button>
                    <br><br>
                </fieldset>
            </form>
        </div>
        <?php
        break;
    default:
    case "index":
        ?>
        <div class="card pad">
            <h1>Invitaciones de usuarios</h1>
            <span>Desde aquí puedes gestionar las invitaciones de usuarios.</span>
            <table>
                <thead>
                    <th>Codigo de invitación</th>
                    <th>
                        <a href="?action=new" class="button pseudo" style="background: white; color: black;">+ Nuevo</a>
                    </th>
                </thead>
                <tbody>
                    <?php
                    $invitations = json_decode(file_get_contents("/DATA/Invitaciones_de_usuarios.json"), true);
                    foreach ($invitations as $inv_key => $inv_data) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($inv_key) . "</td>";
                        echo "<td>";
                        echo '<form method="post" action="?form=delete" style="display:inline;">';
                        echo '<input type="hidden" name="invitation_code" value="' . htmlspecialchars($inv_key) . '"/>';
                        echo '<button type="submit" class="button danger" onclick="return confirm(\'¿Estás seguro de que deseas eliminar esta invitación?\');">Eliminar</button>';
                        echo '</form>';
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
<?php
        break;
}
require_once "_incl/post-body.php";