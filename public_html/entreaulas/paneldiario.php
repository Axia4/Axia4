<?php
require_once "_incl/auth_redir.php";
require_once "_incl/pre-body.php";
switch ($_GET["form"]) {
  case "menu_select":
    // Guardar menú seleccionado en la base de datos (a implementar)
    $selected_date = $_POST["fecha"];
    $plato1 = $_POST["plato1"];
    $plato2 = $_POST["plato2"];
    $postre = $_POST["postre"];
    // Aquí se debería guardar en la base de datos del aulario la selección del menú para la fecha indicada.
    // Por ahora, solo mostramos un mensaje de confirmación.
    // Y redirigimos despues de 10 segundos al panel diario.
    header("Refresh: 10; URL=/entreaulas/paneldiario.php?aulario=" . urlencode($_GET['aulario'] ?? ''));
?>
    <div class="card pad">
      <div>
        <h1 class="card-title">Menú Seleccionado</h1>
        <span>
          Has seleccionado el siguiente menú para el día <?php echo htmlspecialchars($selected_date); ?>:
        </span>
        <ul>
          <li>Primer Plato: <?php echo htmlspecialchars($plato1); ?></li>
          <li>Segundo Plato: <?php echo htmlspecialchars($plato2); ?></li>
          <li>Postre: <?php echo htmlspecialchars($postre); ?></li>
        </ul>
        <a href="/entreaulas/paneldiario.php?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-primary">Volver
          al Panel Diario</a>
      </div>
    </div>
<?php
    die();
    break;
}
?>
<audio id="win-sound" src="/static/sounds/win.mp3" preload="auto"></audio>
<audio id="lose-sound" src="/static/sounds/lose.mp3" preload="auto"></audio>
<audio id="click-sound" src="/static/sounds/click.mp3" preload="auto"></audio>
<script>
  function announceAndMaybeRedirect(message, redirectUrl, success) {
    const fallbackId = success ? 'win-sound' : 'lose-sound';
    const fallbackAudio = document.getElementById(fallbackId);

    const doRedirect = () => {
      if (redirectUrl) {
        window.location.href = redirectUrl;
      }
    };

    const playFallback = () => {
      if (!fallbackAudio) {
        doRedirect();
        return;
      }
      doRedirect();
      return;
      fallbackAudio.pause();
      fallbackAudio.currentTime = 0;
      fallbackAudio.onended = () => {
        fallbackAudio.onended = null;
        doRedirect();
      };
      fallbackAudio.play().catch(doRedirect);
    };

    if (!message) {
      playFallback();
      return;
    }

    if (!('speechSynthesis' in window)) {
      playFallback();
      return;
    }

    try {
      window.speechSynthesis.cancel();
      const utterance = new SpeechSynthesisUtterance(message);
      utterance.lang = 'es-ES';
      utterance.rate = 1;
      utterance.onend = doRedirect;
      utterance.onerror = playFallback;
      window.speechSynthesis.speak(utterance);
    } catch (e) {
      playFallback();
    }
  }
