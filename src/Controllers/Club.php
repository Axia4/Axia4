<?php

namespace Axia4\Controllers;

/**
 * Club controller – public-facing web interface for the club module.
 *
 * This page is intentionally public (no authentication required) so that
 * club members and guests can browse activities and events.
 */
class Club extends \ADIOS\Core\Controller
{
    public bool $requiresUserAuthentication = false;

    public function prepareView(): void
    {
        $this->viewParams['isAuthenticated'] = $this->app->auth->isUserInSession();
        $this->viewParams['user']            = $this->app->auth->getUser();
        $this->setView('@app/Views/Club.twig');
    }
}
