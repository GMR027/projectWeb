<?php

namespace Classes;

/**
 * Campos personalizados por proyecto («Nivel», «Frente», «Torre»…). Cada campo pertenece a una entidad (actividad, punch,
 * submittal, bitácora, reporte) y guarda un valor por registro en campos_valores. Se muestran como columnas «cp_<id>» del
 * diseño de cada lista (ListaDiseno), se editan en la celda y sirven para filtrar y agrupar.
 */
class CamposPersonalizados
{
    use AccesoDb;

    public const ENTIDADES = ['actividad' => 'Actividades del programa', 'punch' => 'Punch list', 'submittal' => 'Submittals', 'bitacora' => 'Bitácora', 'reporte' => 'Reporte diario'];
    public const TIPOS = ['texto' => 'Texto', 'numero' => 'Número', 'fecha' => 'Fecha', 'lista' => 'Lista de opciones'];
    /** Cómo se comprueba que un registro es del proyecto */
    private const TABLAS = [
        'actividad' => 'SELECT a.id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = %d AND a.id = %d',
        'punch' => 'SELECT id FROM punch_items WHERE proyecto_id = %d AND id = %d',
        'submittal' => 'SELECT id FROM submittals WHERE proyecto_id = %d AND id = %d',
        'bitacora' => 'SELECT id FROM bitacora WHERE proyecto_id = %d AND id = %d',
        'reporte' => 'SELECT id FROM reportes_diarios WHERE proyecto_id = %d AND id = %d',
    ];
    public const MAX_POR_ENTIDAD = 12;

    /** Campos del proyecto (de una entidad o todos): [id => fila con 'opciones' como arreglo] */
    public static function deProyecto(int $pid, ?string $entidad = null, bool $recargar = false): array
    {
        static $cache = [];
        if ($recargar || !isset($cache[$pid])) {
            $cache[$pid] = [];
            foreach (self::filas("SELECT * FROM campos_personalizados WHERE proyecto_id = $pid ORDER BY orden, id") as $f) {
                $f['id'] = (int)$f['id'];
                $f['opciones'] = self::listaOpciones((string)$f['opciones']);
                $cache[$pid][$f['id']] = $f;
            }
        }
        return $entidad === null ? $cache[$pid] : array_filter($cache[$pid], fn($c) => $c['entidad'] === $entidad);
    }

