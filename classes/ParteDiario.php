<?php

namespace Classes;

use Model\Personal;
use Model\Proyecto;
use Model\ReporteDiario;
use Model\ReporteEquipo;
use Model\ReporteFoto;
use Model\ReporteMaterial;
use Model\ReportePersonal;
use Model\ReporteSeguridad;
use Model\ReporteTiempo;

/**
 * Parte diario único de campo: reúne en un solo registro el reporte del día,
 * las horas del personal, el equipo, los materiales, los tiempos muertos,
 * la seguridad, las fotos y la firma; y al cerrarlo actualiza el avance,
 * las horas de equipo, la bitácora y el PDF.
 */
class ParteDiario
{
    use AccesoDb;

    /** Avisos del último guardado (avance recortado por calidad, por ejemplo) */
    private static array $avisos = [];

    private const MAX_FOTOS = 30;
    private const MAX_FOTO_BYTES = 15 * 1024 * 1024;
    private const MAX_FIRMA_BYTES = 2 * 1024 * 1024;

    // ---------------------------------------------------------------- catálogos

    /** Datos que necesita el formulario para armarse */
    public static function catalogos(Proyecto $proyecto): array
    {
        $pid = (int)$proyecto->id;
        $personal = [];
        foreach (Personal::deProyecto($pid) as $p) {
            $personal[] = ['id' => (int)$p->id, 'nombre' => $p->nombre, 'departamento' => $p->departamento, 'tarifa_hora' => (float)$p->tarifa_hora];
        }
        $equipo = [];
        foreach (Equipo::lista($pid) as $e) {
            $equipo[] = ['id' => (int)$e['id'], 'nombre' => $e['nombre'], 'tipo' => $e['tipo'], 'tarifa_hora' => (float)$e['tarifa_hora'], 'estado' => $e['estado']];
        }
        $materiales = [];
        foreach (self::filas("SELECT id, nombre, unidad, costo_unitario FROM materiales WHERE proyecto_id = $pid ORDER BY nombre ASC") as $m) {
            $materiales[] = ['id' => (int)$m['id'], 'nombre' => $m['nombre'], 'unidad' => $m['unidad'], 'costo_unitario' => (float)($m['costo_unitario'] ?? 0)];
        }
        $actividades = [];
        $bloqueosCalidad = \Classes\Calidad::disponible() ? \Classes\Calidad::bloqueosProyecto($pid) : [];
        foreach ($proyecto->getPartidasConActividades() as $bloque) {
            foreach ($bloque['actividades'] as $a) {
                $bloq = $bloqueosCalidad[(int)$a->id] ?? null;
                if ($bloq) $bloq['tope'] = \Classes\Calidad::tope($bloq, (int)$a->progreso);
                $actividades[] = [
                    'id' => (int)$a->id,
                    'codigo' => $a->codigo,
                    'nombre' => $a->nombre,
                    'partida' => $bloque['partida']->codigo . ' ' . $bloque['partida']->nombre,
                    'color' => $bloque['partida']->color_bg,
                    'hito' => (int)$a->esHito(),
                    'progreso' => (int)$a->progreso,
                    'subactividades' => (int)$a->tieneSubactividades(),
                    'bloqueo' => $bloq,
                ];
            }
        }
        return [
            'personal' => $personal,
            'equipo' => $equipo,
            'materiales' => $materiales,
            'actividades' => $actividades,
            'clima' => self::climaDelDia($pid, date('Y-m-d')),
            'causas_tiempo' => ReporteTiempo::CAUSAS,
            'tipos_material' => ReporteMaterial::TIPOS,
        ];
    }

    /** Clima real (o pronóstico) guardado por ClimaAuto para una fecha */
    public static function climaDelDia(int $pid, string $fecha): array
    {
        $f = self::esc($fecha);
        $r = self::filas("SELECT precip_mm, viento_kmh, tmax, tmin, tipo FROM clima_dias WHERE proyecto_id = $pid AND fecha = '$f' LIMIT 1");
        if (!$r) return ['precip_mm' => null, 'viento_kmh' => null, 'tmax' => null, 'tmin' => null, 'tipo' => ''];
        return [
            'precip_mm' => (float)$r[0]['precip_mm'],
            'viento_kmh' => (float)$r[0]['viento_kmh'],
            'tmax' => $r[0]['tmax'] !== null ? (float)$r[0]['tmax'] : null,
            'tmin' => $r[0]['tmin'] !== null ? (float)$r[0]['tmin'] : null,
            'tipo' => $r[0]['tipo'],
        ];
    }

    // ---------------------------------------------------------------- lectura

