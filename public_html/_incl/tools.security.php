<?php

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
    $filename = realpath($filename);
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
