<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/../_incl/tools.security.php";
require_once __DIR__ . "/../_incl/auth_redir.php";

function safe_id_segment($value) {
    $value = basename((string)$value);
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value);
}

function safe_centro_id($value) {
    return preg_replace('/[^0-9]/', '', (string)$value);
}

function safe_aulario_config_path($centro_id, $aulario_id) {
    $centro = safe_centro_id($centro_id);
    $aulario = safe_id_segment($aulario_id);
    if ($centro === '' || $aulario === '') {
        return null;
    }
    return "/DATA/entreaulas/Centros/$centro/Aularios/$aulario.json";
}

function menu_types_path($centro_id, $aulario_id) {
    $centro = safe_centro_id($centro_id);
    $aulario = safe_id_segment($aulario_id);
    if ($centro === '' || $aulario === '') {
        return null;
    }
    return "/DATA/entreaulas/Centros/$centro/Aularios/$aulario/Comedor-MenuTypes.json";
}

function comedor_day_base_dir($centro_id, $aulario_id, $ym, $day) {
    $centro = safe_centro_id($centro_id);
    $aulario = safe_id_segment($aulario_id);
    if ($centro === '' || $aulario === '') {
        return null;
    }
    return "/DATA/entreaulas/Centros/$centro/Aularios/$aulario/Comedor/$ym/$day";
}

// Check permissions
if (!in_array("entreaulas:docente", $_SESSION["auth_data"]["permissions"] ?? [])) {
    http_response_code(403);
    die(json_encode(["error" => "Access denied", "code" => "FORBIDDEN"]));
}

$centro_id = safe_centro_id($_SESSION["auth_data"]["entreaulas"]["centro"] ?? "");
if ($centro_id === "") {
    http_response_code(400);
    die(json_encode(["error" => "Centro not found in session", "code" => "INVALID_SESSION"]));
}

$action = $_GET["action"] ?? ($_POST["action"] ?? "");
$aulario_id = safe_id_segment($_GET["aulario"] ?? $_POST["aulario"] ?? "");

// Validate aulario_id
if ($aulario_id === "") {
    http_response_code(400);
    die(json_encode(["error" => "aulario parameter is required", "code" => "MISSING_PARAM"]));
}

// Verify that the user has access to this aulario
$userAulas = $_SESSION["auth_data"]["entreaulas"]["aulas"] ?? [];
$userAulas = array_values(array_filter(array_map('safe_id_segment', $userAulas)));
if (!in_array($aulario_id, $userAulas, true)) {
    http_response_code(403);
    die(json_encode(["error" => "Access denied to this aulario", "code" => "FORBIDDEN"]));
}

$aulario_path = safe_aulario_config_path($centro_id, $aulario_id);
$aulario = ($aulario_path && file_exists($aulario_path)) ? json_decode(file_get_contents($aulario_path), true) : null;

// Handle shared comedor data
$source_aulario_id = $aulario_id;
$is_shared = false;
if ($aulario && !empty($aulario["shared_comedor_from"])) {
    $shared_from = safe_id_segment($aulario["shared_comedor_from"]);
    $shared_aulario_path = safe_aulario_config_path($centro_id, $shared_from);
    if ($shared_aulario_path && file_exists($shared_aulario_path)) {
        $source_aulario_id = $shared_from;
        $is_shared = true;
    }
}

// Check edit permissions (must be sysadmin and not shared)
$canEdit = in_array("sysadmin:access", $_SESSION["auth_data"]["permissions"] ?? []) && !$is_shared;

