<?php
require_once "_incl/auth_redir.php";

// Check if user has docente permission
if (!in_array("entreaulas:docente", $_SESSION["auth_data"]["permissions"] ?? [])) {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied");
}

$aulario_id = $_GET["aulario"] ?? "";
$centro_id = $_SESSION["auth_data"]["entreaulas"]["centro"] ?? "";

if (empty($aulario_id) || empty($centro_id)) {
    require_once "_incl/pre-body.php";
    ?>
    <div class="card pad">
        <h1>Gestión de Alumnos</h1>
        <p>No se ha indicado un aulario válido.</p>
    </div>
    <?php
    require_once "_incl/post-body.php";
    exit;
}

$aulario_id = basename($aulario_id);
$centro_id = basename($centro_id);

// Validate paths with realpath
$base_path = "/DATA/entreaulas/Centros";
$real_base = realpath($base_path);
$alumnos_base_path = "$base_path/$centro_id/Aularios/$aulario_id/Alumnos";

// Ensure base path exists and is valid
if ($real_base === false) {
    require_once "_incl/pre-body.php";
    ?>
    <div class="card pad">
        <h1>Gestión de Alumnos</h1>
        <p>Error: Directorio base no encontrado.</p>
    </div>
    <?php
    require_once "_incl/post-body.php";
    exit;
}

// Handle form submissions
switch ($_GET["form"] ?? '') {
    case 'add':
        $nombre = trim($_POST['nombre'] ?? '');
        if (empty($nombre)) {
            header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("El nombre no puede estar vacío"));
            exit;
        }
        
        // Sanitize filename
        $nombre_safe = basename($nombre);
        $alumno_path = "$alumnos_base_path/$nombre_safe";
        
        // Validate path with realpath (after potential creation)
        if (!is_dir($alumnos_base_path)) {
            mkdir($alumnos_base_path, 0755, true);
        }
        
        $real_alumnos_base = realpath($alumnos_base_path);
        if ($real_alumnos_base === false || strpos($real_alumnos_base, $real_base) !== 0) {
            header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Error: Ruta inválida"));
            exit;
        }
        
        if (file_exists($alumno_path)) {
            header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Ya existe un alumno con ese nombre"));
            exit;
        }
        
        mkdir($alumno_path, 0755, true);
        
        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['photo']['tmp_name'];
            $photo_path = "$alumno_path/photo.jpg";
            
            // Validate image
            $image_info = getimagesize($tmp_name);
            if ($image_info !== false) {
                if (move_uploaded_file($tmp_name, $photo_path)) {
                    chmod($photo_path, 0644);
                }
            }
        }
        
        header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Alumno añadido correctamente"));
        exit;
        
    case 'edit':
        $nombre_old = basename($_POST['nombre_old'] ?? '');
        $nombre_new = trim($_POST['nombre_new'] ?? '');
        
        if (empty($nombre_old) || empty($nombre_new)) {
            header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Nombre inválido"));
            exit;
        }
        
        $nombre_new_safe = basename($nombre_new);
        $alumno_old_path = "$alumnos_base_path/$nombre_old";
        $alumno_new_path = "$alumnos_base_path/$nombre_new_safe";
        
        // Validate paths with realpath
        $real_old_path = realpath($alumno_old_path);
        if ($real_old_path === false || strpos($real_old_path, $real_base) !== 0) {
            header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Alumno no encontrado"));
            exit;
        }
        
        if (!file_exists($alumno_old_path)) {
            header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Alumno no encontrado"));
            exit;
        }
        
        // Rename if name changed
        if ($nombre_old !== $nombre_new_safe && $nombre_new_safe !== '') {
            if (file_exists($alumno_new_path)) {
                header("Location: ?aulario=" . urlencode($aulario_id) . "&action=edit&alumno=" . urlencode($nombre_old) . "&_result=" . urlencode("Ya existe un alumno con ese nombre"));
                exit;
            }
            rename($alumno_old_path, $alumno_new_path);
            $alumno_old_path = $alumno_new_path;
        }
        
        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['photo']['tmp_name'];
            $photo_path = "$alumno_old_path/photo.jpg";
            
            // Validate image
            $image_info = getimagesize($tmp_name);
            if ($image_info !== false) {
                if (move_uploaded_file($tmp_name, $photo_path)) {
                    chmod($photo_path, 0644);
                }
            }
        }
        
        header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Alumno actualizado correctamente"));
        exit;
        
    case 'delete':
        $nombre = basename($_POST['nombre'] ?? '');
        if (empty($nombre)) {
            header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Nombre inválido"));
            exit;
        }
        
        $alumno_path = "$alumnos_base_path/$nombre";
        
        // Validate path with realpath
        $real_alumno_path = realpath($alumno_path);
        if ($real_alumno_path === false || strpos($real_alumno_path, $real_base) !== 0) {
            header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Alumno no encontrado"));
            exit;
        }
        
        if (file_exists($alumno_path) && is_dir($alumno_path)) {
            // Delete photo if exists
            $photo_path = "$alumno_path/photo.jpg";
            if (file_exists($photo_path)) {
                unlink($photo_path);
            }
            rmdir($alumno_path);
        }
        
        header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Alumno eliminado correctamente"));
        exit;
}

