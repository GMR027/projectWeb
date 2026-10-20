<?php

namespace Classes;

/**
 * Revisiones por proyecto. Dos usos con la misma tabla (`revisiones`):
 *  - materiales y actividades: veredicto de normativa/especificaciones (correcto, revisar, no cumple, sin evaluar) + «revisado» (quién y cuándo);
 *  - herramientas de IA: estado de uso (sin ejecutar, al día, desactualizada) + veredicto/revisado puestos a mano.
 * Si el material o la actividad cambia después de revisarse (huella distinta) la revisión se muestra como desactualizada.
 * Cada cambio de estado deja una fila en `revisiones_historial` (solo inserciones). Si la migración está pendiente nada lanza excepciones.
 */
class Revisiones
{
    public const VEREDICTOS = [
        'correcto' => ['Correcto', 'fa-circle-check'],
        'revisar' => ['Requiere revisión', 'fa-triangle-exclamation'],
        'no_cumple' => ['No cumple', 'fa-circle-xmark'],
        'sin_evaluar' => ['Sin evaluar', 'fa-circle'],
    ];
    public const TIPOS = ['material' => 'Material', 'actividad' => 'Actividad', 'herramienta' => 'Herramienta de IA'];

    /** Herramientas de IA que se siguen: clave => [título, SQL de la última ejecución ya registrada por la herramienta] */
    public const HERRAMIENTAS = [
        'inconsistencias' => ['Inconsistencias de proyecto', 'SELECT MAX(creado) f FROM inconsistencias_corridas WHERE proyecto_id = %d'],
        'materiales' => ['Análisis de materiales', 'SELECT MAX(creado) f FROM analisis_materiales WHERE proyecto_id = %d'],
        'programador' => ['Programador de actividades', 'SELECT MAX(creado) f FROM programador_propuestas WHERE proyecto_id = %d'],
        'inteligentes' => ['Materiales inteligentes', 'SELECT MAX(verificado) f FROM material_verificaciones WHERE proyecto_id = %d'],
        'normativa' => ['Normativa y especificaciones', 'SELECT MAX(creado) f FROM normativa_aplicaciones WHERE proyecto_id = %d'],
        'ensamblador' => ['Ensamblador de documentos', 'SELECT MAX(creado) f FROM ensamblador_propuestas WHERE proyecto_id = %d'],
    ];

    private static function db(): \mysqli
    {
        return $GLOBALS['db'];
    }

