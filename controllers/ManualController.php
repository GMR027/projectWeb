<?php

namespace Controllers;

use MVC\Router;

/** Manual de usuario: una página con todas las herramientas explicadas, filtrada según el rol y las herramientas permitidas */
class ManualController
{
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $rol = $_SESSION['rol'] ?? 'visitante';
        $base = require __DIR__ . '/../views/manual/_secciones.php';
        $extra = require __DIR__ . '/../views/manual/_admin.php';
        $figuras = require __DIR__ . '/../views/manual/_ilustraciones.php';

        // Las secciones nuevas se insertan tras la sección que indica su clave «despues» (en el orden en que se declaran)
        $todas = [];
        foreach ($base as $sec) {
            $todas[] = $sec;
            foreach (self::tras($extra, $sec['id']) as $n) $todas[] = $n;
        }
        $secciones = array_values(array_filter(
            $todas,
            fn($s) => in_array($rol, $s['roles'], true) && herramienta_permitida($s['herr'] ?? null)
        ));
        foreach ($secciones as &$s) $s['cuerpo'] = self::figuras($s['cuerpo'], $figuras);
        unset($s);
        $router->render('manual/index', ['titulo' => 'Manual de usuario', 'secciones' => $secciones, 'rol' => $rol]);
    }

    /** Secciones extra que van justo después de $id; las encadenadas (despues = una extra) también */
    private static function tras(array $extra, string $id): array
    {
        $salida = [];
        foreach ($extra as $e) {
            if (($e['despues'] ?? null) !== $id) continue;
            $salida[] = $e;
            foreach (self::tras($extra, $e['id']) as $n) $salida[] = $n;
        }
        return $salida;
    }

    /** Sustituye [[fig:nombre]] por la ilustración con su leyenda (el SVG es fijo, sin datos de usuario) */
    private static function figuras(string $html, array $figuras): string
    {
        return preg_replace_callback('/\[\[fig:([a-z0-9-]+)\]\]/', function ($m) use ($figuras) {
            if (!isset($figuras[$m[1]])) return '';
            [$leyenda, $svg] = $figuras[$m[1]];
            return '<figure class="manual-fig">' . $svg . '<figcaption>' . htmlspecialchars($leyenda, ENT_QUOTES, 'UTF-8') . '</figcaption></figure>';
        }, $html);
    }
}