// Helper functions
function get_menu_types($centro_id, $source_aulario_id) {
    $menuTypesPath = menu_types_path($centro_id, $source_aulario_id);
    $defaultMenuTypes = [
        ["id" => "basal", "label" => "Menú basal", "color" => "#0d6efd"],
        ["id" => "vegetariano", "label" => "Menú vegetariano", "color" => "#198754"],
        ["id" => "alergias", "label" => "Menú alergias", "color" => "#dc3545"],
    ];

    if ($menuTypesPath === null) {
        return $defaultMenuTypes;
    }
    
    if (!file_exists($menuTypesPath)) {
        if (!is_dir(dirname($menuTypesPath))) {
            mkdir(dirname($menuTypesPath), 0777, true);
        }
        file_put_contents($menuTypesPath, json_encode($defaultMenuTypes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultMenuTypes;
    }
    
    $menuTypes = json_decode(@file_get_contents($menuTypesPath), true);
    return (is_array($menuTypes) && count($menuTypes) > 0) ? $menuTypes : $defaultMenuTypes;
}

function blank_menu() {
    return [
        "plates" => [
            "primero" => ["name" => "", "pictogram" => ""],
            "segundo" => ["name" => "", "pictogram" => ""],
            "postre" => ["name" => "", "pictogram" => ""],
        ]
    ];
}

function safe_filename($name) {
    $name = basename($name);
    return preg_replace("/[^a-zA-Z0-9._-]/", "_", $name);
}

// Routes
switch ($action) {
    case "get_menu_types":
        handle_get_menu_types();
        break;
    
    case "get_menu":
        handle_get_menu();
        break;
    
    case "save_menu":
        if (!$canEdit) {
            http_response_code(403);
            die(json_encode(["error" => "Insufficient permissions to edit", "code" => "FORBIDDEN"]));
        }
        handle_save_menu();
        break;
    
    case "add_menu_type":
        if (!$canEdit) {
            http_response_code(403);
            die(json_encode(["error" => "Insufficient permissions to edit", "code" => "FORBIDDEN"]));
        }
        handle_add_menu_type();
        break;
    
    case "delete_menu_type":
        if (!$canEdit) {
            http_response_code(403);
            die(json_encode(["error" => "Insufficient permissions to edit", "code" => "FORBIDDEN"]));
        }
        handle_delete_menu_type();
        break;
    
    case "rename_menu_type":
        if (!$canEdit) {
            http_response_code(403);
            die(json_encode(["error" => "Insufficient permissions to edit", "code" => "FORBIDDEN"]));
        }
        handle_rename_menu_type();
        break;
    
    default:
        http_response_code(400);
        die(json_encode(["error" => "Invalid action", "code" => "INVALID_ACTION"]));
}

function handle_get_menu_types() {
    global $centro_id, $source_aulario_id;
    
    $menuTypes = get_menu_types($centro_id, $source_aulario_id);
    echo json_encode([
        "success" => true,
        "menu_types" => $menuTypes
    ]);
}

function handle_get_menu() {
    global $centro_id, $source_aulario_id;
    
    $date = $_GET["date"] ?? date("Y-m-d");
    $menuTypeId = safe_id_segment($_GET["menu"] ?? "");
    
    // Validate date
    $dateObj = DateTime::createFromFormat("Y-m-d", $date);
    if (!$dateObj) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid date format", "code" => "INVALID_FORMAT"]));
    }
    $date = $dateObj->format("Y-m-d");
    
    // Get menu types
    $menuTypes = get_menu_types($centro_id, $source_aulario_id);
    $menuTypeIds = [];
    foreach ($menuTypes as $t) {
        if (!empty($t["id"])) {
            $menuTypeIds[] = $t["id"];
        }
    }
    
    if ($menuTypeId === "" || !in_array($menuTypeId, $menuTypeIds)) {
        $menuTypeId = $menuTypeIds[0] ?? "basal";
    }
    
    // Get menu data
    $ym = $dateObj->format("Y-m");
    $day = $dateObj->format("d");
    $baseDir = comedor_day_base_dir($centro_id, $source_aulario_id, $ym, $day);
    if ($baseDir === null) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid path parameters", "code" => "INVALID_PATH"]));
    }
    $dataPath = "$baseDir/_datos.json";
    
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
    
    if (!isset($menuData["menus"][$menuTypeId])) {
        $menuData["menus"][$menuTypeId] = blank_menu();
    }
    
    $menuForType = $menuData["menus"][$menuTypeId];
    
    echo json_encode([
        "success" => true,
        "date" => $date,
        "menu_type" => $menuTypeId,
        "menu_types" => $menuTypes,
        "menu" => $menuForType
    ]);
}

function handle_save_menu() {
    global $centro_id, $source_aulario_id;
    
    // Parse JSON body
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $date = $input["date"] ?? date("Y-m-d");
    $menuTypeId = safe_id_segment($input["menu_type"] ?? "");
    $plates = $input["plates"] ?? [];
    
    // Validate date
    $dateObj = DateTime::createFromFormat("Y-m-d", $date);
    if (!$dateObj) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid date format", "code" => "INVALID_FORMAT"]));
    }
    $date = $dateObj->format("Y-m-d");
    
    // Validate menu type
    $menuTypes = get_menu_types($centro_id, $source_aulario_id);
    $validMenuTypeIds = [];
    foreach ($menuTypes as $t) {
        if (!empty($t["id"])) {
            $validMenuTypeIds[] = $t["id"];
        }
    }
    
    if (!in_array($menuTypeId, $validMenuTypeIds)) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid menu type", "code" => "INVALID_MENU_TYPE"]));
    }
    
    // Get existing menu data
    $ym = $dateObj->format("Y-m");
    $day = $dateObj->format("d");
    $baseDir = comedor_day_base_dir($centro_id, $source_aulario_id, $ym, $day);
    if ($baseDir === null) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid path parameters", "code" => "INVALID_PATH"]));
    }
    $dataPath = "$baseDir/_datos.json";
    
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
    
    if (!isset($menuData["menus"][$menuTypeId])) {
        $menuData["menus"][$menuTypeId] = blank_menu();
    }
    
    // Update plates
    $validPlates = ["primero", "segundo", "postre"];
    foreach ($validPlates as $plateKey) {
        if (isset($plates[$plateKey])) {
            if (isset($plates[$plateKey]["name"])) {
                $menuData["menus"][$menuTypeId]["plates"][$plateKey]["name"] = trim($plates[$plateKey]["name"]);
            }
            // Note: pictogram upload not supported via JSON API - use form-data instead
        }
    }
    
    // Save menu
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0777, true);
    }
    file_put_contents($dataPath, json_encode($menuData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        "success" => true,
        "date" => $date,
        "menu_type" => $menuTypeId,
        "menu" => $menuData["menus"][$menuTypeId]
    ]);
}

