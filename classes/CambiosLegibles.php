<?php

namespace Classes;

/**
 * Traduce los datos de los registros de historial (antes/después, elemento y resumen) a texto que se entiende:
 * nombres en vez de ids, etiquetas en vez de nombres de columna, fechas y sí/no. Ninguna pantalla debe mostrar JSON.
 *
 * Regla de partidas: cada vez que aparece una actividad (por id o como elemento) se muestra su nombre y la partida
 * a la que pertenece («A1 Muro · Partida: 1 Obra gris»).
 */
class CambiosLegibles
{
    use AccesoDb;

    private const ETIQUETAS = [
        'fecha_inicio' => 'Inicio', 'fecha_fin' => 'Fin', 'progreso' => 'Avance', 'status' => 'Estado',
        'descripcion' => 'Descripción', 'fecha_limite' => 'Fecha límite', 'fecha_respuesta' => 'Fecha de respuesta',
        'fecha_compra_prog' => 'Compra programada', 'fecha_entrega_prog' => 'Entrega programada',
        'fecha_instalacion_prog' => 'Instalación programada', 'fecha_compra_real' => 'Compra real',
        'fecha_entrega_real' => 'Entrega real', 'numero_submittal' => 'Número de submittal', 'color_bg' => 'Color',
        'fecha_fija' => 'Fecha fija', 'duracion_dias' => 'Duración (días)', 'retraso' => 'Retraso (días hábiles)',
        'partida_id' => 'Partida', 'actividad_id' => 'Actividad', 'predecesora_id' => 'Actividad predecesora',
        'sucesora_id' => 'Actividad sucesora', 'material_id' => 'Material', 'codigo' => 'Código', 'nombre' => 'Nombre',
        'tipo' => 'Tipo', 'hito' => 'Hito', 'sin_material' => 'Sin material', 'visible' => 'Visible',
        'titulo' => 'Título', 'fecha' => 'Fecha', 'motivo' => 'Motivo', 'asunto' => 'Asunto',
        'antes' => 'Antes', 'despues' => 'Después',
    ];

    /** Columnas que apuntan a una partida o actividad: se sustituye el id por su nombre */
    private const FK_PARTIDA = ['partida_id'];
    private const FK_ACTIVIDAD = ['actividad_id', 'predecesora_id', 'sucesora_id'];
    private const FK_MATERIAL = ['material_id'];

    /** Columnas que se leen como sí/no */
    private const BOOLEANAS = ['hito', 'sin_material', 'fecha_fija', 'visible', 'revocado', 'requiere_retiro', 'numerar', 'rastrear', 'leido', 'comprado', 'oculto'];

    private const ESTADOS = ['no_iniciada' => 'No iniciada', 'en_proceso' => 'En proceso', 'completada' => 'Completada'];

    private static array $memo = [];

    /** Etiqueta con mayúscula inicial para una columna */
    public static function etiqueta(string $columna): string
    {
        $t = self::ETIQUETAS[$columna] ?? str_replace('_', ' ', $columna);
        return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }

