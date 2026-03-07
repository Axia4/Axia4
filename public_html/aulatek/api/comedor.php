<?php
header("Content-Type: application/json; charset=utf-8");
require_once "../_incl/auth_redir.php";
require_once "../../_incl/tools.security.php";
require_once "../../_incl/db.php";

// Check permissions
$permissions = $_SESSION["auth_data"]["permissions"] ?? [];
if (!in_array("aulatek:docente", $permissions, true) && !in_array("entreaulas:docente", $permissions, true)) {
    http_response_code(403);
    die(json_encode(["error" => "Access denied", "code" => "FORBIDDEN"]));
}

$tenant_data = $_SESSION["auth_data"]["aulatek"] ?? ($_SESSION["auth_data"]["entreaulas"] ?? []);
$centro_id = safe_organization_id($tenant_data["organizacion"] ?? ($tenant_data["centro"] ?? ""));
if ($centro_id === "") {
    http_response_code(400);
    die(json_encode(["error" => "Organizacion not found in session", "code" => "INVALID_SESSION"]));
}

$action     = $_GET["action"] ?? ($_POST["action"] ?? "");
$aulario_id = safe_id_segment($_GET["aulario"] ?? $_POST["aulario"] ?? "");

if ($aulario_id === "") {
    http_response_code(400);
    die(json_encode(["error" => "aulario parameter is required", "code" => "MISSING_PARAM"]));
}

$userAulas = array_values(array_filter(array_map('safe_id_segment', $tenant_data["aulas"] ?? [])));
if (!in_array($aulario_id, $userAulas, true)) {
    http_response_code(403);
    die(json_encode(["error" => "Access denied to this aulario", "code" => "FORBIDDEN"]));
}

$aulario = db_get_aulario($centro_id, $aulario_id);

$source_aulario_id = $aulario_id;
$is_shared = false;
if ($aulario && !empty($aulario["shared_comedor_from"])) {
    $shared_from = safe_id_segment($aulario["shared_comedor_from"]);
    if (db_get_aulario($centro_id, $shared_from)) {
        $source_aulario_id = $shared_from;
        $is_shared = true;
    }
}

$canEdit = in_array("sysadmin:access", $_SESSION["auth_data"]["permissions"] ?? []) && !$is_shared;

$defaultMenuTypes = [
    ["id" => "basal",       "label" => "Menú basal",       "color" => "#0d6efd"],
    ["id" => "vegetariano", "label" => "Menú vegetariano", "color" => "#198754"],
    ["id" => "alergias",    "label" => "Menú alergias",    "color" => "#dc3545"],
];

function get_menu_types($centro_id, $source_aulario_id) {
    global $defaultMenuTypes;
    $types = db_get_comedor_menu_types($centro_id, $source_aulario_id);
    if (empty($types)) {
        db_set_comedor_menu_types($centro_id, $source_aulario_id, $defaultMenuTypes);
        return $defaultMenuTypes;
    }
    return $types;
}

function blank_menu() {
    return [
        "plates" => [
            "primero" => ["name" => "", "pictogram" => ""],
            "segundo" => ["name" => "", "pictogram" => ""],
            "postre"  => ["name" => "", "pictogram" => ""],
        ]
    ];
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
        if (!$canEdit) { http_response_code(403); die(json_encode(["error" => "Insufficient permissions to edit", "code" => "FORBIDDEN"])); }
        handle_save_menu();
        break;
    case "add_menu_type":
        if (!$canEdit) { http_response_code(403); die(json_encode(["error" => "Insufficient permissions to edit", "code" => "FORBIDDEN"])); }
        handle_add_menu_type();
        break;
    case "delete_menu_type":
        if (!$canEdit) { http_response_code(403); die(json_encode(["error" => "Insufficient permissions to edit", "code" => "FORBIDDEN"])); }
        handle_delete_menu_type();
        break;
    case "rename_menu_type":
        if (!$canEdit) { http_response_code(403); die(json_encode(["error" => "Insufficient permissions to edit", "code" => "FORBIDDEN"])); }
        handle_rename_menu_type();
        break;
    default:
        http_response_code(400);
        die(json_encode(["error" => "Invalid action", "code" => "INVALID_ACTION"]));
}

function handle_get_menu_types() {
    global $centro_id, $source_aulario_id;
    echo json_encode(["success" => true, "menu_types" => get_menu_types($centro_id, $source_aulario_id)]);
}

function handle_get_menu() {
    global $centro_id, $source_aulario_id;
    $date = $_GET["date"] ?? date("Y-m-d");
    $menuTypeId = safe_id_segment($_GET["menu"] ?? "");
    $dateObj = DateTime::createFromFormat("Y-m-d", $date);
    if (!$dateObj) { http_response_code(400); die(json_encode(["error" => "Invalid date format", "code" => "INVALID_FORMAT"])); }
    $date = $dateObj->format("Y-m-d");
    $menuTypes   = get_menu_types($centro_id, $source_aulario_id);
    $menuTypeIds = array_column($menuTypes, "id");
    if ($menuTypeId === "" || !in_array($menuTypeId, $menuTypeIds)) { $menuTypeId = $menuTypeIds[0] ?? "basal"; }
    $ym  = $dateObj->format("Y-m");
    $day = $dateObj->format("d");
    $menuData = ["date" => $date, "menus" => []];
    $existing = db_get_comedor_entry($centro_id, $source_aulario_id, $ym, $day);
    if (!empty($existing)) { $menuData = array_merge($menuData, $existing); }
    if (!isset($menuData["menus"][$menuTypeId])) { $menuData["menus"][$menuTypeId] = blank_menu(); }
    echo json_encode(["success" => true, "date" => $date, "menu_type" => $menuTypeId, "menu_types" => $menuTypes, "menu" => $menuData["menus"][$menuTypeId]]);
}

