<?php

namespace Classes;

use Model\Proyecto;

/**
 * Columnas configurables del programa de obra (la tabla de /programa), al estilo de los MSR:
 * el diseño vive en proyectos.programa_columnas_json como [{k, n}] (clave y nombre propio). NULL = diseño clásico.
 * Los campos nuevos de la actividad (responsable, recuperación, libres) se leen con el modelo y se escriben aquí,
 * con SQL propio, para que el programa siga funcionando aunque la migración aún no esté aplicada.
 */
class ProgramaColumnas
{
    use AccesoDb;

    /** clave => [nombre genérico, tipo, ¿se edita en línea?] — tipo: texto | fecha | numero | actividad | calculado | acciones */
    public const COLUMNAS = [
        'codigo' => ['WBS / Código', 'texto', false],
        'nombre' => ['Nombre', 'texto', false],
        'fecha_inicio' => ['Fecha inicio', 'fecha', true],
        'fecha_fin' => ['Fecha fin', 'fecha', true],
        'duracion' => ['Duración', 'calculado', false],
        'progreso' => ['Progreso', 'numero', true],
        'status' => ['Status', 'calculado', false],
        'prioridad' => ['Prioridad', 'calculado', false],
        'restriccion' => ['Restricción', 'calculado', false],
        'responsable' => ['Responsable', 'texto', true],
        'rec_inicio' => ['Fecha inicial de recuperación', 'fecha', true],
        'rec_fin' => ['Fecha final de recuperación', 'fecha', true],
        'predecesora' => ['Predecesora', 'actividad', true],
        'sucesora' => ['Sucesora', 'actividad', true],
        'libre_texto' => ['Campo libre (texto)', 'texto', true],
        'libre_numero' => ['Campo libre (número)', 'numero', true],
        'libre_fecha' => ['Campo libre (fecha)', 'fecha', true],
        'indicador' => ['Indicador', 'calculado', false],
        'acciones' => ['Acciones', 'acciones', false],
    ];

    /** Diseño de siempre (proyectos sin diseño propio) */
    public const CLASICO = ['codigo', 'nombre', 'fecha_inicio', 'fecha_fin', 'duracion', 'progreso', 'status', 'prioridad', 'restriccion', 'acciones'];
    /** No se pueden quitar: sin ellas la tabla no tendría sentido ni forma de editarse */
    public const OBLIGATORIAS = ['nombre', 'acciones'];
    /** Campos de la actividad que se guardan con guardarCampo(): campo => [tipo, largo máximo] */
    public const EDITABLES = [
        'responsable' => ['texto', 150], 'rec_inicio' => ['fecha', 0], 'rec_fin' => ['fecha', 0],
        'libre_texto' => ['texto', 255], 'libre_numero' => ['numero', 0], 'libre_fecha' => ['fecha', 0],
    ];
    /** Datos que puede evaluar el indicador condicional */
    public const CAMPOS_INDICADOR = ['progreso' => 'Progreso (%)', 'duracion' => 'Duración (días)', 'libre_numero' => 'Campo libre (número)'];
    public const REGLAS_DEFAULT = [
        ['min' => 0, 'max' => 0, 'color' => '#ef4444'],
        ['min' => 1, 'max' => 50, 'color' => '#f59e0b'],
        ['min' => 51, 'max' => 100, 'color' => '#10b981'],
    ];

    /** Campos del programa que una tabla de tareas del vPlan puede mostrar además de las columnas de siempre */
    public const EN_VPLAN = ['responsable', 'rec_inicio', 'rec_fin', 'predecesora', 'sucesora', 'libre_texto', 'libre_numero', 'libre_fecha', 'indicador'];
    /** Campos de EN_VPLAN que se editan en línea (el resto solo se lee) */
    public const EDITABLES_VPLAN = ['responsable', 'rec_inicio', 'rec_fin', 'libre_texto', 'libre_numero', 'libre_fecha'];

