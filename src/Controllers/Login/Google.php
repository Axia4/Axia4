<?php

namespace Axia4\Controllers\Login;

/**
 * Google OAuth controller – initiates the Google OAuth 2.0 flow.
 *
 * GET /login/google → redirect the user to Google's authorization endpoint.
 */
class Google extends \ADIOS\Core\Controller
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

        // Generate and store a CSRF nonce
        $oauthNonce = bin2hex(random_bytes(16));
        $this->app->session->set('oauth_nonce', $oauthNonce);

        $domain      = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'];
        $redirectUri = 'https://' . $domain . '/?route=login/google/callback';
        $redir       = $_GET['redir'] ?? '/';

        $state = base64_encode(json_encode([
            'redir' => $redir,
            'nonce' => $oauthNonce,
        ]));

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'scope'         => 'email openid profile',
            'state'         => $state,
        ]);

        header('Location: https://accounts.google.com/o/oauth2/auth?' . $params);
        exit;
    }
}
