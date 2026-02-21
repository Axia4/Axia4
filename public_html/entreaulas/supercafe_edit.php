<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/tools.security.php";

if (!in_array('supercafe:edit', $_SESSION['auth_data']['permissions'] ?? [])) {
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

$sc_base = "/DATA/entreaulas/Centros/$centro_id/SuperCafe";
define('SC_DATA_DIR', "$sc_base/Comandas");
define('SC_MAX_DEBTS', 3);

$valid_statuses = ['Pedido', 'En preparación', 'Listo', 'Entregado', 'Deuda'];

/**
 * Load personas from the existing Alumnos system (alumnos.php).
 * Returns array keyed by "{aulario_id}:{alumno_name}" with
 * ['Nombre', 'Region' (aulario display name), 'AularioID'] entries.
 * Groups are sorted by aulario name, alumnos sorted alphabetically.
 */
function sc_load_personas_from_alumnos($centro_id)
{
    $aularios_path = "/DATA/entreaulas/Centros/$centro_id/Aularios";
    $personas = [];
    if (!is_dir($aularios_path)) {
        return $personas;
    }
    $aulario_files = glob("$aularios_path/*.json") ?: [];
    foreach ($aulario_files as $aulario_file) {
        $aulario_id   = basename($aulario_file, '.json');
        $aulario_data = json_decode(file_get_contents($aulario_file), true);
        $aulario_name = $aulario_data['name'] ?? $aulario_id;
        $alumnos_path = "$aularios_path/$aulario_id/Alumnos";
        if (!is_dir($alumnos_path)) {
            continue;
        }
        $alumno_dirs = glob("$alumnos_path/*/", GLOB_ONLYDIR) ?: [];
        usort($alumno_dirs, function ($a, $b) {
            return strcasecmp(basename($a), basename($b));
        });
        foreach ($alumno_dirs as $alumno_dir) {
            $alumno_name = basename($alumno_dir);
            // Key uses ':' as separator; safe_id_segment chars [A-Za-z0-9_-] exclude ':'
            $key = $aulario_id . ':' . $alumno_name;
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

function sc_load_menu($sc_base)
{
    $path = "$sc_base/Menu.json";
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function sc_count_debts($persona_key)
{
    if (!is_dir(SC_DATA_DIR)) {
        return 0;
    }
    $count = 0;
    foreach (glob(SC_DATA_DIR . '/*.json') ?: [] as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)
            && ($data['Persona'] ?? '') === $persona_key
            && ($data['Estado'] ?? '') === 'Deuda') {
            $count++;
        }
    }
    return $count;
}

// Determine if creating or editing
$order_id = sc_safe_order_id($_GET['id'] ?? '');
$is_new   = $order_id === '';
if ($is_new) {
    $raw_id   = uniqid('sc', true);
    $order_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $raw_id);
}

$order_file = SC_DATA_DIR . '/' . $order_id . '.json';

$order_data = [
    'Fecha'   => date('Y-m-d'),
    'Persona' => '',
    'Comanda' => '',
    'Notas'   => '',
    'Estado'  => 'Pedido',
];
if (!$is_new && is_readable($order_file)) {
    $existing = json_decode(file_get_contents($order_file), true);
    if (is_array($existing)) {
        $order_data = array_merge($order_data, $existing);
    }
}

$personas = sc_load_personas_from_alumnos($centro_id);
$menu     = sc_load_menu($sc_base);

// Group personas by aulario for the optgroup picker
$personas_by_aulario = [];
foreach ($personas as $key => $pinfo) {
    $personas_by_aulario[$pinfo['Region']][$key] = $pinfo;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $persona_key = $_POST['Persona'] ?? '';
    $notas       = trim($_POST['Notas'] ?? '');
    $estado      = $_POST['Estado'] ?? 'Pedido';

    if (!in_array($estado, $valid_statuses, true)) {
        $estado = 'Pedido';
    }

    // Validate that the submitted persona key exists in the loaded list.
    // When no alumnos are configured ($personas is empty), accept any non-empty free-text value.
    if ($persona_key === '' || (!empty($personas) && !array_key_exists($persona_key, $personas))) {
        $error = '¡Hay que elegir una persona válida!';
    } else {
        // Build comanda string from selected menu items
        $comanda_parts = [];
        if (!empty($menu)) {
            foreach ($menu as $category => $items) {
                foreach ($items as $item_name => $item_price) {
                    $qty_key = 'item_' . md5($category . '_' . $item_name);
                    $qty = (int)($_POST[$qty_key] ?? 0);
                    if ($qty > 0) {
                        $comanda_parts[] = $qty . 'x ' . $item_name;
                    }
                }
            }
        } else {
            $manual = trim($_POST['Comanda_manual'] ?? '');
            if ($manual !== '') {
                $comanda_parts[] = $manual;
            }
        }
        $comanda_str = implode(', ', $comanda_parts);

        // Debt check: only for new orders or when the person changes
        $prev_persona = $order_data['Persona'] ?? '';
        if ($is_new || $prev_persona !== $persona_key) {
            $debt_count = sc_count_debts($persona_key);
            if ($debt_count >= SC_MAX_DEBTS) {
                $error = 'Esta persona tiene ' . $debt_count . ' comandas en deuda. No se puede realizar el pedido.';
            }
        }

        if ($error === '') {
            $new_data = [
                'Fecha'   => date('Y-m-d'),
                'Persona' => $persona_key,
                'Comanda' => $comanda_str,
                'Notas'   => $notas,
                'Estado'  => $is_new ? 'Pedido' : $estado,
            ];

            if (!is_dir(SC_DATA_DIR)) {
                mkdir(SC_DATA_DIR, 0755, true);
            }

            $tmp   = SC_DATA_DIR . '/.' . $order_id . '.tmp';
            $bytes = file_put_contents(
                $tmp,
                json_encode($new_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
            if ($bytes === false || !rename($tmp, $order_file)) {
                @unlink($tmp);
                $error = 'Error al guardar la comanda.';
            } else {
                header('Location: /entreaulas/supercafe.php');
                exit;
            }
        }
    }
}

require_once "_incl/pre-body.php";
?>

<div class="card pad">
    <h1><?= $is_new ? 'Nueva comanda' : 'Editar comanda' ?></h1>
    <a href="/entreaulas/supercafe.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($error !== ''): ?>
    <div class="card pad" style="background: #f8d7da; color: #842029;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post">
    <fieldset class="card pad">
        <legend>
            <strong>Rellenar comanda</strong>
            <code><?= htmlspecialchars($order_id) ?></code>
        </legend>

        <div class="mb-3">
            <label class="form-label"><strong>Persona</strong></label>
            <?php if (!empty($personas_by_aulario)): ?>
                <select name="Persona" class="form-select" required>
                    <option value="">-- Selecciona una persona --</option>
                    <?php foreach ($personas_by_aulario as $region_name => $group): ?>
                        <optgroup label="<?= htmlspecialchars($region_name) ?>">
                            <?php foreach ($group as $pkey => $pinfo): ?>
                                <option value="<?= htmlspecialchars($pkey) ?>"
                                    <?= ($order_data['Persona'] === $pkey) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pinfo['Nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <?php
                // Show photo of the currently selected person (if editing)
                $sel_key  = $order_data['Persona'];
                $sel_info = $personas[$sel_key] ?? null;
                if ($sel_info && $sel_info['HasPhoto']):
                ?>
                    <div id="sc-persona-photo" style="margin-top: 8px;">
                        <?php $photo_url = '/entreaulas/_filefetch.php?type=alumno_photo'
                            . '&centro=' . urlencode($centro_id)
                            . '&aulario=' . urlencode($sel_info['AularioID'])
                            . '&alumno=' . urlencode($sel_info['Nombre']); ?>
                        <img src="<?= htmlspecialchars($photo_url) ?>"
                             alt="Foto de <?= htmlspecialchars($sel_info['Nombre']) ?>"
                             style="height: 80px; border-radius: 8px; border: 2px solid #dee2e6;">
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <input type="text" name="Persona" class="form-control"
                       value="<?= htmlspecialchars($order_data['Persona']) ?>"
                       placeholder="Nombre de la persona" required>
                <small class="text-muted">
                    No hay alumnos registrados en los aularios de este centro.
                    Añade alumnos desde
                    <a href="/entreaulas/">EntreAulas</a>.
                </small>
            <?php endif; ?>
        </div>

        <?php if (!empty($menu)): ?>
            <div class="mb-3">
                <label class="form-label"><strong>Artículos</strong></label>
                <?php foreach ($menu as $category => $items): ?>
                    <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                        <strong><?= htmlspecialchars($category) ?></strong>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px;">
                            <?php foreach ($items as $item_name => $item_price):
                                $qty_key = 'item_' . md5($category . '_' . $item_name);
                            ?>
                                <label style="display: flex; align-items: center; gap: 6px; background: white; padding: 6px 10px; border-radius: 6px; border: 1px solid #dee2e6;">
                                    <input type="number" name="<?= htmlspecialchars($qty_key) ?>"
                                           min="0" max="99" value="0"
                                           style="width: 55px;" class="form-control form-control-sm">
                                    <?= htmlspecialchars($item_name) ?>
                                    <?php if ($item_price > 0): ?>
                                        <small style="color: #6c757d;">(<?= number_format((float)$item_price, 2) ?>c)</small>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <label class="form-label"><strong>Comanda (texto libre)</strong></label>
                <input type="text" name="Comanda_manual" class="form-control"
                       value="<?= htmlspecialchars($order_data['Comanda']) ?>"
                       placeholder="Ej. 1x Café, 1x Bocadillo">
                <small class="text-muted">
                    No hay menú configurado en
                    <code>/DATA/entreaulas/Centros/<?= htmlspecialchars($centro_id) ?>/SuperCafe/Menu.json</code>.
                </small>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label"><strong>Notas</strong></label>
            <textarea name="Notas" class="form-control" rows="2"><?= htmlspecialchars($order_data['Notas']) ?></textarea>
        </div>

        <?php if (!$is_new): ?>
            <div class="mb-3">
                <label class="form-label"><strong>Estado</strong></label>
                <select name="Estado" class="form-select">
                    <?php foreach ($valid_statuses as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>"
                            <?= $st === $order_data['Estado'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">El estado también se puede cambiar desde el listado.</small>
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-success">Guardar</button>
            <a href="/entreaulas/supercafe.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </fieldset>
</form>

<?php require_once "_incl/post-body.php"; ?>

