<?php

namespace Axia4\Auth;

/**
 * Axia4 Authentication Handler
 *
 * Extends the ADIOS core Auth class to integrate Axia4's user storage
 * (JSON files in /DATA/Usuarios/) and support both password-based login
 * and Google OAuth.
 *
 * During database migration, user records may be stored as JSON files.
 * Once a database is configured, users can be migrated to the SQL table.
 */
class Axia4Auth extends \ADIOS\Core\Auth
{
    private string $dataDir;

    public function __construct(\ADIOS\Core\Loader $app)
    {
        parent::__construct($app);
        $this->dataDir = rtrim($app->config->getAsString('dataDir', '/DATA'), '/');
    }

    /**
     * Attempt to authenticate a user by username/email and plain-text password.
     * Loads the user profile from a JSON file and verifies the bcrypt hash.
     *
     * @param  string $username  Username or e-mail address.
     * @param  string $password  Plain-text password.
     * @return array|null        User data array on success, null on failure.
     */
    public function attemptLogin(string $username, string $password): ?array
    {
        $filename = $this->safeUsernameToFilename($username);
        if ($filename === '') {
            return null;
        }

        $filepath = $this->dataDir . '/Usuarios/' . $filename . '.json';
        if (!file_exists($filepath)) {
            return null;
        }

        $userdata = json_decode(file_get_contents($filepath), true);
        if (!is_array($userdata) || !isset($userdata['password_hash'])) {
            return null;
        }

        if (!password_verify($password, $userdata['password_hash'])) {
            return null;
        }

        return array_merge($userdata, ['username' => $username]);
    }

    /**
     * Load a user profile from the JSON file store by username.
     *
     * @param  string $username
     * @return array|null
     */
    public function loadUser(string $username): ?array
    {
        $filename = $this->safeUsernameToFilename($username);
        if ($filename === '') {
            return null;
        }

        $filepath = $this->dataDir . '/Usuarios/' . $filename . '.json';
        if (!file_exists($filepath)) {
            return null;
        }

        $userdata = json_decode(file_get_contents($filepath), true);
        return is_array($userdata) ? array_merge($userdata, ['username' => $username]) : null;
    }

    /**
     * Create or update a user profile in the JSON file store.
     *
     * @param  string $username
     * @param  array  $data
     * @return bool
     */
    public function saveUser(string $username, array $data): bool
    {
        $filename = $this->safeUsernameToFilename($username);
        if ($filename === '') {
            return false;
        }

        $dir = $this->dataDir . '/Usuarios';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filepath = $dir . '/' . $filename . '.json';
        return file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Check whether the currently authenticated user has a given permission.
     *
     * @param  string $permission
     * @return bool
     */
    public function userHasPermission(string $permission): bool
    {
        $user = $this->getUser();
        $permissions = $user['permissions'] ?? [];
        return in_array($permission, $permissions, true);
    }

    /**
     * Convert a username/email to a safe filename (no path traversal).
     * Mirrors the legacy safe_username_to_filename() security function.
     *
     * @param  string $username
     * @return string  Safe filename without extension, or '' on failure.
     */
    public function safeUsernameToFilename(string $username): string
    {
        // Reject path-traversal attempts before any transformation
        if (str_contains($username, '..') || str_contains($username, '/') || str_contains($username, '\\')) {
            return '';
        }

        // Allow only alphanumeric, dots, hyphens, underscores and @
        $safe = preg_replace('/[^a-zA-Z0-9._\-@]/', '', $username);

        // Reject empty results
        if ($safe === '') {
            return '';
        }

        return $safe;
    }
}