    /** Valor de una columna en lenguaje llano. $corto recorta textos largos (para el resumen de la lista) */
    public static function valor(string $columna, $v, int $pid = 0, bool $corto = false): string
    {
        if (in_array($columna, self::FK_PARTIDA, true)) return (int)$v > 0 ? self::partida((int)$v, $pid) : '—';
        if (in_array($columna, self::FK_ACTIVIDAD, true)) return (int)$v > 0 ? self::actividad((int)$v, $pid) : '—';
        if (in_array($columna, self::FK_MATERIAL, true)) return (int)$v > 0 ? self::material((int)$v, $pid) : '—';
        if ($v === null || $v === '') return '—';
        if (in_array($columna, self::BOOLEANAS, true)) return $v ? 'sí' : 'no';
        if ($columna === 'status' && isset(self::ESTADOS[$v])) return self::ESTADOS[$v];
        if ($columna === 'progreso') return $v . ' %';
        if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v) && ($t = strtotime($v))) {
            return date(strlen($v) > 10 ? 'd/m/Y H:i' : 'd/m/Y', $t);
        }
        $s = (string)$v;
        if ($corto && mb_strlen($s) > 60) $s = mb_substr($s, 0, 59) . '…';
        return $s;
    }

    /**
     * Filas para la tabla de antes/después: [campo, antes, después]. Un lado nulo (creación o eliminación) sale como «—».
     * Los datos anidados se aplanan con la ruta de etiquetas («Antes › Inicio»).
     */
    public static function comparar(?array $antes, ?array $despues, int $pid): array
    {
        $a = $antes === null ? [] : self::aplanar($antes);
        $d = $despues === null ? [] : self::aplanar($despues);
        $out = [];
        foreach (array_unique(array_merge(array_keys($a), array_keys($d))) as $ruta) {
            $ref = $a[$ruta] ?? $d[$ruta];
            $out[] = [
                $ref['etiqueta'],
                isset($a[$ruta]) ? self::valor($a[$ruta]['col'], $a[$ruta]['valor'], $pid) : '—',
                isset($d[$ruta]) ? self::valor($d[$ruta]['col'], $d[$ruta]['valor'], $pid) : '—',
            ];
        }
        return $out;
    }

    /**
     * Nombre de un elemento para mostrar. Si es una actividad, añade la partida a la que pertenece.
     * `$nombre` es el nombre guardado al registrar; se usa tal cual si la actividad ya no existe.
     */
    public static function elemento(string $tipo, int $id, string $nombre, int $pid): string
    {
        if ($tipo !== 'actividad' || $id <= 0) return $nombre;
        return self::actividad($id, $pid, $nombre);
    }

    /** «A1 Muro · Partida: 1 Obra gris» (o `$respaldo` si la actividad ya no existe) */
    private static function actividad(int $id, int $pid, string $respaldo = ''): string
    {
        $k = "a:$id:$pid";
        if (!isset(self::$memo[$k])) {
            $f = self::filas("SELECT a.codigo, a.nombre, p.codigo pcodigo, p.nombre pnombre FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $id AND p.proyecto_id = $pid")[0] ?? null;
            self::$memo[$k] = $f ? trim($f['codigo'] . ' ' . $f['nombre']) . ' · Partida: ' . trim($f['pcodigo'] . ' ' . $f['pnombre']) : null;
        }
        return self::$memo[$k] ?? ($respaldo !== '' ? $respaldo : 'Actividad eliminada (#' . $id . ')');
    }

    private static function partida(int $id, int $pid): string
    {
        $k = "p:$id:$pid";
        if (!isset(self::$memo[$k])) {
            $f = self::filas("SELECT codigo, nombre FROM partidas WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
            self::$memo[$k] = $f ? trim($f['codigo'] . ' ' . $f['nombre']) : null;
        }
        return self::$memo[$k] ?? 'Partida eliminada (#' . $id . ')';
    }

    private static function material(int $id, int $pid): string
    {
        $k = "m:$id:$pid";
        if (!isset(self::$memo[$k])) {
            $f = self::filas("SELECT nombre FROM materiales WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
            self::$memo[$k] = $f ? (string)$f['nombre'] : null;
        }
        return self::$memo[$k] ?? 'Material eliminado (#' . $id . ')';
    }

    /**
     * Aplana un dato anidado en [ruta => ['etiqueta', 'col', 'valor']]. El id interno no se muestra.
     * Los elementos de una lista se numeran («Elemento 2»).
     */
    private static function aplanar(array $datos, string $prefijo = '', string $ruta = ''): array
    {
        $out = [];
        foreach ($datos as $k => $v) {
            if ($k === 'id') continue;
            $clave = ($ruta === '' ? '' : $ruta . '.') . $k;
            $etq = is_int($k) ? 'Elemento ' . ($k + 1) : self::etiqueta((string)$k);
            $etiqueta = ($prefijo === '' ? '' : $prefijo . ' › ') . $etq;
            if (is_array($v) && $v !== []) {
                $out += self::aplanar($v, $etiqueta, $clave);
            } else {
                $out[$clave] = ['etiqueta' => $etiqueta, 'col' => is_int($k) ? '' : (string)$k, 'valor' => is_array($v) ? null : $v];
            }
        }
        return $out;
    }
}
