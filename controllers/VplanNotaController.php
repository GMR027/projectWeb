<?php

namespace Controllers;

use Classes\NotasApi;
use Model\Proyecto;
use Model\VplanLamina;
use Model\VplanNota;

/** Notas de una lámina de vPlan (CRUD por JSON; la fecha la pone el servidor al crear) */
class VplanNotaController
{
    /** Lámina de la petición, solo si el usuario puede gestionar su proyecto */
    private static function laminaAutorizada(): VplanLamina
    {
        $l = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? VplanLamina::find(filter_var($_POST['lamina'] ?? null, FILTER_VALIDATE_INT)) : null;
        $proyecto = $l ? Proyecto::find($l->proyecto_id) : null;
        if (!$proyecto || !puede_gestionar_proyecto($proyecto)) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        return $l;
    }

    /** Crea (sin id) o edita (con id) una nota */
    public static function guardar()
    {
        $l = self::laminaAutorizada();
        NotasApi::guardar($l, VplanNota::class, 'lamina_id', fn() => VplanNota::totalDeLamina((int)$l->id), VplanNota::MAX_POR_LAMINA, 'lámina');
    }

    public static function eliminar()
    {
        NotasApi::eliminar(self::laminaAutorizada(), VplanNota::class, 'lamina_id');
    }
}
