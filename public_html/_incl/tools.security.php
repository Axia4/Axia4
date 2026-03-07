<?php

function Ssql($string) {
    // Sanitize a SQL Parameter to be safe on html.
    return htmlspecialchars($string);
}

function Sf($filename) {
    /**
     * Sanitize a filename by removing any path information, null bytes, and replacing any characters that are not alphanumeric, dot, hyphen, or underscore with an underscore.
     * 
     * This function is designed to prevent directory traversal attacks and ensure that the filename is safe to use in file operations.
     * 
     * @param string $filename The input filename to sanitize.
     * @return string The sanitized filename.
     */
    // Remove any path information and null bytes
    $filename = basename($filename);
    if ($filename === false) {
        $filename = "";
    }
    $filename = str_replace("\0", "", $filename);
    // Replace any characters that are not alphanumeric, dot, hyphen, or underscore with an underscore
    $filename = preg_replace("/[^a-zA-Z0-9._-]/", "_", $filename);
    return $filename;
}

function Sp($path) {
    /**
     * Sanitize a file path by removing any null bytes, normalizing directory separators, and preventing directory traversal.
     * 
     * This function is designed to ensure that the file path is safe to use in file operations and does not allow for directory traversal attacks.
     * 
     * @param string $path The input file path to sanitize.
     * @return string The sanitized file path.
     */
    // Remove any null bytes
    $path = str_replace("\0", "", $path);
    // Normalize directory separators
    $path = str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
    // Remove any instances of ".." to prevent directory traversal
    $path = str_replace("..", "", $path);
    // Remove any leading directory separators
    $path = ltrim($path, DIRECTORY_SEPARATOR);
    return $path;
}

