<?php

namespace App\Core;

use Dotenv\Dotenv;
use App\Core\Router;
use App\Core\Database;

class App
{
    public function __construct()
    {
        $this->loadEnvironment();
        $this->setupErrorHandling();
    }

    public function run()
    {
        try {
            // Load Routes
            require_once ROOT_PATH . '/routes/web.php';

            $router = new Router();
            $router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    private function loadEnvironment()
    {
        if (file_exists(ROOT_PATH . '/.env')) {
            $dotenv = Dotenv::createImmutable(ROOT_PATH);
            $dotenv->load();
        }
    }

    private function setupErrorHandling()
    {
        if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
            error_reporting(E_ALL);
        }
    }

    private function handleException(\Exception $e)
    {
        // Simple error page for now
        if ($_ENV['APP_DEBUG'] === 'true') {
            echo "<h1>Application Error</h1>";
            echo "<pre>" . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
        } else {
            http_response_code(500);
            // Ideally render a nice error view
            echo "<h1>500 Internal Server Error</h1>";
        }
    }
}