    /** Todo lo que compone un parte, con compatibilidad para los reportes viejos */
    public static function detalle(Proyecto $proyecto, int $id): ?array
    {
        $pid = (int)$proyecto->id;
        $r = ReporteDiario::find($id);
        if (!$r || (int)$r->proyecto_id !== $pid) return null;

        $acts = [];
        foreach ($proyecto->getPartidasConActividades() as $bloque) {
            foreach ($bloque['actividades'] as $a) $acts[(int)$a->id] = [$a, $bloque['partida']];
        }

        $personal = ReportePersonal::deReporte($id);
        if (!$personal && $r->getPersonal()) {   // reporte viejo: solo tenía ids
            $cat = Personal::indexado($pid);
            foreach ($r->getPersonal() as $pidPersona) {
                if (!isset($cat[$pidPersona])) continue;
                $p = new ReportePersonal();
                $p->reporte_id = $id;
                $p->personal_id = $pidPersona;
                $p->nombre = $cat[$pidPersona]->nombre;
                $p->horas_normal = 0;
                $p->horas_extra = 0;
                $p->cuadrilla = '';
                $p->observacion = '';
                $personal[] = $p;
            }
        }

        $fotos = ReporteFoto::deReporte($id);
        if (!$fotos && $r->getFotos()) {   // reporte viejo: solo tenía nombres de archivo
            foreach ($r->getFotos() as $f) {
                $foto = new ReporteFoto();
                $foto->reporte_id = $id;
                $foto->archivo = $f;
                $foto->leyenda = '';
                $foto->actividad_id = null;
                $fotos[] = $foto;
            }
        }

        return [
            'reporte' => $r,
            'actividades' => array_values(array_filter(array_map(fn($idA) => $acts[$idA] ?? null, $r->getActividades()))),
            'personal' => $personal,
            'equipo' => ReporteEquipo::deReporte($id),
            'materiales' => ReporteMaterial::deReporte($id),
            'tiempos' => ReporteTiempo::deReporte($id),
            'seguridad' => ReporteSeguridad::deReporte($id),
            'fotos' => $fotos,
            'costos' => self::costos($id),
        ];
    }

    public static function costos(int $id): array
    {
        $personal = ReportePersonal::costo($id);
        $equipo = ReporteEquipo::costo($id);
        $material = ReporteMaterial::costoConsumido($id);
        $h = self::filas("SELECT COALESCE(SUM(horas_normal),0) n, COALESCE(SUM(horas_extra),0) e FROM reporte_personal WHERE reporte_id = $id")[0] ?? [];
        return [
            'personal' => round($personal, 2),
            'equipo' => round($equipo, 2),
            'material' => round($material, 2),
            'total' => round($personal + $equipo + $material, 2),
            'horas_personal' => round((float)($h['n'] ?? 0) + (float)($h['e'] ?? 0), 2),
            'horas_equipo' => round((float)(self::filas("SELECT COALESCE(SUM(horas),0) h FROM reporte_equipo WHERE reporte_id = $id")[0]['h'] ?? 0), 2),
            'horas_muertas' => ReporteTiempo::totalHoras($id),
        ];
    }

