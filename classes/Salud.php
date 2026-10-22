<?php

namespace Classes;

use Model\Proyecto;

/**
 * Salud del proyecto: junta en un solo lugar datos que ya viven en otras herramientas (programa, restricciones, submittals/MSR, punch, cambios,
 * seguridad, clima) y los resume en un semáforo. También calcula el «riesgo» de cada actividad (material sin submittal aprobado, pendiente grave
 * del punch, permiso que falta) para marcarla en el programa, el Lookahead y el Last Planner. No guarda nada: todo se calcula al consultar.
 */
class Salud
{
    use AccesoDb;

    /** Solo se marcan las actividades que inician dentro de este número de días (las lejanas aún tienen tiempo) */
    public const HORIZONTE_DIAS = 30;
    /** Tipos de permiso que se ven afectados por lluvia o viento */
    public const PERMISOS_CLIMA = ['altura', 'izaje', 'excavacion', 'electrico', 'caliente'];
    public const NIVELES = ['ok' => 'Sin problemas', 'aviso' => 'Atención', 'critico' => 'Urgente'];

    private static array $cacheRiesgos = [];

    private static function nivelMax(string ...$n): string
    {
        return in_array('critico', $n, true) ? 'critico' : (in_array('aviso', $n, true) ? 'aviso' : 'ok');
    }

    // ---------- riesgo por actividad ----------

