<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/tools.security.php";
require_once "../_incl/db.php";

if (!in_array('supercafe:edit', $_SESSION['auth_data']['permissions'] ?? [])) {
    header('HTTP/1.1 403 Forbidden');
    die('Acceso denegado');
}

$centro_id = safe_centro_id($_SESSION['auth_data']['entreaulas']['centro'] ?? '');
if ($centro_id === '') {
    require_once "_incl/pre-body.php";
    echo '<div class="card pad"><h1>SuperCafe</h1><p>No tienes un centro asignado.</p></div>';
    require_once "_incl/post-body.php";
    exit;
}

define('SC_MAX_DEBTS', 3);

$valid_statuses = ['Pedido', 'En preparación', 'Listo', 'Entregado', 'Deuda'];

/**
 * Load personas from the Alumnos filesystem (photos still on disk).
 * Returns array keyed by "{aulario_id}:{alumno_name}".
 */
function sc_load_personas_from_alumnos($centro_id)
{
    $aularios     = db_get_aularios($centro_id);
    $personas     = [];
    $aularios_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios";
    foreach ($aularios as $aulario_id => $aulario_data) {
        $aulario_name = $aulario_data['name'] ?? $aulario_id;
        $alumnos_path = "$aularios_dir/$aulario_id/Alumnos";
        if (!is_dir($alumnos_path)) {
            continue;
        }
        $alumno_dirs = glob("$alumnos_path/*/", GLOB_ONLYDIR) ?: [];
        usort($alumno_dirs, fn($a, $b) => strcasecmp(basename($a), basename($b)));
        foreach ($alumno_dirs as $alumno_dir) {
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

// Determine if creating or editing
$order_id = safe_id($_GET['id'] ?? '');
$is_new   = $order_id === '';
if ($is_new) {
    $order_id = db_next_supercafe_ref($centro_id);
}

// Load existing order from DB (or defaults)
$order_data = [
    'Fecha'   => date('Y-m-d'),
    'Persona' => '',
    'Comanda' => '',
    'Notas'   => '',
    'Estado'  => 'Pedido',
];
if (!$is_new) {
    $existing = db_get_supercafe_order($centro_id, $order_id);
    if ($existing) {
        $order_data = [
            'Fecha'   => $existing['fecha'],
            'Persona' => $existing['persona'],
            'Comanda' => $existing['comanda'],
            'Notas'   => $existing['notas'],
            'Estado'  => $existing['estado'],
        ];
    }
}

$personas = sc_load_personas_from_alumnos($centro_id);
$menu     = db_get_supercafe_menu($centro_id);

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

    if ($persona_key === '' || (!empty($personas) && !array_key_exists($persona_key, $personas))) {
        $error = '¡Hay que elegir una persona válida!';
    } else {
        $comanda_parts = [];
        if (!empty($menu)) {
            foreach ($menu as $category => $items) {
                $val = trim($_POST[$category] ?? '');
                if ($val !== '' && array_key_exists($val, $items)) {
                    $comanda_parts[] = $val;
                }
            }
        } else {
            $manual = trim($_POST['Comanda_manual'] ?? '');
            if ($manual !== '') {
                $comanda_parts[] = $manual;
            }
        }
        $comanda_str  = implode(', ', $comanda_parts);
        $prev_persona = $order_data['Persona'];
        if ($is_new || $prev_persona !== $persona_key) {
            $debt_count = db_supercafe_count_debts($centro_id, $persona_key);
            if ($debt_count >= SC_MAX_DEBTS) {
                $error = 'Esta persona tiene ' . $debt_count . ' comandas en deuda. No se puede realizar el pedido.';
            }
        }
        if ($error === '') {
            db_upsert_supercafe_order(
                $centro_id, $order_id,
                date('Y-m-d'), $persona_key, $comanda_str, $notas,
                $is_new ? 'Pedido' : $estado
            );
            header('Location: /entreaulas/supercafe.php');
            exit;
        }
    }
}

require_once "_incl/pre-body.php";

?>

<h1>Comanda <code><?= htmlspecialchars($order_id) ?></code></h1>
<a href="/entreaulas/supercafe.php" class="btn btn-secondary">Salir</a>

<?php if ($error !== ''): ?>
    <div class="card pad" style="background: #f8d7da; color: #842029;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post">
    <fieldset class="card pad" style="text-align: center;">
        <legend>Rellenar comanda</legend>

        <label style="display: none;">
            Fecha<br>
            <input readonly disabled type="text" value="<?= htmlspecialchars($order_data['Fecha']) ?>"><br><br>
        </label>

        <label>
            Persona<br>
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
            <br><br>
        </label>

        <label style="display: none;">
            Comanda (utiliza el panel de relleno)<br>
            <textarea readonly disabled><?= htmlspecialchars($order_data['Comanda']) ?></textarea><br><br>
        </label>

        <div>
            <?php if (!empty($menu)): ?>
                <style>
                .sc-details { text-align: center; margin: 5px; padding: 5px; border: 2px solid black; border-radius: 5px; background: white; cursor: pointer; width: calc(100% - 25px); display: inline-block; }
                .sc-details summary { padding: 10px; background-size: contain; background-position: left; background-repeat: no-repeat; text-align: left; padding-left: 55px; font-size: 1.1em; }
                .sc-cat-btn { border-radius: 20px; font-size: 1.1em; margin: 6px; padding: 10px 18px; border: 2px solid #bbb; background: #f8f9fa; display: inline-block; min-width: 90px; min-height: 60px; vertical-align: top; transition: background 0.2s, border 0.2s; }
                .sc-cat-btn.active { background: #ffe066; border: 2px solid #222; }
                .sc-cat-btn img { height: 50px; padding: 5px; background: white; border-radius: 8px; }
                .sc-details .sc-summary-right { float: right; display: flex; align-items: center; gap: 6px; }
                .sc-details .sc-check { height: 30px; }
                </style>
                <?php
                // Iconos por categoría (puedes ampliar este array según tus iconos)
                $sc_actions_icons = [
                  'Tamaño' => 'static/ico/sizes.png',
                  'Temperatura' => 'static/ico/thermometer2.png',
                  'Leche' => 'static/ico/milk.png',
                  'Selección' => 'static/ico/preferences.png',
                  'Cafeina' => 'static/ico/coffee_bean.png',
                  'Endulzante' => 'static/ico/lollipop.png',
                  // ...
                ];
                // Cargar valores previos si existen (para mantener selección tras submit fallido)
                $selected = [];
                foreach ($menu as $cat => $items) {
                  foreach ($items as $iname => $iprice) {
                    $qty_key = 'item_' . md5($cat . '_' . $iname);
                    $selected[$cat] = isset($_POST[$cat]) ? $_POST[$cat] : (isset($order_data['Comanda']) && strpos($order_data['Comanda'], $iname) !== false ? $iname : '');
                  }
                }
                ?>
                <?php foreach ($menu as $category => $items): ?>
                    <details class="sc-details">
                        <summary style="background-image: url('<?= isset($sc_actions_icons[$category]) ? $sc_actions_icons[$category] : '' ?>');">
                            <?= htmlspecialchars($category) ?>
                            <span class="sc-summary-right">
                                <span class="sc-selected-val" id="sc-val-<?= md5($category) ?>">
                                    <?= htmlspecialchars($selected[$category] ?? '') ?>
                                </span>
                                <img class="sc-check" src="static/ico/checkbox_unchecked.png" id="sc-check-<?= md5($category) ?>">
                            </span>
                        </summary>
                        <div>
                        <?php foreach ($items as $item_name => $item_price):
                            $btn_id = 'sc-btn-' . md5($category . '_' . $item_name);
                            $is_active = ($selected[$category] ?? '') === $item_name;
                        ?>
                            <button type="button" class="sc-cat-btn<?= $is_active ? ' active' : '' ?>" id="<?= $btn_id ?>" onclick="
                                document.getElementById('sc-val-<?= md5($category) ?>').innerText = '<?= htmlspecialchars($item_name) ?>';
                                document.getElementById('sc-check-<?= md5($category) ?>').src = 'static/ico/checkbox.png';
                                var btns = this.parentNode.querySelectorAll('.sc-cat-btn');
                                btns.forEach(b => b.classList.remove('active'));
                                this.classList.add('active');
                                document.getElementById('input-<?= md5($category) ?>').value = '<?= htmlspecialchars($item_name) ?>';
                            ">
                                <?= htmlspecialchars($item_name) ?>
                                <?php if ($item_price > 0): ?>
                                    <br><small style="color: #6c757d;">(<?= number_format((float)$item_price, 2) ?>c)</small>
                                <?php endif; ?>
                                <!-- Aquí podrías poner una imagen si tienes -->
                            </button>
                        <?php endforeach; ?>
                        <input type="hidden" name="<?= htmlspecialchars($category) ?>" id="input-<?= md5($category) ?>" value="<?= htmlspecialchars($selected[$category] ?? '') ?>">
                        </div>
                    </details>
                <?php endforeach; ?>
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
        </div>

        <label>
            Notas<br>
            <textarea name="Notas" class="form-control" rows="2"><?= htmlspecialchars($order_data['Notas']) ?></textarea><br><br>
        </label>

        <?php if (!$is_new): ?>
            <label>
                Estado<br>
                <select name="Estado" class="form-select">
                    <?php foreach ($valid_statuses as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>"
                            <?= $st === $order_data['Estado'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <br>Modificar en el listado de comandas<br>
            </label>
        <?php endif; ?>

        <button type="submit" class="btn btn-success">Guardar</button>
        <a href="/entreaulas/supercafe.php" class="btn btn-secondary">Cancelar</a>
    </fieldset>
</form>

<?php require_once "_incl/post-body.php"; ?>

