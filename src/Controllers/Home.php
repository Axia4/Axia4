<?php

namespace Axia4\Controllers;

/**
 * Home controller – renders the Axia4 landing page with the app grid.
 */
class Home extends \ADIOS\Core\Controller
{
    public bool $requiresUserAuthentication = false;

    public function prepareView(): void
    {
        $this->viewParams['isAuthenticated'] = $this->app->auth->isUserInSession();
        $this->viewParams['user'] = $this->app->auth->getUser();
        $this->setView('@app/Views/Home.twig');
    }
}
