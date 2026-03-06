<?php

namespace Axia4;

/**
 * Axia4 Application Loader
 *
 * Main entry point for the ADIOS-based Axia4 platform. Extends the ADIOS
 * core Loader to wire up custom routing, authentication, and configuration.
 */
class App extends \ADIOS\Core\Loader
{
    /**
     * Register the custom Axia4 router.
     */
    public function createRouter(): \ADIOS\Core\Router
    {
        return new \Axia4\Core\Router($this);
    }

    /**
     * Register the custom Axia4 authentication handler.
     */
    public function createAuth(): \ADIOS\Core\Auth
    {
        return new \Axia4\Auth\Axia4Auth($this);
    }
}
