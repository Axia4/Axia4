<?php
require_once "_incl/auth_redir.php";
require_once "../_incl/tools.security.php";

// Check if user has docente permission
if (!in_array("entreaulas:docente", $_SESSION["auth_data"]["permissions"] ?? [])) {
    header("HTTP/1.1 403 Forbidden");
    die("Acceso denegado");
}

function safe_id_segment($value)
{
    $value = basename((string)$value);
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value);
}

function safe_centro_id($value)
{
    return preg_replace('/[^0-9]/', '', (string)$value);
}

function path_is_within($real_base, $real_path)
{
    if ($real_base === false || $real_path === false) {
        return false;
    }
    $base_prefix = rtrim($real_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strpos($real_path, $base_prefix) === 0 || $real_path === rtrim($real_base, DIRECTORY_SEPARATOR);
}

$aulario_id = safe_id_segment(Sf($_GET["aulario"] ?? ""));
$centro_id = safe_centro_id(Sf($_SESSION["auth_data"]["entreaulas"]["centro"] ?? ""));
$alumno = safe_id_segment(Sf($_GET["alumno"] ?? ""));

if (empty($aulario_id) || empty($centro_id)) {
    require_once "_incl/pre-body.php";
    ?>
    <div class="card pad">
        <h1>Diario del Alumno</h1>
        <p>No se ha indicado un aulario válido.</p>
    </div>
    <?php
    require_once "_incl/post-body.php";
    exit;
}

// Validate paths with realpath
$base_path = "/DATA/entreaulas/Centros";
$real_base = realpath($base_path);

if ($real_base === false) {
    require_once "_incl/pre-body.php";
    ?>
    <div class="card pad">
        <h1>Diario del Alumno</h1>
        <p>Error: Directorio base no encontrado.</p>
    </div>
    <?php
    require_once "_incl/post-body.php";
    exit;
}

// Get list of alumnos if not specified
$alumnos_base_path = "$base_path/$centro_id/Aularios/$aulario_id/Alumnos";
$alumnos = [];

// Resolve and validate alumnos path to ensure it stays within the allowed base directory
$alumnos_real_path = realpath($alumnos_base_path);
if ($alumnos_real_path !== false) {
    if (path_is_within($real_base, $alumnos_real_path) && is_dir($alumnos_real_path)) {
        $alumnos = glob($alumnos_real_path . "/*", GLOB_ONLYDIR);
        usort($alumnos, function($a, $b) {
            return strcasecmp(basename($a), basename($b));
        });
    }
}

// If no alumno specified, show list
if (empty($alumno)) {
    require_once "_incl/pre-body.php";
    ?>
    <div class="card pad">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h1 class="card-title" style="margin: 0;">Diarios de Alumnos</h1>
        </div>
        
        <?php if (empty($alumnos)): ?>
            <p>No hay alumnos registrados en este aulario.</p>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <?php foreach ($alumnos as $alumno_path): 
                    $alumno_name = basename($alumno_path);
                    $photo_exists = file_exists("$alumno_path/photo.jpg");
                    $diario_path = "$alumno_path/Diario/" . date("Y-m-d");
                    $has_diary = file_exists("$diario_path/Panel.json");
                ?>
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="background: #f8f9fa; padding: 15px; text-align: center;">
                        <?php if ($photo_exists): ?>
                            <img src="_filefetch.php?type=alumno_photo&alumno=<?= urlencode($alumno_name) ?>&centro=<?= urlencode($centro_id) ?>&aulario=<?= urlencode($aulario_id) ?>" 
                                 alt="Foto de <?= htmlspecialchars($alumno_name) ?>" 
                                 style="width: 120px; height: 120px; object-fit: cover; border-radius: 10px; border: 3px solid #ddd;">
                        <?php else: ?>
                            <div style="width: 120px; height: 120px; background: #e0e0e0; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 3px dashed #999; margin: 0 auto; font-size: 3rem; color: #999;">
                                ?
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 15px;">
                        <h3 style="margin: 0 0 10px 0; text-align: center;"><?= htmlspecialchars($alumno_name) ?></h3>
                        <div style="text-align: center; margin-bottom: 10px;">
                            <?php if ($has_diary): ?>
                                <span class="badge" style="background: #28a745; color: white; padding: 5px 10px; border-radius: 3px;">Diario disponible</span>
                            <?php else: ?>
                                <span class="badge" style="background: #6c757d; color: white; padding: 5px 10px; border-radius: 3px;">Sin diario</span>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: center;">
                            <a href="?aulario=<?= urlencode($aulario_id) ?>&alumno=<?= urlencode($alumno_name) ?>" class="btn btn-primary" style="display: inline-block;">
                                Ver Diario
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="alumnos.php?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary">← Volver a Alumnos</a>
        </div>
    </div>
    <?php
    require_once "_incl/post-body.php";
    exit;
}

// If alumno is specified, validate and show their diary
$alumno_path = "$alumnos_base_path/$alumno";

$real_alumnos_base = realpath($alumnos_base_path);
if (!path_is_within($real_base, $real_alumnos_base)) {
    require_once "_incl/pre-body.php";
    ?>
    <div class="card pad">
        <h1>Diario del Alumno</h1>
        <p>Ruta de alumnos inválida.</p>
    </div>
    <?php
    require_once "_incl/post-body.php";
    exit;
}

// Validate path with realpath
$real_alumno_path = realpath($alumno_path);
if ($real_alumno_path === false || !path_is_within($real_alumnos_base, $real_alumno_path) || !is_dir($real_alumno_path)) {
    require_once "_incl/pre-body.php";
    ?>
    <div class="card pad">
        <h1>Diario del Alumno</h1>
        <p>Alumno no encontrado.</p>
    </div>
    <?php
    require_once "_incl/post-body.php";
    exit;
}
$alumno_path = $real_alumno_path;

// Get diario types and data
$diario_types = [
    'Panel' => [
        'label' => 'Panel Diario',
        'icon' => '📋',
        'description' => 'Registro de actividades diarias del panel'
    ]
];

require_once "_incl/pre-body.php";
?>
<div class="card pad">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h1 class="card-title" style="margin: 0;">Diario de <?= htmlspecialchars($alumno) ?></h1>
    </div>
    
    <div style="background: #f0f0f0; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
        <p style="margin: 0;">
            <strong>Aulario:</strong> <?= htmlspecialchars($aulario_id) ?><br>
            <strong>Fecha actual:</strong> <?= date('d/m/Y'); ?>
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
        <?php foreach ($diario_types as $type_key => $type_info): 
            $type_path = "$alumno_path/Diario";
            $type_file = "$type_path/" . date("Y-m-d") . "/$type_key.json";
            $has_data = file_exists($type_file);
            
            // Get all diary dates for this type
            $diary_dates = [];
            if (is_dir($type_path)) {
                $dates = glob($type_path . "/*/", GLOB_ONLYDIR);
                foreach ($dates as $date_dir) {
                    $date = basename($date_dir);
                    if (file_exists("$date_dir/$type_key.json")) {
                        $diary_dates[] = $date;
                    }
                }
                rsort($diary_dates);
            }
        ?>
        <div class="card" style="background: white; border: 2px solid #ddd;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 10px;"><?= $type_info['icon']; ?></div>
                <h3 style="margin: 0 0 5px 0;"><?= $type_info['label']; ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem; opacity: 0.9;"><?= $type_info['description']; ?></p>
            </div>
            <div style="padding: 20px;">
                <?php if (!empty($diary_dates)): ?>
                    <div style="margin-bottom: 15px;">
                        <p style="margin-bottom: 10px; font-weight: bold;">Registros disponibles:</p>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <?php foreach ($diary_dates as $date): ?>
                                    <li style="padding: 8px; border-bottom: 1px solid #eee;">
                                        <a href="?aulario=<?= urlencode($aulario_id) ?>&alumno=<?= urlencode($alumno) ?>&type=<?= urlencode($type_key) ?>&date=<?= urlencode($date) ?>" 
                                           style="color: #667eea; text-decoration: none; display: block;">
                                            📅 <?= date('d/m/Y', strtotime($date)); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <a href="?aulario=<?= urlencode($aulario_id) ?>&alumno=<?= urlencode($alumno) ?>&type=<?= urlencode($type_key) ?>&date=<?= date('Y-m-d'); ?>" 
                   class="btn btn-<?= $has_data ? 'primary' : 'secondary'; ?>" style="width: 100%; text-align: center; display: block;">
                    <?= $has_data ? '📖 Ver Registro de Hoy' : '📖 Ver Detalles'; ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top: 30px; display: flex; gap: 10px;">
        <a href="?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary">← Volver a Lista de Diarios</a>
        <a href="alumnos.php?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary">← Volver a Alumnos</a>
    </div>
</div>

<?php
// Show specific diary entry if requested
$type = safe_id_segment(Sf($_GET["type"] ?? ""));
$date = Sf($_GET["date"] ?? date("Y-m-d"));

if (!empty($type) && !empty($date)) {
    $date = preg_replace('/[^0-9-]/', '', $date);
    $is_valid_date = false;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        $is_valid_date = $date_obj && $date_obj->format('Y-m-d') === $date;
    }
    if (!$is_valid_date || !array_key_exists($type, $diario_types)) {
        $type = "";
    }
}

