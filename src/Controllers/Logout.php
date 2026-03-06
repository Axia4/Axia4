<?php

namespace Axia4\Controllers;

/**
 * Logout controller – destroys the current session and redirects to home.
 */
class Logout extends \ADIOS\Core\Controller
{
    public bool $requiresUserAuthentication = false;

    public function prepareView(): void
    {
        // Expire cookies (clear both old and new cookie names for compatibility)
        $cookieOpts = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'secure'   => isset($_SERVER['HTTPS']),
            'samesite' => 'Lax',
        ];
        setcookie('auth_user', '', $cookieOpts);
        setcookie('auth_token', '', $cookieOpts);
        // Legacy cookie names (backward compatibility with public_html/ code)
        setcookie('auth_pass_b64', '', $cookieOpts);

        $this->app->auth->signOut();
    }
}
