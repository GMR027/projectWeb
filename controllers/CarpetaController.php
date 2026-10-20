<?php

namespace Controllers;

use Classes\Carpetas;

/** Renombrar carpetas y mover documentos entre ellas dentro de cada herramienta (Redactor, SheetFlow, Minutas) */
class CarpetaController
{
    private static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private static function herramienta(): string
    {
        $h = (string)($_POST['herramienta'] ?? '');
        if (!Carpetas::tabla($h)) self::json(['success' => false, 'error' => 'Herramienta no válida'], 422);
        return $h;
    }

    public static function renombrar()
    {
        $proyecto = proyecto_de_peticion(true);
        $h = self::herramienta();
        $a = trim((string)($_POST['a'] ?? ''));
        if ($a === '') self::json(['success' => false, 'error' => 'El nombre no puede quedar vacío'], 422);
        self::json(['success' => true, 'afectados' => Carpetas::renombrar($h, (int)$proyecto->id, (string)($_POST['de'] ?? ''), $a)]);
    }

    public static function mover()
    {
        $proyecto = proyecto_de_peticion(true);
        $h = self::herramienta();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) self::json(['success' => false, 'error' => 'Documento no válido'], 422);
        self::json(['success' => Carpetas::mover($h, (int)$proyecto->id, $id, (string)($_POST['a'] ?? ''))]);
    }
}
