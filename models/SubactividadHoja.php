<?php

namespace Model;

use Classes\Sheetflow;
use Classes\SheetflowFormulas;

/**
 * Vínculo entre una actividad y una tabla de SheetFlow que muestra sus subactividades.
 *
 * La hoja guarda en `datos.meta.subactividad.ids` el id de la subactividad de cada renglón
 * y en `datos.cols[].rol` el papel de las columnas base (nombre, inicio, fin, status).
 * Así el vínculo sobrevive a renombrar/insertar columnas y a reordenar renglones.
 * Las columnas adicionales se quedan solo en SheetFlow (y en vPlan): nunca se escriben
 * en `subactividades`.
 */
class SubactividadHoja extends ActiveRecord
{
    protected static $tabla = 'subactividad_hojas';
    protected static $columnasDB = ['id', 'proyecto_id', 'actividad_id', 'hoja_id'];

    public const DEPARTAMENTO = 'Programa de obra';

    public $id;
    public $proyecto_id;
    public $actividad_id;
    public $hoja_id;
    public $creado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->actividad_id = $args['actividad_id'] ?? null;
        $this->hoja_id = $args['hoja_id'] ?? null;
        $this->creado = $args['creado'] ?? null;
    }

    /** Vínculo de una actividad (null si no tiene o si la migración aún no está aplicada) */
    public static function deActividad(int $actividadId): ?self
    {
        try {
            $fila = self::where('actividad_id', $actividadId);
            return $fila ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Vínculo de una hoja (null si la hoja es normal o si la migración aún no está aplicada) */
    public static function deHoja(int $hojaId): ?self
    {
        try {
            $fila = self::where('hoja_id', $hojaId);
            return $fila ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Hoja vinculada a una actividad, si existe y sigue viva */
    public static function hojaDeActividad(int $actividadId): ?SheetflowHoja
    {
        $v = self::deActividad($actividadId);
        if (!$v) return null;
        $h = SheetflowHoja::find((int)$v->hoja_id);
        if (!$h) {
            try { $v->eliminar(); } catch (\Throwable $e) {}
            return null;
        }
        return $h;
    }

    /**
     * Crea la tabla de SheetFlow con una fila por subactividad (una vacía si no hay
     * ninguna). Si ya hay vínculo, devuelve el existente.
     * Devuelve [SheetflowHoja, SubactividadHoja].
     */
    public static function crearParaActividad(Actividad $actividad, Proyecto $proyecto, ?int $usuarioId = null): array
    {
        if ($existente = self::deActividad((int)$actividad->id)) {
            $h = SheetflowHoja::find((int)$existente->hoja_id);
            if ($h) return [$h, $existente];
            $existente->eliminar();   // vínculo huérfano
        }

        $cols = [
            ['n' => 'Nombre', 't' => 'texto', 'a' => 'izquierda', 'w' => 260, 'rol' => 'nombre', 'fija' => 1],
            ['n' => 'Inicio', 't' => 'fecha', 'a' => 'centro', 'w' => 100, 'rol' => 'inicio', 'fija' => 1],
            ['n' => 'Fin', 't' => 'fecha', 'a' => 'centro', 'w' => 100, 'rol' => 'fin', 'fija' => 1],
            ['n' => 'Avance (%)', 't' => 'porcentaje', 'a' => 'derecha', 'w' => 95, 'rol' => 'status', 'fija' => 1],
        ];
        $rows = [];
        $ids = [];
        foreach (Subactividad::deActividad((int)$actividad->id) as $s) {
            $rows[] = [(string)$s->nombre, (string)$s->fecha_inicio, (string)$s->fecha_fin, (string)(int)$s->status];
            $ids[] = (int)$s->id;
        }
        if (!$rows) {
            $rows[] = ['', '', '', ''];
            $ids[] = null;
        }
        $datos = Sheetflow::normalizar([
            'cols' => $cols,
            'rows' => $rows,
            'meta' => ['subactividad' => ['actividad_id' => (int)$actividad->id, 'ids' => $ids]],
        ]);

        $etiqueta = trim((string)$actividad->codigo . ' ' . (string)$actividad->nombre);
        if ($etiqueta === '') $etiqueta = 'Actividad #' . (int)$actividad->id;
        $nombre = mb_substr('Subactividades · ' . $etiqueta, 0, 150);
        $descripcion = mb_substr('Tabla vinculada a las subactividades de la actividad ' . $etiqueta
            . '. Los cambios de nombre, fechas y avance se sincronizan en los dos sentidos.', 0, 300);
        $departamento = \Classes\Catalogo::agregarDepartamento((int)$proyecto->id, self::DEPARTAMENTO) ?: self::DEPARTAMENTO;

        $db = SheetflowHoja::conexion();
        $db->begin_transaction();
        try {
            $h = new SheetflowHoja([
                'proyecto_id' => (int)$proyecto->id,
                'nombre' => $nombre,
                'fecha' => date('Y-m-d'),
                'departamento' => $departamento,
                'descripcion' => $descripcion,
                'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
                'creado_por' => $usuarioId,
            ]);
            $res = $h->guardar();
            if (empty($res['resultado'])) throw new \RuntimeException('No se pudo crear la tabla en SheetFlow.');
            $h->id = (int)$res['id'];

            $v = new self([
                'proyecto_id' => (int)$proyecto->id,
                'actividad_id' => (int)$actividad->id,
                'hoja_id' => (int)$h->id,
            ]);
            $res = $v->guardar();
            if (empty($res['resultado'])) throw new \RuntimeException('No se pudo crear el vínculo con la tabla.');
            $v->id = (int)$res['id'];
            $db->commit();
            return [$h, $v];
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Quita el vínculo. La tabla se conserva como una tabla normal de SheetFlow
     * (se le quitan los identificadores y los roles de las columnas base).
     */
    public static function desvincular(Actividad $actividad): void
    {
        $v = self::deActividad((int)$actividad->id);
        if (!$v) return;
        $h = SheetflowHoja::find((int)$v->hoja_id);
        $db = SheetflowHoja::conexion();
        $db->begin_transaction();
        try {
            if ($h) {
                $datos = Sheetflow::sinVinculo($h->contenido());
                $h->datos = json_encode(Sheetflow::normalizar($datos), JSON_UNESCAPED_UNICODE);
                if (!$h->guardar()) throw new \RuntimeException('No se pudo actualizar la tabla antes de desvincularla.');
            }
            $v->eliminar();
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Aplica a las subactividades lo que llega de SheetFlow y devuelve los datos con los
     * identificadores definitivos (los renglones nuevos reciben su id).
     * Lanza RuntimeException con un mensaje para el usuario si algo no cuadra.
     */
    public static function aplicar(SheetflowHoja $hoja, array $datos): array
    {
        $v = self::deHoja((int)$hoja->id);
        if (!$v) return $datos;
        if (!isset($datos['meta']['subactividad']['ids']) || !is_array($datos['meta']['subactividad']['ids'])) {
            throw new \RuntimeException('La tabla no trae el vínculo con las subactividades. Recarga la página y vuelve a intentarlo.');
        }
        $actividad = Actividad::find((int)$v->actividad_id);
        if (!$actividad) throw new \RuntimeException('La actividad vinculada ya no existe.');

        $roles = self::roles($datos);
        foreach (['nombre', 'inicio', 'fin', 'status'] as $rol) {
            if (!isset($roles[$rol])) {
                throw new \RuntimeException('La tabla vinculada necesita las columnas Nombre, Inicio, Fin y Avance. No elimines esas columnas.');
            }
        }

        $ids = self::ids($datos, count($datos['rows']));
        $existentes = [];
        foreach (Subactividad::deActividad((int)$actividad->id) as $s) $existentes[(int)$s->id] = $s;
        $vistos = [];
        $nuevos = [];

        foreach ($datos['rows'] as $r => $fila) {
            $id = (int)($ids[$r] ?? 0);
            $nombre = trim(self::valor($datos, $r, $roles['nombre']));
            if ($nombre === '') {
                if ($id && isset($existentes[$id])) {
                    throw new \RuntimeException('El renglón ' . ($r + 2) . ' no puede quedar sin nombre. Para quitar la subactividad, elimina el renglón.');
                }
                $nuevos[$r] = null;
                continue;
            }
            if (mb_strlen($nombre) > 200) {
                throw new \RuntimeException('El renglón ' . ($r + 2) . ': el nombre no puede superar los 200 caracteres.');
            }
            foreach (['nombre', 'inicio', 'fin', 'status'] as $rol) {
                if (SheetflowFormulas::esFormula((string)($datos['rows'][$r][$roles[$rol]] ?? ''))) {
                    throw new \RuntimeException('El renglón ' . ($r + 2) . ': las columnas Nombre, Inicio, Fin y Avance no pueden ser fórmulas.');
                }
            }

            $inicio = self::fecha(self::valor($datos, $r, $roles['inicio']));
            $fin = self::fecha(self::valor($datos, $r, $roles['fin']));
            if ($inicio === null) throw new \RuntimeException('El renglón ' . ($r + 2) . ': la fecha de inicio no es válida.');
            if ($fin === null) throw new \RuntimeException('El renglón ' . ($r + 2) . ': la fecha de fin no es válida.');
            if ($inicio === '') $inicio = (string)$actividad->fecha_inicio;
            if ($fin === '') $fin = (string)($actividad->fecha_fin ?: $inicio);
            if ($inicio === '' || $fin === '') throw new \RuntimeException('El renglón ' . ($r + 2) . ': faltan las fechas.');
            if ($fin < $inicio) throw new \RuntimeException('El renglón ' . ($r + 2) . ': la fecha de fin es anterior a la de inicio.');

            $status = self::status(self::valor($datos, $r, $roles['status']));
            $sub = ($id && isset($existentes[$id]) && !isset($vistos[$id])) ? $existentes[$id] : new Subactividad(['actividad_id' => (int)$actividad->id]);
            $sub->nombre = $nombre;
            $sub->fecha_inicio = $inicio;
            $sub->fecha_fin = $fin;
            $sub->status = $status;
            $alertas = $sub->validar();
            if (!empty($alertas)) {
                throw new \RuntimeException('El renglón ' . ($r + 2) . ': ' . implode(' ', array_unique($alertas['error'] ?? [])));
            }
            $res = $sub->guardar();
            if (!$res || (is_array($res) && empty($res['resultado']))) {
                throw new \RuntimeException('No se pudo guardar la subactividad del renglón ' . ($r + 2) . '.');
            }
            $idNuevo = (int)(is_array($res) ? ($res['id'] ?? $sub->id) : $sub->id);
            $sub->id = $idNuevo;
            $nuevos[$r] = $idNuevo;
            $vistos[$idNuevo] = true;
            // La hoja se queda con los valores ya validados (recortes, límites, fechas de respaldo),
            // para que no haya diferencia entre lo que muestra y lo que se guardó.
            $datos['rows'][$r][$roles['nombre']] = (string)$sub->nombre;
            $datos['rows'][$r][$roles['inicio']] = (string)$sub->fecha_inicio;
            $datos['rows'][$r][$roles['fin']] = (string)$sub->fecha_fin;
            $datos['rows'][$r][$roles['status']] = (string)(int)$sub->status;
        }

        // Las subactividades que ya no tienen renglón se eliminan
        foreach ($existentes as $id => $sub) {
            if (!isset($vistos[$id])) $sub->eliminar();
        }

        ksort($nuevos);
        $datos['meta']['subactividad'] = [
            'actividad_id' => (int)$actividad->id,
            'ids' => array_values($nuevos),
        ];
        $actividad->recalcularDesdeSubactividades();
        return $datos;
    }

    /**
     * Refleja en la hoja los cambios hechos desde la sección de subactividades.
     * Devuelve un mensaje de error o null. Si hay fórmulas y el cambio agrega o quita
     * renglones, se pide hacerlo en SheetFlow para que las referencias se reescriban.
     */
    public static function reflejar(Actividad $actividad): ?string
    {
        $v = self::deActividad((int)$actividad->id);
        if (!$v) return null;
        $hoja = SheetflowHoja::find((int)$v->hoja_id);
        if (!$hoja) {
            try { $v->eliminar(); } catch (\Throwable $e) {}
            return null;
        }
        $antes = $hoja->imagenes();
        $datos = $hoja->contenido();
        $roles = self::roles($datos);
        foreach (['nombre', 'inicio', 'fin', 'status'] as $rol) {
            if (!isset($roles[$rol])) return 'La tabla vinculada perdió una columna base. Vuelve a crearla desde la actividad.';
        }
        if (!isset($datos['meta']['subactividad']['ids']) || !is_array($datos['meta']['subactividad']['ids'])) {
            return 'La tabla vinculada perdió el vínculo con las subactividades. Desvincúlala y vuelve a generarla.';
        }

        $ids = self::ids($datos, count($datos['rows']));
        $subs = [];
        foreach (Subactividad::deActividad((int)$actividad->id) as $s) $subs[(int)$s->id] = $s;

        $quitar = [];
        foreach ($ids as $r => $id) {
            if ($id && !isset($subs[$id])) $quitar[] = (int)$r;
        }
        $presentes = [];
        foreach ($ids as $id) if ($id) $presentes[$id] = true;
        $agregar = [];
        foreach ($subs as $id => $s) if (!isset($presentes[$id])) $agregar[] = $s;

        if (($quitar || $agregar) && self::tieneFormulas($datos)) {
            return 'La tabla tiene fórmulas en alguna columna. Agrega o elimina renglones en SheetFlow para que sus referencias se ajusten.';
        }

        // Quitar renglones de abajo hacia arriba (y desplazar los formatos)
        foreach (array_reverse($quitar) as $r) {
            array_splice($datos['rows'], $r, 1);
            array_splice($ids, $r, 1);
            $datos['fmt'] = Sheetflow::fmtSinFila($datos['fmt'] ?? [], $r);
        }

        // Actualizar las filas de las subactividades que siguen
        foreach ($ids as $r => $id) {
            if (!$id || !isset($subs[$id]) || !isset($datos['rows'][$r])) continue;
            $s = $subs[$id];
            $datos['rows'][$r][$roles['nombre']] = (string)$s->nombre;
            $datos['rows'][$r][$roles['inicio']] = (string)$s->fecha_inicio;
            $datos['rows'][$r][$roles['fin']] = (string)$s->fecha_fin;
            $datos['rows'][$r][$roles['status']] = (string)(int)$s->status;
        }

        // Agregar las subactividades nuevas en el primer renglón vacío o al final
        foreach ($agregar as $s) {
            $fila = array_fill(0, count($datos['cols']), '');
            $fila[$roles['nombre']] = (string)$s->nombre;
            $fila[$roles['inicio']] = (string)$s->fecha_inicio;
            $fila[$roles['fin']] = (string)$s->fecha_fin;
            $fila[$roles['status']] = (string)(int)$s->status;
            $blanco = null;
            foreach ($datos['rows'] as $r => $f) {
                if (empty($ids[$r]) && self::filaBaseVacia($datos, $r, $roles)) { $blanco = $r; break; }
            }
            if ($blanco === null) {
                $datos['rows'][] = $fila;
                $ids[] = (int)$s->id;
            } else {
                $datos['rows'][$blanco] = array_replace($datos['rows'][$blanco], $fila);
                $ids[$blanco] = (int)$s->id;
            }
        }
        if (!$datos['rows']) {
            $datos['rows'][] = array_fill(0, count($datos['cols']), '');
            $ids[] = null;
        }

        $datos['meta']['subactividad'] = ['actividad_id' => (int)$actividad->id, 'ids' => array_values($ids)];
        unset($datos['calc'], $datos['tot']);   // se recalculan con las filas nuevas
        $datos = Sheetflow::normalizar($datos);
        $hoja->datos = json_encode($datos, JSON_UNESCAPED_UNICODE);
        if (!$hoja->guardar()) return 'No se pudo actualizar la tabla vinculada.';
        Sheetflow::limpiarImagenes($antes, $hoja->imagenes(), (int)$hoja->proyecto_id, (int)$hoja->id);
        return null;
    }

    /** Roles de las columnas base: ['nombre' => índice, …] */
    private static function roles(array $datos): array
    {
        $out = [];
        foreach ((array)($datos['cols'] ?? []) as $i => $c) {
            $rol = (string)($c['rol'] ?? '');
            if (in_array($rol, Sheetflow::ROLES_SUBACTIVIDAD, true) && !isset($out[$rol])) $out[$rol] = (int)$i;
        }
        return $out;
    }

    /** IDs alineados con los renglones (los renglones nuevos llevan null) */
    private static function ids(array $datos, int $n): array
    {
        $guardados = array_values((array)($datos['meta']['subactividad']['ids'] ?? []));
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $id = $guardados[$i] ?? null;
            $out[$i] = is_numeric($id) && (int)$id > 0 ? (int)$id : null;
        }
        return $out;
    }

    /** Valor de una celda; si es fórmula, el resultado calculado por el servidor */
    private static function valor(array $datos, int $fila, int $col): string
    {
        $crudo = (string)($datos['rows'][$fila][$col] ?? '');
        if (!SheetflowFormulas::esFormula($crudo)) return $crudo;
        return (string)(((array)($datos['calc'] ?? []))["$fila,$col"] ?? '');
    }

    /** Fecha ISO de una celda; '' si está vacía y null si no es una fecha válida */
    private static function fecha(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $v : null;
        }
        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})$/', $v, $m)) {
            $y = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
            return checkdate((int)$m[2], (int)$m[1], (int)$y) ? sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]) : null;
        }
        return null;
    }

    private static function status(string $v): int
    {
        $n = Sheetflow::numero($v);
        return $n === null ? 0 : max(0, min(100, (int)round($n)));
    }

    private static function filaBaseVacia(array $datos, int $r, array $roles): bool
    {
        foreach (['nombre', 'inicio', 'fin', 'status'] as $rol) {
            if (trim(self::valor($datos, $r, $roles[$rol])) !== '') return false;
        }
        return true;
    }

    private static function tieneFormulas(array $datos): bool
    {
        foreach ((array)($datos['rows'] ?? []) as $fila) {
            foreach ((array)$fila as $v) if (SheetflowFormulas::esFormula($v)) return true;
        }
        return false;
    }
}
