<?php

namespace Axia4\Controllers;

/**
 * Login controller – handles local username/password authentication.
 *
 * GET  /login  → render the login form
 * POST /login  → validate credentials and start session
 */
class Login extends \ADIOS\Core\Controller
{
    public bool $requiresUserAuthentication = false;

    public function prepareView(): void
    {
        /** @var \Axia4\Auth\Axia4Auth $auth */
        $auth = $this->app->auth;

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim(strtolower((string) ($_POST['user'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');

            $userdata = $auth->attemptLogin($username, $password);

            if ($userdata !== null) {
                session_regenerate_id(true);
                $auth->signIn($userdata);

                // Store a secure session token cookie for persistent login.
                // The token is a random hex string stored server-side in the session;
                // no password material is ever written to a cookie.
                $sessionToken = bin2hex(random_bytes(32));
                $this->app->session->set('persistent_token', $sessionToken);

                $cookieOpts = [
                    'expires'  => time() + (86400 * 30),
                    'path'     => '/',
                    'httponly' => true,
                    'secure'   => isset($_SERVER['HTTPS']),
                    'samesite' => 'Lax',
                ];
                setcookie('auth_user', $username, $cookieOpts);
                setcookie('auth_token', $sessionToken, $cookieOpts);

                $redir = $this->safeRedir($_POST['redir'] ?? $_GET['redir'] ?? '/');
                header('Location: ' . $redir);
                exit;
            }

            $error = 'Credenciales incorrectas. Por favor, inténtalo de nuevo.';
        }

        // Render Google OAuth button only when credentials are configured
        $googleConfigured = !empty($this->app->config->getAsString('googleOAuth.clientId'))
            && !empty($this->app->config->getAsString('googleOAuth.clientSecret'));

        $this->viewParams['error']            = $error;
        $this->viewParams['redir']            = $_GET['redir'] ?? '/';
        $this->viewParams['googleConfigured'] = $googleConfigured;
        $this->setView('@app/Views/Login.twig');
    }

    /**
     * Only allow same-origin relative paths to prevent open-redirect vulnerabilities.
     * Accepts '/' and any path starting with '/' that is not protocol-relative ('//…').
     */
    private function safeRedir(string $url): string
    {
        $url = (string) $url;
        // Reject empty, protocol-relative ('//…') and absolute URLs
        if ($url !== '/' && !preg_match('#^/(?!/)#', $url)) {
            return '/';
        }
        return preg_replace('/[\r\n]/', '', $url);
    }
}
