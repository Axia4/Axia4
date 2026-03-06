<?php

namespace Axia4\Controllers;

/**
 * Account controller – shows the authenticated user's profile and QR code.
 */
class Account extends \ADIOS\Core\Controller
{
    public bool $requiresUserAuthentication = true;

    public function prepareView(): void
    {
        $user = $this->app->auth->getUser();

        $this->viewParams['user']        = $user;
        $this->viewParams['displayName'] = $user['display_name'] ?? 'Usuario';
        $this->viewParams['email']       = $user['email'] ?? '';
        $this->viewParams['username']    = $user['username'] ?? '';
        $this->setView('@app/Views/Account.twig');
    }
}
