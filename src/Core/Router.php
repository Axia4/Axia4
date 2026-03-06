<?php

namespace Axia4\Core;

/**
 * Axia4 Router
 *
 * Defines all application routes. Each route maps a URL pattern (regex) to a
 * controller class. Routes are checked in declaration order; the first match wins.
 */
class Router extends \ADIOS\Core\Router
{
    public function __construct(\ADIOS\Core\Loader $app)
    {
        parent::__construct($app);

        // Public routes (no authentication required)
        $this->httpGet([
            '/^$/'                          => \Axia4\Controllers\Home::class,
            '/^login\/?$/'                  => \Axia4\Controllers\Login::class,
            '/^logout\/?$/'                 => \Axia4\Controllers\Logout::class,
            '/^login\/google\/?$/'          => \Axia4\Controllers\Login\Google::class,
            '/^login\/google\/callback\/?$/' => \Axia4\Controllers\Login\GoogleCallback::class,
            '/^club\/?.*$/'                 => \Axia4\Controllers\Club::class,
            '/^politica-privacidad\/?$/'    => \Axia4\Controllers\PrivacyPolicy::class,
        ]);

        // Authenticated routes
        $this->httpGet([
            '/^account\/?$/'                => \Axia4\Controllers\Account::class,
            '/^account\/register\/?$/'      => \Axia4\Controllers\Account\Register::class,
            '/^entreaulas\/?.*$/'           => \Axia4\Controllers\EntreAulas::class,
            '/^sysadmin\/?.*$/'             => \Axia4\Controllers\SysAdmin::class,
        ]);

        // POST routes
        $this->httpPost([
            '/^login\/?$/'                  => \Axia4\Controllers\Login::class,
            '/^account\/register\/?$/'      => \Axia4\Controllers\Account\Register::class,
        ]);
    }
}
