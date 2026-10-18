<?php

namespace Controllers;

use Model\Proyecto;
use Classes\Consultas;

/** API de solo lectura para el chat «Pregunta a tu proyecto» de la app de escritorio. Solo admin; nunca modifica datos */
class ConsultasIAController
{
    private static function json(array $d, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function catalogo()
    {
        if (!is_admin()) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        self::json(['success' => true, 'csrf' => csrf_token(), 'consultas' => Consultas::catalogo()]);
    }

    /** Ejecuta hasta 4 consultas ({consultas: [{nombre, params}]}) o, con `pregunta`, las que sugiere la ruta por palabras clave */
    public static function ejecutar()
    {
        $p = is_admin() ? Proyecto::find(filter_var($_POST['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$p) self::json(['success' => false, 'error' => 'No autorizado'], 403);
        $lista = json_decode((string)($_POST['consultas'] ?? '[]'), true);
        $lista = is_array($lista) ? $lista : [];
        $ruteadas = false;
        $pregunta = (string)($_POST['pregunta'] ?? '');
        if ($pregunta !== '' && (!$lista || !empty($_POST['completar']))) {
            // Sin plan, o completando el del modelo con las consultas que sugieren las palabras clave (hasta 4 en total)
            $lista = array_slice(array_merge($lista, array_filter(array_map(fn($x) => ['nombre' => $x[0], 'params' => $x[1]], Consultas::rutear($pregunta)),
                fn($r) => !array_filter($lista, fn($c) => is_array($c) && ($c['nombre'] ?? '') === $r['nombre'] && ($c['params'] ?? []) == $r['params']))), 0, 4);
            $ruteadas = true;
        }
        $res = [];
        foreach (array_slice($lista, 0, 4) as $c) {
            if (!is_array($c)) continue;
            try {
                $r = Consultas::ejecutar($p, (string)($c['nombre'] ?? ''), is_array($c['params'] ?? null) ? $c['params'] : []);
                $res[] = $r + ['nombre' => $c['nombre']];
            } catch (\InvalidArgumentException $e) {
                // consulta inventada por el modelo: se ignora
            }
        }
        if (!$res) $res[] = Consultas::ejecutar($p, 'resumen') + ['nombre' => 'resumen'];
        self::json(['success' => true, 'ruteadas' => $ruteadas, 'resultados' => $res, 'texto' => implode("\n", array_map(fn($r) => Consultas::texto($r), $res))]);
    }
}
