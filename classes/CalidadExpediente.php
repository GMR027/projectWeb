<?php

namespace Classes;

use Model\Proyecto;

/**
 * Expediente de calidad por partida: ITP, puntos, ensayos y NC.
 * Se puede consultar vivo o cerrar en un PDF firmado que queda congelado en storage/calidad.
 */
class CalidadExpediente
{
    use AccesoDb;

    public static function datos(int $pid, int $partidaId): ?array
    {
        $partida = self::filas("SELECT * FROM partidas WHERE id = $partidaId AND proyecto_id = $pid")[0] ?? null;
        if (!$partida) return null;
        $acts = self::filas("SELECT id, codigo, nombre FROM actividades WHERE partida_id = $partidaId ORDER BY orden, codigo");
        $ids = array_map('intval', array_column($acts, 'id'));
        $in = $ids ? implode(',', $ids) : '0';
        $planes = self::filas("SELECT c.*, a.codigo act_codigo, a.nombre act_nombre FROM calidad_planes c
            LEFT JOIN actividades a ON a.id = c.actividad_id
            WHERE c.proyecto_id = $pid AND c.actividad_id IN ($in) ORDER BY a.orden, c.fecha, c.id");
        foreach ($planes as &$p) {
            $p['puntos'] = self::filas("SELECT * FROM calidad_puntos WHERE plan_id = " . (int)$p['id'] . ' ORDER BY orden, id');
        }
        unset($p);
        $ensayos = self::filas("SELECT e.*, a.codigo act_codigo, a.nombre act_nombre FROM calidad_ensayos e
            LEFT JOIN actividades a ON a.id = e.actividad_id
            WHERE e.proyecto_id = $pid AND e.actividad_id IN ($in) ORDER BY e.fecha_resultado DESC, e.id DESC");
        $ncs = self::filas("SELECT n.*, a.codigo act_codigo, a.nombre act_nombre FROM calidad_nc n
            LEFT JOIN actividades a ON a.id = n.actividad_id
            WHERE n.proyecto_id = $pid AND n.actividad_id IN ($in) ORDER BY n.id DESC");
        foreach ($ncs as &$n) $n['acciones'] = CalidadCompleta::acciones((int)$n['id']);
        unset($n);
        $puntos = 0; $cumplen = 0; $ncAbiertas = 0; $rechazados = 0; $costoNc = 0.0;
        foreach ($planes as $pl) foreach ($pl['puntos'] as $pt) {
            $puntos++;
            if ($pt['resultado'] === 'cumple') $cumplen++;
        }
        foreach ($ensayos as $e) if ($e['veredicto'] === 'rechazado') $rechazados++;
        foreach ($ncs as $n) {
            if ($n['estado'] !== 'cerrada') { $ncAbiertas++; $costoNc += (float)$n['costo']; }
        }
        return ['partida' => $partida, 'actividades' => $acts, 'planes' => $planes, 'ensayos' => $ensayos, 'ncs' => $ncs,
            'resumen' => ['puntos' => $puntos, 'cumplen' => $cumplen, 'planes' => count($planes), 'ensayos' => count($ensayos),
                'rechazados' => $rechazados, 'nc' => count($ncs), 'nc_abiertas' => $ncAbiertas, 'costo_nc' => round($costoNc, 2)]];
    }

    public static function estado(int $pid, int $partidaId): array
    {
        return self::filas("SELECT * FROM calidad_expedientes WHERE proyecto_id = $pid AND partida_id = $partidaId")[0]
            ?? ['id' => 0, 'estado' => 'borrador', 'pdf_ruta' => '', 'cerrado_en' => null, 'cerrado_por' => '', 'huella' => ''];
    }

    public static function pdf(Proyecto $proyecto, int $partidaId): ?string
    {
        $datos = self::datos((int)$proyecto->id, $partidaId);
        if (!$datos) return null;
        ob_start();
        include __DIR__ . '/../views/calidad/expediente-pdf.php';
        $html = ob_get_clean();
        return Pdf::generar($html, 'letter', 'portrait', ['marca' => $proyecto]);
    }

    /** Cierra el expediente: genera el PDF y lo deja congelado en storage/calidad/<proyecto>/ */
    public static function cerrar(Proyecto $proyecto, int $partidaId): array
    {
        $datos = self::datos((int)$proyecto->id, $partidaId);
        if (!$datos) return [false, 'La partida no existe'];
        if ((int)$datos['resumen']['nc_abiertas'] > 0) return [false, 'No se puede cerrar el expediente: hay ' . (int)$datos['resumen']['nc_abiertas'] . ' no conformidad(es) abierta(s) en la partida.'];
        if ((int)$datos['resumen']['rechazados'] > 0) return [false, 'No se puede cerrar el expediente: hay ' . (int)$datos['resumen']['rechazados'] . ' ensayo(s) rechazado(s) en la partida.'];
        $bytes = self::pdf($proyecto, $partidaId);
        if ($bytes === null) return [false, 'No se pudo generar el expediente'];
        $dir = __DIR__ . '/../storage/calidad/' . (int)$proyecto->id;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return [false, 'No se pudo crear la carpeta del expediente'];
        $archivo = 'expediente-p' . $partidaId . '-' . date('Ymd-His') . '.pdf';
        if (@file_put_contents($dir . '/' . $archivo, $bytes) === false) return [false, 'No se pudo guardar el PDF'];
        $ruta = 'storage/calidad/' . (int)$proyecto->id . '/' . $archivo;
        $huella = md5($bytes);
        $u = self::esc(mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150));
        self::db()->query("INSERT INTO calidad_expedientes (proyecto_id, partida_id, estado, pdf_ruta, huella, cerrado_en, cerrado_por)
            VALUES (" . (int)$proyecto->id . ", $partidaId, 'cerrado', '" . self::esc($ruta) . "', '$huella', NOW(), '$u')
            ON DUPLICATE KEY UPDATE estado = 'cerrado', pdf_ruta = VALUES(pdf_ruta), huella = VALUES(huella), cerrado_en = NOW(), cerrado_por = VALUES(cerrado_por)");
        return [true, 'Expediente cerrado y firmado'];
    }

    public static function reabrir(int $pid, int $partidaId): bool
    {
        $e = self::estado($pid, $partidaId);
        if (!empty($e['pdf_ruta'])) @unlink(__DIR__ . '/../' . $e['pdf_ruta']);
        self::db()->query("UPDATE calidad_expedientes SET estado = 'borrador', pdf_ruta = '', huella = '', cerrado_en = NULL, cerrado_por = '' WHERE proyecto_id = $pid AND partida_id = $partidaId");
        return true;
    }

    public static function rutaAbsoluta(string $ruta): string
    {
        return __DIR__ . '/../' . ltrim($ruta, '/');
    }
}
