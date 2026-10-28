<?php

namespace Classes;

use Model\Actividad;
use Model\ActividadMaterial;
use Model\MaterialDependencia;
use Model\Partida;
use Model\Proyecto;

/**
 * Dependencias de materiales: un material puede requerir que otros estén disponibles (entregados o
 * instalados) antes de usarse. El cálculo es un grafo (muchos a muchos) y nunca escribe fechas solo:
 * `previa()` propone, `aplicar()` escribe y `deshacer()` restaura.
 *
 * También genera sugerencias: relaciones entre actividades que ya existen y actividades nuevas para
 * materiales que todavía no tienen ninguna.
 */
class DependenciasMateriales
{
    use AccesoDb;

    public const MODOS = ['apagado' => 'Apagado', 'informativo' => 'Informativo', 'automatico' => 'Automático'];
    public const REQUISITOS = ['entrega' => 'Entregado en obra', 'instalacion' => 'Instalado'];
    public const ROLES = ['instala' => 'Instala', 'consume' => 'Consume'];

    // ==================== lectura ====================

    /** Materiales del proyecto indexados por id (fila cruda de la BD) */
    public static function materiales(int $proyectoId): array
    {
        $out = [];
        foreach (self::filas("SELECT * FROM materiales WHERE proyecto_id = $proyectoId ORDER BY especialidad ASC, nombre ASC") as $f) {
            $out[(int)$f['id']] = $f;
        }
        return $out;
    }

    /** Actividades que usan cada material: material_id => [actividad, …] */
    public static function actividadesPorMaterial(int $proyectoId): array
    {
        $out = [];
        foreach (self::filas(
            "SELECT am.material_id, am.rol, a.id, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin, a.duracion_dias,
                    a.progreso, a.hito, a.fecha_fija, p.id AS partida_id, p.codigo AS partida_codigo, p.nombre AS partida
             FROM actividad_materiales am
             JOIN actividades a ON a.id = am.actividad_id
             JOIN partidas p ON p.id = a.partida_id
             WHERE p.proyecto_id = $proyectoId
             ORDER BY a.fecha_inicio ASC, a.codigo ASC"
        ) as $f) {
            $out[(int)$f['material_id']][] = $f;
        }
        return $out;
    }