    /**
     * Arma todo lo que pinta la pantalla. Si llega `$post` (formulario rechazado),
     * se usa lo capturado para no perder el trabajo del residente.
     */
    public static function vista(Proyecto $proyecto, int $id = 0, ?array $post = null): array
    {
        $cat = self::catalogos($proyecto);
        $actsSel = [];
        $base = [
            'id' => $id, 'fecha' => date('Y-m-d'), 'area' => '', 'turno' => 'dia', 'supervisor' => '',
            'comentarios' => '', 'estado' => 'borrador', 'visitas' => '', 'firma_nombre' => '',
            'firma_imagen' => null, 'clima_precip_mm' => $cat['clima']['precip_mm'],
            'clima_viento_kmh' => $cat['clima']['viento_kmh'], 'clima_tmax' => $cat['clima']['tmax'],
            'clima_tmin' => $cat['clima']['tmin'], 'offline_uuid' => '',
        ];
        $personal = $equipo = $materiales = $tiempos = $fotos = [];
        $seguridad = ['charla_tema' => '', 'asistentes' => 0, 'epp_ok' => 0, 'incidente_descripcion' => '', 'incidente_tipo' => 'incidente', 'incidente_severidad' => 'media', 'incidente_actividad' => 0];

        if ($post !== null) {
            foreach ($base as $k => $v) if (array_key_exists($k, $post)) $base[$k] = $post[$k];
            $actsSel = array_map('intval', (array)($post['actividades'] ?? []));
            foreach ((array)($post['personal_id'] ?? []) as $i => $pidP) {
                if (!(int)$pidP) continue;
                $personal[] = [
                    'personal_id' => (int)$pidP, 'nombre' => '', 'horas_normal' => (float)($post['personal_horas'][$i] ?? 0),
                    'horas_extra' => (float)($post['personal_extra'][$i] ?? 0), 'actividad_id' => (int)($post['personal_actividad'][$i] ?? 0),
                    'cuadrilla' => (string)($post['personal_cuadrilla'][$i] ?? ''), 'observacion' => (string)($post['personal_observacion'][$i] ?? ''),
                ];
            }
            foreach ((array)($post['equipo_id'] ?? []) as $i => $eid) {
                if (!(int)$eid) continue;
                $equipo[] = ['equipo_id' => (int)$eid, 'nombre' => '', 'horas' => (float)($post['equipo_horas'][$i] ?? 0),
                    'actividad_id' => (int)($post['equipo_actividad'][$i] ?? 0), 'operador' => (string)($post['equipo_operador'][$i] ?? ''), 'importe' => 0];
            }
            foreach ((array)($post['material_id'] ?? []) as $i => $mid) {
                if (!(int)$mid) continue;
                $materiales[] = ['material_id' => (int)$mid, 'nombre' => '', 'cantidad' => (float)($post['material_cantidad'][$i] ?? 0),
                    'unidad' => (string)($post['material_unidad'][$i] ?? ''), 'tipo' => (string)($post['material_tipo'][$i] ?? 'consumido'),
                    'actividad_id' => (int)($post['material_actividad'][$i] ?? 0), 'proveedor' => (string)($post['material_proveedor'][$i] ?? ''),
                    'remision' => (string)($post['material_remision'][$i] ?? '')];
            }
            foreach ((array)($post['tiempo_horas'] ?? []) as $i => $th) {
                if ((float)$th <= 0) continue;
                $tiempos[] = ['horas' => (float)$th, 'causa' => (string)($post['tiempo_causa'][$i] ?? 'otra'),
                    'actividad_id' => (int)($post['tiempo_actividad'][$i] ?? 0), 'nota' => (string)($post['tiempo_nota'][$i] ?? '')];
            }
            foreach (['charla_tema', 'charla_asistentes', 'epp_ok', 'incidente_descripcion', 'incidente_tipo', 'incidente_severidad', 'incidente_actividad'] as $k) {
                if (array_key_exists($k, $post)) $seguridad[$k === 'charla_asistentes' ? 'asistentes' : ($k === 'epp_ok' ? 'epp_ok' : $k)] = $post[$k];
            }
        } elseif ($id) {
            $d = self::detalle($proyecto, $id);
            if (!$d) return [];
            $r = $d['reporte'];
            $base = [
                'id' => (int)$r->id, 'fecha' => $r->fecha, 'area' => $r->area, 'turno' => $r->turno, 'supervisor' => $r->supervisor,
                'comentarios' => (string)$r->comentarios, 'estado' => $r->estado, 'visitas' => (string)$r->visitas,
                'firma_nombre' => (string)$r->firma_nombre, 'firma_imagen' => $r->firma_imagen,
                'clima_precip_mm' => $r->clima_precip_mm, 'clima_viento_kmh' => $r->clima_viento_kmh,
                'clima_tmax' => $r->clima_tmax, 'clima_tmin' => $r->clima_tmin,
                'offline_uuid' => (string)$r->offline_uuid,
            ];
            $actsSel = $r->getActividades();
            foreach ($d['personal'] as $x) $personal[] = ['personal_id' => (int)$x->personal_id, 'nombre' => $x->nombre, 'horas_normal' => (float)$x->horas_normal, 'horas_extra' => (float)$x->horas_extra, 'actividad_id' => (int)$x->actividad_id, 'cuadrilla' => $x->cuadrilla, 'observacion' => $x->observacion];
            foreach ($d['equipo'] as $x) $equipo[] = ['equipo_id' => (int)$x->equipo_id, 'nombre' => $x->nombre, 'horas' => (float)$x->horas, 'actividad_id' => (int)$x->actividad_id, 'operador' => $x->operador, 'importe' => (float)$x->importe];
            foreach ($d['materiales'] as $x) $materiales[] = ['material_id' => (int)$x->material_id, 'nombre' => $x->nombre, 'cantidad' => (float)$x->cantidad, 'unidad' => $x->unidad, 'tipo' => $x->tipo, 'actividad_id' => (int)$x->actividad_id, 'proveedor' => $x->proveedor, 'remision' => $x->remision];
            foreach ($d['tiempos'] as $x) $tiempos[] = ['horas' => (float)$x->horas, 'causa' => $x->causa, 'actividad_id' => (int)$x->actividad_id, 'nota' => $x->nota];
            foreach ($d['fotos'] as $x) $fotos[] = ['id' => (int)$x->id, 'archivo' => $x->archivo, 'leyenda' => $x->leyenda, 'actividad_id' => (int)$x->actividad_id];
            if ($d['seguridad']) {
                $s = $d['seguridad'];
                $seguridad = ['charla_tema' => $s->charla_tema, 'asistentes' => (int)$s->asistentes, 'epp_ok' => (int)$s->epp_ok,
                    'incidente_descripcion' => '', 'incidente_tipo' => 'incidente', 'incidente_severidad' => 'media', 'incidente_actividad' => 0];
            }
        }

        // Precios de catálogo para la vista previa del costo del día
        $tarifasP = array_column($cat['personal'], 'tarifa_hora', 'id');
        $tarifasE = array_column($cat['equipo'], 'tarifa_hora', 'id');
        $costosM = array_column($cat['materiales'], 'costo_unitario', 'id');
        $costo = ['personal' => 0.0, 'equipo' => 0.0, 'material' => 0.0];
        foreach ($personal as $x) {
            $t = (float)($tarifasP[$x['personal_id']] ?? 0);
            $costo['personal'] += $x['horas_normal'] * $t + $x['horas_extra'] * $t * 1.5;
        }
        foreach ($equipo as $x) $costo['equipo'] += $x['horas'] * (float)($tarifasE[$x['equipo_id']] ?? 0);
        foreach ($materiales as $x) if ($x['tipo'] === 'consumido') $costo['material'] += $x['cantidad'] * (float)($costosM[$x['material_id']] ?? 0);
        $costo['total'] = array_sum($costo);

        $acts = [];
        foreach ($cat['actividades'] as $a) {
            $a['sel'] = in_array((int)$a['id'], $actsSel, true);
            $acts[] = $a;
        }

        return [
            'base' => $base, 'actividades' => $acts, 'personal' => $personal, 'equipo' => $equipo,
            'materiales' => $materiales, 'tiempos' => $tiempos, 'seguridad' => $seguridad,
            'fotos' => $fotos, 'costos' => $costo, 'catalogos' => $cat,
        ];
    }


