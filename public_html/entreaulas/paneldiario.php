<?php
require_once "_incl/auth_redir.php";
require_once "_incl/tools.security.php";
ini_set("display_errors", "0");
// Funciones auxiliares para el diario
function getDiarioPath($alumno, $centro_id, $aulario_id) {
  // Validate path components to avoid directory traversal or illegal characters
  // Allow only alphanumeric, underscore and dash for alumno and aulario_id
  $idPattern = '/^[A-Za-z0-9_-]+$/';
  // Typically centro_id is numeric; restrict it accordingly
  $centroPattern = '/^[0-9]+$/';

  if (!preg_match($idPattern, (string)$alumno) ||
      !preg_match($idPattern, (string)$aulario_id) ||
      !preg_match($centroPattern, (string)$centro_id)) {
    // Invalid identifiers, do not construct a filesystem path
    return null;
  }

  // Extra safety: strip any directory components if present
  $alumno_safe = basename($alumno);
  $centro_safe = basename($centro_id);
  $aulario_safe = basename($aulario_id);

  $base_path = "/DATA/entreaulas/Centros/$centro_safe/Aularios/$aulario_safe/Alumnos/$alumno_safe";
  return $base_path . "/Diario/" . date("Y-m-d");
}

function initDiario($alumno, $centro_id, $aulario_id) {
  $diario_path = getDiarioPath($alumno, $centro_id, $aulario_id);
  if ($diario_path) {
    @mkdir($diario_path, 0755, true);
    $panel_file = $diario_path . "/Panel.json";
    if (!file_exists($panel_file)) {
      $data = [
        "date" => date("Y-m-d H:i:s"),
        "alumno" => $alumno,
        "panels" => [
          "quien_soy" => null,
          "calendar" => null,
          "calendario_diasemana" => null,
          "calendario_mes" => null,
          "actividades" => null,
          "menu" => null
        ]
      ];
      file_put_contents($panel_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
  }
}

function guardarPanelDiario($panel_name, $data, $alumno, $centro_id, $aulario_id) {
  $diario_path = getDiarioPath($alumno, $centro_id, $aulario_id);
  if ($diario_path) {
    $panel_file = $diario_path . "/Panel.json";
    if (file_exists($panel_file)) {
      $existing = json_decode(file_get_contents($panel_file), true);
      if (is_array($existing)) {
        $existing["panels"][$panel_name] = [
          "completed" => true,
          "timestamp" => date("Y-m-d H:i:s"),
          "data" => $data
        ];
        file_put_contents($panel_file, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
      }
    }
  }
}

// Manejo de AJAX para guardar paneles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_GET['api'])) {
  header('Content-Type: application/json');
  $api_action = $_GET['api'];
  $alumno = $_SESSION["entreaulas_selected_alumno"] ?? '';
  $centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"] ?? '';
  
  if ($api_action === 'guardar_panel' && $alumno && $centro_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $panel_name = $input['panel'] ?? '';
    $panel_data = $input['data'] ?? [];
    $aulario_id = $_SESSION["entreaulas_selected_aulario"] ?? '';
    guardarPanelDiario($panel_name, $panel_data, $alumno, $centro_id, $aulario_id);
    echo json_encode(['success' => true]);
    die();
  }
}

switch ($_GET["form"]) {
  case "alumno_selected":
    $alumno = $_GET["alumno"] ?? "";
    $centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"] ?? "";
    $aulario_id = $_GET["aulario"] ?? '';
    $photo_url = $_GET["photo"] ?? '';
    if ($alumno !== "" && $centro_id !== "" && $aulario_id !== "") {
      $_SESSION["entreaulas_selected_alumno"] = $alumno;
      $_SESSION["entreaulas_selected_aulario"] = $aulario_id;
      initDiario($alumno, $centro_id, $aulario_id);
      // Guardar el panel "quien_soy" como completado con foto URL si existe
      $who_am_i_data = ["alumno" => $alumno];
      if ($photo_url !== '') {
        $who_am_i_data["photoUrl"] = $photo_url;
      }
      guardarPanelDiario("quien_soy", $who_am_i_data, $alumno, $centro_id, $aulario_id);
      header("Location: paneldiario.php?aulario=" . urlencode($_GET["aulario"] ?? ''));
      die();
    }
    break;
}
require_once "_incl/pre-body.php";
ini_set("display_errors", "0");
?>
<audio id="win-sound" src="/static/sounds/win.mp3" preload="auto"></audio>
<audio id="lose-sound" src="/static/sounds/lose.mp3" preload="auto"></audio>
<audio id="click-sound" src="/static/sounds/click.mp3" preload="auto"></audio>
<script>
  function announceAndMaybeRedirect(message, redirectUrl, success) {
    // Get references to both audio elements
    const winAudio = document.getElementById('win-sound');
    const loseAudio = document.getElementById('lose-sound');
    const selectedAudio = success ? winAudio : loseAudio;
    const otherAudio = success ? loseAudio : winAudio;

    // Pause both audios to ensure clean state
    if (winAudio) {
      winAudio.pause();
      winAudio.currentTime = 0;
    }
    if (loseAudio) {
      loseAudio.pause();
      loseAudio.currentTime = 0;
    }

    const doRedirect = () => {
      if (redirectUrl) {
        window.location.href = redirectUrl;
      }
    };

    const playFallback = () => {
      try {
        if (!selectedAudio) {
          console.warn("Audio element not found");
          doRedirect();
          return;
        }
        selectedAudio.currentTime = 0;
        selectedAudio.onended = () => {
          selectedAudio.onended = null;
          doRedirect();
        };
        selectedAudio.play().catch(doRedirect);
      } catch (e) {
        console.warn("Error playing fallback audio:", e);
        doRedirect();
      }
    };

    if (!message) {
      playFallback();
      return;
    }

    if (!('speechSynthesis' in window)) {
      console.warn("Speech synthesis not supported");
      playFallback();
      return;
    }

    try {
      window.speechSynthesis.cancel();
      const utterance = new SpeechSynthesisUtterance(message);
      let finished = false;
      let started = false;
      const finalize = (fn) => {
        if (finished) return;
        finished = true;
        fn();
      };
      const ttsTimeout = setTimeout(() => {
        if (!started) {
          console.warn("Speech synthesis did not start; using fallback");
          finalize(playFallback);
        }
      }, 750);
      utterance.lang = 'es-ES';
      utterance.rate = 1;
      utterance.onstart = () => {
        started = true;
        clearTimeout(ttsTimeout);
      };
      utterance.onend = () => {
        clearTimeout(ttsTimeout);
        finalize(doRedirect);
      };
      utterance.onerror = () => {
        clearTimeout(ttsTimeout);
        console.warn("Speech synthesis error; using fallback");
        finalize(playFallback);
      };
      window.speechSynthesis.speak(utterance);
    } catch (e) {
      console.warn("Error with speech synthesis:", e);
      playFallback();
    }
  }
</script>
<?php
// Verificar si hay un alumno seleccionado y cargar su progreso
$alumno_actual = $_SESSION["entreaulas_selected_alumno"] ?? '';
$centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"] ?? '';
$aulario_id = $_GET["aulario"] ?? '';

$diario_data = null;
$progress = [];
if ($alumno_actual && $centro_id) {
  $diario_path = getDiarioPath($alumno_actual, $centro_id, $aulario_id);
  if ($diario_path) {
    $panel_file = $diario_path . "/Panel.json";
    if (file_exists($panel_file)) {
      $diario_data = json_decode(file_get_contents($panel_file), true);
      if (is_array($diario_data) && isset($diario_data['panels'])) {
        foreach ($diario_data['panels'] as $panel_name => $panel_value) {
          $progress[$panel_name] = !is_null($panel_value) && isset($panel_value['completed']);
        }
      }
    }
  }
}

// Contar paneles completados
$paneles_totales = 6; // quien_soy, calendar, calendario_diasemana, calendario_mes, actividades, menu
$paneles_completados = count(array_filter($progress));
$porcentaje = ($paneles_completados / $paneles_totales) * 100;
$todos_completados = ($paneles_completados === $paneles_totales);

switch ($_GET["action"]) {
  default:
  case "index":
    if ($alumno_actual):
?>
    <div class="card pad">
      <h2>Panel de Diario - <?php echo htmlspecialchars($alumno_actual); ?></h2>
      <div class="progress-bar">
        <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%"></div>
      </div>
      <p style="text-align: center; margin-top: 10px;">
        <?php echo $paneles_completados; ?> de <?php echo $paneles_totales; ?> paneles completados
      </p>
    </div>
<?php
    endif;
?>
    <div class="grid">
      <!-- ¿Quién soy? -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=quien_soy&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-<?= $progress['quien_soy'] ?? false ? 'success' : 'primary'; ?> grid-item <?php echo $progress['quien_soy'] ?? false ? 'completed' : ''; ?>">
        <img src="/static/arasaac/yo.png" height="125" class="bg-white">
        <br>
        ¿Quién soy?
      </a>
      <!-- Calendario -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=calendar&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-<?= $progress['calendar'] ?? false ? 'success' : 'primary'; ?> grid-item <?php echo $progress['calendar'] ?? false ? 'completed' : ''; ?>">
        <img src="/static/arasaac/calendario.png" height="125" class="bg-white">
        <br>
        ¿Que dia es?
      </a>
      <!-- Actividades -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=actividades&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-<?= $progress['actividades'] ?? false ? 'success' : 'primary'; ?> grid-item <?php echo $progress['actividades'] ?? false ? 'completed' : ''; ?>">
        <img src="/static/arasaac/actividad.png" height="125" class="bg-white">
        <br>
        ¿Que vamos a hacer?
      </a>
      <!-- Menú del comedor -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=menu&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-<?= $progress['menu'] ?? false ? 'success' : 'primary'; ?> grid-item <?php echo $progress['menu'] ?? false ? 'completed' : ''; ?>">
        <img src="/static/arasaac/comedor.png" height="125" class="bg-white">
        <br>
        ¿Que vamos a comer?
      </a>
    </div>

    <?php if ($todos_completados && $alumno_actual): ?>
      <div style="margin-top: 20px;">
        <a onclick="document.getElementById('click-sound').play()" href="?action=resumen&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-success" style="width: 100%; padding: 20px; font-size: 1.2rem; text-align: center;">
          📋 Ver Resumen Imprimible
        </a>
      </div>
    <?php endif; ?>

    <style>
      .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 10px;
        align-items: start;
      }

      .grid-item {
        margin-bottom: 0 !important;
        padding: 15px;
        width: 100%;
        text-align: center;
        position: relative;
      }

      .grid-item.completed {
        border: 3px solid #28a745;
        box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
      }

      .badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #28a745;
        color: white;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
      }

      .grid-item img {
        margin: 0 auto;
        height: 125px;
      }

      .progress-bar {
        width: 100%;
        height: 30px;
        background: #e9ecef;
        border-radius: 15px;
        overflow: hidden;
        margin: 10px 0;
      }

      .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #0d6efd, #0d6efd);
        transition: width 0.3s ease;
      }
    </style>

  <?php
    break;

  case "resumen":
    // Mostrar resumen imprimible
    if ($alumno_actual && $centro_id && $diario_data):
  ?>
    <div class="resumen-container">
      <div class="resumen-header">
        <h1>Resumen del Diario</h1>
        <p class="resumen-info">
          <strong>Alumno:</strong> <?php echo htmlspecialchars($alumno_actual); ?><br>
          <strong>Fecha:</strong> <?php echo date('d/m/Y'); ?><br>
          <strong>Hora de registro:</strong> <?php echo date('H:i:s'); ?>
        </p>
      </div>

      <div class="resumen-content">
        <h2>Paneles Completados</h2>
        
        <?php
        $panel_labels = [
          'quien_soy' => '¿Quién soy?',
          'calendar' => '¿Qué día es?',
          'calendario_diasemana' => 'Día de la semana',
          'calendario_mes' => 'Mes',
          'actividades' => '¿Qué vamos a hacer?',
          'menu' => '¿Qué vamos a comer?'
        ];

        foreach ($diario_data['panels'] as $panel_name => $panel_info):
          $label = $panel_labels[$panel_name] ?? $panel_name;
          $completed = !is_null($panel_info) && isset($panel_info['completed']);
          $timestamp = $completed ? $panel_info['timestamp'] : 'No completado';
          $panel_data = $completed && isset($panel_info['data']) ? $panel_info['data'] : [];
        ?>
          <div class="resumen-item <?php echo $completed ? 'completado' : 'pendiente'; ?>">
            <div class="resumen-item-status">
              <?php echo $completed ? '✓' : '✗'; ?>
            </div>
            <div class="resumen-item-content">
              <h3><?php echo htmlspecialchars($label); ?></h3>
              <p class="timestamp"><?php echo $timestamp; ?></p>
              
              <?php if ($completed && !empty($panel_data)): ?>
                <div class="resumen-item-data">
                  <?php if ($panel_name === 'quien_soy' && !empty($panel_data['alumno'])): ?>
                    <p><strong>Alumno seleccionado:</strong> <?php echo htmlspecialchars($panel_data['alumno']); ?></p>
                    <?php if (!empty($panel_data['photoUrl'])): ?>
                      <img class="resumen-thumb" src="<?php echo htmlspecialchars($panel_data['photoUrl']); ?>" alt="Foto del alumno">
                    <?php endif; ?>
                  <?php elseif ($panel_name === 'calendar' && !empty($panel_data['dia'])): ?>
                    <p><strong>Día seleccionado:</strong> <?php echo htmlspecialchars($panel_data['dia'] . '/' . $panel_data['mes'] . '/' . $panel_data['year']); ?></p>
                  <?php elseif ($panel_name === 'calendario_diasemana' && !empty($panel_data['nombre'])): ?>
                    <p><strong>Día de la semana:</strong> <?php echo htmlspecialchars($panel_data['nombre']); ?></p>
                    <?php if (!empty($panel_data['pictogram'])): ?>
                      <img class="resumen-thumb" src="<?php echo htmlspecialchars($panel_data['pictogram']); ?>" alt="<?php echo htmlspecialchars($panel_data['nombre']); ?>">
                    <?php endif; ?>
                  <?php elseif ($panel_name === 'calendario_mes' && !empty($panel_data['nombre'])): ?>
                    <p><strong>Mes seleccionado:</strong> <?php echo htmlspecialchars($panel_data['nombre']); ?></p>
                    <?php if (!empty($panel_data['pictogram'])): ?>
                      <img class="resumen-thumb" src="<?php echo htmlspecialchars($panel_data['pictogram']); ?>" alt="<?php echo htmlspecialchars($panel_data['nombre']); ?>">
                    <?php endif; ?>
                  <?php elseif ($panel_name === 'actividades' && !empty($panel_data['actividad'])): ?>
                    <p><strong>Actividad:</strong> <?php echo htmlspecialchars($panel_data['actividad']); ?></p>
                    <?php if (!empty($panel_data['pictogram'])): ?>
                      <img class="resumen-thumb" src="<?php echo htmlspecialchars($panel_data['pictogram']); ?>" alt="<?php echo htmlspecialchars($panel_data['actividad']); ?>">
                    <?php endif; ?>
                  <?php elseif ($panel_name === 'menu' && !empty($panel_data['menuType'])): ?>
                    <p><strong>Tipo de menú:</strong> <?php echo htmlspecialchars($panel_data['menuType']); ?></p>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="resumen-footer">
        <button onclick="window.print();" class="btn btn-primary" style="margin-right: 10px;">
          🖨️ Imprimir Resumen
        </button>
        <a href="?action=index&aulario=<?php echo urlencode($aulario_id); ?>" class="btn btn-secondary">
          ← Volver
        </a>
      </div>
    </div>

    <style>
      @media print {
        body {
          background: white;
          margin: 0;
          padding: 0;
        }
        .resumen-footer {
          display: none;
        }
        .grid, .card, .btn {
          page-break-inside: avoid;
        }
      }

      .resumen-container {
        max-width: 800px;
        margin: 0 auto;
      }

      .resumen-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 30px;
        text-align: center;
      }

      .resumen-header h1 {
        margin: 0 0 15px 0;
        font-size: 2.5rem;
      }

      .resumen-info {
        margin: 0;
        font-size: 1.1rem;
        line-height: 1.8;
      }

      .resumen-content {
        margin-bottom: 30px;
      }

      .resumen-content h2 {
        color: #333;
        border-bottom: 3px solid #667eea;
        padding-bottom: 10px;
        margin-bottom: 20px;
      }

      .resumen-item {
        display: flex;
        align-items: flex-start;
        background: white;
        border: 2px solid #ddd;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
      }

      .resumen-item.completado {
        border-color: #28a745;
        background: #f0f8f4;
      }

      .resumen-item.pendiente {
        border-color: #dc3545;
        background: #f8f0f0;
      }

      .resumen-item-status {
        font-size: 1.8rem;
        font-weight: bold;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin-right: 15px;
        flex-shrink: 0;
      }

      .resumen-item.completado .resumen-item-status {
        background: #28a745;
        color: white;
      }

      .resumen-item.pendiente .resumen-item-status {
        background: #dc3545;
        color: white;
      }

      .resumen-item-content h3 {
        margin: 0 0 5px 0;
        color: #333;
      }

      .resumen-item-content .timestamp {
        margin: 0;
        color: #666;
        font-size: 0.9rem;
      }

      .resumen-item-data {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid rgba(0,0,0,0.1);
      }

      .resumen-item-data p {
        margin: 5px 0;
        font-size: 0.95rem;
      }

      .resumen-thumb {
        max-height: 100px;
        max-width: 150px;
        margin-top: 10px;
        border-radius: 5px;
        border: 1px solid #ddd;
        display: inline-block;
      }

      .resumen-footer {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 30px;
      }

      .resumen-footer .btn {
        padding: 12px 30px;
        font-size: 1rem;
        text-decoration: none;
        border-radius: 5px;
        cursor: pointer;
        border: none;
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
    else:
      echo '<div class="card pad"><p>Error: No hay datos de diario disponibles.</p></div>';
    endif;
    break;

  case "quien_soy":
    // ¿Quién soy? - Identificación del alumno
    $aulario_id = basename($_GET["aulario"] ?? '');
    $centro_id = basename($_SESSION["auth_data"]["entreaulas"]["centro"] ?? '');

    // Validate parameters
    if (empty($aulario_id) || empty($centro_id)) {
      echo '<div class="card pad"><p>Error: Parámetros inválidos.</p></div>';
      break;
    }

    $base_path = "/DATA/entreaulas/Centros";
    $alumnos_path = "$base_path/$centro_id/Aularios/$aulario_id/Alumnos";

    // Validate the path is within the expected directory
    $real_path = realpath($alumnos_path);
    $real_base = realpath($base_path);

    $alumnos = [];
    if ($real_path !== false && $real_base !== false && strpos($real_path, $real_base) === 0 && is_dir($real_path)) {
      $alumnos = glob($real_path . "/*", GLOB_ONLYDIR);
    }
  ?>
    <script>
      function seleccionarAlumno(element, nombre, hasPhoto, centro, aulario) {
        element.style.backgroundColor = "#9cff9f"; // Verde
        let photoUrl = '';
        if (hasPhoto) {
          photoUrl = '/entreaulas/_filefetch.php?type=alumno_photo&alumno=' + encodeURIComponent(nombre) + 
                    '&centro=' + encodeURIComponent(centro) + '&aulario=' + encodeURIComponent(aulario);
        }
        announceAndMaybeRedirect(
          "¡Hola " + nombre + "!",
          "/entreaulas/paneldiario.php?aulario=" + encodeURIComponent(aulario) + "&form=alumno_selected&alumno=" + encodeURIComponent(nombre) + 
          (photoUrl ? "&photo=" + encodeURIComponent(photoUrl) : ''),
          true
        );
      }
    </script>
    <div class="card pad">
      <div>
        <h1 class="card-title">¿Quién soy?</h1>
      </div>
    </div>
    <div class="grid">
      <?php
      if (empty($alumnos)) {
      ?>
        <div class="card pad" style="width: 100%;">
          <p>No hay alumnos registrados en este aulario.</p>
          <p>Para añadir alumnos, accede al inicio del aulario.</p>
        </div>
        <?php
      } else {
        foreach ($alumnos as $alumno_path) {
          $alumno_name = basename($alumno_path);
          $photo_path = $alumno_path . "/photo.jpg";
          $photo_exists = file_exists($photo_path);
        ?>
          <a href="#" class="card grid-item" style="color: black;" onclick='seleccionarAlumno(this, "<?php echo htmlspecialchars($alumno_name, ENT_QUOTES); ?>", <?php echo $photo_exists ? 'true' : 'false'; ?>, "<?php echo htmlspecialchars($centro_id, ENT_QUOTES); ?>", "<?php echo htmlspecialchars($aulario_id, ENT_QUOTES); ?>");' aria-label="Seleccionar alumno <?php echo htmlspecialchars($alumno_name); ?>">
            <?php if ($photo_exists): ?>
              <img src="_filefetch.php?type=alumno_photo&alumno=<?php echo urlencode($alumno_name); ?>&centro=<?php echo urlencode($centro_id); ?>&aulario=<?php echo urlencode($aulario_id); ?>" height="150" class="bg-white" alt="Foto de <?php echo htmlspecialchars($alumno_name); ?>">
            <?php else: ?>
              <div style="width: 150px; height: 150px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 2px dashed #ccc;">
                <span style="font-size: 48px;">?</span>
              </div>
            <?php endif; ?>
            <br>
            <span style="font-size: 20px; font-weight: bold;"><?php echo htmlspecialchars($alumno_name); ?></span>
          </a>
      <?php
        }
      }
      ?>
    </div>
    <style>
      .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 10px;
        align-items: start;
      }

      .grid-item {
        margin-bottom: 0 !important;
        padding: 15px;
        width: 100%;
        text-align: center;
        text-decoration: none;
        align-items: center;
        display: flex;
        flex-direction: column;
      }

      .grid-item img {
        margin: 0 auto;
        height: 150px;
        border-radius: 10px;
        border: 3px solid #ddd;
      }
    </style>
  <?php
    break;
  case "actividades":
    $actividades = glob("/DATA/entreaulas/Centros/" . $_SESSION["auth_data"]["entreaulas"]["centro"] . "/Panel/Actividades/*", GLOB_ONLYDIR);
  ?>
    <script>
      function seleccionarActividad(element, actividad, pictogramUrl) {
        element.style.backgroundColor = "#9cff9f"; // Verde
        
        // Guardar al diario antes de redirigir
        fetch('/entreaulas/paneldiario.php?api=guardar_panel', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            panel: 'actividades',
            data: { 
              actividad: actividad,
              pictogram: pictogramUrl
            }
          })
        }).finally(() => {
          announceAndMaybeRedirect(
            actividad + ", Actividad seleccionada",
            "/entreaulas/paneldiario.php?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
            true
          );
        });
      }
    </script>
    <div class="card pad">
      <div>
        <h1 class="card-title">¿Que vamos a hacer?</h1>
      </div>
    </div>
    <div class="grid">
      <?php foreach ($actividades as $actividad_path) {
        $actividad_name = basename($actividad_path);
        $pictogram_url = '/entreaulas/_filefetch.php?type=panel_actividades&activity=' . urlencode($actividad_name) . '&centro=' . urlencode($_SESSION["auth_data"]["entreaulas"]["centro"]);
      ?>
        <a class="card grid-item" style="color: black;" onclick="seleccionarActividad(this, '<?php echo htmlspecialchars($actividad_name); ?>', '<?php echo htmlspecialchars($pictogram_url); ?>');">
          <img src="<?php echo htmlspecialchars($pictogram_url); ?>" height="125" class="bg-white">
          <?php echo htmlspecialchars($actividad_name); ?>
        </a>
      <?php } ?>
    </div>
    <style>
      .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 10px;
        align-items: start;
      }

      .grid-item {
        margin-bottom: 0 !important;
        padding: 15px;
        width: 100%;
        text-align: center;
        text-decoration: none;
      }

      .grid-item img {
        margin: 0 auto;
        height: 150px;
      }
    </style>
  <?php
    break;
  case "menu":
    // Menú del comedor (nuevo sistema, vista simplificada)
    $aulario_id = Sf($_GET["aulario"] ?? '');
    $centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"] ?? "";

    $source_aulario_id = $aulario_id;
    $is_shared = false;
    if ($aulario_id !== "" && $centro_id !== "") {
      $aulario_path = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json";
      $aulario = file_exists($aulario_path) ? json_decode(file_get_contents($aulario_path), true) : null;
      if ($aulario && !empty($aulario["shared_comedor_from"])) {
        $shared_from = Sf($aulario["shared_comedor_from"]);
        $shared_aulario_path = "/DATA/entreaulas/Centros/$centro_id/Aularios/$shared_from.json";
        if (file_exists($shared_aulario_path)) {
          $source_aulario_id = $shared_from;
          $is_shared = true;
        }
      }
    }

    $dateParam = $_GET["date"] ?? date("Y-m-d");
    $dateObj = DateTime::createFromFormat("Y-m-d", $dateParam) ?: new DateTime();
    $date = $dateObj->format("Y-m-d");

    $menuTypesPath = "/DATA/entreaulas/Centros/$centro_id/Aularios/$source_aulario_id/Comedor-MenuTypes.json";
    $defaultMenuTypes = [
      ["id" => "basal", "label" => "Menú basal", "color" => "#0d6efd"],
      ["id" => "vegetariano", "label" => "Menú vegetariano", "color" => "#198754"],
      ["id" => "alergias", "label" => "Menú alergias", "color" => "#dc3545"],
    ];
    $menuTypes = json_decode(@file_get_contents($menuTypesPath), true);
    if (!is_array($menuTypes) || count($menuTypes) === 0) {
      $menuTypes = $defaultMenuTypes;
    }

    $menuTypeIds = [];
    foreach ($menuTypes as $t) {
      if (!empty($t["id"])) {
        $menuTypeIds[] = $t["id"];
      }
    }
    $menuTypeId = $_GET["menu"] ?? ($menuTypeIds[0] ?? "basal");
    if (!in_array($menuTypeId, $menuTypeIds, true)) {
      $menuTypeId = $menuTypeIds[0] ?? "basal";
    }

    $ym = $dateObj->format("Y-m");
    $day = $dateObj->format("d");
    $dataPath = "/DATA/entreaulas/Centros/$centro_id/Aularios/$source_aulario_id/Comedor/$ym/$day/_datos.json";

    $menuData = [
      "date" => $date,
      "menus" => []
    ];
    if (file_exists($dataPath)) {
      $existing = json_decode(file_get_contents($dataPath), true);
      if (is_array($existing)) {
        $menuData = array_merge($menuData, $existing);
      }
    }
    $menuForType = $menuData["menus"][$menuTypeId] ?? null;

    function image_src_simple($value, $centro_id, $aulario_id, $date)
    {
      if (!$value) {
        return "";
      }
      return "/entreaulas/_filefetch.php?type=comedor_image&centro=" . urlencode($centro_id) . "&aulario=" . urlencode($aulario_id) . "&date=" . urlencode($date) . "&file=" . urlencode($value);
    }
  ?>
    <script>
      function seleccionarMenuTipo(element, hasData, menu_ty) {
        element.style.backgroundColor = "#9cff9f"; // Verde
        
        // Guardar al diario antes de redirigir
        fetch('/entreaulas/paneldiario.php?api=guardar_panel', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            panel: 'menu',
            data: { menuType: menu_ty }
          })
        }).finally(() => {
          announceAndMaybeRedirect(
            menu_ty + ", Menú seleccionado",
            "/entreaulas/paneldiario.php?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
            true
          );
        });
      }
    </script>
    <div class="card pad">
      <h1>¿Qué vamos a comer?</h1>
      <div class="text-muted"><?= htmlspecialchars($date) ?></div>
    </div>

    <div class="menum-grid">
      <?php
      $plates = [
        "primero" => "Primer plato",
        "segundo" => "Segundo plato",
        "postre" => "Postre"
      ];
      foreach ($menuTypes as $type):
        $typeId = $type["id"] ?? "";
        $typeLabel = $type["label"] ?? $typeId;
        $typeColor = $type["color"] ?? "#0d6efd";
        $menuItem = $menuData["menus"][$typeId] ?? null;
        $hasData = false;
        if (is_array($menuItem)) {
          foreach ($plates as $plateKey => $plateLabel) {
            $plate = $menuItem["plates"][$plateKey] ?? [];
            if (!empty($plate["name"]) || !empty($plate["pictogram"]) || !empty($plate["photo"])) {
              $hasData = true;
              break;
            }
          }
        }
      ?>
        <div class="card pad menum-card" onclick="seleccionarMenuTipo(this, <?php echo $hasData ? 'true' : 'false'; ?>, '<?= htmlspecialchars($typeLabel) ?>');" style="cursor: pointer; border: 4px solid <?= htmlspecialchars($typeColor) ?>;">
          <h3 class="menum-title" style="color: <?= htmlspecialchars($typeColor) ?>;">
            <?= htmlspecialchars($typeLabel) ?>
          </h3>
          <?php if (!$hasData): ?>
            <div class="menum-placeholder">Menú no disponible</div>
          <?php else: ?>
            <div class="menum-lines">
              <?php
              $loop = 0;
              foreach ($plates as $plateKey => $plateLabel):
                $loop++;
                $plate = $menuItem["plates"][$plateKey] ?? ["name" => "", "pictogram" => "", "photo" => ""];
                $pictSrc = image_src_simple($plate["pictogram"] ?? "", $centro_id, $source_aulario_id, $date);
              ?>
                <div class="menum-line">
                  <?php if ($pictSrc !== ""): ?>
                    <img class="menum-line-img" src="<?= htmlspecialchars($pictSrc) ?>" alt="<?= htmlspecialchars($plateLabel) ?>">
                  <?php else: ?>
                    <div class="menum-line-img placeholder">
                      <!-- Nª Plato --><?= $loop ?>
                    </div>
                  <?php endif; ?>
                  <div class="menum-line-name">
                    <?= $plate["name"] !== "" ? htmlspecialchars($plate["name"]) : "?" ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <style>
      .menum-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 12px;
        align-items: stretch;
        min-width: 0;
      }

      .menum-card {
        min-height: 260px;
        width: 100%;
        min-width: 0;
      }

      .menum-title {
        font-size: 1.6rem;
        margin-bottom: 10px;
      }

      .menum-lines {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        min-width: 0;
      }

      .menum-line {
        display: grid;
        grid-template-columns: 100px minmax(0, 1fr);
        align-items: center;
        gap: 8px;
        background: #fff;
        border-radius: 10px;
        padding: 6px 8px;
        border: 2px solid #eee;
        min-width: 0;
      }

      .menum-line-title {
        font-weight: bold;
      }

      .menum-line-img {
        width: 100px;
        height: 100px;
        object-fit: contain;
        background: #fff;
        border-radius: 8px;
        border: 2px solid #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
      }

      .menum-line-img.placeholder {
        background: #f1f1f1;
        border-style: dashed;
        color: #333;
        font-size: 5rem;
      }

      .menum-line-name {
        font-size: 1.1rem;
        font-weight: bold;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .menum-placeholder {
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f1f1;
        border-radius: 12px;
        border: 2px dashed #aaa;
        color: #666;
        font-weight: bold;
      }
    </style>
  <?php
    break;
  case "calendar":
    // Calendario, elegir el dia, mes, y dia-de-la-semana.
    $mes_correcto = date('m');
    $dia_correcto = date('d');
    $ds_correcto = date('N'); // 1 (Lunes) a 7 (Domingo)
    $first_ds = date('N', strtotime(date('Y-m-01'))); // 1 (Lunes) a 7 (Domingo)
    $days_in_month = (int) date('t');
  ?>
    <div class="card pad">
      <h1>¿Que dia es?</h1>
    </div>
    <div class="calendar-grid">
      <script>
        function seleccionarDia(element, dia, mes, year, ds) {
          // Si es dia correcto
          if (dia == <?php echo $dia_correcto; ?> && mes == <?php echo $mes_correcto; ?> && ds == <?php echo $ds_correcto; ?>) {
            element.style.backgroundColor = "#9cff9f"; // Verde
            
            // Guardar al diario antes de redirigir
            fetch('/entreaulas/paneldiario.php?api=guardar_panel', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                panel: 'calendar',
                data: { dia: dia, mes: mes, year: year, diaSemana: ds }
              })
            }).finally(() => {
              announceAndMaybeRedirect(
                dia + ", Correcto",
                "?action=calendario_diasemana&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
                true
              );
            });
          } else {
            element.style.backgroundColor = "#ff9088"; // Rojo
            announceAndMaybeRedirect(dia + ", No es correcto", null, false);
            setTimeout(() => {
              element.style.backgroundColor = ""; // Volver al color anterior
            }, 2000);
          }
        }
      </script>
      <?php
      $leading_blanks = max(0, $first_ds - 1);
      for ($i = 0; $i < $leading_blanks; $i++) {
        echo '<div class="card grid-item calendar-empty"></div>';
      }

      foreach (range(1, $days_in_month) as $dia) {
        $ds = date('N', strtotime(date('Y-m-') . sprintf("%02d", $dia)));
        if ($ds > 5) {
      ?>
          <div class="card grid-item" style="background-color: #000; color: #fff; text-align: center;">
            <span style="font-size: 48px;"><?php echo $dia; ?></span>
          </div>
        <?php
          continue;
        }
        $is_today = ($dia == $dia_correcto);
        ?>
        <a class="card grid-item" style="color: black; text-align: center;"
          onclick="seleccionarDia(this, <?php echo $dia; ?>, <?php echo $mes_correcto; ?>, <?php echo date('Y'); ?>, <?php echo $ds; ?>);">
          <span style="font-size: 48px;"><?php echo $dia; ?></span>
        </a>
      <?php }

      $total_cells = $leading_blanks + $days_in_month;
      $trailing_blanks = (7 - ($total_cells % 7)) % 7;
      for ($i = 0; $i < $trailing_blanks; $i++) {
        echo '<div class="card grid-item calendar-empty"></div>';
      }
      ?>
    </div>
    <style>
      .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 10px;
        align-items: stretch;
        grid-auto-rows: 100px;
      }

      .grid-item {
        margin-bottom: 0 !important;
        padding: 15px;
        width: 100%;
        height: 100%;
        text-align: center;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .grid-item img {
        margin: 0 auto;
        height: 125px;
      }

      .calendar-empty {
        background: transparent;
        border: 2px dashed #e0e0e0;
        box-shadow: none;
      }

      .calendar-grid {
        min-width: 0;
      }
    </style>
  <?php
    break;
  case "calendario_diasemana":
    // Calendario - Día de la semana
    $dia_de_la_semana = date('N'); // 1 (Lunes) a 7 (Domingo)
  ?>
    <div class="card pad">
      <h1>¿Que día de la semana es?</h1>
    </div>
    <div class="grid">
      <script>
        function seleccionarDiaSemana(element, ds, pictogramUrl) {
          var dow = ["", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"];
          // Si es dia de la semana correcto
          if (ds == <?php echo $dia_de_la_semana; ?>) {
            element.style.backgroundColor = "#9cff9f"; // Verde
            
            // Guardar al diario antes de redirigir
            fetch('/entreaulas/paneldiario.php?api=guardar_panel', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                panel: 'calendario_diasemana',
                data: { diaSemana: ds, nombre: dow[ds], pictogram: pictogramUrl }
              })
            }).finally(() => {
              announceAndMaybeRedirect(
                dow[ds] + ", Correcto",
                "/entreaulas/paneldiario.php?action=calendario_mes&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
                true
              );
            });
          } else {
            element.style.backgroundColor = "#ff9088"; // Rojo
            announceAndMaybeRedirect(dow[ds] + ", No es correcto", null, false);
            setTimeout(() => {
              element.style.backgroundColor = ""; // Volver al color anterior
            }, 2000);
          }
        }
      </script>
      <?php
      $days_of_week = [
        1 => "Lunes",
        2 => "Martes",
        3 => "Miércoles",
        4 => "Jueves",
        5 => "Viernes"
      ];
      $dow_euskara = [
        1 => "Astelehena",
        2 => "Asteartea",
        3 => "Asteazkena",
        4 => "Osteguna",
        5 => "Ostirala"
      ];
      foreach ($days_of_week as $ds => $day_name) {
        $pictogram_url = '/static/arasaac/diadelasemana/' . strtolower($day_name) . '.png';
      ?>
        <a class="card grid-item" style="width: 225px; height: 225px; color: black;"
          onclick="seleccionarDiaSemana(this, <?php echo $ds; ?>, '<?php echo htmlspecialchars($pictogram_url); ?>');">
          <span style="font-size: 30px;"><?php echo $day_name; ?></span>
          <img src="<?php echo htmlspecialchars($pictogram_url); ?>" alt=""
            style="width: 100px; height: 100px;">
          <span style="font-size: 30px; color: blue;"><?php echo $dow_euskara[$ds]; ?></span>
        </a>
      <?php } ?>
    </div>
    <style>
      .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 100%;
        text-align: center;
        text-decoration: none;
      }

      .grid-item img {
        margin: 0 auto;
        height: 125px;
      }
    </style>
    <style>
      .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(225px, 1fr));
        gap: 10px;
        align-items: start;
      }
    </style>
  <?php
    break;
  case "calendario_mes":
    // Calendario - Mes
    $mes_correcto = date('m');
    $meses_esp = [
      1 => "Enero",
      2 => "Febrero",
      3 => "Marzo",
      4 => "Abril",
      5 => "Mayo",
      6 => "Junio",
      7 => "Julio",
      8 => "Agosto",
      9 => "Septiembre",
      10 => "Octubre",
      11 => "Noviembre",
      12 => "Diciembre"
    ];
    $meses_eus = [
      1 => "Urtarrila",
      2 => "Otsaila",
      3 => "Martxoa",
      4 => "Apirila",
      5 => "Maiatza",
      6 => "Ekaina",
      7 => "Uztaila",
      8 => "Abuztua",
      9 => "Iraila",
      10 => "Urria",
      11 => "Azaroa",
      12 => "Abendua"
    ];
  ?>
    <div class="card pad">
      <h1>¿Que mes es?</h1>
    </div>
    <div class="grid">
      <script>
        function seleccionarMes(element, mes, pictogramUrl) {
          // Si es mes correcto
          var meses = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
          if (mes == <?php echo $mes_correcto; ?>) {
            element.style.backgroundColor = "#9cff9f"; // Verde
            
            // Guardar al diario antes de redirigir
            fetch('/entreaulas/paneldiario.php?api=guardar_panel', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                panel: 'calendario_mes',
                data: { mes: mes, nombre: meses[mes], pictogram: pictogramUrl }
              })
            }).finally(() => {
              announceAndMaybeRedirect(
                meses[mes] + ", Correcto",
                "/entreaulas/paneldiario.php?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
                true
              );
            });
          } else {
            element.style.backgroundColor = "#ff9088"; // Rojo
            announceAndMaybeRedirect(meses[mes] + ", No es correcto", null, false);
            setTimeout(() => {
              element.style.backgroundColor = ""; // Volver al color anterior
            }, 2000);
          }
        }
      </script>
      <?php foreach ($meses_esp as $mes => $mes_name) {
        $pictogram_url = '/static/arasaac/mesesdelano/' . strtolower($mes_name) . '.png';
      ?>
        <a class="card grid-item" style="width: 180px; height: 180px; color: black;"
          onclick="seleccionarMes(this, <?php echo $mes; ?>, '<?php echo htmlspecialchars($pictogram_url); ?>');">
          <span style="font-size: 24px;"><?php echo $mes_name; ?></span>
          <img src="<?php echo htmlspecialchars($pictogram_url); ?>" alt=""
            style="width: 80px; height: 80px;">
          <span style="font-size: 24px; color: blue;"><?php echo $meses_eus[$mes]; ?></span>
        </a>
      <?php } ?>
    </div>
    <style>
      .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 100%;
        text-align: center;
        text-decoration: none;
      }

      .grid-item img {
        margin: 0 auto;
        height: 125px;
      }
    </style>
    <style>
      .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
        align-items: start;
      }
    </style>
<?php
    break;
}
require_once "_incl/post-body.php"; ?>