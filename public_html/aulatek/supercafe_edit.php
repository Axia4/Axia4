<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/tools.security.php";
require_once "../_incl/db.php";

if (!in_array('supercafe:edit', $_SESSION['auth_data']['permissions'] ?? [])) {
    header('HTTP/1.1 403 Forbidden');
    die('Acceso denegado');
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

$valid_statuses = ['Pedido', 'En preparación', 'Listo', 'Entregado', 'Deuda'];

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
            header('Location: /aulatek/supercafe.php');
            exit;
        }
    }
}

require_once "_incl/pre-body.php";

?>
<style>
    .sc-legacy-wrap {
        max-width: 320px;
    }
    .sc-legacy-title {
        font-size: 1.9rem;
        margin: 0 0 0.2rem;
        font-weight: 700;
    }
    .sc-legacy-subtitle {
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.35rem;
    }
    .sc-legacy-exit {
        padding: 2px 8px;
        border: 1px solid #111;
        border-radius: 2px;
        background: #fff;
        color: #111;
        text-decoration: none;
        display: inline-block;
        margin-bottom: 0.35rem;
        font-size: 0.82rem;
    }
    .sc-legacy-fieldset {
        border: 1px solid #bcbcbc;
        border-radius: 0;
        padding: 6px;
        background: #fff;
    }
    .sc-legacy-fieldset legend {
        font-size: 0.95rem;
        font-weight: 700;
        margin-bottom: 0;
    }
    .sc-legacy-persona-photo img {
        height: 64px;
        border-radius: 10px;
        border: 2px solid #777;
        background: #fff;
    }
    .sc-persona-current {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-top: 5px;
        min-height: 36px;
        font-size: 0.8rem;
    }
    .sc-persona-current img {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid #666;
        background: #fff;
        object-fit: cover;
    }
    .sc-persona-group {
        margin-top: 5px;
        padding-top: 3px;
        border-top: 1px dashed #ddd;
    }
    .sc-persona-group-label {
        font-size: 0.72rem;
        color: #555;
        margin-bottom: 3px;
        font-weight: 700;
    }
    .sc-person-btn {
        border: 5px solid transparent;
        outline: 2px solid black;
        border-radius: 10px;
        background: #fff;
        color: #111;
        min-width: 86px;
        max-width: 125px;
        min-height: 54px;
        padding: 4px 6px;
        font-size: 0.75rem;
        line-height: 1.05;
        text-align: center;
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
    }
    .sc-person-btn img {
        width: 24px;
        height: 24px;
        border-radius: 7px;
        border: 1px solid #777;
        object-fit: cover;
        background: #fff;
    }
    .sc-person-btn.active {
        background: #d9ff1f;
        border: 5px dashed #000;
        outline: 2px solid black;
    }
    .sc-details {
        margin: 4px 0;
        border: 1px solid #444;
        border-radius: 3px;
        background: #fff;
        width: 100%;
    }
    .sc-details summary {
        list-style: none;
        cursor: pointer;
        font-size: 0.86rem;
        padding: 4px 6px;
        background: #f3f3f3;
        border-bottom: 1px solid #ddd;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
    }
    .sc-details summary::-webkit-details-marker {
        display: none;
    }
    .sc-summary-left {
        display: flex;
        align-items: center;
        gap: 5px;
        min-width: 0;
    }
    .sc-summary-left img {
        height: 18px;
        width: 18px;
        object-fit: contain;
    }
    .sc-summary-right {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
    }
    .sc-selected-val {
        max-width: 95px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.78rem;
        color: #444;
    }
    .sc-check {
        height: 15px;
    }
    .sc-category-body {
        padding: 4px;
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    .sc-cat-btn {
        border: 5px solid transparent;
        outline: 2px solid black;
        border-radius: 9px;
        background: #d9ff1f;
        color: #111;
        min-width: 90px;
        min-height: 44px;
        padding: 3px 6px;
        font-size: 0.78rem;
        line-height: 1.1;
        text-align: center;
    }
    .sc-cat-btn small {
        font-size: 0.68rem;
    }
    .sc-cat-btn.active {
        outline: 2px solid black;
        border: 5px dashed #000;
    }
    .sc-cat-btn.sc-size-btn {
        background: #ff3030;
        color: #fff;
    }
    .sc-notes {
        width: 100%;
        min-height: 78px;
        font-size: 0.85rem;
    }
    .sc-actions {
        display: flex;
        gap: 6px;
        justify-content: flex-start;
        margin-top: 6px;
    }
    .sc-actions .btn {
        border-radius: 2px;
        padding: 3px 9px;
        font-size: 0.82rem;
    }
</style>

<div class="sc-legacy-wrap">
    <h1 class="sc-legacy-title">Comanda <small style="font-size:.72rem;color:#666;"><?= htmlspecialchars($order_id) ?></small></h1>
    <a href="/aulatek/supercafe.php" class="sc-legacy-exit">Salir</a>

<?php if ($error !== ''): ?>
    <div class="card pad" style="background: #f8d7da; color: #842029;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="post">
        <fieldset class="sc-legacy-fieldset">
                <legend>Rellenar comanda</legend>

        <label style="display: none;">
            Fecha<br>
            <input readonly disabled type="text" value="<?= htmlspecialchars($order_data['Fecha']) ?>"><br><br>
        </label>

        <label style="display:block; margin-bottom:6px;">
            Persona<br>
            <?php if (!empty($personas_by_aulario)): ?>
                <?php
                $sel_key  = $order_data['Persona'];
                $sel_info = $personas[$sel_key] ?? null;
                ?>
                <details class="sc-details" open>
                    <summary>
                        <span class="sc-summary-left">
                            <span>Persona</span>
                        </span>
                        <span class="sc-summary-right">
                            <span class="sc-selected-val" id="sc-persona-selected-label"><?= htmlspecialchars($sel_info['Nombre'] ?? '') ?></span>
                            <img class="sc-check" src="static/ico/<?= $sel_info ? 'checkbox.png' : 'checkbox_unchecked.png' ?>" id="sc-persona-check" alt="">
                        </span>
                    </summary>
                    <div class="sc-category-body" id="sc-persona-panel">
                        <?php foreach ($personas_by_aulario as $region_name => $group): ?>
                            <div class="sc-persona-group" style="width:100%;">
                                <div class="sc-persona-group-label"><?= htmlspecialchars($region_name) ?></div>
                                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                    <?php foreach ($group as $pkey => $pinfo): ?>
                                        <?php
                                        $p_photo_url = '/aulatek/_filefetch.php?type=alumno_photo'
                                            . '&org=' . urlencode($centro_id)
                                            . '&aulario=' . urlencode($pinfo['AularioID'])
                                            . '&alumno=' . urlencode($pinfo['Nombre']);
                                        $is_person_active = ($order_data['Persona'] === $pkey);
                                        ?>
                                        <button type="button"
                                                class="sc-person-btn<?= $is_person_active ? ' active' : '' ?>"
                                                data-person-key="<?= htmlspecialchars($pkey, ENT_QUOTES) ?>"
                                                data-person-name="<?= htmlspecialchars($pinfo['Nombre'], ENT_QUOTES) ?>"
                                                data-person-region="<?= htmlspecialchars($region_name, ENT_QUOTES) ?>"
                                                data-person-photo="<?= htmlspecialchars($pinfo['HasPhoto'] ? $p_photo_url : '/static/arasaac/alumnos.png', ENT_QUOTES) ?>"
                                                onclick="scSelectPersona(this)">
                                            <img src="<?= htmlspecialchars($pinfo['HasPhoto'] ? $p_photo_url : '/static/arasaac/alumnos.png') ?>" alt="">
                                            <span><?= htmlspecialchars($pinfo['Nombre']) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
                <input type="hidden" name="Persona" id="sc-persona-input" value="<?= htmlspecialchars($order_data['Persona']) ?>">
                <div class="sc-persona-current" id="sc-persona-current" style="display: none;">
                    <?php if ($sel_info): ?>
                        <?php $photo_url = '/aulatek/_filefetch.php?type=alumno_photo'
                            . '&org=' . urlencode($centro_id)
                            . '&aulario=' . urlencode($sel_info['AularioID'])
                            . '&alumno=' . urlencode($sel_info['Nombre']); ?>
                        <img src="<?= htmlspecialchars($sel_info['HasPhoto'] ? $photo_url : '/static/arasaac/alumnos.png') ?>" alt="Foto">
                        <span><strong><?= htmlspecialchars($sel_info['Nombre']) ?></strong> (<?= htmlspecialchars($sel_info['Region']) ?>)</span>
                    <?php else: ?>
                        <span style="color:#666;">No hay persona seleccionada.</span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <input type="text" name="Persona" class="form-control"
                       value="<?= htmlspecialchars($order_data['Persona']) ?>"
                       placeholder="Nombre de la persona" required>
                <small class="text-muted">
                    No hay alumnos registrados en los aularios de esta organizacion.
                    Añade alumnos desde
                    <a href="/aulatek/">AulaTek</a>.
                </small>
            <?php endif; ?>
            <br>
        </label>

        <label style="display: none;">
            Comanda (utiliza el panel de relleno)<br>
            <textarea readonly disabled><?= htmlspecialchars($order_data['Comanda']) ?></textarea><br><br>
        </label>

        <div>
            <?php if (!empty($menu)): ?>
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
                        <summary>
                            <span class="sc-summary-left">
                                <?php if (isset($sc_actions_icons[$category])): ?>
                                    <img src="<?= htmlspecialchars($sc_actions_icons[$category]) ?>" alt="">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($category) ?></span>
                            </span>
                            <span class="sc-summary-right">
                                <span class="sc-selected-val" id="sc-val-<?= md5($category) ?>">
                                    <?= htmlspecialchars($selected[$category] ?? '') ?>
                                </span>
                                <img class="sc-check" src="static/ico/checkbox_unchecked.png" id="sc-check-<?= md5($category) ?>">
                            </span>
                        </summary>
                        <div class="sc-category-body">
                        <?php foreach ($items as $item_name => $item_price):
                            $btn_id = 'sc-btn-' . md5($category . '_' . $item_name);
                            $is_active = ($selected[$category] ?? '') === $item_name;
                            $btn_extra_class = ($category === 'Tamaño') ? ' sc-size-btn' : '';
                        ?>
                            <button type="button" class="sc-cat-btn<?= $btn_extra_class ?><?= $is_active ? ' active' : '' ?>" id="<?= $btn_id ?>" onclick="
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
                        <code><?= htmlspecialchars(aulatek_orgs_base_path() . "/" . $centro_id . "/SuperCafe/Menu.json") ?></code>.
                    </small>
                </div>
            <?php endif; ?>
        </div>

        <label style="display:block; margin-top:6px;">
            Notas<br>
            <textarea name="Notas" class="form-control sc-notes" rows="2"><?= htmlspecialchars($order_data['Notas']) ?></textarea>
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
                <br><small>Modificar en el listado de comandas</small><br>
            </label>
        <?php endif; ?>

        <div class="sc-actions">
            <button type="submit" class="btn btn-success">Guardar</button>
            <a href="/aulatek/supercafe.php" class="btn btn-danger">Cancelar</a>
        </div>
    </fieldset>
</form>
</div>

<script>
function scSelectPersona(button) {
    var key = button.getAttribute('data-person-key') || '';
    var name = button.getAttribute('data-person-name') || '';
    var region = button.getAttribute('data-person-region') || '';
    var photo = button.getAttribute('data-person-photo') || '/static/arasaac/alumnos.png';

    var hiddenInput = document.getElementById('sc-persona-input');
    if (hiddenInput) {
        hiddenInput.value = key;
    }

    var allButtons = document.querySelectorAll('.sc-person-btn');
    allButtons.forEach(function(btn) {
        btn.classList.remove('active');
    });
    button.classList.add('active');

    var label = document.getElementById('sc-persona-selected-label');
    if (label) {
        label.innerText = name;
    }

    var check = document.getElementById('sc-persona-check');
    if (check) {
        check.src = 'static/ico/checkbox.png';
    }

    var current = document.getElementById('sc-persona-current');
    if (current) {
        current.innerHTML = '<img src="' + photo + '" alt="Foto">' +
            '<span><strong>' + name + '</strong>' + (region ? ' (' + region + ')' : '') + '</span>';
    }
}
</script>

<?php require_once "_incl/post-body.php"; ?>