    // ---------------------------------------------------------------- guardado

    /**
     * Crea o actualiza un parte. Devuelve ['ok', 'id', 'errores'].
     * Si llega un `offline_uuid` ya guardado, devuelve ese parte (idempotencia).
     */
    public static function guardar(Proyecto $proyecto, array $post, array $files = []): array
    {
        $pid = (int)$proyecto->id;
        self::$avisos = [];
        $id = (int)($post['id'] ?? 0);
        $uuid = mb_substr(trim((string)($post['offline_uuid'] ?? '')), 0, 64);

        if (!$id && $uuid !== '') {
            $previo = ReporteDiario::porOfflineUuid($pid, $uuid);
            if ($previo) return ['ok' => true, 'id' => (int)$previo->id, 'errores' => []];
        }

        $r = $id ? ReporteDiario::find($id) : new ReporteDiario();
        if ($id && (!$r || (int)$r->proyecto_id !== $pid)) return ['ok' => false, 'id' => 0, 'errores' => ['El parte no existe']];
        if ($id && $r->estaCerrado()) return ['ok' => false, 'id' => $id, 'errores' => ['El parte está cerrado. Reábrelo para editarlo.']];

        $r->proyecto_id = $pid;
        $r->fecha = trim((string)($post['fecha'] ?? ''));
        $r->area = mb_substr(trim((string)($post['area'] ?? '')), 0, 150);
        $r->turno = isset(ReporteDiario::TURNOS[$post['turno'] ?? '']) ? $post['turno'] : 'dia';
        $r->supervisor = mb_substr(trim((string)($post['supervisor'] ?? '')), 0, 150);
        $r->comentarios = trim((string)($post['comentarios'] ?? ''));
        $r->visitas = mb_substr(trim((string)($post['visitas'] ?? '')), 0, 500);
        $r->clima_precip_mm = self::numeroONull($post['clima_precip_mm'] ?? null);
        $r->clima_viento_kmh = self::numeroONull($post['clima_viento_kmh'] ?? null);
        $r->clima_tmax = self::numeroONull($post['clima_tmax'] ?? null);
        $r->clima_tmin = self::numeroONull($post['clima_tmin'] ?? null);
        $r->firma_nombre = mb_substr(trim((string)($post['firma_nombre'] ?? '')), 0, 150);
        if ($uuid !== '') $r->offline_uuid = $uuid;
        if (!$r->id && trim((string)$r->creado_por) === '') $r->creado_por = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);

        $catalogo = self::catalogos($proyecto);
        $actsValidas = array_column($catalogo['actividades'], 'id');
        $acts = array_values(array_intersect(array_map('intval', (array)($post['actividades'] ?? [])), $actsValidas));
        $r->setActividades($acts);

        $personasValidas = array_column($catalogo['personal'], 'id');
        if (array_key_exists('personal_id', $post) || !empty($post['personal_present'])) {
            $personalFilas = self::filasPersonal($post, $personasValidas);
            $r->setPersonal(array_values(array_unique(array_filter(array_map(fn($f) => (int)$f['personal_id'], $personalFilas)))));
        }

        $errores = $r->validar();
        if ($errores) return ['ok' => false, 'id' => $id, 'errores' => array_values(array_unique($errores['error'] ?? []))];
        if (empty($acts)) return ['ok' => false, 'id' => $id, 'errores' => ['Selecciona al menos una actividad del programa']];

        $res = $r->guardar();
        $ok = is_array($res) ? (bool)($res['resultado'] ?? false) : (bool)$res;
        if (!$ok) return ['ok' => false, 'id' => $id, 'errores' => ['No se pudo guardar el parte']];
        $rid = (int)($r->id ?: (is_array($res) ? ($res['id'] ?? 0) : 0));
        if (!$rid) return ['ok' => false, 'id' => 0, 'errores' => ['No se pudo guardar el parte']];

