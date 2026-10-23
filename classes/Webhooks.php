<?php

namespace Classes;

/**
 * Bus de eventos interno de ProjectWeb: cuando algo cambia en un proyecto se avisa a quien lo escuche.
 * Hoy el único suscriptor es el bot de Telegram (`Telegram::aviso()`), que filtra con `Telegram::EVENTOS`.
 *
 * Estaba la herramienta «Webhooks» (Fase 5.1), que además enviaba cada evento por HTTP a URL de terceros con
 * firma HMAC-SHA256 (`X-ProjectWeb-Signature`); se retiró del servicio. Las tablas `webhooks` y `webhook_entregas`
 * se conservan en la BD por si se vuelve a activar (no se borran datos), pero ya no se leen ni se escriben.
 *
 * Ojo con `desdeTabla`: los modelos lo llaman al crear/actualizar/eliminar, así que no debe hacer consultas caras
 * y nunca puede romper el guardado. `ActiveRecord::crear` guarda el `insert_id` antes de avisar.
 */
class Webhooks
{
    use AccesoDb;

    /** Catálogo de eventos del bus (quien se suscriba elige entre estos) */
    public const EVENTOS = [
        'actividad.creada' => 'Se creó una actividad', 'actividad.actualizada' => 'Cambió una actividad (avance, fechas…)', 'actividad.eliminada' => 'Se eliminó una actividad',
        'restriccion.creada' => 'Nueva restricción', 'restriccion.actualizada' => 'Cambió una restricción (p. ej. se resolvió)',
        'bitacora.creada' => 'Nuevo registro en la bitácora', 'reporte.creado' => 'Nuevo reporte diario',
        'submittal.creado' => 'Nuevo paquete de submittals', 'submittal.actualizado' => 'Cambió un submittal (p. ej. su estatus)',
        'punch.creado' => 'Nuevo pendiente del punch list', 'punch.actualizado' => 'Cambió un pendiente', 'punch.estado' => 'Cambió el estado de un pendiente',
        'rfi.creado' => 'Nuevo RFI', 'rfi.actualizado' => 'Cambió un RFI', 'rfi.estado' => 'Cambió el estado de un RFI',
    ];
    /** Modelos que avisan solos desde ActiveRecord: tabla => [acción => evento] */
    public const EVENTO_DE = [
        'actividades' => ['creada' => 'actividad.creada', 'actualizada' => 'actividad.actualizada', 'eliminada' => 'actividad.eliminada'],
        'restricciones' => ['creada' => 'restriccion.creada', 'actualizada' => 'restriccion.actualizada'],
        'bitacora' => ['creada' => 'bitacora.creada'],
        'reportes_diarios' => ['creada' => 'reporte.creado'],
        'submittals' => ['creada' => 'submittal.creado', 'actualizada' => 'submittal.actualizado'],
    ];

    /** Reparte el evento: avisa a los suscriptores (hasta ahora, solo el bot de Telegram) */
    public static function emitir(int $pid, string $evento, array $datos): void
    {
        Telegram::aviso($pid, $evento, $datos);
    }

    /** Lo llama ActiveRecord al crear, actualizar o eliminar en las tablas de EVENTO_DE */
    public static function desdeTabla(string $tabla, string $accion, int $id, array $fila): void
    {
        $evento = self::EVENTO_DE[$tabla][$accion] ?? null;
        if ($evento === null || !Telegram::proyectosConDestino()) return;   // sin destinos de avisos no hay nada que repartir
        $pid = self::proyectoDe($tabla, $fila);
        if ($pid) self::emitir($pid, $evento, ['id' => $id] + self::limpiar($fila));
    }

    /** Proyecto al que pertenece un registro de una de las tablas que avisan */
    private static function proyectoDe(string $tabla, array $fila): int
    {
        switch ($tabla) {
            case 'actividades': return (int)(self::filas('SELECT proyecto_id FROM partidas WHERE id = ' . (int)($fila['partida_id'] ?? 0))[0]['proyecto_id'] ?? 0);
            case 'restricciones': return (int)(self::filas('SELECT p.proyecto_id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = ' . (int)($fila['actividad_id'] ?? 0))[0]['proyecto_id'] ?? 0);
            default: return (int)($fila['proyecto_id'] ?? 0);
        }
    }

    /** Solo valores simples y cortos (sin binarios ni textos enormes) */
    private static function limpiar(array $fila): array
    {
        $o = [];
        foreach ($fila as $k => $v) {
            if (is_scalar($v) || $v === null) $o[$k] = is_string($v) ? mb_substr($v, 0, 500) : $v;
        }
        return $o;
    }
}
