<?php

namespace Axia4\Controllers;

/**
 * SysAdmin controller – administration panel for Axia4 platform configuration.
 *
 * Access is restricted to users with the 'sysadmin:access' permission.
 */
class SysAdmin extends \ADIOS\Core\Controller
{
    public bool $requiresUserAuthentication = true;

    public function prepareView(): void
    {
        /** @var \Axia4\Auth\Axia4Auth $auth */
        $auth = $this->app->auth;

        // Check for sysadmin permission
        if (!$auth->userHasPermission('sysadmin:access')) {
            http_response_code(403);
            $this->viewParams['error'] = 'No tienes permiso para acceder al panel de administración.';
            $this->setView('@app/Views/Error.twig');
            return;
        }

        $dataDir = $this->app->config->getAsString('dataDir', '/DATA');

        // Load list of users
        $users = [];
        $userDir = $dataDir . '/Usuarios';
        if (is_dir($userDir)) {
            foreach (glob($userDir . '/*.json') as $file) {
                $data = json_decode(file_get_contents($file), true);
                if (is_array($data)) {
                    $username = basename($file, '.json');
                    $users[]  = array_merge($data, ['username' => $username]);
                }
            }
        }

        $this->viewParams['users']       = $users;
        $this->viewParams['currentUser'] = $auth->getUser();
        $this->setView('@app/Views/SysAdmin.twig');
    }
}
