<?php

namespace Axia4\Controllers;

/**
 * PrivacyPolicy controller – public privacy policy page.
 */
class PrivacyPolicy extends \ADIOS\Core\Controller
{
    public bool $requiresUserAuthentication = false;

    public function prepareView(): void
    {
        $this->setView('@app/Views/PrivacyPolicy.twig');
    }
}