    public static function listaOpciones(string $texto): array
    {
        $o = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $l) {
            $l = mb_substr(trim(strip_tags($l)), 0, 80);
            if ($l !== '' && !in_array($l, $o, true)) $o[] = $l;
        }
        return array_slice($o, 0, 50);
    }

    /** Crea o edita un campo. Devuelve [ok, mensaje] */
    public static function guardar(int $pid, int $id, string $entidad, string $nombre, string $tipo, string $opciones): array
    {
        $nombre = mb_substr(trim(strip_tags($nombre)), 0, 60);
        if ($nombre === '') return [false, 'Escribe el nombre del campo.'];
        if (!isset(self::TIPOS[$tipo]) || ($id <= 0 && !isset(self::ENTIDADES[$entidad]))) return [false, 'Datos no válidos.'];
        $ops = $tipo === 'lista' ? self::listaOpciones($opciones) : [];
        if ($tipo === 'lista' && !$ops) return [false, 'Escribe al menos una opción (una por línea).'];
        $opTexto = $ops ? implode("\n", $ops) : null;
        $db = self::db();
        if ($id > 0) {
            $actual = self::deProyecto($pid)[$id] ?? null;
            if (!$actual) return [false, 'El campo no existe.'];
            $st = $db->prepare('UPDATE campos_personalizados SET nombre = ?, tipo = ?, opciones = ? WHERE id = ? AND proyecto_id = ?');
            $st->bind_param('sssii', $nombre, $tipo, $opTexto, $id, $pid);
        } else {
            if (count(self::deProyecto($pid, $entidad)) >= self::MAX_POR_ENTIDAD) return [false, 'Máximo ' . self::MAX_POR_ENTIDAD . ' campos por herramienta.'];
            foreach (self::deProyecto($pid, $entidad) as $c) if (mb_strtolower($c['nombre']) === mb_strtolower($nombre)) return [false, 'Ya existe un campo con ese nombre.'];
            $orden = count(self::deProyecto($pid));
            $st = $db->prepare('INSERT INTO campos_personalizados (proyecto_id, entidad, nombre, tipo, opciones, orden) VALUES (?, ?, ?, ?, ?, ?)');
            $st->bind_param('issssi', $pid, $entidad, $nombre, $tipo, $opTexto, $orden);
        }
        $ok = $st->execute();
        self::deProyecto($pid, null, true);
        return $ok ? [true, ''] : [false, 'No se pudo guardar.'];
    }

    public static function eliminar(int $pid, int $id): bool
    {
        $c = self::deProyecto($pid)[$id] ?? null;
        if (!$c) return false;
        self::db()->query("DELETE FROM campos_personalizados WHERE id = $id AND proyecto_id = $pid");
        self::deProyecto($pid, null, true);
        ListaDiseno::quitarClave($pid, 'cp_' . $id);
        return true;
    }

    /** Valores de una entidad: [registro_id => [campo_id => valor]] */
    public static function valores(int $pid, string $entidad): array
    {
        $ids = array_keys(self::deProyecto($pid, $entidad));
        if (!$ids) return [];
        $out = [];
        foreach (self::filas('SELECT campo_id, registro_id, valor FROM campos_valores WHERE campo_id IN (' . implode(',', $ids) . ')') as $f) {
            $out[(int)$f['registro_id']][(int)$f['campo_id']] = (string)$f['valor'];
        }
        return $out;
    }

    /** Valida y guarda un valor. Devuelve [ok, valor normalizado | mensaje] */
    public static function guardarValor(int $pid, int $campoId, int $registroId, string $valor): array
    {
        $c = self::deProyecto($pid)[$campoId] ?? null;
        if (!$c) return [false, 'El campo no existe.'];
        if (!self::filas(sprintf(self::TABLAS[$c['entidad']], $pid, $registroId))) return [false, 'El registro no es de este proyecto.'];
        $valor = trim($valor);
        if ($valor !== '') {
            switch ($c['tipo']) {
                case 'fecha':
                    $d = \DateTime::createFromFormat('Y-m-d', $valor);
                    if (!$d || $d->format('Y-m-d') !== $valor) return [false, 'Fecha no válida'];
                    break;
                case 'numero':
                    $valor = str_replace(',', '.', $valor);
                    if (!is_numeric($valor) || abs((float)$valor) > 9999999999) return [false, 'Número no válido'];
                    $valor = rtrim(rtrim(number_format((float)$valor, 4, '.', ''), '0'), '.');
                    break;
                case 'lista':
                    if (!in_array($valor, $c['opciones'], true)) return [false, 'Elige una de las opciones'];
                    break;
                default:
                    $valor = mb_substr(strip_tags($valor), 0, 500);
            }
        }
        if ($valor === '') {
            self::db()->query("DELETE FROM campos_valores WHERE campo_id = $campoId AND registro_id = $registroId");
        } else {
            $st = self::db()->prepare('INSERT INTO campos_valores (campo_id, registro_id, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
            $st->bind_param('iis', $campoId, $registroId, $valor);
            if (!$st->execute()) return [false, 'No se pudo guardar'];
        }
        return [true, $valor];
    }

    /** Texto para mostrar un valor (las fechas en d/m/y) */
    public static function texto(array $campo, ?string $valor): string
    {
        if ($valor === null || $valor === '') return '';
        return $campo['tipo'] === 'fecha' ? date('d/m/y', strtotime($valor)) : $valor;
    }

    /** Control (o texto) de un campo para un registro; editable se guarda por /api/campos/valor */
    public static function control(array $campo, int $registroId, ?string $valor, bool $editable): string
    {
        $v = (string)$valor;
        if (!$editable) return s(self::texto($campo, $v));
        $attr = ' class="cp-input" data-campo="' . (int)$campo['id'] . '" data-registro="' . $registroId . '" aria-label="' . s($campo['nombre']) . '"';
        if ($campo['tipo'] === 'lista') {
            $h = '<select' . $attr . '><option value="">—</option>';
            foreach ($campo['opciones'] as $o) $h .= '<option' . ($o === $v ? ' selected' : '') . '>' . s($o) . '</option>';
            return $h . '</select>';
        }
        $tipo = ['texto' => 'text', 'numero' => 'number', 'fecha' => 'date'][$campo['tipo']];
        return '<input type="' . $tipo . '"' . $attr . ' value="' . s($v) . '"' . ($tipo === 'number' ? ' step="any"' : ($tipo === 'text' ? ' maxlength="500"' : '')) . '>';
    }

    /** Celda <td> de un campo para un registro */
    public static function celda(array $campo, int $registroId, ?string $valor, bool $editable, string $extraClase = 'hide-m'): string
    {
        return '<td class="' . trim('cp-celda ' . $extraClase) . '">' . self::control($campo, $registroId, $valor, $editable) . '</td>';
    }

    // ---------- filtro y agrupación ----------

    /** Filtros de la URL (cp[campo_id]=valor): solo de campos de esa entidad */
    public static function filtrosDePeticion(int $pid, string $entidad): array
    {
        $campos = self::deProyecto($pid, $entidad);
        $out = [];
        foreach ((array)($_GET['cp'] ?? []) as $id => $v) {
            if (isset($campos[(int)$id]) && is_string($v) && trim($v) !== '') $out[(int)$id] = trim($v);
        }
        return $out;
    }

    /** Campo por el que se agrupa (de la URL), o 0 */
    public static function agruparDePeticion(int $pid, string $entidad): int
    {
        $id = (int)($_GET['agrupar'] ?? 0);
        return isset(self::deProyecto($pid, $entidad)[$id]) ? $id : 0;
    }

    /** ¿Los valores de un registro [campo_id => valor] cumplen todos los filtros? */
    public static function coincide(array $valoresRegistro, array $filtros, array $campos): bool
    {
        foreach ($filtros as $cid => $buscado) {
            $v = $valoresRegistro[$cid] ?? '';
            if ($buscado === '__vacio') { if ($v !== '') return false; continue; }
            if (($campos[$cid]['tipo'] ?? '') === 'texto' ? mb_stripos($v, $buscado) === false : $v !== $buscado) return false;
        }
        return true;
    }

    /**
     * Aplica filtros y agrupación a una lista de registros; $idDe saca el id de cada uno.
     * 'items' queda ordenado por grupo y 'grupo' da la etiqueta de cada registro (vacío si no se agrupa).
     */
    public static function aplicar(int $pid, string $entidad, array $items, callable $idDe): array
    {
        $valores = self::valores($pid, $entidad);
        $campos = self::deProyecto($pid, $entidad);
        $filtros = self::filtrosDePeticion($pid, $entidad);
        $items = array_values(array_filter($items, fn($it) => self::coincide($valores[$idDe($it)] ?? [], $filtros, $campos)));
        $grupo = [];
        $agrupar = self::agruparDePeticion($pid, $entidad);
        if ($agrupar) {
            $sin = '(sin ' . mb_strtolower($campos[$agrupar]['nombre']) . ')';
            foreach ($items as $it) $grupo[$idDe($it)] = ($valores[$idDe($it)][$agrupar] ?? '') ?: $sin;
            usort($items, function ($a, $b) use ($grupo, $idDe, $sin) {   // usort es estable: dentro de cada grupo se conserva el orden
                [$ga, $gb] = [$grupo[$idDe($a)], $grupo[$idDe($b)]];
                if (($ga === $sin) !== ($gb === $sin)) return $ga === $sin ? 1 : -1;   // «sin valor» al final
                return strnatcasecmp($ga, $gb);
            });
        }
        return ['items' => $items, 'grupo' => $grupo, 'valores' => $valores, 'campos' => $campos, 'filtros' => $filtros, 'agrupar' => $agrupar];
    }
}
