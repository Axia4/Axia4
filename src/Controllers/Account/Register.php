<?php

namespace Axia4\Controllers\Account;

/**
 * Registration controller – allows new users to create a local account.
 *
 * GET  /account/register → render registration form
 * POST /account/register → create the account
 */
class Register extends \ADIOS\Core\Controller
{
    public bool $requiresUserAuthentication = false;

    public function prepareView(): void
    {
        /** @var \Axia4\Auth\Axia4Auth $auth */
        $auth  = $this->app->auth;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username    = trim(strtolower((string) ($_POST['user'] ?? '')));
            $displayName = trim((string) ($_POST['display_name'] ?? ''));
            $email       = trim(strtolower((string) ($_POST['email'] ?? '')));
            $password    = (string) ($_POST['password'] ?? '');
            $password2   = (string) ($_POST['password2'] ?? '');

            if ($username === '' || $password === '' || $email === '') {
                $error = 'Por favor, rellena todos los campos obligatorios.';
            } elseif ($password !== $password2) {
                $error = 'Las contraseñas no coinciden.';
            } elseif (strlen($password) < 8) {
                $error = 'La contraseña debe tener al menos 8 caracteres.';
            } elseif ($auth->loadUser($username) !== null) {
                $error = 'El nombre de usuario ya está en uso.';
            } else {
                $userdata = [
                    'display_name'  => $displayName ?: $username,
                    'email'         => $email,
                    'permissions'   => ['public'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ];

                if ($auth->saveUser($username, $userdata)) {
                    session_regenerate_id(true);
                    $auth->signIn(array_merge($userdata, ['username' => $username]));
                    header('Location: /?route=account');
                    exit;
                }

                $error = 'No se pudo crear la cuenta. Por favor, inténtalo de nuevo.';
            }
        }

        $this->viewParams['error'] = $error;
        $this->setView('@app/Views/Account/Register.twig');
    }
}
