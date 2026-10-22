<?php

namespace Classes;

/**
 * Niveles de riesgo de lo que proponen las herramientas de IA. Cada acción es «simple» (se puede aceptar de golpe, sin confirmación)
 * o «permiso» (siempre se acepta una por una y con confirmación). El administrador cambia el nivel por proyecto (tabla `ia_ajustes`);
 * sin configuración rigen los valores por defecto: lo que mueve fechas, relaciones o avance pide permiso.
 */
class RiesgoIA
{
    use AccesoDb;

    public const NIVELES = ['simple' => 'Simple', 'permiso' => 'Pide permiso'];

    /** acción => [descripción, nivel por defecto] */
    public const ACCIONES = [
        'nota' => ['Sin cambio de datos', 'simple'],
        'ajustar_fechas' => ['Cambia fechas', 'permiso'],
        'ajustar_duracion' => ['Cambia duración', 'permiso'],
        'aceptar_partida' => ['Cambia el programa', 'permiso'],
        'agregar_dia_no_habil' => ['Marca día no hábil', 'permiso'],
        'ajustar_progreso' => ['Cambia avance', 'permiso'],
        'agregar_dependencia' => ['Agrega una relación', 'permiso'],
        'quitar_dependencia' => ['Quita relación', 'permiso'],
        'resolver_restriccion' => ['Resuelve restricción', 'permiso'],
        'marcar_comprado' => ['Marca material comprado', 'permiso'],
        'sincronizar_instalacion' => ['Recalcula instalación del MSR', 'permiso'],
        'sincronizar_status' => ['Cambia estatus', 'simple'],
        'recalcular_partida' => ['Recalcula partida', 'simple'],
        'renombrar_actividad' => ['Renombra la actividad', 'simple'],
        'editar_descripcion' => ['Edita la descripción', 'simple'],
        'renombrar_material' => ['Renombra el material', 'simple'],
        'sincronizar_msr_material' => ['Actualiza fechas del MSR', 'simple'],
        'sincronizar_msr_submittal' => ['Actualiza estatus del MSR', 'simple'],
        'vincular_normativa' => ['Vincula normativa', 'simple'],
    ];

    /** Acciones que por su naturaleza nunca se aceptan por lote (se revisan en su propia pantalla) */
    public const SIN_LOTE = ['aceptar_partida'];

    public static function etiqueta(string $accion): string
    {
        return self::ACCIONES[$accion][0] ?? $accion;
    }

    /** Niveles elegidos por el administrador (solo los que difieren del defecto) */
    public static function personalizados(int $pid): array
    {
        $j = self::filas("SELECT riesgo_json FROM ia_ajustes WHERE proyecto_id = $pid")[0]['riesgo_json'] ?? null;
        $m = $j ? json_decode($j, true) : [];
        return is_array($m) ? array_filter($m, fn($n, $a) => isset(self::ACCIONES[$a], self::NIVELES[$n]), ARRAY_FILTER_USE_BOTH) : [];
    }

    /** Nivel efectivo de cada acción */
    public static function niveles(int $pid): array
    {
        $p = self::personalizados($pid);
        $out = [];
        foreach (self::ACCIONES as $a => [, $def]) $out[$a] = $p[$a] ?? $def;
        return $out;
    }

    public static function nivel(int $pid, string $accion): string
    {
        static $cache = [];
        $cache[$pid] ??= self::niveles($pid);
        return $cache[$pid][$accion] ?? 'permiso';   // lo desconocido siempre pide permiso
    }

    /** Guarda los niveles recibidos ({accion: nivel}); lo que coincide con el defecto no se guarda */
    public static function guardar(int $pid, array $niveles): bool
    {
        $m = [];
        foreach (self::ACCIONES as $a => [, $def]) {
            $n = $niveles[$a] ?? $def;
            if (isset(self::NIVELES[$n]) && $n !== $def) $m[$a] = $n;
        }
        try {
            $j = $m ? "'" . self::esc(json_encode($m)) . "'" : 'NULL';
            self::db()->query("INSERT INTO ia_ajustes (proyecto_id, riesgo_json) VALUES ($pid, $j) ON DUPLICATE KEY UPDATE riesgo_json = $j");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