    /** Relaciones guardadas */
    public static function relaciones(int $proyectoId): array
    {
        $out = [];
        try {
            foreach (self::filas("SELECT id, predecesor_id AS pred, sucesor_id AS suc, requisito, retraso, nota
                                  FROM material_dependencias WHERE proyecto_id = $proyectoId ORDER BY id ASC") as $f) {
                $out[] = [
                    'id' => (int)$f['id'], 'pred' => (int)$f['pred'], 'suc' => (int)$f['suc'],
                    'requisito' => (string)$f['requisito'], 'retraso' => (int)$f['retraso'], 'nota' => (string)$f['nota'],
                ];
            }
        } catch (\Throwable $e) {   // migración pendiente
        }
        return $out;
    }

    // ==================== cálculo puro ====================

    /**
     * Calcula la disponibilidad efectiva de cada material.
     * @param array $materiales  id => ['id','nombre','entrega'=>?Y-m-d,'instalacion'=>?Y-m-d]
     * @param array $relaciones  lista de ['pred','suc','requisito','retraso']
     * @param callable|null $sumarDias (fecha, días) => fecha. Por defecto suma días naturales.
     * @return array{materiales:array,orden:array,ciclo:?array}
     */
    public static function calcular(array $materiales, array $relaciones, ?callable $sumarDias = null): array
    {
        $sumarDias ??= fn(string $f, int $n): string => date('Y-m-d', strtotime($f . ' +' . $n . ' day'));
        $ids = array_keys($materiales);
        $rels = [];
        $bySuc = [];
        $adj = [];
        $grado = array_fill_keys($ids, 0);
        foreach ($relaciones as $r) {
            $p = (int)($r['pred'] ?? 0);
            $s = (int)($r['suc'] ?? 0);
            if ($p === $s || !isset($materiales[$p], $materiales[$s])) continue;
            $req = ($r['requisito'] ?? 'entrega') === 'instalacion' ? 'instalacion' : 'entrega';
            $rel = ['pred' => $p, 'suc' => $s, 'requisito' => $req, 'retraso' => max(0, (int)($r['retraso'] ?? 0))];
            $rels[] = $rel;
            $bySuc[$s][] = $rel;
            $adj[$p][] = $s;
            $grado[$s] = ($grado[$s] ?? 0) + 1;
        }

        // Orden topológico (Kahn). Si quedan nodos, hay ciclo.
        $cola = [];
        foreach ($grado as $id => $g) if ($g === 0) $cola[] = $id;
        $orden = [];
        while ($cola) {
            $id = array_shift($cola);
            $orden[] = $id;
            foreach ($adj[$id] ?? [] as $s) {
                if (--$grado[$s] === 0) $cola[] = $s;
            }
        }
        $ciclo = count($orden) < count($ids) ? array_values(array_diff($ids, $orden)) : null;
        if ($ciclo) {   // se calcula igual, en el orden que se pueda, para no dejar la pantalla vacía
            foreach ($ids as $id) if (!in_array($id, $orden, true)) $orden[] = $id;
        }

        $calc = [];
        foreach ($orden as $id) {
            $m = $materiales[$id];
            $propiaE = self::fecha($m['entrega'] ?? null);
            $propiaI = self::fecha($m['instalacion'] ?? null);
            $motivos = [];
            $bloqueado = false;
            if ($propiaE === null) {
                $bloqueado = true;
                $motivos[] = 'sin fecha de entrega';
            }
            $reqs = $bySuc[$id] ?? [];
            if ($reqs) $motivos[] = 'requiere ' . count($reqs) . ' material(es)';
            $fechas = $propiaE !== null ? [$propiaE] : [];
            $desconocido = false;
            foreach ($reqs as $r) {
                $p = $calc[$r['pred']] ?? null;
                $pf = $p ? ($r['requisito'] === 'instalacion' ? $p['instalacion_ef'] : $p['entrega_ef']) : null;
                if ($pf === null) {
                    $desconocido = true;
                    $bloqueado = true;
                    $motivos[] = '«' . ($p['nombre'] ?? 'material') . '» sin fecha';
                    continue;
                }
                $fechas[] = $sumarDias($pf, (int)$r['retraso']);
            }
            $efE = ($propiaE === null || $desconocido || !$fechas) ? null : max($fechas);
            if ($efE === null && $propiaE !== null) $bloqueado = true;

            $efI = $propiaI;
            if ($efI === null && $efE !== null) {
                $efI = $efE;
                $motivos[] = 'sin actividad de instalación: se usa la entrega';
            }
            if ($efE === null) $efI = null;
            elseif ($efI !== null) $efI = max($efI, $efE);

            $calc[$id] = [
                'id' => $id, 'nombre' => (string)($m['nombre'] ?? ''),
                'entrega' => $propiaE, 'instalacion' => $propiaI,
                'entrega_ef' => $efE, 'instalacion_ef' => $efI,
                'instalacion_actividad' => $m['instalacion_actividad'] ?? null,
                'bloqueado' => $bloqueado, 'motivos' => array_values(array_unique($motivos)),
                'requiere' => array_map(fn($r) => $r['pred'], $reqs),
            ];
        }

        return ['materiales' => $calc, 'orden' => $orden, 'ciclo' => $ciclo];
    }

    /** Prepara y calcula con el calendario real del proyecto */
    public static function disponibilidad(int $proyectoId, ?Calendario $cal = null): array
    {
        $cal ??= new Calendario($proyectoId);
        $rc = new RutaCritica($cal);
        $mats = self::materiales($proyectoId);
        $acts = self::actividadesPorMaterial($proyectoId);

        $prep = [];
        foreach ($mats as $id => $m) {
            $lista = $acts[$id] ?? [];
            $instalacion = null;
            $fuente = null;
            $minTodas = null;
            $fuenteTodas = null;
            foreach ($lista as $a) {
                $fin = self::fecha($a['fecha_fin'] ?? null);
                if ($fin === null) continue;
                if ($minTodas === null || $fin < $minTodas) { $minTodas = $fin; $fuenteTodas = (int)$a['id']; }
                if (($a['rol'] ?? 'instala') === 'instala' && ($instalacion === null || $fin < $instalacion)) {
                    $instalacion = $fin;
                    $fuente = (int)$a['id'];
                }
            }
            if ($instalacion === null && $minTodas !== null) { $instalacion = $minTodas; $fuente = $fuenteTodas; }
            $prep[$id] = [
                'id' => $id, 'nombre' => $m['nombre'],
                'entrega' => self::fecha($m['fecha_entrega_real'] ?? null) ?: self::fecha($m['fecha_entrega_prog'] ?? null),
                'instalacion' => $instalacion, 'instalacion_actividad' => $fuente,
            ];
        }

        $calc = self::calcular($prep, self::relaciones($proyectoId), fn(string $f, int $n): string => $rc->mas($f, $n));
        $calc['prep'] = $prep;
        $calc['necesidad'] = [];
        foreach ($mats as $id => $m) {
            $ini = [];
            foreach ($acts[$id] ?? [] as $a) if (!empty($a['fecha_inicio'])) $ini[] = $a['fecha_inicio'];
            $calc['necesidad'][$id] = $ini ? min($ini) : null;
        }
        return $calc;
    }

    /** Fecha en que una actividad puede empezar por sus materiales: [fecha, bloqueado, motivos] */
    public static function pisoActividad(array $materialIds, array $calc): array
    {
        $fechas = [];
        $motivos = [];
        $bloqueado = false;
        foreach (array_values(array_unique(array_map('intval', $materialIds))) as $mid) {
            $c = $calc['materiales'][$mid] ?? null;
            if (!$c) continue;
            if ($c['entrega_ef'] === null) {
                $bloqueado = true;
                $motivos[] = '«' . $c['nombre'] . '»: ' . implode(', ', $c['motivos'] ?: ['sin fecha disponible']);
                continue;
            }
            $fechas[] = $c['entrega_ef'];
            if ($c['motivos']) $motivos[] = '«' . $c['nombre'] . '»: ' . implode(', ', $c['motivos']);
        }
        return ['fecha' => $fechas ? max($fechas) : null, 'bloqueado' => $bloqueado || !$fechas, 'motivos' => array_values(array_unique($motivos))];
    }

    /** ¿La actividad instala alguno de esos materiales? (para no empujarla con su propia fecha de instalación) */
    private static function instalaMaterial(array $materialIds, array $calc, int $actividadId): bool
    {
        foreach ($materialIds as $mid) {
            if ((int)($calc['materiales'][$mid]['instalacion_actividad'] ?? 0) === $actividadId) return true;
        }
        return false;
    }

    // ==================== pantalla ====================

    public static function datos(Proyecto $proyecto): array
    {
        $pid = (int)$proyecto->id;
        $mats = self::materiales($pid);
        $acts = self::actividadesPorMaterial($pid);
        $rels = self::relaciones($pid);
        $calc = self::disponibilidad($pid);

        $req = [];
        $dep = [];
        foreach ($rels as $r) {
            $req[$r['suc']] = ($req[$r['suc']] ?? 0) + 1;
            $dep[$r['pred']] = ($dep[$r['pred']] ?? 0) + 1;
        }

        $lista = [];
        foreach ($mats as $id => $m) {
            $c = $calc['materiales'][$id] ?? [];
            $usado = !empty($acts[$id]) || ($req[$id] ?? 0) > 0 || ($dep[$id] ?? 0) > 0;
            $lista[] = [
                'id' => $id,
                'nombre' => $m['nombre'], 'referencia' => $m['referencia'], 'especialidad' => $m['especialidad'],
                'imagen' => $m['imagen'], 'unidad' => $m['unidad'], 'cantidad' => $m['cantidad'],
                'compra' => $m['compra'], 'entrega_real' => $m['fecha_entrega_real'], 'entrega_prog' => $m['fecha_entrega_prog'],
                'compra_prog' => $m['fecha_compra_prog'],
                'actividades' => array_map(fn($a) => [
                    'id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'],
                    'partida' => $a['partida_codigo'] . ' · ' . $a['partida'], 'rol' => $a['rol'] ?: 'instala', 'progreso' => (int)$a['progreso'],
                ], $acts[$id] ?? []),
                'requiere' => $req[$id] ?? 0, 'requerido_por' => $dep[$id] ?? 0,
                'disponible' => $c['entrega_ef'] ?? null, 'instalado' => $c['instalacion_ef'] ?? null,
                'bloqueado' => $usado && (bool)($c['bloqueado'] ?? true), 'motivos' => $usado ? ($c['motivos'] ?? []) : [],
                'necesidad' => $calc['necesidad'][$id] ?? null,
            ];
        }
        usort($lista, fn($x, $y) => [$x['especialidad'], $x['nombre']] <=> [$y['especialidad'], $y['nombre']]);

        $nombres = [];
        foreach ($mats as $id => $m) $nombres[$id] = $m['nombre'];
        $relsOut = [];
        foreach ($rels as $r) {
            $relsOut[] = $r + [
                'pred_nombre' => $nombres[$r['pred']] ?? '—', 'suc_nombre' => $nombres[$r['suc']] ?? '—',
                'pred_imagen' => $mats[$r['pred']]['imagen'] ?? null, 'suc_imagen' => $mats[$r['suc']]['imagen'] ?? null,
            ];
        }

        // Actividades con materiales: piso, bloqueo y atraso
        $porActividad = [];
        foreach ($acts as $mid => $listaActs) {
            foreach ($listaActs as $a) $porActividad[(int)$a['id']][] = (int)$mid;
        }
        $actividades = [];
        $bloqueadas = 0;
        $atrasadas = 0;
        $dias = 0;
        foreach (self::filas(
            "SELECT a.id, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin, a.duracion_dias, a.progreso, a.hito, a.fecha_fija,
                    p.id AS partida_id, p.codigo AS partida_codigo, p.nombre AS partida
             FROM actividades a JOIN partidas p ON p.id = a.partida_id
             WHERE p.proyecto_id = $pid AND a.progreso < 100
             ORDER BY a.fecha_inicio ASC, a.codigo ASC"
        ) as $a) {
            $ids = $porActividad[(int)$a['id']] ?? [];
            if (!$ids) continue;
            $piso = self::pisoActividad($ids, $calc);
            if (self::instalaMaterial($ids, $calc, (int)$a['id']) && $piso['fecha'] !== null && $piso['fecha'] === $a['fecha_fin']) {
                $piso['bloqueado'] = true;
                $piso['motivos'][] = 'La actividad instala el material y el enlace de instalación no se puede resolver dentro de la misma actividad; usa «entregado» o separa la instalación';
            }
            $dif = 0;
            if (!$piso['bloqueado'] && $piso['fecha'] && $a['fecha_inicio'] && $piso['fecha'] > $a['fecha_inicio']) {
                $dif = (int)floor((strtotime($piso['fecha']) - strtotime($a['fecha_inicio'])) / 86400);
                if ($dif > 0) { $atrasadas++; $dias += $dif; }
            }
            if ($piso['bloqueado']) $bloqueadas++;
            $actividades[] = [
                'id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'],
                'duracion' => (int)$a['duracion_dias'], 'progreso' => (int)$a['progreso'], 'hito' => (int)$a['hito'], 'fija' => (int)$a['fecha_fija'],
                'partida_id' => (int)$a['partida_id'], 'partida' => $a['partida_codigo'] . ' · ' . $a['partida'],
                'piso' => $piso['fecha'], 'bloqueado' => $piso['bloqueado'], 'motivos' => $piso['motivos'], 'dias' => $dif,
            ];
        }