    /** Título de cada campo del programa en el vPlan: el nombre que el usuario le puso en «Diseñar programa» (o el genérico) */
    public static function titulosVplan(Proyecto $p): array
    {
        $out = [];
        foreach (self::EN_VPLAN as $k) $out[$k] = self::COLUMNAS[$k][0];
        foreach (self::columnas($p) as $c) if (isset($out[$c['k']])) $out[$c['k']] = $c['n'];
        return $out;
    }

    /** Lo que necesitan las tablas de tareas para dibujar estos campos (se arma una vez por vista o PDF) */
    public static function contextoVplan(Proyecto $p): array
    {
        $pid = (int)$p->id;
        return [
            'titulos' => self::titulosVplan($p), 'vecinas' => self::vecinas($pid), 'indicador' => self::indicador($p),
            'codigos' => array_map(fn($a) => $a['codigo'], Dependencias::actividades($pid)),
        ];
    }

    /** Valor de un campo del programa para una actividad: ['raw' => dato, 'texto' => para mostrar, 'color' => indicador o null] */
    public static function valorVplan(string $k, \Model\Actividad $a, array $ctx): array
    {
        $fecha = fn($f) => $f ? date('d/m/y', strtotime($f)) : '';
        $num = fn($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float)$v, 4, '.', ''), '0'), '.');
        switch ($k) {
            case 'responsable': case 'libre_texto':
                return ['raw' => (string)($a->$k ?? ''), 'texto' => (string)($a->$k ?? ''), 'color' => null];
            case 'rec_inicio': case 'rec_fin': case 'libre_fecha':
                return ['raw' => (string)($a->$k ?? ''), 'texto' => $fecha($a->$k ?? null), 'color' => null];
            case 'libre_numero':
                return ['raw' => $num($a->libre_numero), 'texto' => $num($a->libre_numero), 'color' => null];
            case 'predecesora': case 'sucesora':
                $ids = $ctx['vecinas'][$k === 'predecesora' ? 'pred' : 'suc'][(int)$a->id] ?? [];
                $t = implode(', ', array_filter(array_map(fn($i) => $ctx['codigos'][$i] ?? '', $ids)));
                return ['raw' => $t, 'texto' => $t, 'color' => null];
            case 'indicador':
                $campo = $ctx['indicador']['campo'];
                $v = $campo === 'duracion' ? ($a->hito ? null : $a->duracion_dias) : ($campo === 'libre_numero' ? $a->libre_numero : $a->progreso);
                $c = self::colorIndicador($ctx['indicador']['reglas'], $v);
                return ['raw' => $v, 'texto' => $c ? $num($v) : '', 'color' => $c];
        }
        return ['raw' => '', 'texto' => '', 'color' => null];
    }

    /** ¿Es un campo personalizado de las actividades (cp_<id>, ver CamposPersonalizados)? */
    public static function esCp(string $k): bool
    {
        return (bool)preg_match('/^cp_\d+$/', $k);
    }

    public static function esLibre(string $k): bool
    {
        return in_array($k, ['libre_texto', 'libre_numero', 'libre_fecha'], true);
    }

    /** Diseño vigente: [{k, n (nombre mostrado), generico, tipo, editable, propio (¿tiene nombre propio?)}] */
    public static function columnas(Proyecto $p): array
    {
        $json = json_decode((string)($p->programa_columnas_json ?? ''), true);
        $campos = CamposPersonalizados::deProyecto((int)$p->id, 'actividad');
        $lista = is_array($json) && $json ? $json : array_map(fn($k) => ['k' => $k, 'n' => ''], self::CLASICO);
        if (!(is_array($json) && $json) && $campos) {   // sin diseño propio: los campos personalizados salen antes de las acciones
            array_splice($lista, count($lista) - 1, 0, array_map(fn($id) => ['k' => 'cp_' . $id, 'n' => ''], array_keys($campos)));
        }
        $out = [];
        foreach (self::normalizar($lista) as $c) {
            if (self::esCp($c['k'])) {
                $campo = $campos[(int)substr($c['k'], 3)] ?? null;
                if (!$campo) continue;   // el campo ya no existe
                $out[] = ['k' => $c['k'], 'n' => $c['n'] !== '' ? $c['n'] : $campo['nombre'], 'generico' => $campo['nombre'], 'tipo' => 'cp', 'editable' => true, 'propio' => $c['n'] !== '', 'campo' => $campo];
                continue;
            }
            [$gen, $tipo, $edit] = self::COLUMNAS[$c['k']];
            $out[] = ['k' => $c['k'], 'n' => $c['n'] !== '' ? $c['n'] : t($gen), 'generico' => t($gen), 'tipo' => $tipo, 'editable' => $edit, 'propio' => $c['n'] !== ''];
        }
        return $out;
    }

    /** Todas las columnas del catálogo: primero las activas (en su orden) y luego las demás, marcadas como inactivas */
    public static function paraFormulario(Proyecto $p): array
    {
        $act = self::columnas($p);
        $usadas = array_column($act, 'k');
        foreach ($act as &$c) $c['activa'] = true;
        unset($c);
        foreach (self::COLUMNAS as $k => [$gen, $tipo, $edit]) {
            if (!in_array($k, $usadas, true)) $act[] = ['k' => $k, 'n' => $gen, 'generico' => $gen, 'tipo' => $tipo, 'editable' => $edit, 'propio' => false, 'activa' => false];
        }
        foreach (CamposPersonalizados::deProyecto((int)$p->id, 'actividad') as $c) {
            if (!in_array('cp_' . $c['id'], $usadas, true)) $act[] = ['k' => 'cp_' . $c['id'], 'n' => $c['nombre'], 'generico' => $c['nombre'], 'tipo' => 'cp', 'editable' => true, 'propio' => false, 'activa' => false];
        }
        return $act;
    }

    /** Lista blanca: solo claves del catálogo, sin repetir, nombre ≤ 60; siempre con las obligatorias */
    public static function normalizar(array $entradas): array
    {
        $out = []; $vistas = [];
        foreach ($entradas as $e) {
            $k = (string)($e['k'] ?? '');
            if ((!isset(self::COLUMNAS[$k]) && !self::esCp($k)) || isset($vistas[$k])) continue;
            $vistas[$k] = true;
            $n = mb_substr(trim(strip_tags((string)($e['n'] ?? ''))), 0, 60);
            if (isset(self::COLUMNAS[$k]) && $n === self::COLUMNAS[$k][0]) $n = '';   // igual al genérico = sin nombre propio
            $out[] = ['k' => $k, 'n' => $n];
        }
        foreach (self::OBLIGATORIAS as $k) {
            if (isset($vistas[$k])) continue;
            if ($k === 'acciones') $out[] = ['k' => $k, 'n' => ''];
            else array_unshift($out, ['k' => $k, 'n' => '']);
        }
        return $out;
    }

    public static function nombrePrograma(Proyecto $p): string
    {
        $n = trim((string)($p->programa_nombre ?? ''));
        return $n !== '' ? $n : (string)$p->nombre;
    }

    /** Indicador vigente: ['campo' => …, 'reglas' => [{min,max,color}]] */
    public static function indicador(Proyecto $p): array
    {
        $j = json_decode((string)($p->programa_indicador_json ?? ''), true);
        $campo = is_array($j) && isset(self::CAMPOS_INDICADOR[$j['campo'] ?? '']) ? $j['campo'] : 'progreso';
        $reglas = is_array($j) && isset($j['reglas']) ? self::normalizarReglas((array)$j['reglas']) : self::REGLAS_DEFAULT;
        return ['campo' => $campo, 'reglas' => $reglas];
    }

    /** Reglas válidas: números con min ≤ max y color #rrggbb (en el orden dado; la primera que coincide manda) */
    public static function normalizarReglas(array $reglas): array
    {
        $out = [];
        foreach ($reglas as $r) {
            if (!is_array($r) || !is_numeric($r['min'] ?? null) || !is_numeric($r['max'] ?? null)) continue;
            $min = (float)$r['min']; $max = (float)$r['max'];
            if ($min > $max) [$min, $max] = [$max, $min];
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($r['color'] ?? '')) ? strtolower($r['color']) : '#94a3b8';
            $out[] = ['min' => $min == (int)$min ? (int)$min : $min, 'max' => $max == (int)$max ? (int)$max : $max, 'color' => $color];
            if (count($out) >= 12) break;
        }
        return $out;
    }

    /** Color de la regla que contiene el valor (null si no hay valor o ninguna coincide) */
    public static function colorIndicador(array $reglas, $valor): ?string
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) return null;
        foreach ($reglas as $r) if ($valor >= $r['min'] && $valor <= $r['max']) return $r['color'];
        return null;
    }

    /** Guarda nombre, columnas e indicador del programa (el diseño vacío/clásico se guarda como NULL) */
    public static function guardarDiseno(int $pid, string $nombre, array $columnas, array $indicador): bool
    {
        $cols = self::normalizar($columnas);
        $esClasico = array_column($cols, 'k') === self::CLASICO && !array_filter(array_column($cols, 'n'));
        $json = $esClasico ? null : json_encode($cols, JSON_UNESCAPED_UNICODE);
        $campo = isset(self::CAMPOS_INDICADOR[$indicador['campo'] ?? '']) ? $indicador['campo'] : 'progreso';
        $ind = json_encode(['campo' => $campo, 'reglas' => self::normalizarReglas((array)($indicador['reglas'] ?? []))], JSON_UNESCAPED_UNICODE);
        $nombre = mb_substr(trim($nombre), 0, 200);
        $nombre = $nombre === '' ? null : $nombre;
        try {
            $st = self::db()->prepare('UPDATE proyectos SET programa_nombre = ?, programa_columnas_json = ?, programa_indicador_json = ? WHERE id = ?');
            $st->bind_param('sssi', $nombre, $json, $ind, $pid);
            return $st->execute();
        } catch (\Throwable $e) {   // migración pendiente
            error_log('Programa: ' . $e->getMessage());
            return false;
        }
    }

    /** Diseño explícito [{k,n}] del proyecto (el clásico se vuelve explícito al renombrar o reordenar) */
    private static function explicito(Proyecto $p): array
    {
        return array_map(fn($c) => ['k' => $c['k'], 'n' => $c['propio'] ? $c['n'] : ''], self::columnas($p));
    }

    private static function guardarColumnas(int $pid, array $cols): bool
    {
        $json = json_encode(self::normalizar($cols), JSON_UNESCAPED_UNICODE);
        try {
            $st = self::db()->prepare('UPDATE proyectos SET programa_columnas_json = ? WHERE id = ?');
            $st->bind_param('si', $json, $pid);
            return $st->execute();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function renombrar(Proyecto $p, string $k, string $nombre): bool
    {
        $cols = self::explicito($p);
        $ok = false;
        foreach ($cols as &$c) if ($c['k'] === $k) { $c['n'] = $nombre; $ok = true; }
        unset($c);
        return $ok && self::guardarColumnas((int)$p->id, $cols);
    }

    /** Reordena las columnas activas según la lista de claves recibida */
    public static function ordenar(Proyecto $p, array $claves): bool
    {
        $actuales = [];
        foreach (self::explicito($p) as $c) $actuales[$c['k']] = $c;
        $nuevo = [];
        foreach ($claves as $k) if (isset($actuales[$k])) { $nuevo[] = $actuales[$k]; unset($actuales[$k]); }
        foreach ($actuales as $c) $nuevo[] = $c;   // las que no llegaron se conservan al final
        return self::guardarColumnas((int)$p->id, $nuevo);
    }

    /** Guarda un campo nuevo de la actividad. Devuelve [ok, valor normalizado | mensaje de error] */
    public static function guardarCampo(int $actividadId, string $campo, string $valor): array
    {
        if (!isset(self::EDITABLES[$campo])) return [false, 'Campo no editable'];
        [$tipo, $max] = self::EDITABLES[$campo];
        $valor = trim($valor);
        if ($valor === '') {
            $dato = null;
        } elseif ($tipo === 'fecha') {
            $d = \DateTime::createFromFormat('Y-m-d', $valor);
            if (!$d || $d->format('Y-m-d') !== $valor) return [false, 'Fecha no válida'];
            $dato = $valor;
        } elseif ($tipo === 'numero') {
            $valor = str_replace(',', '.', $valor);
            if (!is_numeric($valor) || abs((float)$valor) > 9999999999) return [false, 'Número no válido'];
            $dato = (string)(float)$valor;
        } else {
            $dato = mb_substr(strip_tags($valor), 0, $max);
        }
        if ($dato !== null && in_array($campo, ['rec_inicio', 'rec_fin'], true)) {
            $otro = $campo === 'rec_inicio' ? 'rec_fin' : 'rec_inicio';
            $f = self::filas("SELECT $otro AS v FROM actividades WHERE id = $actividadId");
            $ov = $f[0]['v'] ?? null;
            if ($ov && (($campo === 'rec_inicio' && $dato > $ov) || ($campo === 'rec_fin' && $dato < $ov))) return [false, 'La fecha final de recuperación no puede ser anterior a la inicial'];
        }
        try {
            $st = self::db()->prepare("UPDATE actividades SET $campo = ? WHERE id = ?");
            $st->bind_param('si', $dato, $actividadId);
            if (!$st->execute()) return [false, 'No se pudo guardar'];
        } catch (\Throwable $e) {
            return [false, 'Falta aplicar la migración del programa'];
        }
        return [true, $dato ?? ''];
    }

    /** Predecesoras y sucesoras por actividad: [actividad_id => [ids…]] (para los selectores de la tabla) */
    public static function vecinas(int $pid): array
    {
        $pred = []; $suc = [];
        foreach (Dependencias::relaciones($pid) as $r) {
            $pred[$r['suc']][] = $r['pred'];
            $suc[$r['pred']][] = $r['suc'];
        }
        return ['pred' => $pred, 'suc' => $suc];
    }

    /**
     * Fija la predecesora (o la sucesora) de una actividad con la tabla de dependencias de siempre.
     * Reemplaza la primera relación existente de ese lado (conserva su tipo y retraso) y rechaza ciclos.
     * Devuelve [ok, mensaje].
     */
    public static function fijarVecina(Proyecto $proyecto, int $actividadId, string $lado, int $otraId): array
    {
        $pid = (int)$proyecto->id;
        $rels = Dependencias::relaciones($pid);
        $propia = $lado === 'pred' ? 'suc' : 'pred';   // la actividad es la sucesora de su predecesora, y al revés
        $primera = null;
        foreach ($rels as $r) if ($r[$propia] === $actividadId) { $primera = $r; break; }
        if ($otraId <= 0) {
            if ($primera) Dependencias::eliminar($pid, (int)$primera['id']);
            return [true, ''];
        }
        $pred = $lado === 'pred' ? $otraId : $actividadId;
        $suc = $lado === 'pred' ? $actividadId : $otraId;
        if ($primera && $primera['pred'] === $pred && $primera['suc'] === $suc) return [true, ''];
        [$ok, $msg] = Dependencias::guardar($pid, $pred, $suc, $primera['tipo'] ?? 'FS', (int)($primera['retraso'] ?? 0));
        if (!$ok) return [false, $msg];
        if ($primera) Dependencias::eliminar($pid, (int)$primera['id']);
        Dependencias::propagar($proyecto);
        return [true, ''];
    }
}