function Si($input) {
    /**
     * Sanitize a string input by removing null bytes, trimming whitespace, and converting special characters to HTML entities.
     * 
     * This function is designed to prevent XSS attacks and ensure that the input string is safe to use in HTML contexts.
     * 
     * @param string $input The input string to sanitize.
     * @return string The sanitized string.
     */
    // Remove any null bytes
    $input = str_replace("\0", "", $input);
    // Trim whitespace from the beginning and end of the input
    $input = trim($input);
    // Convert special characters to HTML entities to prevent XSS
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

function safe_username_to_filename($username) {
    /**
     * Convert a username (plain username or email) to a safe filename for use in file operations.
     * 
     * Email addresses have @ replaced with __ to match how Google OAuth users are stored.
     * The result contains only alphanumeric characters, dots, underscores, and hyphens.
     * 
     * @param string $username The username or email to convert.
     * @return string The safe filename (without path or extension), or "" if invalid.
     */
    $filename = strtolower((string)$username);
    // Remove null bytes
    $filename = str_replace("\0", "", $filename);
    // Replace @ with __ (to match Google OAuth file naming)
    $filename = str_replace("@", "__", $filename);
    // Remove any path components to prevent directory traversal
    $filename = basename($filename);
    // Remove .. sequences
    $filename = str_replace("..", "", $filename);
    // Keep only alphanumeric, dot, underscore, hyphen
    $filename = preg_replace("/[^a-zA-Z0-9._-]/", "_", $filename);
    // Trim dots and underscores from ends
    $filename = trim($filename, "._");
    return $filename;
}

function Sb($input) {
    /**
     * Sanitize a boolean input by converting it to a boolean value.
     * 
     * This function is designed to ensure that the input is treated as a boolean value, which can be useful for configuration settings or form inputs.
     * 
     * @param mixed $input The input value to sanitize.
     * @return bool The sanitized boolean value.
     */
    if (is_bool($input)) {
        return $input;
    }
    if (is_string($input)) {
        $input = strtolower($input);
        if (in_array($input, ["true", "1", "yes", "on"])) {
            return true;
        } elseif (in_array($input, ["false", "0", "no", "off"])) {
            return false;
        }
    }
    return (bool)$input;
}
function get_user_file_path($username)
{
    $users_dir = defined('USERS_DIR') ? USERS_DIR : '/DATA/Usuarios/';
    return rtrim($users_dir, '/') . '/' . $username . '.json';
}

function safe_organization_id($value)
{
  return preg_replace('/[^a-zA-Z0-9._-]/', '', (string)$value);
}

function safe_organizacion_id($value)
{
        return safe_organization_id($value);
}

function safe_centro_id($value)
{
        return safe_organization_id($value);
}

function safe_aulario_id($value)
{
  $value = basename((string)$value);
  return preg_replace('/[^a-zA-Z0-9._-]/', '', $value);
}

function safe_filename($name)
{
    // Normalize to base name to avoid directory traversal
    $name = basename((string)$name);

    // Best-effort normalize encoding to avoid odd Unicode tricks
    if (function_exists('mb_convert_encoding')) {
        $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
    }

    // Replace disallowed characters with underscore
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    // Collapse multiple underscores introduced by replacement
    $name = preg_replace('/_+/', '_', $name);

    // Remove leading dots to avoid hidden/special files like ".htaccess"
    $name = ltrim($name, '.');

    // Ensure there is at most one dot in the filename to prevent extension confusion
    if (substr_count($name, '.') > 1) {
        $parts = explode('.', $name);
        $ext   = array_pop($parts);
        $base  = implode('_', $parts);
        // Ensure extension is not empty
        if ($ext === '') {
            $name = $base === '' ? 'file' : $base;
        } else {
            $name = ($base === '' ? 'file' : $base) . '.' . $ext;
        }
    }

    // Trim stray dots/underscores from the start and end
    $name = trim($name, "._");

    // Enforce a maximum length (common filesystem limit is 255 bytes)
    $maxLen = 255;
    if (strlen($name) > $maxLen) {
        $dotPos = strrpos($name, '.');
        if ($dotPos !== false) {
            $ext  = substr($name, $dotPos);
            $base = substr($name, 0, $dotPos);
            $baseMaxLen = $maxLen - strlen($ext);
            if ($baseMaxLen < 1) {
                // Fallback if extension is unusually long
                $name = substr($name, 0, $maxLen);
            } else {
                $name = substr($base, 0, $baseMaxLen) . $ext;
            }
        } else {
            $name = substr($name, 0, $maxLen);
        }
    }

    // Ensure we never return an empty or invalid filename
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'file';
    }

    return $name;
}
function safe_id_segment($value)
{
    $value = basename((string)$value);
    return preg_replace('/[^A-Za-z0-9._-]/', '', $value);
}
function safe_id($value)
{
    return preg_replace('/[^a-zA-Z0-9._-]/', '', basename((string)$value));
}
function safe_alumno_name($value)
{
    $value = basename((string)$value);
    $value = trim($value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    $value = str_replace(['/', '\\'], '', $value);
    return $value;
}

function path_is_within($real_base, $real_path)
{
    if ($real_base === false || $real_path === false) {
        return false;
    }
    $base_prefix = rtrim($real_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strpos($real_path, $base_prefix) === 0 || $real_path === rtrim($real_base, DIRECTORY_SEPARATOR);
}

function aulatek_orgs_base_path()
{
    $orgs_path = '/DATA/entreaulas/Organizaciones';
    $legacy_path = '/DATA/entreaulas/Centros';
    if (is_dir($orgs_path)) {
        return $orgs_path;
    }
    if (is_dir($legacy_path)) {
        return $legacy_path;
    }
    return $orgs_path;
}

function entreaulas_orgs_base_path()
{
    return aulatek_orgs_base_path();
}

function safe_aulario_config_path($centro_id, $aulario_id)
{
    $centro = safe_organization_id($centro_id);
    $aulario = safe_id_segment($aulario_id);
    if ($centro === '' || $aulario === '') {
        return null;
    }
    return aulatek_orgs_base_path() . "/$centro/Aularios/$aulario.json";
}

function safe_redir($url, $default = "/")
{
    if (empty($url) || !is_string($url)) {
        return $default;
    }
    // Only allow relative URLs that start with /
    if (str_starts_with($url, "/") && !str_contains($url, "\0")) {
        return $url;
    }
    return $default;
}