<?php
require_once "_incl/auth_redir.php";
require_once "_incl/tools.security.php";

function safe_path_segment($value)
{
    $value = trim((string)$value);
    $value = str_replace(["\0", "/", "\\"], "", $value);
    $value = str_replace("..", "", $value);
    $value = basename($value);
    if ($value === "." || $value === "..") {
        return "";
    }
    return $value;
}

$form_action = $_GET["form"] ?? "";
switch ($form_action) {
    case "delete":
        $aulario_id = safe_path_segment(Sf($_POST["aulario_id"] ?? ""));
        $centro_id = safe_path_segment(Sf($_POST["centro_id"] ?? ""));
        $aulario_file = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json";
        if (!file_exists($aulario_file)) {
            die("Aulario no encontrado.");
        }
        // Remove aulario directory and contents
        $aulario_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id";
        function rrmdir($dir) {
            if (is_dir($dir)) {
                $objects = scandir($dir);
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        $obj_path = $dir . "/" . $object;
                        if (is_dir($obj_path)) {
                            rrmdir($obj_path);
                        } else {
                            unlink($obj_path);
                        }
                    }
                }
                rmdir($dir);
            }
        }
        rrmdir($aulario_dir);
        // Remove aulario config file
        unlink($aulario_file);
        header("Location: ?action=index");
        exit();
        break;
    case "create":
        $user_data = $_SESSION["auth_data"];
        $centro_id = safe_path_segment(Sf($_POST["centro"] ?? ""));
        if (empty($centro_id) || !is_dir("/DATA/entreaulas/Centros/$centro_id")) {
            die("Centro no válido.");
        }
        $aulario_id = strtolower(preg_replace("/[^a-zA-Z0-9_-]/", "_", Sf($_POST["name"] ?? "")));
        $aulario_data = [
            "name" => Sf($_POST["name"] ?? ""),
            "icon" => Sf($_POST["icon"] ?? "/static/logo-entreaulas.png")
        ];
        // Make path recursive (mkdir -p equivalent)
        @mkdir("/DATA/entreaulas/Centros/$centro_id/Aularios/", 0777, true);
        @mkdir("/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id/Proyectos/", 0777, true);
        file_put_contents("/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json", json_encode($aulario_data));
        // Update user data
        $_SESSION["auth_data"]["entreaulas"]["aulas"][] = $aulario_id;
        header("Location: ?action=index");
        exit();
        break;
    case "save_edit":
        $aulario_id = safe_path_segment(Sf($_POST["aulario_id"] ?? ""));
        $centro_id = safe_path_segment(Sf($_POST["centro_id"] ?? ""));
        $aulario_file = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json";
        if (!file_exists($aulario_file)) {
            die("Aulario no encontrado.");
        }
        $aulario_data = json_decode(file_get_contents($aulario_file), true);
        $aulario_data["name"] = Sf($_POST["name"] ?? "");
        $aulario_data["icon"] = Sf($_POST["icon"] ?? "/static/logo-entreaulas.png");
        
        // Handle shared comedor configuration
        $share_comedor_from = safe_path_segment(Sf($_POST["share_comedor_from"] ?? ""));
        
        if (!empty($share_comedor_from) && $share_comedor_from !== "none") {
            $aulario_data["shared_comedor_from"] = $share_comedor_from;
        } else {
            unset($aulario_data["shared_comedor_from"]);
        }
        
        // Handle linked projects configuration
        $linked_projects = [];
        $linked_aularios = $_POST["linked_aulario"] ?? [];
        $linked_project_ids = $_POST["linked_project_id"] ?? [];
        $linked_permissions = $_POST["linked_permission"] ?? [];
        
        for ($i = 0; $i < count($linked_aularios); $i++) {
            $src_aul = safe_path_segment($linked_aularios[$i] ?? "");
            $proj_id = safe_path_segment($linked_project_ids[$i] ?? "");
            $perm = in_array(($linked_permissions[$i] ?? "read_only"), ["read_only", "request_edit", "full_edit"], true)
                ? ($linked_permissions[$i] ?? "read_only")
                : "read_only";
            if (!empty($src_aul) && !empty($proj_id)) {
                $linked_projects[] = [
                    "source_aulario" => $src_aul,
                    "project_id" => $proj_id,
                    "permission" => $perm
                ];
            }
        }
        
        if (count($linked_projects) > 0) {
            $aulario_data["linked_projects"] = $linked_projects;
        } else {
            unset($aulario_data["linked_projects"]);
        }
        @mkdir("/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id/Proyectos/", 0777, true);
        file_put_contents($aulario_file, json_encode($aulario_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        header("Location: ?action=edit&aulario=" . urlencode($aulario_id) . "&centro=" . urlencode($centro_id) . "&saved=1");
        exit();
        break;
}

require_once "_incl/pre-body.php"; 
$view_action = $_GET["action"] ?? "index";
switch ($view_action) {
    case "new":
        ?>
<div class="card pad">
    <div>
        <h1 class="card-title">Nuevo Aulario</h1>
        <span>
            Aquí puedes crear un nuevo aulario para el centro que administras.
        </span>
        <form method="post" action="?form=create">
            <div class="mb-3">
                <label for="centro" class="form-label"><b>Centro:</b></label>
                <select required id="centro" name="centro" class="form-select">
                    <option value="">-- Selecciona un centro --</option>
                    <?php
                    foreach (glob("/DATA/entreaulas/Centros/*", GLOB_ONLYDIR) as $centro_folder) {
                        $centro_id = basename($centro_folder);
                        $selected = ($centro_id == $_SESSION["auth_data"]["entreaulas"]["centro"]) ? "selected" : "";
                        echo '<option value="' . htmlspecialchars($centro_id) . '" ' . $selected . '>' . htmlspecialchars($centro_id) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="name" class="form-label">Nombre del Aulario:</label>
                <input required type="text" id="name" name="name" class="form-control" placeholder="Ej: Aulario Principal">
            </div>
            <div class="mb-3">
                <label for="icon" class="form-label">Icono del Aulario (URL):</label>
                <input type="text" id="icon" name="icon" class="form-control" placeholder="Ej: https://example.com/icon.png" value="/static/logo-entreaulas.png">
            </div>
            <button type="submit" class="btn btn-primary">Crear Aulario</button>
        </form>
    </div>
</div>

<?php
        break;
    case "edit":
        $aulario_id = safe_path_segment(Sf($_GET["aulario"] ?? ""));
        $centro_id = safe_path_segment(Sf($_GET["centro"] ?? ""));
        $aulario_file = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aulario_id.json";
        if (!file_exists($aulario_file)) {
            die("Aulario no encontrado.");
        }
        $aulario_data = json_decode(file_get_contents($aulario_file), true);
        
        // Get all aularios from the same centro for sharing options
        $available_aularios = [];
        $aularios_files = glob("/DATA/entreaulas/Centros/$centro_id/Aularios/*.json");
        foreach ($aularios_files as $aul_file) {
            $aul_id = basename($aul_file, ".json");
            if ($aul_id !== $aulario_id) { // Don't allow sharing from itself
                $aul_data = json_decode(file_get_contents($aul_file), true);
                $available_aularios[$aul_id] = $aul_data['name'] ?? $aul_id;
            }
        }
        
        // Get available projects from other aularios
        $available_projects_by_aulario = [];
        foreach ($available_aularios as $aul_id => $aul_name) {
            $proj_dir = "/DATA/entreaulas/Centros/$centro_id/Aularios/$aul_id/Proyectos";
            if (is_dir($proj_dir)) {
                $projects = [];
                $files = glob("$proj_dir/*.json");
                foreach ($files as $file) {
                    $proj_data = json_decode(file_get_contents($file), true);
                    // Only include root projects (no parent)
                    if ($proj_data && ($proj_data["parent_id"] ?? null) === null) {
                        $projects[] = [
                            "id" => $proj_data["id"] ?? basename($file, ".json"),
                            "name" => $proj_data["name"] ?? "Sin nombre"
                        ];
                    }
                }
                if (count($projects) > 0) {
                    $available_projects_by_aulario[$aul_id] = $projects;
                }
            }
        }
?>
<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success">Cambios guardados correctamente.</div>
<?php endif; ?>
<div class="card pad">
    <div>
        <h1 class="card-title">Editar Aulario: <?php echo htmlspecialchars($aulario_data['name'] ?? 'Sin Nombre'); ?></h1>
        <form method="post" action="?form=save_edit">
            <div class="mb-3">
                <label for="name" class="form-label">Nombre del Aulario:</label>
                <input required type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($aulario_data['name'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label for="icon" class="form-label">Icono del Aulario (URL):</label>
                <input type="text" id="icon" name="icon" class="form-control" value="<?php echo htmlspecialchars($aulario_data['icon'] ?? '/static/iconexperience/blackboard.png'); ?>">
            </div>
            
            <hr>
            <h3>Compartir Menú Comedor</h3>
            <p class="text-muted">Configura desde qué aulario compartir los datos del menú comedor. Si se selecciona un aulario origen, este aulario mostrará los menús del aulario seleccionado en lugar de los propios.</p>
            
            <div class="mb-3">
                <label for="share_comedor_from" class="form-label">Menú Comedor - Compartir desde:</label>
                <select id="share_comedor_from" name="share_comedor_from" class="form-select">
                    <option value="none">No compartir (usar datos propios)</option>
                    <?php foreach ($available_aularios as $aul_id => $aul_name): ?>
                        <option value="<?php echo htmlspecialchars($aul_id); ?>" 
                            <?php echo ($aulario_data['shared_comedor_from'] ?? '') === $aul_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($aul_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <hr>
            <h3>Proyectos Enlazados</h3>
            <p class="text-muted">Selecciona proyectos raíz específicos de otros aularios para mostrarlos en este aulario. Puedes configurar el nivel de permisos: Solo lectura, Solicitar permiso para cambiar, o Cambiar sin solicitar.</p>
            
            <div id="linked-projects-container">
                <?php
                $existing_links = $aulario_data['linked_projects'] ?? [];
                if (count($existing_links) === 0) {
                    // Show one empty row
                    $existing_links = [["source_aulario" => "", "project_id" => "", "permission" => "read_only"]];
                }
                foreach ($existing_links as $idx => $link):
                    $source_aul = $link['source_aulario'] ?? '';
                    $proj_id = $link['project_id'] ?? '';
                    $permission = $link['permission'] ?? 'read_only';
                ?>
                <div class="row mb-2 linked-project-row">
                    <div class="col-md-4">
                        <select name="linked_aulario[]" class="form-select linked-aulario-select" data-row="<?php echo $idx; ?>">
                            <option value="">-- Seleccionar aulario origen --</option>
                            <?php foreach ($available_aularios as $aul_id => $aul_name): ?>
                                <option value="<?php echo htmlspecialchars($aul_id); ?>" 
                                    <?php echo $source_aul === $aul_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($aul_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="linked_project_id[]" class="form-select linked-project-select" data-row="<?php echo $idx; ?>">
                            <option value="">-- Seleccionar proyecto --</option>
                            <?php if (!empty($source_aul) && isset($available_projects_by_aulario[$source_aul])): ?>
                                <?php foreach ($available_projects_by_aulario[$source_aul] as $proj): ?>
                                    <option value="<?php echo htmlspecialchars($proj['id']); ?>"
                                        <?php echo $proj_id === $proj['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($proj['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="linked_permission[]" class="form-select">
                            <option value="read_only" <?php echo $permission === 'read_only' ? 'selected' : ''; ?>>Solo lectura</option>
                            <option value="request_edit" <?php echo $permission === 'request_edit' ? 'selected' : ''; ?>>Solicitar permiso para cambiar</option>
                            <option value="full_edit" <?php echo $permission === 'full_edit' ? 'selected' : ''; ?>>Cambiar sin solicitar</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger remove-link-btn" onclick="removeLinkedProject(this)">Eliminar</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" class="btn btn-secondary mb-3" onclick="addLinkedProject()">+ Añadir Proyecto Enlazado</button>
            
            <script>
                // Store available projects data
                const availableProjects = <?php echo json_encode($available_projects_by_aulario); ?>;
                let rowCounter = <?php echo count($existing_links); ?>;
                
                function addLinkedProject() {
                    const container = document.getElementById('linked-projects-container');
                    const newRow = document.createElement('div');
                    newRow.className = 'row mb-2 linked-project-row';
                    newRow.innerHTML = `
                        <div class="col-md-4">
                            <select name="linked_aulario[]" class="form-select linked-aulario-select" data-row="${rowCounter}">
                                <option value="">-- Seleccionar aulario origen --</option>
                                <?php foreach ($available_aularios as $aul_id => $aul_name): ?>
                                    <option value="<?php echo htmlspecialchars($aul_id); ?>">
                                        <?php echo htmlspecialchars($aul_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="linked_project_id[]" class="form-select linked-project-select" data-row="${rowCounter}">
                                <option value="">-- Seleccionar proyecto --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="linked_permission[]" class="form-select">
                                <option value="read_only">Solo lectura</option>
                                <option value="request_edit">Solicitar permiso para cambiar</option>
                                <option value="full_edit">Cambiar sin solicitar</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger remove-link-btn" onclick="removeLinkedProject(this)">Eliminar</button>
                        </div>
                    `;
                    container.appendChild(newRow);
                    
                    // Attach change event to new aulario select
                    const newAularioSelect = newRow.querySelector('.linked-aulario-select');
                    newAularioSelect.addEventListener('change', updateProjectOptions);
                    
                    rowCounter++;
                }
                
                function removeLinkedProject(btn) {
                    btn.closest('.linked-project-row').remove();
                }
                
                function updateProjectOptions(event) {
                    const aularioSelect = event.target;
                    const rowId = aularioSelect.dataset.row;
                    const projectSelect = document.querySelector(`.linked-project-select[data-row="${rowId}"]`);
                    const selectedAulario = aularioSelect.value;
                    
                    // Clear project options
                    projectSelect.innerHTML = '<option value="">-- Seleccionar proyecto --</option>';
                    
                    // Add new options
                    if (selectedAulario && availableProjects[selectedAulario]) {
                        availableProjects[selectedAulario].forEach(proj => {
                            const option = document.createElement('option');
                            option.value = proj.id;
                            option.textContent = proj.name;
                            projectSelect.appendChild(option);
                        });
                    }
                }
                
                // Attach event listeners to existing selects
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.linked-aulario-select').forEach(select => {
                        select.addEventListener('change', updateProjectOptions);
                    });
                });
            </script>
            
            <input type="hidden" name="aulario_id" value="<?php echo htmlspecialchars($aulario_id); ?>">
            <input type="hidden" name="centro_id" value="<?php echo htmlspecialchars($centro_id); ?>">
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </form>
        <form method="post" action="?form=delete" style="display: inline;">
            <input type="hidden" name="aulario_id" value="<?php echo htmlspecialchars($aulario_id); ?>">
            <input type="hidden" name="centro_id" value="<?php echo htmlspecialchars($centro_id); ?>">
            <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que deseas eliminar este aulario? Esta acción no se puede deshacer.')">Eliminar Aulario</button>
        </form>
    </div>
</div>
<?php 
        break;
    case "index":
    default:
?>
<div class="card pad">
    <div>
        <h1 class="card-title">Gestión de Aularios</h1>
        <span>
            Desde esta sección puedes administrar los aularios asociados al centro que estás administrando.
        </span>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Icono</th>
                    <th>Nombre</th>
                    <th>
                        <a href="?action=new" class="btn btn-success">+ Nuevo</a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                $user_data = $_SESSION["auth_data"];
                $centro_filter = safe_path_segment(Sf($_GET['centro'] ?? ""));
                if ($centro_filter !== "") {
                    $aulas_filelist = glob("/DATA/entreaulas/Centros/$centro_filter/Aularios/*.json") ?: [];
                } else {
                    $aulas_filelist = glob("/DATA/entreaulas/Centros/*/Aularios/*.json") ?: [];
                }
                foreach ($aulas_filelist as $aula_file) {
                    $aula_data = json_decode(file_get_contents($aula_file), true);
                    $centro_id = basename(dirname(dirname($aula_file)));
                    echo '<tr>';
                    echo '<td><img src="' . htmlspecialchars($aula_data['icon'] ?? '/static/logo-entreaulas.png') . '" alt="Icono" style="height: 50px;"></td>';
                    echo '<td>' . htmlspecialchars($aula_data['name'] ?? 'Sin Nombre') . '<br><small>' . $centro_id . '</small></td>';
                    echo '<td><a href="?action=edit&aulario=' . urlencode(basename($aula_file, ".json")) . '&centro=' . urlencode($centro_id) . '" class="btn btn-primary">Gestionar</a></td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<?php 
        break;
}



require_once "_incl/post-body.php"; ?>