        self::guardarHijos($proyecto, $rid, $r, $post, $files, $catalogo);
        return ['ok' => true, 'id' => $rid, 'errores' => [], 'avisos' => self::$avisos];
    }

    private static function numeroONull($v): ?float
    {
        return ($v === null || $v === '' || !is_numeric($v)) ? null : (float)$v;
    }

    private static function filasPersonal(array $post, array $validas): array
    {
        $ids = (array)($post['personal_id'] ?? []);
        $horas = (array)($post['personal_horas'] ?? []);
        $extra = (array)($post['personal_extra'] ?? []);
        $acts = (array)($post['personal_actividad'] ?? []);
        $cuadrillas = (array)($post['personal_cuadrilla'] ?? []);
        $obs = (array)($post['personal_observacion'] ?? []);
        $out = [];
        foreach ($ids as $i => $id) {
            $id = (int)$id;
            if (!$id || !in_array($id, $validas, true)) continue;
            $out[] = [
                'personal_id' => $id,
                'horas_normal' => max(0, min(24, (float)($horas[$i] ?? 0))),
                'horas_extra' => max(0, min(24, (float)($extra[$i] ?? 0))),
                'actividad_id' => (int)($acts[$i] ?? 0) ?: null,
                'cuadrilla' => mb_substr(trim((string)($cuadrillas[$i] ?? '')), 0, 100),
                'observacion' => mb_substr(trim((string)($obs[$i] ?? '')), 0, 500),
            ];
        }
        return $out;
    }

    private static function guardarHijos(Proyecto $proyecto, int $rid, ReporteDiario $r, array $post, array $files, array $catalogo): void
    {
        $pid = (int)$proyecto->id;
        $fecha = self::esc($r->fecha);
        $db = self::db();

        // ---------- personal ----------
        $porId = [];
        foreach ($catalogo['personal'] as $p) $porId[(int)$p['id']] = $p;
        if (array_key_exists('personal_id', $post) || !empty($post['personal_present'])) {
        $db->query("DELETE FROM reporte_personal WHERE reporte_id = $rid");
        foreach (self::filasPersonal($post, array_keys($porId)) as $f) {
            $p = $porId[$f['personal_id']];
            $db->query("INSERT INTO reporte_personal (reporte_id, personal_id, nombre, actividad_id, horas_normal, horas_extra, cuadrilla, observacion) VALUES
                ($rid, " . $f['personal_id'] . ", '" . self::esc($p['nombre']) . "', " . ($f['actividad_id'] ?: 'NULL') . ",
                {$f['horas_normal']}, {$f['horas_extra']}, '" . self::esc($f['cuadrilla']) . "', '" . self::esc($f['observacion']) . "')");
        }
        }

        // ---------- equipo ----------
        $eqPorId = [];
        foreach ($catalogo['equipo'] as $e) $eqPorId[(int)$e['id']] = $e;
        if (array_key_exists('equipo_id', $post) || !empty($post['equipo_present'])) {
        $db->query("DELETE FROM reporte_equipo WHERE reporte_id = $rid");
        $db->query("DELETE FROM equipo_horas WHERE reporte_id = $rid");
        $eqIds = (array)($post['equipo_id'] ?? []);
        $eqHoras = (array)($post['equipo_horas'] ?? []);
        $eqActs = (array)($post['equipo_actividad'] ?? []);
        $eqOper = (array)($post['equipo_operador'] ?? []);
        foreach ($eqIds as $i => $eid) {
            $eid = (int)$eid;
            if (!$eid || !isset($eqPorId[$eid])) continue;
            $horas = max(0, min(24, (float)($eqHoras[$i] ?? 0)));
            if ($horas <= 0) continue;
            $act = (int)($eqActs[$i] ?? 0) ?: null;
            $oper = self::esc(mb_substr(trim((string)($eqOper[$i] ?? '')), 0, 150));
            $importe = round($horas * (float)$eqPorId[$eid]['tarifa_hora'], 2);
            $db->query("INSERT INTO reporte_equipo (reporte_id, equipo_id, nombre, actividad_id, horas, operador, importe) VALUES
                ($rid, $eid, '" . self::esc($eqPorId[$eid]['nombre']) . "', " . ($act ?: 'NULL') . ", $horas, '$oper', $importe)");
            $db->query("INSERT INTO equipo_horas (proyecto_id, equipo_id, actividad_id, reporte_id, fecha, horas, importe, operador) VALUES
                ($pid, $eid, " . ($act ?: 'NULL') . ", $rid, '$fecha', $horas, $importe, '$oper')");
        }
        }

        // ---------- materiales ----------
        $matPorId = [];
        foreach ($catalogo['materiales'] as $m) $matPorId[(int)$m['id']] = $m;
        if (array_key_exists('material_id', $post) || !empty($post['material_present'])) {
        $db->query("DELETE FROM reporte_materiales WHERE reporte_id = $rid");
        $matIds = (array)($post['material_id'] ?? []);
        $matCant = (array)($post['material_cantidad'] ?? []);
        $matUni = (array)($post['material_unidad'] ?? []);
        $matTipo = (array)($post['material_tipo'] ?? []);
        $matAct = (array)($post['material_actividad'] ?? []);
        $matProv = (array)($post['material_proveedor'] ?? []);
        $matRem = (array)($post['material_remision'] ?? []);
        foreach ($matIds as $i => $mid) {
            $mid = (int)$mid;
            if (!$mid || !isset($matPorId[$mid])) continue;
            $cant = max(0, (float)($matCant[$i] ?? 0));
            if ($cant <= 0) continue;
            $tipo = isset(ReporteMaterial::TIPOS[$matTipo[$i] ?? '']) ? $matTipo[$i] : 'consumido';
            $unidad = self::esc(mb_substr(trim((string)($matUni[$i] ?? $matPorId[$mid]['unidad'])), 0, 30));
            $db->query("INSERT INTO reporte_materiales (reporte_id, material_id, nombre, actividad_id, cantidad, unidad, tipo, proveedor, remision) VALUES
                ($rid, $mid, '" . self::esc($matPorId[$mid]['nombre']) . "', " . ((int)($matAct[$i] ?? 0) ?: 'NULL') . ", $cant, '$unidad', '$tipo',
                '" . self::esc(mb_substr(trim((string)($matProv[$i] ?? '')), 0, 150)) . "', '" . self::esc(mb_substr(trim((string)($matRem[$i] ?? '')), 0, 80)) . "')");
            if ($tipo === 'recibido') $db->query("UPDATE materiales SET fecha_entrega_real = '$fecha' WHERE id = $mid AND proyecto_id = $pid AND (fecha_entrega_real IS NULL OR fecha_entrega_real < '$fecha')");
        }
        }

        // ---------- tiempos muertos ----------
        if (array_key_exists('tiempo_horas', $post) || !empty($post['tiempo_present'])) {
        $db->query("DELETE FROM reporte_tiempos WHERE reporte_id = $rid");
        $tActs = (array)($post['tiempo_actividad'] ?? []);
        $tHoras = (array)($post['tiempo_horas'] ?? []);
        $tCausas = (array)($post['tiempo_causa'] ?? []);
        $tNotas = (array)($post['tiempo_nota'] ?? []);
        foreach ($tHoras as $i => $h) {
            $h = max(0, min(24, (float)$h));
            if ($h <= 0) continue;
            $causa = isset(ReporteTiempo::CAUSAS[$tCausas[$i] ?? '']) ? $tCausas[$i] : 'otra';
            $db->query("INSERT INTO reporte_tiempos (reporte_id, actividad_id, horas, causa, nota) VALUES ($rid, " . ((int)($tActs[$i] ?? 0) ?: 'NULL') . ", $h, '$causa', '" . self::esc(mb_substr(trim((string)($tNotas[$i] ?? '')), 0, 500)) . "')");
        }
        }

        // ---------- seguridad ----------
        if (!empty($post['seguridad_present']) || array_key_exists('charla_tema', $post) || array_key_exists('charla_asistentes', $post) || array_key_exists('epp_ok', $post)) {
            $tema = self::esc(mb_substr(trim((string)($post['charla_tema'] ?? '')), 0, 255));
            $asistentes = max(0, min(32767, (int)($post['charla_asistentes'] ?? 0)));
            $epp = !empty($post['epp_ok']) ? 1 : 0;
            $db->query("INSERT INTO reporte_seguridad (reporte_id, charla_tema, asistentes, epp_ok) VALUES ($rid, '$tema', $asistentes, $epp)
                ON DUPLICATE KEY UPDATE charla_tema = VALUES(charla_tema), asistentes = VALUES(asistentes), epp_ok = VALUES(epp_ok)");
        }
        $inc = trim((string)($post['incidente_descripcion'] ?? ''));
        if ($inc !== '') {
            $ya = self::filas("SELECT incidente_id FROM reporte_seguridad WHERE reporte_id = $rid")[0]['incidente_id'] ?? null;
            if (!$ya) {
                $tipoInc = isset(Seguridad::INCIDENTES[$post['incidente_tipo'] ?? '']) ? $post['incidente_tipo'] : 'incidente';
                $sev = in_array($post['incidente_severidad'] ?? '', ['baja', 'media', 'alta', 'critica'], true) ? $post['incidente_severidad'] : 'media';
                $db->query("INSERT INTO seguridad_incidentes (proyecto_id, fecha, tipo, severidad, area, actividad_id, reporte_id, descripcion, acciones, reportado_por) VALUES
                    ($pid, '$fecha', '$tipoInc', '$sev', '" . self::esc(mb_substr($r->area, 0, 150)) . "', " . ((int)($post['incidente_actividad'] ?? 0) ?: 'NULL') . ", $rid,
                    '" . self::esc(mb_substr($inc, 0, 3000)) . "', '', '" . self::esc(mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150)) . "')");
                $db->query("UPDATE reporte_seguridad SET incidente_id = " . (int)$db->insert_id . " WHERE reporte_id = $rid");
            }
        }

        // ---------- avance de actividades ----------
        self::aplicarAvance($proyecto, $r, (array)($post['avance'] ?? []));

        // ---------- fotos y firma ----------
        self::guardarFotos($proyecto, $rid, $post, $files);
        self::guardarFirma($proyecto, $r, $post['firma_data'] ?? '');

        // `fotos_json` sigue siendo la lista canónica para PDF y Evidencias
        $nombres = array_column(self::filas("SELECT archivo FROM reporte_fotos WHERE reporte_id = $rid ORDER BY id"), 'archivo');
        $db->query("UPDATE reportes_diarios SET fotos_json = '" . self::esc(json_encode(array_values($nombres))) . "' WHERE id = $rid");
    }

    /** Copia el avance del parte a las actividades (misma regla que el botón «Actualizar avance») */
    private static function aplicarAvance(Proyecto $proyecto, ReporteDiario $r, array $avance): void
    {
        if (!$avance) return;
        $db = self::db();
        $fecha = self::esc($r->fecha);
        $acts = [];
        foreach ($proyecto->getPartidasConActividades() as $bloque) {
            foreach ($bloque['actividades'] as $a) $acts[(int)$a->id] = $a;
        }
        foreach ($avance as $actId => $valor) {
            $actId = (int)$actId;
            if (!isset($acts[$actId]) || !in_array($actId, $r->getActividades(), true) || !is_numeric($valor)) continue;
            $a = $acts[$actId];
            if ($a->esHito() || $a->tieneSubactividades()) continue;
            $nuevo = max(0, min(100, (int)round((float)$valor)));
            if ($nuevo === (int)$a->progreso) continue;
            $res = $db->query("SELECT progreso FROM actividad_avance WHERE actividad_id = $actId AND fecha = CURDATE()");
            $previoHoy = $res && ($f = $res->fetch_row()) ? (int)$f[0] : null;
            $a->progreso = $nuevo;
            $a->sincronizarStatus();
            if (!$a->guardar()) continue;
            $bloq = \Model\Actividad::bloqueoCalidad();
            if ($bloq) {
                self::$avisos[] = trim($a->codigo . ' · ' . $a->nombre) . ': se guardó ' . (int)$a->progreso . ' % (pediste ' . $nuevo . ' %). ' . $bloq['motivo'];
            }
            if ($r->fecha !== date('Y-m-d')) {
                $db->query("INSERT INTO actividad_avance (actividad_id, fecha, progreso) VALUES ($actId, '$fecha', " . (int)$a->progreso . ") ON DUPLICATE KEY UPDATE progreso = VALUES(progreso)");
                $db->query($previoHoy === null
                    ? "DELETE FROM actividad_avance WHERE actividad_id = $actId AND fecha = CURDATE()"
                    : "UPDATE actividad_avance SET progreso = $previoHoy WHERE actividad_id = $actId AND fecha = CURDATE()");
            }
        }
    }

    /** Avisos del último guardado (por ejemplo, avance recortado por calidad) */
    public static function avisos(): array
    {
        return self::$avisos;
    }

    private static function guardarFotos(Proyecto $proyecto, int $rid, array $post, array $files): void
    {
        $pid = (int)$proyecto->id;
        $db = self::db();

        // Reportes anteriores a la migración: sus fotos solo viven en fotos_json.
        // Se pasan a la tabla hija una vez para que se puedan quitar y conservar.
        $hay = self::filas("SELECT id FROM reporte_fotos WHERE reporte_id = $rid LIMIT 1");
        if (!$hay) {
            $json = json_decode((string)(self::filas("SELECT fotos_json FROM reportes_diarios WHERE id = $rid")[0]['fotos_json'] ?? '[]'), true);
            foreach (is_array($json) ? $json : [] as $nombre) {
                $nombre = basename((string)$nombre);
                if ($nombre === '' || !is_file(__DIR__ . '/../public/uploads/reportes/' . $nombre)) continue;
                $db->query("INSERT INTO reporte_fotos (reporte_id, archivo, leyenda) VALUES ($rid, '" . self::esc($nombre) . "', '')");
            }
        }

        // Quitar las marcadas
        foreach ((array)($post['quitar_fotos'] ?? []) as $fid) {
            $fid = (int)$fid;
            if (!$fid) continue;
            $f = self::filas("SELECT archivo FROM reporte_fotos WHERE id = $fid AND reporte_id = $rid")[0] ?? null;
            if (!$f) continue;
            \Classes\Imagenes::eliminarEn('reportes', $f['archivo']);
            $db->query("DELETE FROM reporte_fotos WHERE id = $fid AND reporte_id = $rid");
        }

        $subidas = $files['fotos'] ?? null;
        if (!$subidas || !is_array($subidas['tmp_name'] ?? null)) return;
        $libre = \Classes\Almacenamiento::disponible($pid);
        $n = 0;
        foreach ($subidas['tmp_name'] as $i => $tmp) {
            if (!$tmp || !is_uploaded_file($tmp) || ++$n > self::MAX_FOTOS) continue;
            if ($subidas['size'][$i] > self::MAX_FOTO_BYTES) continue;
            if ($subidas['size'][$i] > $libre) break;
            $nombre = \Classes\Imagenes::guardarEn('reportes', $tmp, 1600, 82);
            if (!$nombre) continue;
            $libre -= (int)@filesize(__DIR__ . '/../public/uploads/reportes/' . $nombre);
            $act = (int)($post['foto_actividad'][$i] ?? 0) ?: null;
            $leyenda = self::esc(mb_substr(trim((string)($post['foto_leyenda'][$i] ?? '')), 0, 255));
            $lat = self::numeroONull($post['foto_lat'][$i] ?? null);
            $lng = self::numeroONull($post['foto_lng'][$i] ?? null);
            $tomada = trim((string)($post['foto_tomada'][$i] ?? ''));
            $tomadaSql = preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $tomada) ? "'" . self::esc(str_replace('T', ' ', substr($tomada, 0, 19))) . "'" : 'NULL';
            $db->query("INSERT INTO reporte_fotos (reporte_id, archivo, actividad_id, leyenda, lat, lng, tomada_en) VALUES
                ($rid, '" . self::esc($nombre) . "', " . ($act ?: 'NULL') . ", '$leyenda', " . ($lat !== null ? $lat : 'NULL') . ", " . ($lng !== null ? $lng : 'NULL') . ", $tomadaSql)");
        }
    }

    private static function guardarFirma(Proyecto $proyecto, ReporteDiario $r, string $dataUrl): void
    {
        if (!preg_match('#^data:image/png;base64,(.+)$#s', $dataUrl, $m)) return;
        $bin = base64_decode($m[1], true);
        if (!$bin || strlen($bin) > self::MAX_FIRMA_BYTES || substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") return;
        if (!\Classes\Almacenamiento::cabe((int)$proyecto->id, strlen($bin))) return;
        $carpeta = __DIR__ . '/../public/uploads/reportes';
        if (!is_dir($carpeta)) @mkdir($carpeta, 0775, true);
        $nombre = bin2hex(random_bytes(16)) . '.png';   // mismo formato que Media espera
        if (!@file_put_contents($carpeta . '/' . $nombre, $bin)) return;
        if ($r->firma_imagen) \Classes\Imagenes::eliminarEn('reportes', $r->firma_imagen);
        $r->firma_imagen = $nombre;
        self::db()->query("UPDATE reportes_diarios SET firma_imagen = '" . self::esc($nombre) . "' WHERE id = " . (int)$r->id);
    }

    // ---------------------------------------------------------------- cierre

    /** Cierra el parte: lo bloquea y deja el resumen en la bitácora. Solo dueño/admin. */
    public static function cerrar(Proyecto $proyecto, int $id): array
    {
        $pid = (int)$proyecto->id;
        $r = ReporteDiario::find($id);
        if (!$r || (int)$r->proyecto_id !== $pid) return [false, 'El parte no existe'];
        if ($r->estaCerrado()) return [true, 'El parte ya estaba cerrado'];
        $db = self::db();
        $db->query("UPDATE reportes_diarios SET estado = 'cerrado', cerrado_en = NOW() WHERE id = $id AND proyecto_id = $pid");
        $ya = self::filas("SELECT id FROM bitacora WHERE reporte_id = $id LIMIT 1");
        if (!$ya) {
            $db->query("INSERT INTO bitacora (proyecto_id, fecha, reporte_id, departamento, descripcion, importancia) VALUES
                ($pid, '" . self::esc($r->fecha) . "', $id, 'Obra', '" . self::esc($r->resumen()) . "', 'media')");
        }
        return [true, 'Parte cerrado. Se agregó el resumen a la bitácora.'];
    }

    public static function reabrir(Proyecto $proyecto, int $id): array
    {
        $pid = (int)$proyecto->id;
        $r = ReporteDiario::find($id);
        if (!$r || (int)$r->proyecto_id !== $pid) return [false, 'El parte no existe'];
        self::db()->query("UPDATE reportes_diarios SET estado = 'borrador', cerrado_en = NULL WHERE id = $id AND proyecto_id = $pid");
        return [true, 'Parte reabierto para edición'];
    }

    /** ¿Puede este usuario cerrar o reabrir? Dueño del proyecto o admin. */
    public static function puedeCerrar(Proyecto $proyecto): bool
    {
        return is_admin() || es_propietario($proyecto);
    }
}
