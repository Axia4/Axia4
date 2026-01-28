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
        <div class="card-body">
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
<?php
switch ($_GET["action"]) {
  default:
  case "index":
    ?>
    <div class="card pad">
        <div class="card-body">
            <h1 class="card-title">Panel diario</h1>
            <span>
                Desde este panel puedes apuntar las actividades diarias del aulario.
            </span>
        </div>
    </div>
    <div id="grid">
      <!-- Calendario -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=calendar&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-primary grid-item">
        <img src="/static/arasaac/calendario.png" height="125" class="bg-white">
        <br>
        Calendario
      </a>
      <!-- Actividades -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=actividades&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-primary grid-item">
        <img src="/static/arasaac/actividad.png" height="125" class="bg-white">
        <br>
        Actividades
      </a>
      <!-- Menú del comedor -->
      <a onclick="document.getElementById('click-sound').play()" href="?action=menu&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>" class="btn btn-primary grid-item">
        <img src="/static/arasaac/comedor.png" height="125" class="bg-white">
        <br>
        Menú del Comedor
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
      }, 250); window.onresize = () => {msnry.layout()}
    </script>

    <?php
    break;
  case "actividades":
    // Actividades, establecidas en /DATA/entreaulas/Centros/$centro/Panel/Actividades/<nombre>/photo.jpg
    $aulario_id = $_GET['aulario'] ?? '';
    $centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"];
    $actividades_paths = glob("/DATA/entreaulas/Centros/$centro_id/Panel/Actividades/*/photo.jpg");
    ?>
    <div class="card pad">
        <div class="card-body">
            <h1 class="card-title">Actividades</h1>
            <span>
                Aquí podrás ver y seleccionar las actividades del día para el aulario.
            </span>
        </div>
    </div>
    <div id="grid">
      <script>
        function seleccionarActividad(element, actividad) {
          element.style.backgroundColor = "#9cff9f"; // Verde
          document.getElementById('win-sound').play();
          setTimeout(() => {
            location.href = "?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>";
          }, 2000);
        }
      </script>
      <?php foreach ($actividades_paths as $actividad_path) { 
        $actividad_name = basename(dirname($actividad_path));
        ?>
        <a class="card grid-item" onclick="seleccionarActividad(this, '<?php echo htmlspecialchars($actividad_name); ?>')">
          <img src="_filefetch.php?type=panel_actividades&centro=<?= urlencode($centro_id) ?>&activity=<?= urlencode($actividad_name) ?>" height="150">
          <br>
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
      }, 250); window.onresize = () => {msnry.layout()}
    </script>
    <?php
    break;
  case "menu":
    // Menú del comedor

    $months = [
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

    $dow = [
      1 => "Lunes",
      2 => "Martes",
      3 => "Miércoles",
      4 => "Jueves",
      5 => "Viernes",
      6 => "Sábado",
      7 => "Domingo"
    ];

    $month = $_GET['month'] ?? date('n');
    $year = $_GET['year'] ?? date('Y');
    $MENUTY = $_GET['menu'] ?? "basal";

    $parsedTable = null;
    function getMenuForDay(string $pageText, string $day)
    {
      global $parsedTable;

      // ---------------------------------------------
      // 1. Parse table only once
      // ---------------------------------------------
      if ($parsedTable === null) {

        $lines = preg_split("/\R/", $pageText);
        $rows = [];

        foreach ($lines as $line) {
          $trim = trim($line);

          // Only lines that start with "|" and are table rows
          if (strpos($trim, "|") === 0 && substr($trim, -1) === "|") {

            // Remove leading and trailing |, then split
            $cols = explode("|", trim($trim, "|"));
            $cols = array_map("trim", $cols);

            if (count($cols) >= 4) {
              $rows[] = [
                "fecha" => $cols[0],
                "plato1" => $cols[1],
                "plato2" => $cols[2],
                "postre" => $cols[3]
              ];
            }
          }
        }

        $parsedTable = $rows; // store result (parsed only once)
      }

      // ---------------------------------------------
      // 2. Look for the requested date
      // ---------------------------------------------
      foreach ($parsedTable as $row) {
        if ($row["fecha"] === $day) {
          return $row;
        }
      }

      return null; // not found
    }

    $MENUDATA = file_get_contents("https://aularios.tech.eus/aldamiz_ortuella/menu_comedor/tabla/$MENUTY?do=export_raw");
    // Solo semana actual, botones. cuando se pulse el botón del dia actual, se enviara un POST ?form=menu con los valores del menu
    $weeknow = date('W');
    ?>
    <script>
      function seleccionarMenuDia(element, dia) {
        // Si es dia correcto
        var today = new Date();
        var currentDay = today.getDate();
        if (dia == currentDay) {
          element.style.backgroundColor = "#9cff9f"; // Verde
          document.getElementById('win-sound').play();
          setTimeout(() => {
            location.href = "?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>";
          }, 2000);
        } else {
          element.style.backgroundColor = "#ff9088"; // Rojo
          document.getElementById('lose-sound').play();
          setTimeout(() => {
            element.style.backgroundColor = ""; // Volver al color anterior
          }, 2000);
        }
      }
    </script>
    <div class="card pad">
      <h1>Menú del Comedor</h1>
    </div>
    <div class="grid">
      <?php for ($d = 1; $d <= 31; $d++) {
        $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $d);
        $dayOfWeek = date('N', strtotime($dateStr));
        $weekofmonth = date('W', strtotime($dateStr));
        if ($dayOfWeek > 5) {
          continue; // Skip weekends
        }
        if ($weekofmonth != $weeknow) {
          continue; // Only current week
        }
        $menuForDay = getMenuForDay($MENUDATA, $dateStr);
        if ($menuForDay === null) {
          continue; // No menu for this day
        }
        ?>
        <a class="card grid-item" style="width: 250px; height: 250px; color: black;" onclick="seleccionarMenuDia(this, <?php echo $d; ?>);">
          <h3><?php echo $dow[$dayOfWeek] . " " . $d ?></h3>
          <ol style="text-align: left; padding-left: 15px;">
            <li><?php echo htmlspecialchars($menuForDay["plato1"]); ?></li>
            <li><?php echo htmlspecialchars($menuForDay["plato2"]); ?></li>
            <li><?php echo htmlspecialchars($menuForDay["postre"]); ?></li>
          </ol>
        </a>
      <?php } ?>
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
      var msnry = new Masonry('.grid', {
        "columnWidth": 250,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
      });
      setTimeout(() => {
        msnry.layout()
      }, 250); window.onresize = () => {msnry.layout()}
    </script>

    <?php
    break;
  case "calendar":
    // Calendario, elegir el dia, mes, y dia-de-la-semana.
    $mes_correcto = date('m');
    $dia_correcto = date('d');
    $ds_correcto = date('N'); // 1 (Lunes) a 7 (Domingo)
    ?>
    <div class="card pad">
      <h1>Calendario</h1>
      <span>
        Aquí podrás ver y gestionar el calendario de actividades del aulario.
      </span>
    </div>
    <div class="grid">
      <script>
        function seleccionarDia(element, dia, mes, year, ds) {
          // Si es dia correcto
          if (dia == <?php echo $dia_correcto; ?> && mes == <?php echo $mes_correcto; ?> && ds == <?php echo $ds_correcto; ?>) {
            element.style.backgroundColor = "#9cff9f"; // Verde
            document.getElementById('win-sound').play();
            setTimeout(() => {
              window.location.href = "?action=calendario_diasemana&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>";
            }, 2000);
          } else {
            element.style.backgroundColor = "#ff9088"; // Rojo
            document.getElementById('lose-sound').play();
            setTimeout(() => {
              element.style.backgroundColor = ""; // Volver al color anterior
            }, 2000);
          }
        }
      </script>
      <?php foreach (range(1, 31) as $dia) {
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
      <?php } ?>
    </div>
    <style>
      .grid-item {
        margin-bottom: 10px !important;
        padding: 15px;
        width: 130px;
        height: 100px;
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
        "columnWidth": 130,
        "itemSelector": ".grid-item",
        "gutter": 10,
        "transitionDuration": 0
      });
      setTimeout(() => {
        msnry.layout()
      }, 250); window.onresize = () => {msnry.layout()}
    </script>
    <?php
    break;
  case "calendario_diasemana":
    // Calendario - Día de la semana
    $dia_de_la_semana = date('N'); // 1 (Lunes) a 7 (Domingo)
    ?>
    <div class="card pad">
      <h1>Calendario - Día de la Semana</h1>
      <span>
        Has seleccionado el día correcto. ¡Ahora pon el dia de la semana!
      </span>
    </div>
    <div class="grid">
      <script>
        function seleccionarDiaSemana(element, ds) {
          // Si es dia de la semana correcto
          if (ds == <?php echo $dia_de_la_semana; ?>) {
            element.style.backgroundColor = "#9cff9f"; // Verde
            document.getElementById('win-sound').play();
            setTimeout(() => {
              location.href = "/entreaulas/paneldiario.php?action=calendario_mes&aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>";
            }, 2000);
          } else {
            element.style.backgroundColor = "#ff9088"; // Rojo
            document.getElementById('lose-sound').play();
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
      }, 250); window.onresize = () => {msnry.layout()}
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
      <h1>Calendario - Mes</h1>
      <span>
        Has seleccionado el día y el día de la semana correctos. ¡Ahora pon el mes!
      </span>
    </div>
    <div class="grid">
      <script>
        function seleccionarMes(element, mes) {
          // Si es mes correcto
          if (mes == <?php echo $mes_correcto; ?>) {
            element.style.backgroundColor = "#9cff9f"; // Verde
            document.getElementById('win-sound').play();
            setTimeout(() => {
              window.location.href = "/entreaulas/paneldiario.php?aulario=<?php echo urlencode($_GET['aulario'] ?? ''); ?>";
            }, 2000);
          } else {
            element.style.backgroundColor = "#ff9088"; // Rojo
            document.getElementById('lose-sound').play();
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
      }, 250); window.onresize = () => {msnry.layout()}
    </script>
    <?php
    break;
}
require_once "_incl/post-body.php"; ?>
