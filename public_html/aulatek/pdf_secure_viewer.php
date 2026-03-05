<?php
require_once "_incl/auth_redir.php";

$file = $_GET["file"] ?? "";
$file = trim($file);

$is_valid = false;
if ($file !== "") {
  $parsed = parse_url($file);
  if (!isset($parsed["scheme"]) && !isset($parsed["host"])) {
    if (strpos($file, "/entreaulas/_filefetch.php") === 0) {
      $is_valid = true;
    }
  }
}

if (!$is_valid) {
  header("HTTP/1.1 400 Bad Request");
  echo "URL de archivo no válida.";
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PDF Seguro</title>
  <style>
    html, body {
      margin: 0;
      padding: 0;
      background: #f5f5f5;
      font-family: Arial, sans-serif;
      color: #222;
      height: 100%;
    }
    body {
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .viewer-header {
      padding: 10px 16px;
      background: #fff3cd;
      border-bottom: 1px solid #e0e0e0;
      color: #000;
      font-size: 14px;
    }
    .viewer-toolbar {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      background: #ffffff;
      border-bottom: 1px solid #e0e0e0;
      position: sticky;
      top: 0;
      z-index: 5;
    }
    .viewer-toolbar button {
      border: 1px solid #ccc;
      background: #f8f9fa;
      padding: 6px 10px;
      border-radius: 4px;
      cursor: pointer;
    }
    .viewer-toolbar button:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .zoom-label {
      font-weight: bold;
      min-width: 64px;
    }
    .pdf-container {
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 16px;
      overflow-x: auto;
      align-items: flex-start;
      overflow-y: auto;
      flex: 1 1 auto;
      min-height: 0;
    }
    .page {
      background: white;
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
      border-radius: 4px;
      padding: 8px;
      display: inline-block;
      margin: 0 auto;
      box-sizing: border-box;
    }
    canvas {
      display: block;
      height: auto;
    }
    .error {
      padding: 16px;
      color: #b00020;
    }
  </style>
</head>
<body>
  <div class="viewer-header">
    Este PDF se muestra en modo seguro. No se permite la descarga, impresión o copia del contenido.
  </div>
  <div class="viewer-toolbar">
    <button type="button" id="zoom_out">−</button>
    <button type="button" id="zoom_reset">100%</button>
    <button type="button" id="zoom_in">+</button>
    <span class="zoom-label" id="zoom_label">100%</span>
  </div>
  <div class="pdf-container" id="pdf_container">
    Cargando PDF...
  </div>

  <script>
    (function () {
      var pdfUrl = <?= json_encode($file, JSON_UNESCAPED_UNICODE) ?>;
      var container = document.getElementById('pdf_container');
      var CDN_BASES = [
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174',
        'https://unpkg.com/pdfjs-dist@3.11.174/build'
      ];

      function loadScript(src, onload, onerror) {
        var s = document.createElement('script');
        s.src = src;
        s.onload = onload;
        s.onerror = onerror;
        document.head.appendChild(s);
      }

      var pdfDoc = null;
      var currentScale = 1;
      var minScale = 0.5;
      var maxScale = 3;
      var renderToken = 0;

      function updateZoomLabel() {
        var label = document.getElementById('zoom_label');
        if (label) {
          label.textContent = Math.round(currentScale * 100) + '%';
        }
        var resetBtn = document.getElementById('zoom_reset');
        if (resetBtn) {
          resetBtn.textContent = Math.round(currentScale * 100) + '%';
        }
      }

      function clearContainer() {
        container.innerHTML = '';
      }

      function renderAllPages(scale) {
        if (!pdfDoc) return;
        renderToken += 1;
        var token = renderToken;
        clearContainer();
        var total = pdfDoc.numPages;
        for (var i = 1; i <= total; i++) {
          (function (pageNumber) {
            pdfDoc.getPage(pageNumber).then(function (page) {
              if (token !== renderToken) return;
              var viewport = page.getViewport({ scale: scale });
              var outputScale = window.devicePixelRatio || 1;
              var canvas = document.createElement('canvas');
              var context = canvas.getContext('2d');
              canvas.width = Math.floor(viewport.width * outputScale);
              canvas.height = Math.floor(viewport.height * outputScale);
              canvas.style.width = Math.floor(viewport.width) + 'px';
              canvas.style.height = 'auto';

              var wrapper = document.createElement('div');
              wrapper.className = 'page';
              wrapper.appendChild(canvas);
              container.appendChild(wrapper);

              var renderContext = {
                canvasContext: context,
                viewport: page.getViewport({ scale: scale * outputScale })
              };
              page.render(renderContext);
            });
          })(i);
        }
      }

      function getFitScale() {
        if (!pdfDoc) return currentScale;
        var availableWidth = Math.max(320, container.clientWidth || 0);
        return pdfDoc.getPage(1).then(function (page) {
          var viewport = page.getViewport({ scale: 1 });
          var baseWidth = viewport.width || 1;
          var fitScale = availableWidth / baseWidth;
          fitScale = Math.min(maxScale, Math.max(minScale, fitScale));
          return fitScale;
        }).catch(function () {
          return currentScale;
        });
      }

      function initPdf() {
        if (!window.pdfjsLib) {
          container.innerHTML = '<div class="error">No se pudo cargar el visor PDF.</div>';
          return;
        }

        pdfjsLib.getDocument(pdfUrl).promise.then(function (pdf) {
          pdfDoc = pdf;
          return getFitScale();
        }).then(function (fitScale) {
          currentScale = fitScale || 1;
          updateZoomLabel();
          renderAllPages(currentScale);
        }).catch(function () {
          container.innerHTML = '<div class="error">No se pudo cargar el PDF.</div>';
        });
      }

      function tryLoad(index) {
        if (index >= CDN_BASES.length) {
          container.innerHTML = '<div class="error">No se pudo cargar el visor PDF.</div>';
          return;
        }
        var base = CDN_BASES[index];
        var scriptUrl = base + '/pdf.min.js';
        var workerUrl = base + '/pdf.worker.min.js';
        loadScript(scriptUrl, function () {
          if (window.pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;
          }
          initPdf();
        }, function () {
          tryLoad(index + 1);
        });
      }

      tryLoad(0);

      var zoomInBtn = document.getElementById('zoom_in');
      var zoomOutBtn = document.getElementById('zoom_out');
      var zoomResetBtn = document.getElementById('zoom_reset');

      if (zoomInBtn) {
        zoomInBtn.addEventListener('click', function () {
          currentScale = Math.min(maxScale, currentScale + 0.1);
          updateZoomLabel();
          renderAllPages(currentScale);
        });
      }
      if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', function () {
          currentScale = Math.max(minScale, currentScale - 0.1);
          updateZoomLabel();
          renderAllPages(currentScale);
        });
      }
      if (zoomResetBtn) {
        zoomResetBtn.addEventListener('click', function () {
          getFitScale().then(function (fitScale) {
            currentScale = fitScale || 1;
            updateZoomLabel();
            renderAllPages(currentScale);
          });
        });
      }

      var resizeTimer = null;
      window.addEventListener('resize', function () {
        if (resizeTimer) clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
          getFitScale().then(function (fitScale) {
            currentScale = fitScale || currentScale;
            updateZoomLabel();
            renderAllPages(currentScale);
          });
        }, 200);
      });
    })();
  </script>
</body>
</html>
