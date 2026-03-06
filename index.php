<?php
/**
 * Axia4 - Application Entry Point (ADIOS Framework)
 *
 * This file bootstraps the ADIOS-based Axia4 application.
 */

// Load environment configuration
require_once __DIR__ . '/env.php';

// Load Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load the application class
require_once __DIR__ . '/src/App.php';

// Create the application instance and render the output
echo (new \Axia4\App($config))->render();