function handle_save_menu() {
    global $centro_id, $source_aulario_id;
    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $date       = $input["date"] ?? date("Y-m-d");
    $menuTypeId = safe_id_segment($input["menu_type"] ?? "");
    $plates     = $input["plates"] ?? [];
    $dateObj = DateTime::createFromFormat("Y-m-d", $date);
    if (!$dateObj) { http_response_code(400); die(json_encode(["error" => "Invalid date format", "code" => "INVALID_FORMAT"])); }
    $date = $dateObj->format("Y-m-d");
    $menuTypes      = get_menu_types($centro_id, $source_aulario_id);
    $validMenuTypeIds = array_column($menuTypes, "id");
    if (!in_array($menuTypeId, $validMenuTypeIds)) { http_response_code(400); die(json_encode(["error" => "Invalid menu type", "code" => "INVALID_MENU_TYPE"])); }
    $ym  = $dateObj->format("Y-m");
    $day = $dateObj->format("d");
    $menuData = ["date" => $date, "menus" => []];
    $existing = db_get_comedor_entry($centro_id, $source_aulario_id, $ym, $day);
    if (!empty($existing)) { $menuData = array_merge($menuData, $existing); }
    if (!isset($menuData["menus"][$menuTypeId])) { $menuData["menus"][$menuTypeId] = blank_menu(); }
    foreach (["primero", "segundo", "postre"] as $plateKey) {
        if (isset($plates[$plateKey]["name"])) {
            $menuData["menus"][$menuTypeId]["plates"][$plateKey]["name"] = trim($plates[$plateKey]["name"]);
        }
    }
    db_set_comedor_entry($centro_id, $source_aulario_id, $ym, $day, $menuData);
    echo json_encode(["success" => true, "date" => $date, "menu_type" => $menuTypeId, "menu" => $menuData["menus"][$menuTypeId]]);
}

function handle_add_menu_type() {
    global $centro_id, $source_aulario_id;
    $input    = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $newId    = safe_id_segment(strtolower(trim($input["id"] ?? "")));
    $newLabel = trim($input["label"] ?? "");
    $newColor = trim($input["color"] ?? "#0d6efd");
    if ($newId === "" || $newLabel === "") { http_response_code(400); die(json_encode(["error" => "id and label are required", "code" => "MISSING_PARAM"])); }
    $menuTypes = get_menu_types($centro_id, $source_aulario_id);
    foreach ($menuTypes as $t) {
        if (($t["id"] ?? "") === $newId) { http_response_code(400); die(json_encode(["error" => "Menu type already exists", "code" => "DUPLICATE"])); }
    }
    $menuTypes[] = ["id" => $newId, "label" => $newLabel, "color" => $newColor];
    db_set_comedor_menu_types($centro_id, $source_aulario_id, $menuTypes);
    echo json_encode(["success" => true, "menu_type" => ["id" => $newId, "label" => $newLabel, "color" => $newColor], "message" => "Menu type added successfully"]);
}

function handle_delete_menu_type() {
    global $centro_id, $source_aulario_id;
    $input    = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $deleteId = safe_id_segment(trim($input["id"] ?? ""));
    if ($deleteId === "") { http_response_code(400); die(json_encode(["error" => "id is required", "code" => "MISSING_PARAM"])); }
    $menuTypes    = get_menu_types($centro_id, $source_aulario_id);
    $newMenuTypes = array_values(array_filter($menuTypes, fn($t) => ($t["id"] ?? "") !== $deleteId));
    if (count($newMenuTypes) === count($menuTypes)) { http_response_code(404); die(json_encode(["error" => "Menu type not found", "code" => "NOT_FOUND"])); }
    db_set_comedor_menu_types($centro_id, $source_aulario_id, $newMenuTypes);
    echo json_encode(["success" => true, "message" => "Menu type deleted successfully"]);
}

function handle_rename_menu_type() {
    global $centro_id, $source_aulario_id;
    $input    = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $renameId = safe_id_segment(trim($input["id"] ?? ""));
    $newLabel = trim($input["label"] ?? "");
    $newColor = trim($input["color"] ?? "");
    if ($renameId === "" || $newLabel === "") { http_response_code(400); die(json_encode(["error" => "id and label are required", "code" => "MISSING_PARAM"])); }
    $menuTypes = get_menu_types($centro_id, $source_aulario_id);
    $found = false;
    foreach ($menuTypes as &$t) {
        if (($t["id"] ?? "") === $renameId) {
            $t["label"] = $newLabel;
            if ($newColor !== "") { $t["color"] = $newColor; }
            $found = true;
            break;
        }
    }
    unset($t);
    if (!$found) { http_response_code(404); die(json_encode(["error" => "Menu type not found", "code" => "NOT_FOUND"])); }
    db_set_comedor_menu_types($centro_id, $source_aulario_id, $menuTypes);
    echo json_encode(["success" => true, "message" => "Menu type renamed successfully"]);
}
