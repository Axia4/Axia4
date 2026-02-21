<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/tools.security.php";

if (!in_array('supercafe:access', $_SESSION['auth_data']['permissions'] ?? [])) {
    header('HTTP/1.1 403 Forbidden');
    die('Acceso denegado');
}

function safe_centro_id_sc($value)
{
    return preg_replace('/[^0-9]/', '', (string)$value);
}

function sc_safe_order_id($value)
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', basename((string)$value));
}

$centro_id = safe_centro_id_sc($_SESSION['auth_data']['entreaulas']['centro'] ?? '');
if ($centro_id === '') {
    require_once "_incl/pre-body.php";
    echo '<div class="card pad"><h1>SuperCafe</h1><p>No tienes un centro asignado.</p></div>';
    require_once "_incl/post-body.php";
    exit;
}

define('SC_DATA_DIR', "/DATA/entreaulas/Centros/$centro_id/SuperCafe/Comandas");
define('SC_MAX_DEBTS', 3);

$estados_colores = [
    'Pedido'         => '#FFFFFF',
    'En preparación' => '#FFCCCB',
    'Listo'          => 'gold',
    'Entregado'      => 'lightgreen',
    'Deuda'          => '#f5d3ff',
];

$can_edit = in_array('supercafe:edit', $_SESSION['auth_data']['permissions'] ?? []);

// Handle POST actions (requires edit permission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_status') {
        $order_id  = sc_safe_order_id($_POST['order_id'] ?? '');
        $new_status = $_POST['status'] ?? '';
        if ($order_id !== '' && array_key_exists($new_status, $estados_colores)) {
            $order_file = SC_DATA_DIR . '/' . $order_id . '.json';
            if (is_readable($order_file)) {
                $data = json_decode(file_get_contents($order_file), true);
                if (is_array($data)) {
                    $data['Estado'] = $new_status;
                    file_put_contents(
                        $order_file,
                        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                        LOCK_EX
                    );
                }
            }
        }
        header('Location: /entreaulas/supercafe.php');
        exit;
    }

    if ($action === 'delete') {
        $order_id = sc_safe_order_id($_POST['order_id'] ?? '');
        if ($order_id !== '') {
            $order_file = SC_DATA_DIR . '/' . $order_id . '.json';
            if (is_file($order_file)) {
                unlink($order_file);
            }
        }
        header('Location: /entreaulas/supercafe.php');
        exit;
    }
}

// Load all orders
$orders = [];
if (is_dir(SC_DATA_DIR)) {
    $files = glob(SC_DATA_DIR . '/*.json') ?: [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            continue;
        }
        $data['_id'] = basename($file, '.json');
        $orders[] = $data;
    }
}

// Sort newest first (by Fecha desc)
usort($orders, function ($a, $b) {
    return strcmp($b['Fecha'] ?? '', $a['Fecha'] ?? '');
});

$orders_active = array_filter($orders, fn($o) => ($o['Estado'] ?? '') !== 'Deuda');
$orders_deuda  = array_filter($orders, fn($o) => ($o['Estado'] ?? '') === 'Deuda');

require_once "_incl/pre-body.php";
?>

<div class="card pad">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <h1 style="margin: 0;">SuperCafe – Cafetería</h1>
        <?php if ($can_edit): ?>
            <a href="/entreaulas/supercafe_edit.php" class="btn btn-success">+ Nueva comanda</a>
        <?php endif; ?>
    </div>
</div>

<!-- Active orders -->
<details class="card pad" open
    style="background: beige; border: 2px solid black; border-radius: 15px;">
    <summary style="font-weight: bold; font-size: 1.1rem; cursor: pointer;">
        Todas las comandas (<?= count($orders_active) ?>)
    </summary>
    <div style="margin-top: 10px;">
        <?php if (empty($orders_active)): ?>
            <p style="color: #666;">No hay comandas activas.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table table-bordered" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Persona</th>
                            <th>Comanda</th>
                            <th>Notas</th>
                            <th>Estado</th>
                            <?php if ($can_edit): ?><th>Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders_active as $order):
                            $estado = $order['Estado'] ?? 'Pedido';
                            $bg = $estados_colores[$estado] ?? '#FFFFFF';
                        ?>
                        <tr style="background: <?= htmlspecialchars($bg) ?>;">
                            <td><?= htmlspecialchars($order['Fecha'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['Persona'] ?? '') ?></td>
                            <td style="white-space: pre-wrap; max-width: 250px;"><?= htmlspecialchars($order['Comanda'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['Notas'] ?? '') ?></td>
                            <td><strong><?= htmlspecialchars($estado) ?></strong></td>
                            <?php if ($can_edit): ?>
                                <td style="white-space: nowrap;">
                                    <a href="/entreaulas/supercafe_edit.php?id=<?= urlencode($order['_id']) ?>"
                                       class="btn btn-sm btn-primary">Editar</a>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['_id']) ?>">
                                        <select name="status" class="form-select form-select-sm"
                                                style="display: inline; width: auto;"
                                                onchange="this.form.submit();">
                                            <?php foreach (array_keys($estados_colores) as $st): ?>
                                                <option value="<?= htmlspecialchars($st) ?>"
                                                    <?= $st === $estado ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($st) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <form method="post" style="display: inline;"
                                          onsubmit="return confirm('¿Borrar esta comanda?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['_id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Borrar</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</details>

<!-- Debts -->
<details class="card pad" open
    style="background: lightpink; border: 2px solid black; border-radius: 15px; margin-top: 10px;">
    <summary style="font-weight: bold; font-size: 1.1rem; cursor: pointer;">
        Deudas (<?= count($orders_deuda) ?>)
    </summary>
    <div style="margin-top: 10px;">
        <?php if (empty($orders_deuda)): ?>
            <p style="color: #666;">No hay comandas en deuda.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table table-bordered" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Persona</th>
                            <th>Comanda</th>
                            <th>Notas</th>
                            <?php if ($can_edit): ?><th>Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders_deuda as $order): ?>
                        <tr style="background: #f5d3ff;">
                            <td><?= htmlspecialchars($order['Fecha'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['Persona'] ?? '') ?></td>
                            <td style="white-space: pre-wrap; max-width: 250px;"><?= htmlspecialchars($order['Comanda'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['Notas'] ?? '') ?></td>
                            <?php if ($can_edit): ?>
                                <td style="white-space: nowrap;">
                                    <a href="/entreaulas/supercafe_edit.php?id=<?= urlencode($order['_id']) ?>"
                                       class="btn btn-sm btn-primary">Editar</a>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['_id']) ?>">
                                        <select name="status" class="form-select form-select-sm"
                                                style="display: inline; width: auto;"
                                                onchange="this.form.submit();">
                                            <?php foreach (array_keys($estados_colores) as $st): ?>
                                                <option value="<?= htmlspecialchars($st) ?>"
                                                    <?= $st === 'Deuda' ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($st) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <form method="post" style="display: inline;"
                                          onsubmit="return confirm('¿Borrar esta comanda?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['_id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Borrar</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</details>

<?php require_once "_incl/post-body.php"; ?>
