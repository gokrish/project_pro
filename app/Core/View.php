<?php

namespace App\Core;

class View
{
    public static function render(string $viewPath, array $data = []): void
    {
        // Extract data to variables
        extract($data);

        // Convert dot notation to path (e.g., 'auth.login' -> 'auth/login')
        $viewFile = ROOT_PATH . '/resources/views/' . str_replace('.', '/', $viewPath) . '.php';

        if (!file_exists($viewFile)) {
            throw new \Exception("View file not found: $viewFile");
        }

        // Start buffering
        ob_start();
        include $viewFile;
        // Output buffer is flushed automatically at end of script, or we could return it
        echo ob_get_clean();
    }

    // Render with layout
    public static function renderLayout(string $viewPath, array $data = [], string $layout = 'main'): void
    {
        extract($data);

        // Capture view content
        ob_start();
        $viewFile = ROOT_PATH . '/resources/views/' . str_replace('.', '/', $viewPath) . '.php';
        if (!file_exists($viewFile))
            throw new \Exception("View file not found: $viewFile");
        include $viewFile;
        $content = ob_get_clean();

        // Render layout with content
        $layoutFile = ROOT_PATH . '/resources/views/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile))
            throw new \Exception("Layout file not found: $layoutFile");

        include $layoutFile;
    }
}