// Handle action views
switch ($_GET["action"] ?? '') {
    case 'add':
        require_once "_incl/pre-body.php";
        ?>
        <div class="card pad">
            <h1>Añadir Alumno</h1>
            <form method="post" action="?aulario=<?= urlencode($aulario_id) ?>&form=add" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre del alumno:</label>
                    <input type="text" id="nombre" name="nombre" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="photo" class="form-label">Foto del alumno (JPG/PNG):</label>
                    <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                    <small class="form-text text-muted">La foto se convertirá a formato JPG</small>
                </div>
                <button type="submit" class="btn btn-primary">Añadir</button>
                <a href="?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
        <?php
        require_once "_incl/post-body.php";
        exit;
        
    case 'edit':
        $nombre = basename($_GET['alumno'] ?? '');
        $alumno_path = "$alumnos_base_path/$nombre";
        
        if (empty($nombre) || !file_exists($alumno_path)) {
            header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Alumno no encontrado"));
            exit;
        }
        
        require_once "_incl/pre-body.php";
        $photo_exists = file_exists("$alumno_path/photo.jpg");
        ?>
        <div class="card pad">
            <h1>Editar Alumno: <?= htmlspecialchars($nombre) ?></h1>
            <?php if (!empty($_GET['_result'])): ?>
                <div class="alert alert-info"><?= htmlspecialchars($_GET['_result']) ?></div>
            <?php endif; ?>
            <form method="post" action="?aulario=<?= urlencode($aulario_id) ?>&form=edit" enctype="multipart/form-data">
                <input type="hidden" name="nombre_old" value="<?= htmlspecialchars($nombre) ?>">
                <div class="mb-3">
                    <label for="nombre_new" class="form-label">Nombre del alumno:</label>
                    <input type="text" id="nombre_new" name="nombre_new" value="<?= htmlspecialchars($nombre) ?>" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Foto actual:</label>
                    <?php if ($photo_exists): ?>
                        <div class="mb-2">
                            <img src="_filefetch.php?type=alumno_photo&alumno=<?= urlencode($nombre) ?>&centro=<?= urlencode($centro_id) ?>&aulario=<?= urlencode($aulario_id) ?>" 
                                 alt="Foto de <?= htmlspecialchars($nombre) ?>" 
                                 style="max-width: 200px; max-height: 200px; border: 2px solid #ddd; border-radius: 10px;">
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No hay foto</p>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="photo" class="form-label">Nueva foto (JPG/PNG):</label>
                    <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                    <small class="form-text text-muted">Dejar vacío para mantener la foto actual</small>
                </div>
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
        <?php
        require_once "_incl/post-body.php";
        exit;
        
    case 'delete':
        $nombre = basename($_GET['alumno'] ?? '');
        $alumno_path = "$alumnos_base_path/$nombre";
        
        if (empty($nombre) || !file_exists($alumno_path)) {
            header("Location: ?aulario=" . urlencode($aulario_id) . "&_result=" . urlencode("Alumno no encontrado"));
            exit;
        }
        
        require_once "_incl/pre-body.php";
        ?>
        <div class="card pad">
            <h1>Eliminar Alumno</h1>
            <p>¿Estás seguro de que quieres eliminar al alumno <strong><?= htmlspecialchars($nombre) ?></strong>?</p>
            <p class="text-danger">Esta acción no se puede deshacer.</p>
            <form method="post" action="?aulario=<?= urlencode($aulario_id) ?>&form=delete">
                <input type="hidden" name="nombre" value="<?= htmlspecialchars($nombre) ?>">
                <button type="submit" class="btn btn-danger">Eliminar</button>
                <a href="?aulario=<?= urlencode($aulario_id) ?>" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
        <?php
        require_once "_incl/post-body.php";
        exit;
        
    default:
        // List all alumnos
        require_once "_incl/pre-body.php";
        
        $alumnos = [];
        if (is_dir($alumnos_base_path)) {
            $alumnos = glob($alumnos_base_path . "/*", GLOB_ONLYDIR);
            usort($alumnos, function($a, $b) {
                return strcasecmp(basename($a), basename($b));
            });
        }
        ?>
        <div class="card pad">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h1 class="card-title" style="margin: 0;">Gestión de Alumnos</h1>
                <a href="?aulario=<?= urlencode($aulario_id) ?>&action=add" class="btn btn-success">+ Añadir Alumno</a>
            </div>
            
            <?php if (!empty($_GET['_result'])): ?>
                <div class="alert alert-info"><?= htmlspecialchars($_GET['_result']) ?></div>
            <?php endif; ?>
            
            <?php if (empty($alumnos)): ?>
                <p>No hay alumnos registrados en este aulario.</p>
                <p>Haz clic en "Añadir Alumno" para empezar.</p>
            <?php else: ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nombre</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumnos as $alumno_path): 
                            $nombre = basename($alumno_path);
                            $photo_exists = file_exists("$alumno_path/photo.jpg");
                        ?>
                        <tr>
                            <td>
                                <?php if ($photo_exists): ?>
                                    <img src="_filefetch.php?type=alumno_photo&alumno=<?= urlencode($nombre) ?>&centro=<?= urlencode($centro_id) ?>&aulario=<?= urlencode($aulario_id) ?>" 
                                         alt="Foto de <?= htmlspecialchars($nombre) ?>" 
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 5px; border: 2px dashed #ccc;">
                                        <span>?</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($nombre) ?></strong></td>
                            <td>
                                <a href="?aulario=<?= urlencode($aulario_id) ?>&action=edit&alumno=<?= urlencode($nombre) ?>" class="btn btn-sm btn-primary">Editar</a>
                                <a href="?aulario=<?= urlencode($aulario_id) ?>&action=delete&alumno=<?= urlencode($nombre) ?>" class="btn btn-sm btn-danger">Eliminar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <a href="/entreaulas/aulario.php?id=<?= urlencode($aulario_id) ?>" class="btn btn-secondary mt-3">Volver al Aulario</a>
        </div>
        <?php
        require_once "_incl/post-body.php";
        exit;
}