    /**
     * Actividades pendientes que inician pronto y tienen algo que las pone en riesgo.
     * @return array<int, array[]> actividad_id => [['tipo' => 'material|punch|permiso', 'texto' => …, 'url' => …], …]
     */
    public static function riesgosActividades(int $pid): array
    {
        if (isset(self::$cacheRiesgos[$pid])) return self::$cacheRiesgos[$pid];
        $hoy = date('Y-m-d');
        $tope = date('Y-m-d', strtotime('+' . self::HORIZONTE_DIAS . ' days'));
        $pendientes = self::filas("SELECT a.id, a.sin_material FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE p.proyecto_id = $pid AND a.hito = 0 AND a.progreso < 100 AND a.fecha_inicio <= '$tope'");
        $ids = [];
        foreach ($pendientes as $f) $ids[(int)$f['id']] = (int)$f['sin_material'];
        $out = [];
        if (!$ids) return self::$cacheRiesgos[$pid] = $out;
        $lista = implode(',', array_keys($ids));

        // Material sin submittal aprobado (ni en un submittal aprobado ni con status A/AN en el MSR)
        foreach (self::filas("SELECT am.actividad_id, m.nombre FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id
            WHERE am.actividad_id IN ($lista) AND m.proyecto_id = $pid
              AND NOT EXISTS (SELECT 1 FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE sm.material_id = m.id AND s.estatus IN ('aprobado','aprobado_an'))
              AND NOT EXISTS (SELECT 1 FROM msr_items i WHERE i.material_id = m.id AND i.status IN ('A','AN'))
            ORDER BY m.nombre") as $f) {
            $a = (int)$f['actividad_id'];
            if (!empty($ids[$a])) continue;   // marcada «sin material»
            $mats[$a][] = $f['nombre'];
        }
        foreach ($mats ?? [] as $a => $nombres) {
            $out[$a][] = ['tipo' => 'material', 'texto' => 'Material sin submittal aprobado: ' . implode(', ', array_slice($nombres, 0, 3)) . (count($nombres) > 3 ? ' y ' . (count($nombres) - 3) . ' más' : ''), 'url' => "/submittals?proyecto=$pid"];
        }

        // Pendientes graves del punch
        foreach (self::filas("SELECT actividad_id, COUNT(*) n, SUM(severidad = 'critica') c FROM punch_items
            WHERE proyecto_id = $pid AND actividad_id IN ($lista) AND estado IN ('abierto','en_proceso') AND severidad IN ('alta','critica') GROUP BY actividad_id") as $f) {
            $out[(int)$f['actividad_id']][] = ['tipo' => 'punch', 'texto' => (int)$f['n'] . ' pendiente(s) grave(s) del punch' . ((int)$f['c'] ? ' (' . (int)$f['c'] . ' crítico)' : ''), 'url' => "/punch?proyecto=$pid"];
        }

        // RFI abiertos que bloquean la actividad
        foreach (self::filas("SELECT actividad_id, COUNT(*) n, SUM(fecha_limite < CURDATE()) v, MIN(numero) numero FROM rfis
            WHERE proyecto_id = $pid AND actividad_id IN ($lista) AND estado = 'abierto' GROUP BY actividad_id") as $f) {
            $out[(int)$f['actividad_id']][] = ['tipo' => 'rfi', 'texto' => (int)$f['n'] . ' RFI abierto(s) sin respuesta (' . $f['numero'] . ((int)$f['n'] > 1 ? ' y más' : '') . ')' . ((int)$f['v'] ? ', vencido' : ''), 'url' => "/rfi?proyecto=$pid&estado=abierto"];
        }

        // Permisos de trabajo que faltan
        foreach (Seguridad::requeridos($pid, 14) as $r) {
            if ($r['estado'] !== 'falta') continue;
            $a = (int)$r['actividad']['id'];
            if (isset($ids[$a])) $out[$a][] = ['tipo' => 'permiso', 'texto' => 'Falta el permiso de trabajo (' . $r['tipo'] . ')', 'url' => "/seguridad?proyecto=$pid"];
        }
        return self::$cacheRiesgos[$pid] = $out;
    }

    /** Insignia HTML «En riesgo» con el detalle en la burbuja; vacío si la actividad no tiene riesgos */
    public static function insignia(array $riesgos, int $actividadId): string
    {
        $r = $riesgos[$actividadId] ?? [];
        if (!$r) return '';
        $texto = implode(' · ', array_map(fn($x) => $x['texto'], $r));
        return '<a class="badge-riesgo" href="' . htmlspecialchars($r[0]['url'], ENT_QUOTES) . '" title="' . htmlspecialchars($texto, ENT_QUOTES) . '"><i class="fa-solid fa-triangle-exclamation"></i> En riesgo</a>';
    }

    // ---------- clima ↔ seguridad ----------

    /**
     * Permisos de trabajo (aprobados o en trámite) vigentes en un día de clima adverso y sensibles a lluvia/viento.
     * @return array[] filas de permisos_trabajo (id, tipo, titulo, estado, actividad_id)
     */
    public static function permisosDelDia(int $pid, string $fecha): array
    {
        $f = self::db()->real_escape_string($fecha);
        $tipos = "'" . implode("','", self::PERMISOS_CLIMA) . "'";
        return self::filas("SELECT id, tipo, titulo, estado, actividad_id FROM permisos_trabajo WHERE proyecto_id = $pid AND estado IN ('solicitado','aprobado')
            AND vigente_desde <= '$f' AND vigente_hasta >= '$f' AND tipo IN ($tipos) ORDER BY vigente_hasta");
    }

    /**
     * Próximos días con lluvia o viento y los permisos sensibles que caen en ellos.
     * @return array[] ['fecha','condicion','precip','viento','permisos'=>[…]] (solo días con permisos)
     */
    public static function climaYPermisos(int $pid, int $dias = 7): array
    {
        $out = [];
        foreach (Clima::dias($pid, date('Y-m-d'), date('Y-m-d', strtotime("+$dias days"))) as $d) {
            if (!($c = Clima::condicion($d))) continue;
            $p = self::permisosDelDia($pid, $d['fecha']);
            if ($p) $out[] = ['fecha' => $d['fecha'], 'condicion' => $c, 'precip' => (float)$d['precip_mm'], 'viento' => (float)$d['viento_kmh'], 'permisos' => $p];
        }
        return $out;
    }

    // ---------- semáforo ----------

    /**
     * @return array{nivel:string, indicadores:array[]} cada indicador: clave, titulo, valor, nivel, detalle, url, icono
     */
    public static function semaforo(Proyecto $proyecto): array
    {
        $pid = (int)$proyecto->id;
        $hoy = date('Y-m-d');
        $en7 = date('Y-m-d', strtotime('+7 days'));
        $n = fn(string $sql) => (int)(self::filas($sql)[0]['n'] ?? 0);
        $ind = [];

        // Atraso del programa
        $total = $n("SELECT COUNT(*) n FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.hito = 0");
        $atras = $n("SELECT COUNT(*) n FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND a.hito = 0 AND a.progreso < 100 AND a.fecha_fin < '$hoy'");
        $ind[] = ['clave' => 'atraso', 'titulo' => 'Actividades atrasadas', 'valor' => $atras, 'icono' => 'fa-clock', 'url' => "/programa?proyecto=$pid",
            'nivel' => !$atras ? 'ok' : (($total && $atras / $total >= 0.15) || $atras >= 8 ? 'critico' : 'aviso'),
            'detalle' => $atras ? "Ya debieron terminar y no están al 100 % (de $total)." : 'Ninguna actividad vencida sin terminar.'];

        // Restricciones activas
        $rest = $n("SELECT COUNT(*) n FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = $pid AND r.estado = 'activa'");
        $restUrg = $n("SELECT COUNT(DISTINCT r.id) n FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas p ON p.id = a.partida_id
            WHERE p.proyecto_id = $pid AND r.estado = 'activa' AND a.progreso < 100 AND a.fecha_inicio <= '$en7'");
        $ind[] = ['clave' => 'restricciones', 'titulo' => 'Restricciones abiertas', 'valor' => $rest, 'icono' => 'fa-ban', 'url' => "/lookahead?proyecto=$pid",
            'nivel' => !$rest ? 'ok' : ($restUrg ? 'critico' : 'aviso'),
            'detalle' => $rest ? ($restUrg ? "$restUrg afectan actividades que inician en 7 días o ya están en curso." : 'Ninguna afecta todavía actividades de la próxima semana.') : 'Sin restricciones activas.'];

        // Submittals vencidos o rechazados
        $venc = $n("SELECT COUNT(*) n FROM msr_items WHERE proyecto_id = $pid AND status = 'P' AND fecha_envio_real IS NULL AND fecha_envio_requerida IS NOT NULL AND fecha_envio_requerida < '$hoy'");
        $rech = $n("SELECT COUNT(*) n FROM submittals WHERE proyecto_id = $pid AND estatus = 'rechazado'");
        $ind[] = ['clave' => 'submittals', 'titulo' => 'Submittals con problema', 'valor' => $venc + $rech, 'icono' => 'fa-file-circle-exclamation', 'url' => "/submittals?proyecto=$pid",
            'nivel' => !($venc + $rech) ? 'ok' : (($venc + $rech) >= 5 ? 'critico' : 'aviso'),
            'detalle' => ($venc + $rech) ? "$venc material(es) con envío vencido sin enviar · $rech submittal(s) rechazado(s) por resubmitar." : 'Envíos al corriente.'];

        // Punch
        $graves = $n("SELECT COUNT(*) n FROM punch_items WHERE proyecto_id = $pid AND estado IN ('abierto','en_proceso') AND severidad IN ('alta','critica')");
        $crit = $n("SELECT COUNT(*) n FROM punch_items WHERE proyecto_id = $pid AND estado IN ('abierto','en_proceso') AND severidad = 'critica'");
        $vencP = $n("SELECT COUNT(*) n FROM punch_items WHERE proyecto_id = $pid AND estado IN ('abierto','en_proceso') AND fecha_limite IS NOT NULL AND fecha_limite < '$hoy'");
        $ind[] = ['clave' => 'punch', 'titulo' => 'Pendientes graves (punch)', 'valor' => $graves, 'icono' => 'fa-list-check', 'url' => "/punch?proyecto=$pid",
            'nivel' => $crit ? 'critico' : ($graves || $vencP ? 'aviso' : 'ok'),
            'detalle' => ($graves || $vencP) ? "$graves de severidad alta o crítica ($crit crítico) · $vencP con fecha límite vencida." : 'Sin pendientes graves abiertos.'];

        // Órdenes de cambio por aprobar
        $oc = $n("SELECT COUNT(*) n FROM ordenes_cambio WHERE proyecto_id = $pid AND estado = 'solicitada'");
        $ind[] = ['clave' => 'cambios', 'titulo' => 'Órdenes de cambio por aprobar', 'valor' => $oc, 'icono' => 'fa-file-signature', 'url' => "/cambios?proyecto=$pid",
            'nivel' => $oc ? 'aviso' : 'ok', 'detalle' => $oc ? 'Esperan una decisión del administrador.' : 'Nada pendiente de aprobar.'];

        // RFI (solicitudes de información)
        if (Rfi::disponible()) {
            $rf = Rfi::resumen($pid);
            $ind[] = ['clave' => 'rfi', 'titulo' => 'RFI abiertos', 'valor' => $rf['abierto'], 'icono' => 'fa-circle-question', 'url' => "/rfi?proyecto=$pid&estado=abierto",
                'nivel' => $rf['vencidos'] || $rf['bloqueando'] >= 3 ? 'critico' : ($rf['abierto'] ? 'aviso' : 'ok'),
                'detalle' => $rf['abierto'] ? $rf['vencidos'] . ' con la fecha límite vencida · ' . $rf['bloqueando'] . ' bloqueando actividades sin terminar.' : 'Sin solicitudes de información pendientes.'];
        }

        // Recursos sobrecargados (próximas 4 semanas)
        if (Recursos::disponible() && ($sobre = Recursos::sobrecargasProximas($proyecto, 4))) {
            $quienes = array_values(array_unique(array_column($sobre, 'recurso')));
            $ind[] = ['clave' => 'recursos', 'titulo' => 'Recursos sobrecargados', 'valor' => count($quienes), 'icono' => 'fa-people-group', 'url' => "/recursos?proyecto=$pid",
                'nivel' => count($quienes) >= 3 ? 'critico' : 'aviso', 'detalle' => implode(', ', array_slice($quienes, 0, 4)) . (count($quienes) > 4 ? ' y más' : '') . ' superan el 100 % de su jornada en las próximas 4 semanas.'];
        }

        // Costos (solo si hay presupuesto capturado): CPI = valor ganado / costo real
        if (puede_gestionar_proyecto($proyecto) && Costos::disponible() && ($cs = Costos::resumen($proyecto)['total']) && $cs['bac'] > 0 && $cs['ac'] > 0) {
            $ind[] = ['clave' => 'costos', 'titulo' => 'Índice de costo (CPI)', 'valor' => number_format($cs['cpi'], 2), 'icono' => 'fa-sack-dollar', 'url' => "/costos?proyecto=$pid",
                'nivel' => Costos::nivel($cs['cpi']), 'detalle' => $cs['cpi'] < 1 ? 'Se ha gastado más de lo que se ha avanzado (CPI menor a 1). Costo final estimado ' . Costos::moneda($cs['eac']) . '.' : 'El costo va por debajo o igual al valor ganado.'];
        }

        // Seguridad
        $seg = Seguridad::resumen($pid);
        $climaPerm = self::climaYPermisos($pid, 7);
        $ind[] = ['clave' => 'seguridad', 'titulo' => 'Permisos de trabajo', 'valor' => $seg['faltan'] + $seg['vencidos'] + count($climaPerm), 'icono' => 'fa-helmet-safety', 'url' => "/seguridad?proyecto=$pid",
            'nivel' => $seg['faltan'] ? 'critico' : (($seg['vencidos'] || $seg['por_aprobar'] || $climaPerm) ? 'aviso' : 'ok'),
            'detalle' => ($seg['faltan'] || $seg['vencidos'] || $seg['por_aprobar'] || $climaPerm)
                ? $seg['faltan'] . ' actividad(es) de la semana sin permiso · ' . $seg['vencidos'] . ' vencido(s) sin cerrar · ' . $seg['por_aprobar'] . ' por aprobar' . ($climaPerm ? ' · ' . count($climaPerm) . ' día(s) de lluvia o viento con permisos vigentes' : '') . '.'
                : 'Permisos al corriente.'];

        // Actividades en riesgo (materiales/punch/permisos)
        $riesgos = count(self::riesgosActividades($pid));
        $ind[] = ['clave' => 'riesgo', 'titulo' => 'Actividades en riesgo (30 días)', 'valor' => $riesgos, 'icono' => 'fa-triangle-exclamation', 'url' => "/programa?proyecto=$pid&riesgo=1",
            'nivel' => !$riesgos ? 'ok' : 'aviso', 'detalle' => $riesgos ? 'Material sin submittal aprobado, pendientes graves o permisos que faltan.' : 'Ninguna actividad próxima en riesgo.'];

        return ['nivel' => self::nivelMax(...array_column($ind, 'nivel')), 'indicadores' => $ind];
    }
}
