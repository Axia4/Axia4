<?php

namespace Axia4\Controllers\Login;

/**
 * Google OAuth callback controller.
 *
 * GET /login/google/callback → exchange the authorization code for a token,
 * fetch user info, and create/load the local user profile.
 */
class GoogleCallback extends \ADIOS\Core\Controller
{
    public bool $requiresUserAuthentication = false;

    public function prepareView(): void
    {
        $clientId     = $this->app->config->getAsString('googleOAuth.clientId');
        $clientSecret = $this->app->config->getAsString('googleOAuth.clientSecret');

        if (empty($clientId) || empty($clientSecret)) {
            http_response_code(500);
            echo 'Error: La autenticación de Google no está configurada.';
            exit;
        }

        // Validate CSRF nonce
        $stateRaw   = $_GET['state'] ?? '';
        $state      = json_decode(base64_decode($stateRaw), true);
        $stateNonce = $state['nonce'] ?? '';
        $storedNonce = $this->app->session->get('oauth_nonce') ?? '';

        if (!$stateNonce || !hash_equals($storedNonce, $stateNonce)) {
            http_response_code(400);
            echo 'Error: Estado OAuth inválido. Por favor, inténtalo de nuevo.';
            exit;
        }
        $this->app->session->set('oauth_nonce', null);

        $code = $_GET['code'] ?? '';
        if (empty($code)) {
            http_response_code(400);
            echo 'Error: No se recibió el código de autorización de Google.';
            exit;
        }

        $domain      = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'];
        $redirectUri = 'https://' . $domain . '/?route=login/google/callback';

        // Exchange code for access token
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'code'          => $code,
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri'  => $redirectUri,
                    'grant_type'    => 'authorization_code',
                ]),
            ],
        ]);

        $tokenResponse = file_get_contents('https://oauth2.googleapis.com/token', false, $context);
        $tokenData     = json_decode($tokenResponse, true);

        if (empty($tokenData['access_token'])) {
            http_response_code(502);
            echo 'Error: No se pudo obtener el token de acceso de Google.';
            exit;
        }

        // Fetch user info
        $userInfoResponse = file_get_contents(
            'https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token=' . urlencode($tokenData['access_token'])
        );
        $userInfo = json_decode($userInfoResponse, true);

        if (empty($userInfo['email'])) {
            http_response_code(502);
            echo 'Error: No se pudo obtener la información del usuario de Google.';
            exit;
        }

        $email    = $userInfo['email'];
        $name     = $userInfo['name'] ?? explode('@', $email)[0];

        /** @var \Axia4\Auth\Axia4Auth $auth */
        $auth     = $this->app->auth;
        $userdata = $auth->loadUser($email);

        if ($userdata === null) {
            // First-time Google login → create local user profile
            $randomPassword = bin2hex(random_bytes(16));
            $userdata = [
                'display_name'  => $name,
                'email'         => $email,
                'permissions'   => ['public'],
                'password_hash' => password_hash($randomPassword, PASSWORD_DEFAULT),
                'google_auth'   => true,
            ];
            $auth->saveUser($email, $userdata);
        }

        session_regenerate_id(true);
        $auth->signIn(array_merge($userdata, ['username' => $email]));

        $redir = $state['redir'] ?? '/';
        // Reject empty, protocol-relative ('//…') and absolute URLs
        if ($redir !== '/' && !preg_match('#^/(?!/)#', $redir)) {
            $redir = '/';
        }
        $redir = preg_replace('/[\r\n]/', '', $redir);

        header('Location: ' . $redir);
        exit;
    }
}