</script>
<?php
switch ($_GET["action"]) {
  default:
  case "index":
?>
    <div id="grid">
      <!-- ¿Quién soy? -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=quien_soy&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-primary grid-item">
        <img src="/static/arasaac/yo.png" height="125" class="bg-white">
        <br>
        ¿Quién soy?
      </a>
      <!-- Calendario -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=calendar&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-primary grid-item">
        <img src="/static/arasaac/calendario.png" height="125" class="bg-white">
        <br>
        ¿Que dia es?
      </a>
      <!-- Actividades -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=actividades&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-primary grid-item">
        <img src="/static/arasaac/actividad.png" height="125" class="bg-white">
        <br>
        ¿Que vamos a hacer?
      </a>
      <!-- Menú del comedor -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=menu&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-primary grid-item">
        <img src="/static/arasaac/comedor.png" height="125" class="bg-white">
        <br>
        ¿Que vamos a comer?
      </a>
    </div>

    <style>
      .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 250px;
        text-align: center;
      }

      .grid-item img {
        margin: 0 auto;
        height: 125px;
      }
    </style>


    <script>
      var msnry = new Masonry('#grid', {
        "columnWidth": 250,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
      });
      setTimeout(() => {
        msnry.layout()
      }, 250);
      window.onresize = () => {
        msnry.layout()
      }
    </script>

  <?php
    break;
  case "quien_soy":
    // ¿Quién soy? - Identificación del alumno
    $aulario_id = $_GET["aulario"] ?? "";
    $centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"] ?? "";
    
    $alumnos_path = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id/Alumnos";
    $alumnos = [];
    
    if (is_dir($alumnos_path)) {
      $alumnos = glob($alumnos_path . "/*", GLOB_ONLYDIR);
    }
  ?>
    <script>
      function seleccionarAlumno(element, nombre) {
        element.style.backgroundColor = "#9cff9f"; // Verde
        announceAndMaybeRedirect(
          nombre + ", ¡Hola " + nombre + "!",
          "/entreaulas/paneldiario.php?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
          true
        );
      }
    </script>
    <div class="card pad">
      <div>
        <h1 class="card-title">¿Quién soy?</h1>
      </div>
    </div>
    <div id="grid">
      <?php 
      if (empty($alumnos)) {
      ?>
        <div class="card pad" style="width: 100%;">
          <p>No hay alumnos registrados en este aulario.</p>
          <p>Para añadir alumnos, crea carpetas con sus nombres en: <code><?php echo htmlspecialchars($alumnos_path); ?></code></p>
          <p>Cada carpeta debe contener un archivo <code>photo.jpg</code> con la foto o pictograma del alumno.</p>
        </div>
      <?php 
      } else {
        foreach ($alumnos as $alumno_path) {
          $alumno_name = basename($alumno_path);
          $photo_path = $alumno_path . "/photo.jpg";
          $photo_exists = file_exists($photo_path);
      ?>
        <a class="card grid-item" style="color: black;" onclick="seleccionarAlumno(this, '<?php echo htmlspecialchars($alumno_name); ?>');">
          <?php if ($photo_exists): ?>
            <img src="_filefetch.php?type=alumno_photo&alumno=<?php echo urlencode($alumno_name); ?>&centro=<?php echo urlencode($centro_id); ?>&aulario=<?php echo urlencode($aulario_id); ?>" height="150" class="bg-white">
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
      .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 250px;
        text-align: center;
        text-decoration: none;
      }

      .grid-item img {
        margin: 0 auto;
        height: 150px;
        border-radius: 10px;
        border: 3px solid #ddd;
      }
    </style>
    <script>
      var msnry = new Masonry('#grid', {
        "columnWidth": 250,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
      });
      setTimeout(() => {
        msnry.layout()
      }, 250);
      window.onresize = () => {
        msnry.layout()
      }
    </script>
  <?php
    break;
  case "actividades":
    $actividades = glob("/DATA/entreaulas/Centros/" . $_SESSION["auth_data"]["entreaulas"]["centro"] . "/Panel/Actividades/*", GLOB_ONLYDIR);
  ?>
    <script>
      function seleccionarActividad(element, actividad) {
        element.style.backgroundColor = "#9cff9f"; // Verde
        announceAndMaybeRedirect(
          actividad + ", Actividad seleccionada",
          "/entreaulas/paneldiario.php?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
          true
        );
      }
    </script>
    <div class="card pad">
      <div>
        <h1 class="card-title">¿Que vamos a hacer?</h1>
      </div>
    </div>
    <div id="grid">
      <?php foreach ($actividades as $actividad_path) {
        $actividad_name = basename($actividad_path);
      ?>
        <a class="card grid-item" style="color: black;" onclick="seleccionarActividad(this, '<?php echo htmlspecialchars($actividad_name); ?>');">
          <img src="_filefetch.php?type=panel_actividades&activity=<?php echo urlencode($actividad_name); ?>&centro=<?php echo urlencode($_SESSION["auth_data"]["entreaulas"]["centro"]); ?>" height="125" class="bg-white">
          <?php echo htmlspecialchars($actividad_name); ?>
        </a>
      <?php } ?>
    </div>
    <style>
      .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 250px;
        text-align: center;
        text-decoration: none;
      }

      .grid-item img {
        margin: 0 auto;
        height: 150px;
      }
    </style>
    <script>
      var msnry = new Masonry('#grid', {
        "columnWidth": 250,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
      });
      setTimeout(() => {
        msnry.layout()
      }, 250);
      window.onresize = () => {
        msnry.layout()
      }
    </script>
  <?php
    break;
  case "menu":
    // Menú del comedor (nuevo sistema, vista simplificada)
    $aulario_id = $_GET["aulario"] ?? "";
    $centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"] ?? "";

    $source_aulario_id = $aulario_id;
    $is_shared = false;
    if ($aulario_id !== "" && $centro_id !== "") {
      $aulario_path = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json";
      $aulario = file_exists($aulario_path) ? json_decode(file_get_contents($aulario_path), true) : null;
      if ($aulario && !empty($aulario["shared_comedor_from"])) {
        $shared_from = $aulario["shared_comedor_from"];
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
        announceAndMaybeRedirect(
          menu_ty + ", Menú seleccionado",
          "/entreaulas/paneldiario.php?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
          true
        );
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
            announceAndMaybeRedirect(
              dia + ", Correcto",
              "?action=calendario_diasemana&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
              true
            );
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
        function seleccionarDiaSemana(element, ds) {
          var dow = ["", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo"];
          // Si es dia de la semana correcto
          if (ds == <?php echo $dia_de_la_semana; ?>) {
            element.style.backgroundColor = "#9cff9f"; // Verde
            announceAndMaybeRedirect(
              dow[ds] + ", Correcto",
              "/entreaulas/paneldiario.php?action=calendario_mes&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
              true
            );
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
      ?>
        <a class="card grid-item" style="width: 225px; height: 225px; color: black;"
          onclick="seleccionarDiaSemana(this, <?php echo $ds; ?>);">
          <span style="font-size: 30px;"><?php echo $day_name; ?></span>
          <img src="/static/arasaac/diadelasemana/<?php echo strtolower($day_name); ?>.png" alt=""
            style="width: 100px; height: 100px;">
          <span style="font-size: 30px; color: blue;"><?php echo $dow_euskara[$ds]; ?></span>
        </a>
      <?php } ?>
    </div>
    <style>
      .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 225px;
        text-align: center;
        text-decoration: none;
      }

      .grid-item img {
        margin: 0 auto;
        height: 125px;
      }
    </style>
    <script>
      var msnry = new Masonry('.grid', {
        "columnWidth": 225,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
      });
      setTimeout(() => {
        msnry.layout()
      }, 250);
      window.onresize = () => {
        msnry.layout()
      }
    </script>
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
        function seleccionarMes(element, mes) {
          // Si es mes correcto
          var meses = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
          if (mes == <?php echo $mes_correcto; ?>) {
            element.style.backgroundColor = "#9cff9f"; // Verde
            announceAndMaybeRedirect(
              meses[mes] + ", Correcto",
              "/entreaulas/paneldiario.php?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>",
              true
            );
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
      ?>
        <a class="card grid-item" style="width: 180px; height: 180px; color: black;"
          onclick="seleccionarMes(this, <?php echo $mes; ?>);">
          <span style="font-size: 24px;"><?php echo $mes_name; ?></span>
          <img src="/static/arasaac/mesesdelano/<?php echo strtolower($mes_name); ?>.png" alt=""
            style="width: 80px; height: 80px;">
          <span style="font-size: 24px; color: blue;"><?php echo $meses_eus[$mes]; ?></span>
        </a>
      <?php } ?>
    </div>
    <style>
      .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 180px;
        text-align: center;
        text-decoration: none;
      }

      .grid-item img {
        margin: 0 auto;
        height: 125px;
      }
    </style>
    <script>
      var msnry = new Masonry('.grid', {
        "columnWidth": 180,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
      });
      setTimeout(() => {
        msnry.layout()
      }, 250);
      window.onresize = () => {
        msnry.layout()
      }
    </script>
<?php
    break;
}
require_once "_incl/post-body.php"; ?>