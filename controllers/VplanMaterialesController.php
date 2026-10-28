<?php

namespace Controllers;

use Classes\MaterialesLamina;
use Model\Proyecto;
use Model\VplanLamina;
use Model\VplanTabla;

/** API de la tabla de instalación de materiales de una lámina de vPlan */
class VplanMaterialesController
{
    private static function lamina(Proyecto $proyecto): ?VplanLamina
    {
        $l = VplanLamina::find(filter_var($_POST['lamina'] ?? null, FILTER_VALIDATE_INT));
        return $l && (int)$l->proyecto_id === (int)$proyecto->id ? $l : null;
    }

    private static function salida(Proyecto $proyecto, VplanLamina $l): void
    {
        $d = MaterialesLamina::datos($proyecto, $l);
        json_respuesta(['success' => true] + $d);
    }

    public static function crear()
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::lamina($proyecto);
        if (!$l) json_respuesta(['success' => false, 'error' => 'Lámina no encontrada'], 404);
        try {
            MaterialesLamina::crear($proyecto, $l, (int)($_SESSION['id'] ?? 0) ?: null);
        } catch (\Throwable $e) {
            error_log('Materiales vPlan (crear): ' . $e->getMessage());
            json_respuesta(['success' => false, 'error' => 'No se pudo crear la tabla de materiales'], 500);
        }
        self::salida($proyecto, VplanLamina::find((int)$l->id));
    }

    public static function sincronizar()
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::lamina($proyecto);
        if (!$l) json_respuesta(['success' => false, 'error' => 'Lámina no encontrada'], 404);
        try {
            MaterialesLamina::sincronizar($proyecto, $l, !empty($_POST['refrescar']));
        } catch (\Throwable $e) {
            error_log('Materiales vPlan (sincronizar): ' . $e->getMessage());
            json_respuesta(['success' => false, 'error' => 'No se pudo actualizar la tabla de materiales'], 500);
        }
        self::salida($proyecto, VplanLamina::find((int)$l->id));
    }

    public static function datos()
    {
        $proyecto = proyecto_de_peticion(false);
        $l = self::lamina($proyecto);
        if (!$l) json_respuesta(['success' => false, 'error' => 'Lámina no encontrada'], 404);
        self::salida($proyecto, $l);
    }

    /** Imágenes del catálogo de materiales de las actividades asignadas */
    public static function imagenes()
    {
        $proyecto = proyecto_de_peticion(false);
        $l = self::lamina($proyecto);
        if (!$l) json_respuesta(['success' => false, 'error' => 'Lámina no encontrada'], 404);
        $ids = $_POST['actividades'] ?? [];
        if (is_string($ids)) $ids = preg_split('/\D+/', $ids, -1, PREG_SPLIT_NO_EMPTY);
        json_respuesta(['success' => true, 'imagenes' => MaterialesLamina::imagenes($proyecto, (array)$ids)]);
    }

    public static function desvincular()
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::lamina($proyecto);
        if (!$l) json_respuesta(['success' => false, 'error' => 'Lámina no encontrada'], 404);
        $l->materiales_tabla_id = null;
        $l->guardar();
        self::salida($proyecto, VplanLamina::find((int)$l->id));
    }

    /** Vuelve a enlazar una tabla de materiales ya creada (por ejemplo, tras quitar su bloque) */
    public static function vincular()
    {
        $proyecto = proyecto_de_peticion(true);
        $l = self::lamina($proyecto);
        $t = VplanTabla::find(filter_var($_POST['tabla'] ?? null, FILTER_VALIDATE_INT));
        if (!$l || !$t || (int)$t->proyecto_id !== (int)$proyecto->id || !$t->esMateriales()) {
            json_respuesta(['success' => false, 'error' => 'Tabla no válida'], 422);
        }
        $l->materiales_tabla_id = (int)$t->id;
        $l->guardar();
        $l->agregarTabla((int)$t->id);
        self::salida($proyecto, VplanLamina::find((int)$l->id));
    }
}