if (!empty($type) && !empty($date)) {
    $type_file = "$alumno_path/Diario/$date/$type.json";
    
    if (file_exists($type_file)):
        $diary_data = json_decode(file_get_contents($type_file), true);
        
        // For Panel type, show the data
        if ($type === "Panel" && is_array($diary_data)):
        ?>
        
        <div style="margin-top: 30px; border-top: 2px solid #ddd; padding-top: 20px;">
            <h2>Detalles del Registro - <?= htmlspecialchars($type) ?> (<?= date('d/m/Y', strtotime($date)); ?>)</h2>
            
            <div style="background: #f9f9f9; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <p style="margin: 0;">
                    <strong>Registro creado:</strong> <?= htmlspecialchars($diary_data['date'] ?? 'N/A'); ?><br>
                    <strong>Alumno:</strong> <?= htmlspecialchars($diary_data['alumno'] ?? 'N/A'); ?>
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <?php
                $panel_labels = [
                    'quien_soy' => '¿Quién soy?',
                    'calendar' => '¿Qué día es?',
                    'calendario_diasemana' => 'Día de la semana',
                    'calendario_mes' => 'Mes',
                    'actividades' => '¿Qué vamos a hacer?',
                    'menu' => '¿Qué vamos a comer?'
                ];
                
                foreach ($diary_data['panels'] ?? [] as $panel_name => $panel_info):
                    $label = $panel_labels[$panel_name] ?? $panel_name;
                    $completed = !is_null($panel_info) && isset($panel_info['completed']);
                    $timestamp = $completed ? $panel_info['timestamp'] : 'No completado';
                    $panel_data = $completed && isset($panel_info['data']) ? $panel_info['data'] : [];
                ?>
                <div class="card" style="border: 2px solid <?= $completed ? '#28a745' : '#dc3545'; ?>; padding: 15px;">
                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                        <div style="font-size: 1.5rem;">
                            <?= $completed ? '✓' : '✗'; ?>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 5px 0; color: #333;"><?= htmlspecialchars($label); ?></h4>
                            <p style="margin: 0 0 10px 0; font-size: 0.85rem; color: #666;">
                                <?= $timestamp; ?>
                            </p>
                            
                            <?php if ($completed && !empty($panel_data)): ?>
                                <div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px;">
                                    <?php if ($panel_name === 'quien_soy' && !empty($panel_data['alumno'])): ?>
                                        <p style="margin: 5px 0;"><strong>Alumno:</strong> <?= htmlspecialchars($panel_data['alumno']); ?></p>
                                        <?php if (!empty($panel_data['photoUrl'])): ?>
                                            <img src="<?= htmlspecialchars($panel_data['photoUrl']); ?>" alt="Foto" style="max-height: 60px; max-width: 100px; margin-top: 5px; border-radius: 5px;">
                                        <?php endif; ?>
                                    <?php elseif ($panel_name === 'calendar' && !empty($panel_data['dia'])): ?>
                                        <p style="margin: 5px 0;"><strong>Día:</strong> <?= htmlspecialchars($panel_data['dia'] . '/' . $panel_data['mes'] . '/' . $panel_data['year']); ?></p>
                                    <?php elseif ($panel_name === 'calendario_diasemana' && !empty($panel_data['nombre'])): ?>
                                        <p style="margin: 5px 0;"><strong>Día:</strong> <?= htmlspecialchars($panel_data['nombre']); ?></p>
                                        <?php if (!empty($panel_data['pictogram'])): ?>
                                            <img src="<?= htmlspecialchars($panel_data['pictogram']); ?>" alt="Pictograma" style="max-height: 60px; margin-top: 5px;">
                                        <?php endif; ?>
                                    <?php elseif ($panel_name === 'calendario_mes' && !empty($panel_data['nombre'])): ?>
                                        <p style="margin: 5px 0;"><strong>Mes:</strong> <?= htmlspecialchars($panel_data['nombre']); ?></p>
                                        <?php if (!empty($panel_data['pictogram'])): ?>
                                            <img src="<?= htmlspecialchars($panel_data['pictogram']); ?>" alt="Pictograma" style="max-height: 60px; margin-top: 5px;">
                                        <?php endif; ?>
                                    <?php elseif ($panel_name === 'actividades' && !empty($panel_data['actividad'])): ?>
                                        <p style="margin: 5px 0;"><strong>Actividad:</strong> <?= htmlspecialchars($panel_data['actividad']); ?></p>
                                        <?php if (!empty($panel_data['pictogram'])): ?>
                                            <img src="<?= htmlspecialchars($panel_data['pictogram']); ?>" alt="Pictograma" style="max-height: 60px; margin-top: 5px;">
                                        <?php endif; ?>
                                    <?php elseif ($panel_name === 'menu' && !empty($panel_data['menuType'])): ?>
                                        <p style="margin: 5px 0;"><strong>Menú:</strong> <?= htmlspecialchars($panel_data['menuType']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 5px; border-left: 4px solid #667eea;">
                <p style="margin: 0; font-size: 0.9rem; color: #333;">
                    <strong>💡 Nota:</strong> Este diario se genera automáticamente cuando el alumno completa los paneles en la aplicación del Panel Diario.
                </p>
            </div>
        </div>
        <?php
        endif;
    endif;
}
?>

<style>
    .badge {
        display: inline-block;
    }
    
    .btn {
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        font-size: 0.95rem;
        transition: all 0.3s;
    }
    
    .btn-primary {
        background: #667eea;
        color: white;
    }
    
    .btn-primary:hover {
        background: #5568d3;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
    }
</style>

<?php
require_once "_incl/post-body.php";
