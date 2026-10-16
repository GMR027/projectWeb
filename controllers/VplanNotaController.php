<?php

namespace Controllers;

use Model\Proyecto;
use Model\VplanLamina;
use Model\VplanNota;

/** Notas de una lámina de vPlan (CRUD por JSON; la fecha la pone el servidor al crear) */
class VplanNotaController
{
    private static function responder(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        echo json_encode($datos);
        exit;
    }

    /** Lámina de la petición, solo si el usuario puede gestionar su proyecto */
    private static function laminaAutorizada(): VplanLamina
    {
        header('Content-Type: application/json');
        $l = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? VplanLamina::find(filter_var($_POST['lamina'] ?? null, FILTER_VALIDATE_INT)) : null;
        $proyecto = $l ? Proyecto::find($l->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) self::responder(['success' => false, 'error' => 'No autorizado'], 403);
        return $l;
    }

    private static function notaDe(VplanLamina $l): ?VplanNota
    {
        $n = VplanNota::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        return $n && (int)$n->lamina_id === (int)$l->id ? $n : null;
    }

    /** Crea (sin id) o edita (con id) una nota */
    public static function guardar()
    {
        $l = self::laminaAutorizada();
        $texto = trim((string)($_POST['descripcion'] ?? ''));
        if ($texto === '') self::responder(['success' => false, 'error' => 'La descripción es obligatoria'], 422);
        if (mb_strlen($texto) > VplanNota::MAX_LARGO) self::responder(['success' => false, 'error' => 'Máximo ' . VplanNota::MAX_LARGO . ' caracteres'], 422);

        if (!empty($_POST['id'])) {
            $n = self::notaDe($l);
            if (!$n) self::responder(['success' => false, 'error' => 'Nota no encontrada'], 404);
            $n->descripcion = $texto;   // la fecha original se conserva
            $n->guardar();
        } else {
            if (VplanNota::totalDeLamina((int)$l->id) >= VplanNota::MAX_POR_LAMINA) self::responder(['success' => false, 'error' => 'Máximo ' . VplanNota::MAX_POR_LAMINA . ' notas por lámina'], 422);
            $n = new VplanNota(['lamina_id' => $l->id, 'descripcion' => $texto]);
            $n->id = $n->guardar()['id'];
        }
        self::responder(['success' => true, 'nota' => $n->aArray()]);
    }

    public static function eliminar()
    {
        $l = self::laminaAutorizada();
        $n = self::notaDe($l);
        if (!$n) self::responder(['success' => false, 'error' => 'Nota no encontrada'], 404);
        $n->eliminar();
        self::responder(['success' => true]);
    }
}