        $ultimo = null;
        try {
            $q = self::db()->query("SELECT creado FROM material_dependencias_cambios WHERE proyecto_id = $pid ORDER BY id DESC LIMIT 1");
            $f = $q ? $q->fetch_row() : null;
            $ultimo = $f ? $f[0] : null;
        } catch (\Throwable $e) {
        }

        $bloqueadosMat = 0;
        foreach ($lista as $m) if ($m['bloqueado']) $bloqueadosMat++;

        return [
            'modo' => $proyecto->modo_dependencias_materiales ?? 'apagado',
            'materiales' => $lista, 'relaciones' => $relsOut, 'actividades' => $actividades,
            'ciclo' => $calc['ciclo'],
            'resumen' => [
                'relaciones' => count($rels), 'materiales' => count($lista), 'materiales_bloqueados' => $bloqueadosMat,
                'actividades_bloqueadas' => $bloqueadas, 'actividades_atrasadas' => $atrasadas, 'dias' => $dias,
            ],
            'ultimaAplicacion' => $ultimo,
        ];
    }

    // ==================== relaciones ====================

    private static function alcanza(int $desde, int $hasta, array $rels): bool
    {
        $adj = [];
        foreach ($rels as $r) $adj[$r['pred']][] = $r['suc'];
        $visto = [];
        $pila = [$desde];
        while ($pila) {
            $x = array_pop($pila);
            if ($x === $hasta) return true;
            if (isset($visto[$x])) continue;
            $visto[$x] = true;
            foreach ($adj[$x] ?? [] as $y) $pila[] = $y;
        }
        return false;
    }

    /** Crea o actualiza una relación. Devuelve [ok, mensaje] */
    public static function guardar(int $proyectoId, int $pred, int $suc, string $requisito, int $retraso, string $nota = ''): array
    {
        $mats = self::materiales($proyectoId);
        if (!isset($mats[$pred], $mats[$suc])) return [false, 'Los materiales deben ser de este proyecto'];
        if ($pred === $suc) return [false, 'Un material no puede depender de sí mismo'];
        if (!isset(self::REQUISITOS[$requisito])) return [false, 'El requisito no es válido'];
        $retraso = max(0, min(365, $retraso));
        $nota = mb_substr(trim($nota), 0, 255);
        $rels = self::relaciones($proyectoId);
        $existe = null;
        foreach ($rels as $r) if ($r['pred'] === $pred && $r['suc'] === $suc) $existe = $r;
        if (!$existe && self::alcanza($suc, $pred, $rels)) return [false, 'Esa relación crearía un ciclo (A requiere B y B requiere A, directa o indirectamente)'];

        $md = $existe ? MaterialDependencia::find((int)$existe['id']) : new MaterialDependencia();
        if (!$md) return [false, 'No se encontró la relación'];
        $md->proyecto_id = $proyectoId;
        $md->predecesor_id = $pred;
        $md->sucesor_id = $suc;
        $md->requisito = $requisito;
        $md->retraso = $retraso;
        $md->nota = $nota;
        $alertas = $md->validar();
        if ($alertas) return [false, implode('. ', $alertas['error'] ?? ['Datos no válidos'])];
        $r = $md->guardar();
        if (is_array($r) && empty($r['resultado'])) return [false, 'No se pudo guardar la relación'];
        return [true, ''];
    }

    public static function eliminar(int $proyectoId, int $id): void
    {
        $md = MaterialDependencia::find($id);
        if ($md && (int)$md->proyecto_id === $proyectoId) $md->eliminar();
    }

    public static function cambiarModo(Proyecto $proyecto, string $modo): bool
    {
        if (!isset(self::MODOS[$modo])) return false;
        $proyecto->modo_dependencias_materiales = $modo;
        $proyecto->guardar();
        return true;
    }

    // ==================== reprogramación ====================

    /** Qué movería la reprogramación: ['cambios' => [...], 'bloqueadas' => [...]] */
    public static function previa(Proyecto $proyecto): array
    {
        $pid = (int)$proyecto->id;
        $calc = self::disponibilidad($pid);
        $cal = new Calendario($pid);
        $rc = new RutaCritica($cal);
        $acts = self::actividadesPorMaterial($pid);
        $porActividad = [];
        foreach ($acts as $mid => $lista) foreach ($lista as $a) $porActividad[(int)$a['id']][] = (int)$mid;

        $cambios = [];
        $bloqueadas = [];
        foreach (self::filas(
            "SELECT a.id, a.codigo, a.nombre, a.fecha_inicio, a.fecha_fin, a.duracion_dias, a.progreso, a.hito, a.fecha_fija
             FROM actividades a JOIN partidas p ON p.id = a.partida_id
             WHERE p.proyecto_id = $pid AND a.progreso = 0 AND a.fecha_fija = 0 AND a.hito = 0
             ORDER BY a.fecha_inicio ASC, a.codigo ASC"
        ) as $a) {
            $ids = $porActividad[(int)$a['id']] ?? [];
            if (!$ids) continue;
            $piso = self::pisoActividad($ids, $calc);
            if (self::instalaMaterial($ids, $calc, (int)$a['id']) && $piso['fecha'] !== null && $piso['fecha'] === $a['fecha_fin']) {
                $bloqueadas[] = ['id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'inicio' => $a['fecha_inicio'],
                    'motivos' => ['La actividad instala el material y el enlace de instalación no se puede resolver dentro de la misma actividad; cambia el requisito a «entregado» o separa la instalación']];
                continue;
            }
            if ($piso['bloqueado']) {
                $bloqueadas[] = ['id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'inicio' => $a['fecha_inicio'], 'motivos' => $piso['motivos']];
                continue;
            }
            if (!$piso['fecha'] || $piso['fecha'] <= $a['fecha_inicio']) continue;
            $nuevoInicio = $cal->esHabil($piso['fecha']) ? $piso['fecha'] : $rc->sig($piso['fecha']);
            $nuevoFin = $cal->calcularFechaFin($nuevoInicio, max(1, (int)$a['duracion_dias']));
            $cambios[] = [
                'id' => (int)$a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'],
                'inicio' => $a['fecha_inicio'], 'fin' => $a['fecha_fin'], 'nuevoInicio' => $nuevoInicio, 'nuevoFin' => $nuevoFin,
                'dias' => $rc->difHab($a['fecha_inicio'], $nuevoInicio), 'motivos' => $piso['motivos'],
            ];
        }
        usort($cambios, fn($x, $y) => [$x['nuevoInicio'], $x['id']] <=> [$y['nuevoInicio'], $y['id']]);
        return ['cambios' => $cambios, 'bloqueadas' => $bloqueadas];
    }

    /** Aplica la reprogramación y guarda la foto para deshacer. Devuelve cuántas actividades movió */
    public static function aplicar(Proyecto $proyecto, bool $guardarSnapshot = true): int
    {
        $prev = self::previa($proyecto);
        $cambios = $prev['cambios'];
        if (!$cambios) return 0;
        $db = self::db();
        if ($guardarSnapshot) {
            // Foto de TODAS las actividades: la propagación de dependencias de obra también puede moverlas
            $foto = self::filas("SELECT a.id, a.fecha_inicio AS inicio, a.fecha_fin AS fin FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = " . (int)$proyecto->id);
            $db->query("INSERT INTO material_dependencias_cambios (proyecto_id, cambios_json) VALUES (" . (int)$proyecto->id . ", '" . $db->real_escape_string(json_encode($foto)) . "')");
        }
        self::escribir($proyecto, array_map(fn($c) => ['id' => $c['id'], 'inicio' => $c['nuevoInicio'], 'fin' => $c['nuevoFin']], $cambios));
        return count($cambios);
    }

    /** Escribe fechas en las actividades y refresca partidas, MSR y dependencias de obra */
    private static function escribir(Proyecto $proyecto, array $fechas, bool $propagar = true): void
    {
        $partidas = [];
        foreach ($fechas as $f) {
            $a = Actividad::find($f['id']);
            if (!$a) continue;
            $a->fecha_inicio = $f['inicio'];
            $a->fecha_fin = $f['fin'];
            $a->guardar();
            $partidas[(int)$a->partida_id] = true;
        }
        foreach (array_keys($partidas) as $pid) {
            if ($p = Partida::find($pid)) $p->recalcularFechas();
        }
        try {
            ActividadMaterial::sincronizarInstalacion((int)$proyecto->id);
        } catch (\Throwable $e) {
        }
        if ($propagar) {
            try {
                Dependencias::propagar($proyecto);   // las relaciones de obra siguen mandando
            } catch (\Throwable $e) {
            }
        }
    }

    /** Restaura las fechas anteriores a la última reprogramación. Devuelve cuántas actividades restauró */
    public static function deshacer(Proyecto $proyecto): int
    {
        $db = self::db();
        $pid = (int)$proyecto->id;
        try {
            $q = $db->query("SELECT id, cambios_json FROM material_dependencias_cambios WHERE proyecto_id = $pid ORDER BY id DESC LIMIT 1");
            $f = $q ? $q->fetch_assoc() : null;
        } catch (\Throwable $e) {
            return 0;
        }
        if (!$f) return 0;
        $prev = json_decode($f['cambios_json'], true) ?: [];
        self::escribir($proyecto, $prev, false);   // restaura exactamente la foto, sin volver a propagar
        $db->query('DELETE FROM material_dependencias_cambios WHERE id = ' . (int)$f['id']);
        return count($prev);
    }

    /** En modo automático empuja las actividades tras un cambio de material o de fechas. Devuelve cuántas movió */
    public static function propagar(Proyecto $proyecto): int
    {
        if (($proyecto->modo_dependencias_materiales ?? 'apagado') !== 'automatico') return 0;
        try {
            return self::aplicar($proyecto, false);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ==================== sugerencias ====================

    /**
     * Sugerencias para el panel:
     * - relaciones entre actividades que ya existen (material A → material B);
     * - actividades nuevas para materiales que todavía no tienen ninguna.
     */
    public static function sugerencias(Proyecto $proyecto): array
    {
        $pid = (int)$proyecto->id;
        $mats = self::materiales($pid);
        $acts = self::actividadesPorMaterial($pid);
        $rels = self::relaciones($pid);
        $existentes = [];
        foreach (Dependencias::relaciones($pid) as $r) $existentes[$r['pred'] . '-' . $r['suc']] = true;

        $relSug = [];
        $actSug = [];
        $vistos = [];
        $partidas = [];
        foreach (self::filas("SELECT id, codigo, nombre FROM partidas WHERE proyecto_id = $pid ORDER BY orden ASC, codigo ASC") as $p) $partidas[] = $p;

        $instala = function (int $mid) use ($acts): array {
            $lista = $acts[$mid] ?? [];
            $ok = array_values(array_filter($lista, fn($a) => ($a['rol'] ?? 'instala') === 'instala'));
            return $ok ?: $lista;
        };

        foreach ($rels as $r) {
            $aA = $instala($r['pred']);
            $aB = $instala($r['suc']);
            $nombreA = $mats[$r['pred']]['nombre'] ?? 'material';
            $nombreB = $mats[$r['suc']]['nombre'] ?? 'material';
            if ($aA && $aB) {
                // La última actividad que instala A debe terminar antes de la primera que instala B
                usort($aA, fn($x, $y) => [$x['fecha_fin'], $x['id']] <=> [$y['fecha_fin'], $y['id']]);
                usort($aB, fn($x, $y) => [$x['fecha_inicio'], $x['id']] <=> [$y['fecha_inicio'], $y['id']]);
                $pred = end($aA);
                foreach (array_slice($aB, 0, 3) as $suc) {
                    $k = $pred['id'] . '-' . $suc['id'];
                    if ($pred['id'] === $suc['id'] || isset($existentes[$k], $vistos[$k])) continue;
                    $vistos[$k] = true;
                    $relSug[] = [
                        'pred' => (int)$pred['id'], 'suc' => (int)$suc['id'],
                        'pred_codigo' => $pred['codigo'], 'pred_nombre' => $pred['nombre'], 'suc_codigo' => $suc['codigo'], 'suc_nombre' => $suc['nombre'],
                        'motivo' => $nombreB . ' requiere ' . $nombreA,
                    ];
                }
            }
            if (!$aB) {
                // Todas las actividades que instalan los prerrequisitos de este material (puede haber varias)
                $todasA = [];
                foreach ($rels as $rr) {
                    if ($rr['suc'] !== $r['suc']) continue;
                    foreach ($instala($rr['pred']) as $a) $todasA[] = $a;
                }
                usort($todasA, fn($x, $y) => [$x['fecha_fin'], $x['id']] <=> [$y['fecha_fin'], $y['id']]);
                $base = $todasA ? end($todasA) : null;
                $predIds = array_values(array_unique(array_map(fn($a) => (int)$a['id'], $todasA)));
                $predMats = [];
                foreach ($rels as $rr) if ($rr['suc'] === $r['suc']) $predMats[] = (int)$rr['pred'];
                $predMats = array_values(array_unique($predMats));
                $partida = $base ? (int)$base['partida_id'] : (int)($partidas[0]['id'] ?? 0);
                $k = 'nuevo-' . $r['suc'];
                if (!isset($vistos[$k]) && $partida) {
                    $vistos[$k] = true;
                    $actSug[] = [
                        'material_id' => (int)$r['suc'], 'material' => $nombreB, 'especialidad' => $mats[$r['suc']]['especialidad'] ?? '',
                        'nombre' => 'Suministro e instalación de ' . mb_strtolower((string)$nombreB),
                        'partida_id' => $partida, 'partida' => self::nombrePartida($partidas, $partida),
                        'duracion_dias' => 1, 'fecha_inicio' => $base ? self::diaSiguiente((string)$base['fecha_fin'], $pid) : null,
                        'pred_ids' => $predIds, 'pred_materiales' => $predMats, 'sucesoras_ids' => [],
                        'motivo' => ($predMats ? 'El material no tiene actividad; se propone después de ' . count($predMats) . ' material(es) prerrequisito' : 'El material no tiene actividad'),
                    ];
                }
            }
        }

        // Materiales sin ninguna actividad que participen en una dependencia (o todos si no hay relaciones)
        $sinActividad = [];
        foreach ($mats as $id => $m) {
            if (!empty($acts[$id])) continue;
            $participa = false;
            foreach ($rels as $r) if ($r['pred'] === $id || $r['suc'] === $id) { $participa = true; break; }
            if (!$participa) continue;   // solo se sugieren materiales que forman parte de una cadena
            if (!$partidas) continue;
            $k = 'nuevo-' . $id;
            if (isset($vistos[$k])) continue;
            $vistos[$k] = true;
            $predMats = [];
            $predIds = [];
            $sucesoras = [];
            $partidaSug = (int)($partidas[0]['id'] ?? 0);
            foreach ($rels as $rr) {
                if ($rr['suc'] === $id) {
                    $predMats[] = (int)$rr['pred'];
                    foreach ($instala($rr['pred']) as $a) $predIds[] = (int)$a['id'];
                }
                if ($rr['pred'] === $id) {
                    foreach ($instala($rr['suc']) as $a) {
                        $sucesoras[] = (int)$a['id'];
                        $partidaSug = (int)$a['partida_id'];   // misma partida que la actividad que lo necesita
                    }
                }
            }
            $sinActividad[] = [
                'material_id' => $id, 'material' => $m['nombre'], 'especialidad' => $m['especialidad'],
                'nombre' => 'Suministro e instalación de ' . mb_strtolower((string)$m['nombre']),
                'partida_id' => $partidaSug, 'partida' => self::nombrePartida($partidas, $partidaSug),
                'duracion_dias' => 1, 'fecha_inicio' => null, 'pred_ids' => array_values(array_unique($predIds)),
                'pred_materiales' => array_values(array_unique($predMats)), 'sucesoras_ids' => array_values(array_unique($sucesoras)),
                'motivo' => $sucesoras ? 'Material sin actividad; se propone antes de ' . count(array_unique($sucesoras)) . ' actividad(es) que lo requieren' : 'Material del catálogo sin actividad',
            ];
        }
        $actSug = array_merge($actSug, array_slice($sinActividad, 0, 60));

        // Orden topológico por material: primero los que no dependen de otro material nuevo
        $porMat = [];
        foreach ($actSug as $i => $a) $porMat[(int)$a['material_id']] = $i;
        $grado = array_fill(0, count($actSug), 0);
        $adj = [];
        foreach ($actSug as $i => $a) {
            foreach ($a['pred_materiales'] ?? [] as $pm) {
                $j = $porMat[(int)$pm] ?? null;
                if ($j !== null && $j !== $i) { $adj[$j][] = $i; $grado[$i]++; }
            }
        }
        $cola = [];
        foreach ($grado as $i => $g) if ($g === 0) $cola[] = $i;
        $orden = [];
        while ($cola) {
            $i = array_shift($cola);
            $orden[] = $i;
            foreach ($adj[$i] ?? [] as $j) if (--$grado[$j] === 0) $cola[] = $j;
        }
        if (count($orden) === count($actSug)) $actSug = array_map(fn($i) => $actSug[$i], $orden);

        return [
            'relaciones' => $relSug,
            'actividades' => $actSug,
            'partidas' => $partidas,
            'resumen' => ['relaciones' => count($relSug), 'actividades' => count($actSug)],
        ];
    }

    /** Aplica las sugerencias aceptadas. Devuelve totales y errores */
    public static function aplicarSugerencias(Proyecto $proyecto, array $relaciones, array $actividades): array
    {
        $pid = (int)$proyecto->id;
        $mats = self::materiales($pid);
        $partidas = [];
        foreach (self::filas("SELECT id, codigo FROM partidas WHERE proyecto_id = $pid") as $p) $partidas[(int)$p['id']] = $p;
        $cal = new Calendario($pid);
        $rc = new RutaCritica($cal);
        $nRel = 0;
        $nAct = 0;
        $errores = [];
        $tocadas = [];
        $creadas = [];
        $vistas = [];

        $crearRel = function (int $pred, int $suc) use (&$nRel, &$vistas, &$errores, $pid): void {
            if ($pred <= 0 || $suc <= 0 || $pred === $suc) return;
            $k = $pred . '-' . $suc;
            if (isset($vistas[$k])) return;
            [$ok, $msg] = Dependencias::guardar($pid, $pred, $suc, 'FS', 0);
            if ($ok) { $vistas[$k] = true; $nRel++; } else $errores[] = $msg;
        };

        foreach ($relaciones as $r) $crearRel((int)($r['pred'] ?? 0), (int)($r['suc'] ?? 0));

        // 1) Crear las actividades nuevas (sin relaciones todavía: algunas se refieren a otras nuevas)
        foreach ($actividades as $a) {
            $mid = (int)($a['material_id'] ?? 0);
            $partidaId = (int)($a['partida_id'] ?? 0);
            if (!isset($mats[$mid]) || !isset($partidas[$partidaId])) { $errores[] = 'Material o partida no válidos'; continue; }
            $nombre = trim((string)($a['nombre'] ?? '')) ?: 'Suministro e instalación de ' . mb_strtolower((string)$mats[$mid]['nombre']);
            $dur = max(1, min(3650, (int)($a['duracion_dias'] ?? 1)));
            $explicita = self::fecha($a['fecha_inicio'] ?? null);
            $inicio = $explicita ?: ($proyecto->fecha_inicio ?: date('Y-m-d'));
            if (!$cal->esHabil($inicio)) $inicio = $rc->sig($inicio);
            $existentes = self::filas("SELECT codigo, orden FROM actividades WHERE partida_id = $partidaId");
            $usados = array_fill_keys(array_column($existentes, 'codigo'), true);
            $prefijo = preg_replace('/\.0+$/', '', (string)$partidas[$partidaId]['codigo']);
            $n = count($existentes) + 1;
            while (isset($usados[$prefijo . '.' . $n])) $n++;
            $orden = 1;
            foreach ($existentes as $e) $orden = max($orden, (int)$e['orden'] + 1);

            $act = new Actividad([
                'partida_id' => $partidaId, 'codigo' => $prefijo . '.' . $n, 'nombre' => mb_substr($nombre, 0, 200),
                'fecha_inicio' => $inicio, 'duracion_dias' => $dur, 'orden' => $orden, 'progreso' => 0,
            ]);
            $act->fecha_fin = $cal->calcularFechaFin($inicio, $dur);
            $act->sincronizarStatus();
            $res = $act->guardar();
            if (!$res || empty($res['resultado'])) { $errores[] = 'No se pudo crear «' . $nombre . '»'; continue; }
            $nuevoId = (int)$res['id'];
            ActividadMaterial::guardarDeActividad($nuevoId, $pid, [$mid], [$mid => 'instala']);
            $creadas[$mid] = [
                'id' => $nuevoId, 'partida_id' => $partidaId, 'nombre' => $nombre, 'explicita' => (bool)$explicita,
                'pred_ids' => array_values(array_filter(array_map('intval', (array)($a['pred_ids'] ?? [])))),
                'pred_materiales' => array_values(array_filter(array_map('intval', (array)($a['pred_materiales'] ?? [])))),
                'sucesoras_ids' => array_values(array_filter(array_map('intval', (array)($a['sucesoras_ids'] ?? [])))),
            ];
            $tocadas[$partidaId] = true;
            $nAct++;
        }

        // 2) Relaciones: actividades existentes y cadenas entre actividades nuevas
        foreach ($creadas as $mid => $c) {
            foreach ($c['pred_ids'] as $predId) $crearRel($predId, $c['id']);
            foreach ($c['pred_materiales'] as $pm) {
                if (isset($creadas[$pm]) && $creadas[$pm]['id'] !== $c['id']) $crearRel($creadas[$pm]['id'], $c['id']);
            }
            foreach ($c['sucesoras_ids'] as $sucId) $crearRel($c['id'], $sucId);
        }

        // 3) Las actividades nuevas sin fecha explícita arrancan después de sus predecesoras
        foreach ($creadas as $c) {
            if ($c['explicita']) continue;
            $finMax = null;
            foreach (self::filas("SELECT a.fecha_fin FROM actividad_dependencias d JOIN actividades a ON a.id = d.predecesora_id WHERE d.sucesora_id = " . $c['id']) as $f) {
                if ($f['fecha_fin'] && (!$finMax || $f['fecha_fin'] > $finMax)) $finMax = $f['fecha_fin'];
            }
            if (!$finMax) continue;
            $act = Actividad::find($c['id']);
            if (!$act) continue;
            $nuevoInicio = self::diaSiguiente($finMax, $pid, $rc);
            if ($nuevoInicio <= $act->fecha_inicio) continue;
            $act->fecha_inicio = $nuevoInicio;
            $act->fecha_fin = $cal->calcularFechaFin($nuevoInicio, max(1, (int)$act->duracion_dias));
            $act->guardar();
        }

        // 4) Las actividades existentes que reciben un prerrequisito nuevo se mueven después de él
        $sucesoras = [];
        foreach ($creadas as $c) foreach ($c['sucesoras_ids'] as $sid) $sucesoras[$sid] = true;
        foreach (array_keys($sucesoras) as $sid) {
            $act = Actividad::find($sid);
            if (!$act || (int)$act->progreso > 0 || (int)$act->fecha_fija || (int)$act->hito) continue;
            $finMax = null;
            foreach (self::filas("SELECT a.fecha_fin FROM actividad_dependencias d JOIN actividades a ON a.id = d.predecesora_id WHERE d.sucesora_id = $sid") as $f) {
                if ($f['fecha_fin'] && (!$finMax || $f['fecha_fin'] > $finMax)) $finMax = $f['fecha_fin'];
            }
            if (!$finMax) continue;
            $nuevoInicio = self::diaSiguiente($finMax, $pid, $rc);
            if ($nuevoInicio <= $act->fecha_inicio) continue;
            $act->fecha_inicio = $nuevoInicio;
            $act->fecha_fin = $cal->calcularFechaFin($nuevoInicio, max(1, (int)$act->duracion_dias));
            $act->guardar();
            $tocadas[(int)$act->partida_id] = true;
        }

        foreach (array_keys($tocadas) as $pId) {
            if ($p = Partida::find($pId)) $p->recalcularFechas();
        }
        ActividadMaterial::sincronizarInstalacion($pid);
        Dependencias::propagar($proyecto);
        self::propagar($proyecto);
        return ['actividades' => $nAct, 'relaciones' => $nRel, 'errores' => array_values(array_unique($errores))];
    }

    private static function nombrePartida(array $partidas, int $id): string
    {
        foreach ($partidas as $p) if ((int)$p['id'] === $id) return $p['codigo'] . ' · ' . $p['nombre'];
        return '';
    }

    private static function diaSiguiente(string $fecha, int $proyectoId, ?RutaCritica $rc = null): string
    {
        $cal = new Calendario($proyectoId);
        $rc ??= new RutaCritica($cal);
        return $rc->sig(date('Y-m-d', strtotime($fecha . ' +1 day')));
    }

    private static function fecha($v): ?string
    {
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($v, 0, 10))) return null;
        return substr($v, 0, 10);
    }
}