    private static function filas(string $sql): array
    {
        try {
            $r = self::db()->query($sql);
            return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function esc($v): string
    {
        return self::db()->real_escape_string((string)$v);
    }

    // ---------- huellas ----------

    public static function huellaMaterial(array $m): string
    {
        return MaterialesInteligentes::huella($m);
    }

    public static function huellaActividad(array $a): string
    {
        $n = fn($k) => mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', (string)($a[$k] ?? ''))));
        return md5(implode('|', array_map($n, ['codigo', 'nombre', 'descripcion', 'fecha_inicio', 'duracion_dias', 'fecha_fin'])));
    }

    /** Resume los datos del proyecto (actividades, materiales y vínculos con normativa): si cambian después de ejecutar una herramienta, queda desactualizada */
    public static function huellaProyecto(int $pid): string
    {
        $a = self::filas("SELECT COUNT(*) n, COALESCE(SUM(CRC32(CONCAT_WS('|', a.id, a.codigo, a.nombre, a.fecha_inicio, a.duracion_dias, a.fecha_fin, a.progreso))), 0) h FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid")[0] ?? [];
        $m = self::filas("SELECT COUNT(*) n, COALESCE(SUM(CRC32(CONCAT_WS('|', id, nombre, referencia, especialidad, compra, cantidad))), 0) h FROM materiales WHERE proyecto_id = $pid")[0] ?? [];
        return md5(json_encode([$a, $m]));
    }

    // ---------- lectura ----------

    /** @return array<string, array> ref → fila de revisión del tipo */
    public static function mapa(int $pid, string $tipo): array
    {
        $out = [];
        foreach (self::filas("SELECT * FROM revisiones WHERE proyecto_id = $pid AND tipo = '" . self::esc($tipo) . "'") as $r) $out[$r['ref']] = $r;
        return $out;
    }

    public static function obtener(int $pid, string $tipo, string $ref): ?array
    {
        return self::filas("SELECT * FROM revisiones WHERE proyecto_id = $pid AND tipo = '" . self::esc($tipo) . "' AND ref = '" . self::esc($ref) . "'")[0] ?? null;
    }

    /** Estado listo para pintar: veredicto, revisado (false si cambió después), cambio (la revisión es de datos anteriores) */
    public static function estado(?array $r, string $huellaActual): array
    {
        if (!$r) return ['veredicto' => 'sin_evaluar', 'revisado' => false, 'cambio' => false, 'nota' => '', 'usuario' => '', 'fecha' => ''];
        $cambio = $r['huella'] !== '' && $r['huella'] !== $huellaActual && ((int)$r['revisado'] === 1 || $r['veredicto'] !== 'sin_evaluar');
        return ['veredicto' => $r['veredicto'], 'revisado' => (int)$r['revisado'] === 1 && !$cambio, 'cambio' => $cambio, 'nota' => $r['nota'], 'usuario' => $r['usuario'], 'fecha' => $r['actualizado']];
    }

    /** Materiales o actividades del proyecto con su estado de revisión */
    public static function elementos(int $pid, string $tipo): array
    {
        $mapa = self::mapa($pid, $tipo);
        $out = [];
        if ($tipo === 'material') {
            foreach (self::filas("SELECT * FROM materiales WHERE proyecto_id = $pid ORDER BY especialidad, nombre") as $m) {
                $e = self::estado($mapa[(string)$m['id']] ?? null, self::huellaMaterial($m));
                $out[] = ['ref' => (string)$m['id'], 'nombre' => $m['nombre'], 'detalle' => trim($m['referencia'] . ' · ' . $m['especialidad'], ' ·')] + $e;
            }
        } else {
            foreach (self::filas("SELECT a.*, p.nombre partida FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid ORDER BY p.orden, p.codigo, a.orden, a.codigo") as $a) {
                $e = self::estado($mapa[(string)$a['id']] ?? null, self::huellaActividad($a));
                $out[] = ['ref' => (string)$a['id'], 'nombre' => $a['codigo'] . ' ' . $a['nombre'], 'detalle' => $a['partida']] + $e;
            }
        }
        return $out;
    }

    /** Conteo por estado de una lista de `elementos()` */
    public static function conteo(array $elementos): array
    {
        $c = ['total' => count($elementos), 'correcto' => 0, 'revisar' => 0, 'no_cumple' => 0, 'sin_evaluar' => 0, 'revisados' => 0, 'sin_revisar' => 0, 'cambio' => 0];
        foreach ($elementos as $e) {
            $c[$e['veredicto']]++;
            $e['revisado'] ? $c['revisados']++ : $c['sin_revisar']++;
            if ($e['cambio']) $c['cambio']++;
        }
        return $c;
    }

    public static function resumen(int $pid): array
    {
        return ['materiales' => self::conteo(self::elementos($pid, 'material')), 'actividades' => self::conteo(self::elementos($pid, 'actividad')), 'herramientas' => self::conteoHerramientas(self::herramientas($pid))];
    }

    // ---------- escritura ----------

    private static function nombreDe(int $pid, string $tipo, string $ref): string
    {
        $id = (int)$ref;
        if ($tipo === 'material') return (string)(self::filas("SELECT nombre FROM materiales WHERE id = $id AND proyecto_id = $pid")[0]['nombre'] ?? '');
        if ($tipo === 'actividad') {
            $a = self::filas("SELECT a.codigo, a.nombre FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $id AND p.proyecto_id = $pid")[0] ?? null;
            return $a ? $a['codigo'] . ' ' . $a['nombre'] : '';
        }
        return self::HERRAMIENTAS[$ref][0] ?? (str_starts_with($ref, 'informe:') ? 'Informe: ' . substr($ref, 8) : $ref);
    }

    /** La huella actual del elemento (vacía si no existe: así se rechaza una referencia inválida) */
    private static function huellaDe(int $pid, string $tipo, string $ref): ?string
    {
        $id = (int)$ref;
        if ($tipo === 'material') {
            $m = self::filas("SELECT * FROM materiales WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
            return $m ? self::huellaMaterial($m) : null;
        }
        if ($tipo === 'actividad') {
            $a = self::filas("SELECT a.* FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $id AND p.proyecto_id = $pid")[0] ?? null;
            return $a ? self::huellaActividad($a) : null;
        }
        return isset(self::HERRAMIENTAS[$ref]) || str_starts_with($ref, 'informe:') ? '' : null;
    }

    /**
     * Cambia el veredicto y/o «revisado». $veredicto o $revisado en null se dejan como están.
     * @return array|null estado nuevo (null si la referencia no existe en el proyecto)
     */
    public static function marcar(int $pid, string $tipo, string $ref, ?string $veredicto, ?bool $revisado, ?string $nota, ?array $usuario): ?array
    {
        if (!isset(self::TIPOS[$tipo]) || ($veredicto !== null && !isset(self::VEREDICTOS[$veredicto]))) return null;
        $huella = self::huellaDe($pid, $tipo, $ref);
        if ($huella === null) return null;
        $antes = self::obtener($pid, $tipo, $ref);
        $vAntes = $antes['veredicto'] ?? 'sin_evaluar';
        $rAntes = (int)($antes['revisado'] ?? 0);
        $vNuevo = $veredicto ?? $vAntes;
        // Elegir un veredicto cuenta como revisado; quitarlo (sin evaluar) lo deja pendiente
        $rNuevo = $revisado !== null ? (int)$revisado : ($veredicto !== null ? (int)($veredicto !== 'sin_evaluar') : $rAntes);
        $notaNueva = $nota !== null ? mb_substr(trim($nota), 0, 500) : (string)($antes['nota'] ?? '');
        if ($antes && $vNuevo === $vAntes && $rNuevo === $rAntes && $notaNueva === $antes['nota']) return self::estado($antes, $huella);
        $uid = (int)($usuario['id'] ?? 0);
        $nombreU = mb_substr((string)($usuario['nombre'] ?? ''), 0, 150);
        $db = self::db();
        try {
            $db->query("INSERT INTO revisiones (proyecto_id, tipo, ref, veredicto, revisado, huella, nota, usuario_id, usuario) VALUES ($pid, '" . self::esc($tipo) . "', '" . self::esc($ref) . "', '$vNuevo', $rNuevo, '$huella', '" . self::esc($notaNueva) . "', " . ($uid ?: 'NULL') . ", '" . self::esc($nombreU) . "')
                ON DUPLICATE KEY UPDATE veredicto = VALUES(veredicto), revisado = VALUES(revisado), huella = VALUES(huella), nota = VALUES(nota), usuario_id = VALUES(usuario_id), usuario = VALUES(usuario)");
            $db->query("INSERT INTO revisiones_historial (proyecto_id, tipo, ref, nombre, veredicto_antes, veredicto_despues, revisado_antes, revisado_despues, nota, usuario) VALUES ($pid, '" . self::esc($tipo) . "', '" . self::esc($ref) . "', '" . self::esc(mb_substr(self::nombreDe($pid, $tipo, $ref), 0, 255)) . "', '$vAntes', '$vNuevo', $rAntes, $rNuevo, '" . self::esc($notaNueva) . "', '" . self::esc($nombreU) . "')");
        } catch (\Throwable $e) {
            return null;
        }
        return self::estado(self::obtener($pid, $tipo, $ref), $huella);
    }

    /** Las herramientas de IA llaman aquí al guardar su resultado: queda la fecha y la huella de los datos con que se ejecutó */
    public static function registrarEjecucion(int $pid, string $herramienta): void
    {
        try {
            $h = self::huellaProyecto($pid);
            self::db()->query("INSERT INTO revisiones (proyecto_id, tipo, ref, ejecutada_en, huella_ejecucion) VALUES ($pid, 'herramienta', '" . self::esc($herramienta) . "', NOW(), '$h')
                ON DUPLICATE KEY UPDATE ejecutada_en = NOW(), huella_ejecucion = VALUES(huella_ejecucion), revisado = 0");
        } catch (\Throwable $e) {
        }
    }

    // ---------- herramientas de IA ----------

    /**
     * Estado de uso de cada herramienta: sin_ejecutar | al_dia | desactualizada, más veredicto/revisado manuales.
     * Si la herramienta ya se ejecutó antes de existir este registro se toma la fecha de su propia tabla y se asume al día.
     */
    public static function herramientas(int $pid): array
    {
        $mapa = self::mapa($pid, 'herramienta');
        $huellaHoy = self::huellaProyecto($pid);
        $claves = array_keys(self::HERRAMIENTAS);
        foreach (self::filas("SELECT tipo, MAX(creado) f FROM informes_ia WHERE proyecto_id = $pid GROUP BY tipo") as $i) $claves[] = 'informe:' . $i['tipo'];
        foreach ($mapa as $k => $_) if (!in_array($k, $claves, true)) $claves[] = $k;
        $out = [];
        foreach ($claves as $k) {
            $r = $mapa[$k] ?? null;
            $fecha = $r['ejecutada_en'] ?? null;
            $huella = $r['huella_ejecucion'] ?? '';
            if (isset(self::HERRAMIENTAS[$k])) {
                $f = self::filas(sprintf(self::HERRAMIENTAS[$k][1], $pid))[0]['f'] ?? null;
                if ($f && (!$fecha || $f > $fecha)) { $fecha = $f; $huella = ''; }
            } elseif (str_starts_with($k, 'informe:')) {
                $f = self::filas("SELECT MAX(creado) f FROM informes_ia WHERE proyecto_id = $pid AND tipo = '" . self::esc(substr($k, 8)) . "'")[0]['f'] ?? null;
                if ($f && (!$fecha || $f > $fecha)) { $fecha = $f; $huella = ''; }
            }
            $estado = !$fecha ? 'sin_ejecutar' : ($huella !== '' && $huella !== $huellaHoy ? 'desactualizada' : 'al_dia');
            $out[] = ['clave' => $k, 'titulo' => self::nombreDe($pid, 'herramienta', $k), 'estado' => $estado, 'ejecutada' => $fecha,
                'veredicto' => $r['veredicto'] ?? 'sin_evaluar', 'revisado' => (int)($r['revisado'] ?? 0) === 1, 'nota' => $r['nota'] ?? '', 'usuario' => $r['usuario'] ?? ''];
        }
        return $out;
    }

    public static function conteoHerramientas(array $lista): array
    {
        $c = ['total' => count($lista), 'sin_ejecutar' => 0, 'al_dia' => 0, 'desactualizada' => 0, 'revisadas' => 0];
        foreach ($lista as $h) {
            $c[$h['estado']]++;
            if ($h['revisado']) $c['revisadas']++;
        }
        return $c;
    }

    public const ESTADOS_HERRAMIENTA = [
        'sin_ejecutar' => ['Sin ejecutar', 'fa-circle'],
        'al_dia' => ['Ejecutada y al día', 'fa-circle-check'],
        'desactualizada' => ['Desactualizada: los datos cambiaron, conviene volver a ejecutarla', 'fa-rotate'],
    ];

    // ---------- historial ----------

    public static function historial(int $pid, array $f = [], int $limite = 100): array
    {
        $w = ["proyecto_id = $pid"];
        if (isset(self::TIPOS[$f['tipo'] ?? ''])) $w[] = "tipo = '{$f['tipo']}'";
        if (($f['ref'] ?? '') !== '') $w[] = "ref = '" . self::esc($f['ref']) . "'";
        return self::filas('SELECT * FROM revisiones_historial WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT ' . max(1, $limite));
    }

    // ---------- pintado ----------

    /** Chip clicable (o fijo si no se puede editar). $e = estado() + ref */
    public static function chip(string $tipo, string $ref, array $e, bool $editable, string $titulo = ''): string
    {
        if (!is_admin()) return '';   // Revisiones es solo del administrador
        $v = $e['veredicto'];
        [$etq, $icono] = self::VEREDICTOS[$v];
        $revisado = !empty($e['revisado']);
        $pista = $etq . ($revisado ? ' · revisado' : ' · sin revisar') . (!empty($e['cambio']) ? ' · cambió después de revisarse' : '')
            . (!empty($e['usuario']) ? ' (' . $e['usuario'] . ')' : '') . (!empty($e['nota']) ? ' — ' . $e['nota'] : '');
        $attr = $editable ? ' type="button" data-rev-chip' : ' type="button" tabindex="-1" disabled';
        return '<button class="rev-chip rev-' . $v . ($revisado ? ' rev-ok' : '') . (!empty($e['cambio']) ? ' rev-cambio' : '') . '"' . $attr
            . ' data-tipo="' . htmlspecialchars($tipo) . '" data-ref="' . htmlspecialchars($ref) . '" data-veredicto="' . $v . '" data-revisado="' . (int)$revisado . '" data-nota="' . htmlspecialchars((string)($e['nota'] ?? '')) . '"'
            . ' title="' . htmlspecialchars($pista) . '" aria-label="' . htmlspecialchars(($titulo !== '' ? $titulo . ': ' : '') . $pista) . '">'
            . '<i class="fa-solid ' . $icono . '"></i>'
            . '<span class="rev-vistos" aria-hidden="true">' . (!empty($e['cambio']) ? '<i class="fa-solid fa-rotate"></i>' : ($revisado ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-regular fa-eye-slash"></i>')) . '</span>'
            . '</button>';
    }
}
