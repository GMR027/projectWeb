<?php

namespace Model;

use Classes\Sheetflow;

/**
 * Historial de versiones de una tabla de SheetFlow: una copia del JSON en cada
 * guardado con cambios (como mucho una por minuto; «Guardar» siempre deja una).
 */
class SheetflowVersion extends ActiveRecord
{
    protected static $tabla = 'sheetflow_versiones';
    protected static $columnasDB = ['id', 'hoja_id', 'usuario_id', 'nombre', 'datos', 'creado'];

    public $id, $hoja_id, $usuario_id, $nombre, $datos, $creado;

    /** Copias que se conservan por tabla */
    public const MAXIMO = 60;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->hoja_id = $args['hoja_id'] ?? null;
        $this->usuario_id = $args['usuario_id'] ?? null;
        $this->nombre = (string)($args['nombre'] ?? '');
        $this->datos = $args['datos'] ?? null;
    }

    /** Versiones de una tabla, de la más nueva a la más vieja (sin el JSON) */
    public static function deHoja(int $hojaId, int $limite = 30): array
    {
        $limite = max(1, min(self::MAXIMO, $limite));
        $out = [];
        try {
            $r = self::$db->query(
                "SELECT v.id, v.hoja_id, v.usuario_id, v.nombre, v.creado, LENGTH(v.datos) AS bytes, u.nombre AS usuario
                 FROM sheetflow_versiones v LEFT JOIN usuarios u ON u.id = v.usuario_id
                 WHERE v.hoja_id = $hojaId ORDER BY v.id DESC LIMIT $limite"
            );
            while ($r && ($f = $r->fetch_assoc())) $out[] = $f;
        } catch (\Throwable $e) {   // migración pendiente
        }
        return $out;
    }

    /** Una versión con su JSON (solo si pertenece a la tabla indicada) */
    public static function deHojaId(int $id, int $hojaId): ?SheetflowVersion
    {
        try {
            $v = self::find($id);
        } catch (\Throwable $e) {
            return null;
        }
        return $v && (int)$v->hoja_id === $hojaId ? $v : null;
    }

    /**
     * Guarda una copia si el contenido cambió respecto de la última.
     * Con $forzar se guarda aunque la última sea muy reciente (lo usa «Guardar»).
     */
    public static function guardarCopia(SheetflowHoja $h, ?int $usuarioId, bool $forzar = false): void
    {
        $datos = (string)$h->datos;
        if ($datos === '') return;
        try {
            $hojaId = (int)$h->id;
            $r = self::$db->query("SELECT datos, UNIX_TIMESTAMP(creado) AS ts FROM sheetflow_versiones WHERE hoja_id = $hojaId ORDER BY id DESC LIMIT 1");
            if ($r && ($f = $r->fetch_assoc())) {
                if (md5((string)$f['datos']) === md5($datos)) return;                        // sin cambios
                if (!$forzar && time() - (int)$f['ts'] < 60) return;                          // autoguardado: como mucho uno por minuto
            }
            $v = new self([
                'hoja_id' => $hojaId, 'usuario_id' => $usuarioId, 'nombre' => (string)$h->nombre,
                'datos' => $datos,
            ]);
            $v->guardar();
            // Conserva solo las últimas MAXIMO copias
            $ids = [];
            $r = self::$db->query("SELECT id FROM sheetflow_versiones WHERE hoja_id = $hojaId ORDER BY id DESC LIMIT " . self::MAXIMO);
            while ($r && ($f = $r->fetch_assoc())) $ids[] = (int)$f['id'];
            if ($ids) self::$db->query("DELETE FROM sheetflow_versiones WHERE hoja_id = $hojaId AND id NOT IN (" . implode(',', $ids) . ")");
        } catch (\Throwable $e) {   // sin tabla: la tabla sigue funcionando sin historial
            error_log('SheetFlow versiones: ' . $e->getMessage());
        }
    }

    /** Contenido saneado de la versión, listo para volver a la hoja */
    public function contenido(): array
    {
        return Sheetflow::limpiar(json_decode((string)$this->datos, true));
    }
}
