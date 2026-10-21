<?php

namespace Controllers;

use Classes\Carpetas;

/** Renombrar carpetas y mover documentos entre ellas dentro de cada herramienta (Redactor, SheetFlow, Minutas) */
class CarpetaController
{
    private static function herramienta(): string
    {
        $h = (string)($_POST['herramienta'] ?? '');
        if (!Carpetas::tabla($h)) json_respuesta(['success' => false, 'error' => 'Herramienta no válida'], 422);
        return $h;
    }

    public static function renombrar()
    {
        $proyecto = proyecto_de_peticion(true);
        $h = self::herramienta();
        $a = trim((string)($_POST['a'] ?? ''));
        if ($a === '') json_respuesta(['success' => false, 'error' => 'El nombre no puede quedar vacío'], 422);
        json_respuesta(['success' => true, 'afectados' => Carpetas::renombrar($h, (int)$proyecto->id, (string)($_POST['de'] ?? ''), $a)]);
    }

    public static function mover()
    {
        $proyecto = proyecto_de_peticion(true);
        $h = self::herramienta();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) json_respuesta(['success' => false, 'error' => 'Documento no válido'], 422);
        json_respuesta(['success' => Carpetas::mover($h, (int)$proyecto->id, $id, (string)($_POST['a'] ?? ''))]);
    }
}
