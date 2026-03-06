<?php

namespace Axia4\Controllers;

/**
 * EntreAulas controller – gateway to the connected-classroom management module.
 *
 * The full EntreAulas feature set (aulario, comedor, panel diario, etc.) is
 * available via sub-routes handled by this controller. For the initial ADIOS
 * migration the controller renders an informational landing page; individual
 * sub-controllers can be added under Controllers/EntreAulas/ as needed.
 */
class EntreAulas extends \ADIOS\Core\Controller
{
    public bool $requiresUserAuthentication = true;

    public function prepareView(): void
    {
        $user = $this->app->auth->getUser();

        $this->viewParams['user']        = $user;
        $this->viewParams['displayName'] = $user['display_name'] ?? 'Usuario';
        $this->viewParams['permissions'] = $user['permissions'] ?? [];
        $this->setView('@app/Views/EntreAulas.twig');
    }
}
