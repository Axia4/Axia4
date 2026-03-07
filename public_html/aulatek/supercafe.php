<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/tools.security.php";
require_once "../_incl/db.php";

if (!in_array('supercafe:access', $_SESSION['auth_data']['permissions'] ?? [])) {
    header('HTTP/1.1 403 Forbidden');
    die('Acceso denegado');
}

/**
 * Load personas from the Alumnos filesystem (photos still on disk).
 * Returns array keyed by "{aulario_id}:{alumno_name}".
 */
function sc_load_personas_from_alumnos($centro_id)
{
    $aularios     = db_get_aularios($centro_id);
    $personas     = [];
    $aularios_dir = aulatek_orgs_base_path() . "/$centro_id/Aularios";
    foreach ($aularios as $aulario_id => $aulario_data) {
        $aulario_name = $aulario_data['name'] ?? $aulario_id;
        $alumnos_path = "$aularios_dir/$aulario_id/Alumnos";
        if (!is_dir($alumnos_path)) {
            continue;
        }
        foreach (glob("$alumnos_path/*/", GLOB_ONLYDIR) ?: [] as $alumno_dir) {
            $alumno_name = basename($alumno_dir);
            $key         = $aulario_id . ':' . $alumno_name;
            $personas[$key] = [
                'Nombre'    => $alumno_name,
                'Region'    => $aulario_name,
                'AularioID' => $aulario_id,
                'HasPhoto'  => file_exists("$alumno_dir/photo.jpg"),
            ];
        }
    }
    return $personas;
}

/**
 * Return a human-readable label for a persona key.
 * Falls back to showing the raw stored value for legacy orders.
 */
function sc_persona_label($persona_key, $personas)
{
    if (isset($personas[$persona_key])) {
        $p = $personas[$persona_key];
        return $p['Nombre'] . ' (' . $p['Region'] . ')';
    }
    return $persona_key;
}

$tenant_data = $_SESSION['auth_data']['aulatek'] ?? ($_SESSION['auth_data']['entreaulas'] ?? []);
$centro_id = safe_organization_id($tenant_data['organizacion'] ?? ($tenant_data['centro'] ?? ''));
if ($centro_id === '') {
    require_once "_incl/pre-body.php";
    echo '<div class="card pad"><h1>SuperCafe</h1><p>No tienes una organizacion asignada.</p></div>';
    require_once "_incl/post-body.php";
    exit;
}

define('SC_MAX_DEBTS', 3);

$estados_colores = [
    'Pedido'         => '#FFFFFF',
    'En preparación' => '#FFCCCB',
    'Listo'          => 'gold',
    'Entregado'      => 'lightgreen',
    'Deuda'          => '#f5d3ff',
];

$can_edit = in_array('supercafe:edit', $_SESSION['auth_data']['permissions'] ?? []);
$personas = sc_load_personas_from_alumnos($centro_id);

// Handle POST actions (requires edit permission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_status') {
        $order_id   = safe_id($_POST['order_id'] ?? '');
        $new_status = $_POST['status'] ?? '';
        if ($order_id !== '' && array_key_exists($new_status, $estados_colores)) {
            $row = db_get_supercafe_order($centro_id, $order_id);
            if ($row) {
                db_upsert_supercafe_order(
                    $centro_id, $order_id,
                    $row['fecha'], $row['persona'], $row['comanda'], $row['notas'], $new_status
                );
            }
        }
        header('Location: /aulatek/supercafe.php');
        exit;
    }

    if ($action === 'delete') {
        $order_id = safe_id($_POST['order_id'] ?? '');
        if ($order_id !== '') {
                db()->prepare('DELETE FROM supercafe_orders WHERE org_id = ? AND order_ref = ?')
               ->execute([$centro_id, $order_id]);
        }
        header('Location: /aulatek/supercafe.php');
        exit;
    }
}

// Load all orders from DB
$db_orders = db_get_supercafe_orders($centro_id);
$orders = [];
foreach ($db_orders as $row) {
    $orders[] = [
        '_id'    => $row['order_ref'],
        'Fecha'  => $row['fecha'],
        'Persona'=> $row['persona'],
        'Comanda'=> $row['comanda'],
        'Notas'  => $row['notas'],
        'Estado' => $row['estado'],
    ];
}

// Sort newest first (by Fecha desc)
usort($orders, fn($a, $b) => strcmp($b['Fecha'] ?? '', $a['Fecha'] ?? ''));

$orders_active = array_filter($orders, fn($o) => ($o['Estado'] ?? '') !== 'Deuda');
$orders_deuda  = array_filter($orders, fn($o) => ($o['Estado'] ?? '') === 'Deuda');

require_once "_incl/pre-body.php";
?>


<div class="card pad">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <h1 style="margin: 0;">SuperCafe – Cafetería</h1>
        <?php if ($can_edit): ?>
            <a href="/aulatek/supercafe_edit.php" class="btn btn-success">+ Nueva comanda</a>
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
                            <td><?= htmlspecialchars(sc_persona_label($order['Persona'] ?? '', $personas)) ?></td>
                            <td style="white-space: pre-wrap; max-width: 250px;"><?= htmlspecialchars($order['Comanda'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['Notas'] ?? '') ?></td>
                            <td><strong><?= htmlspecialchars($estado) ?></strong></td>
                            <?php if ($can_edit): ?>
                                <td style="white-space: nowrap;">
                                    <a href="/aulatek/supercafe_edit.php?id=<?= urlencode($order['_id']) ?>"
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
                            <td><?= htmlspecialchars(sc_persona_label($order['Persona'] ?? '', $personas)) ?></td>
                            <td style="white-space: pre-wrap; max-width: 250px;"><?= htmlspecialchars($order['Comanda'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['Notas'] ?? '') ?></td>
                            <?php if ($can_edit): ?>
                                <td style="white-space: nowrap;">
                                    <a href="/aulatek/supercafe_edit.php?id=<?= urlencode($order['_id']) ?>"
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