function handle_add_menu_type() {
    global $centro_id, $source_aulario_id;
    
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $newId = safe_id_segment(strtolower(trim($input["id"] ?? "")));
    $newLabel = trim($input["label"] ?? "");
    $newColor = trim($input["color"] ?? "#0d6efd");
    
    if ($newId === "" || $newLabel === "") {
        http_response_code(400);
        die(json_encode(["error" => "id and label are required", "code" => "MISSING_PARAM"]));
    }
    
    $menuTypesPath = menu_types_path($centro_id, $source_aulario_id);
    if ($menuTypesPath === null) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid path parameters", "code" => "INVALID_PATH"]));
    }
    $menuTypes = get_menu_types($centro_id, $source_aulario_id);
    
    // Check if already exists
    foreach ($menuTypes as $t) {
        if (($t["id"] ?? "") === $newId) {
            http_response_code(400);
            die(json_encode(["error" => "Menu type already exists", "code" => "DUPLICATE"]));
        }
    }
    
    $menuTypes[] = ["id" => $newId, "label" => $newLabel, "color" => $newColor];
    file_put_contents($menuTypesPath, json_encode($menuTypes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        "success" => true,
        "menu_type" => ["id" => $newId, "label" => $newLabel, "color" => $newColor],
        "message" => "Menu type added successfully"
    ]);
}

function handle_delete_menu_type() {
    global $centro_id, $source_aulario_id;
    
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $deleteId = safe_id_segment(trim($input["id"] ?? ""));
    
    if ($deleteId === "") {
        http_response_code(400);
        die(json_encode(["error" => "id is required", "code" => "MISSING_PARAM"]));
    }
    
    $menuTypesPath = menu_types_path($centro_id, $source_aulario_id);
    if ($menuTypesPath === null) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid path parameters", "code" => "INVALID_PATH"]));
    }
    $menuTypes = get_menu_types($centro_id, $source_aulario_id);
    
    $deleted = false;
    $newMenuTypes = [];
    foreach ($menuTypes as $t) {
        if (($t["id"] ?? "") === $deleteId) {
            $deleted = true;
        } else {
            $newMenuTypes[] = $t;
        }
    }
    
    if (!$deleted) {
        http_response_code(404);
        die(json_encode(["error" => "Menu type not found", "code" => "NOT_FOUND"]));
    }
    
    file_put_contents($menuTypesPath, json_encode($newMenuTypes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        "success" => true,
        "message" => "Menu type deleted successfully"
    ]);
}

function handle_rename_menu_type() {
    global $centro_id, $source_aulario_id;
    
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $renameId = safe_id_segment(trim($input["id"] ?? ""));
    $newLabel = trim($input["label"] ?? "");
    $newColor = trim($input["color"] ?? "");
    
    if ($renameId === "" || $newLabel === "") {
        http_response_code(400);
        die(json_encode(["error" => "id and label are required", "code" => "MISSING_PARAM"]));
    }
    
    $menuTypesPath = menu_types_path($centro_id, $source_aulario_id);
    if ($menuTypesPath === null) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid path parameters", "code" => "INVALID_PATH"]));
    }
    $menuTypes = get_menu_types($centro_id, $source_aulario_id);
    
    $found = false;
    foreach ($menuTypes as &$t) {
        if (($t["id"] ?? "") === $renameId) {
            $t["label"] = $newLabel;
            if ($newColor !== "") {
                $t["color"] = $newColor;
            }
            $found = true;
            break;
        }
    }
    unset($t);
    
    if (!$found) {
        http_response_code(404);
        die(json_encode(["error" => "Menu type not found", "code" => "NOT_FOUND"]));
    }
    
    file_put_contents($menuTypesPath, json_encode($menuTypes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        "success" => true,
        "menu_type" => ["id" => $renameId, "label" => $newLabel, "color" => $newColor],
        "message" => "Menu type renamed successfully"
    ]);
}
