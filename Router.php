<?php

namespace MVC;

class Router
{
    public array $getRoutes = [];
    public array $postRoutes = [];

    public function get($url, $fn)
    {
        $this->getRoutes[$url] = $fn;
    }

    public function post($url, $fn)
    {
        $this->postRoutes[$url] = $fn;
    }

    public function comprobarRutas()
    {
        session_start();
        $currentUrl = strtok($_SERVER['REQUEST_URI'], '?') ?: '/';
        // Normalizar: si el proyecto corre en subdirectorio, se limpia aquí
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            $fn = $this->getRoutes[$currentUrl] ?? null;
        } else {
            $fn = $this->postRoutes[$currentUrl] ?? null;
        }

        if ($fn) {
            call_user_func($fn, $this);
        } else {
            http_response_code(404);
            $this->render('templates/404', ['titulo' => 'Página no encontrada']);
        }
    }

    public function render($view, $datos = [])
    {
        foreach ($datos as $key => $value) {
            $$key = $value;
        }

        ob_start();
        include_once __DIR__ . "/views/$view.php";
        $contenido = ob_get_clean();

        $layout = __DIR__ . '/views/layout.php';
        if (isset($landingLayout) && $landingLayout) {
            $layout = __DIR__ . '/views/layout-landing.php';
        } elseif (isset($authLayout) && $authLayout) {
            $layout = __DIR__ . '/views/layout-auth.php';
        }
        include_once $layout;
    }
}
