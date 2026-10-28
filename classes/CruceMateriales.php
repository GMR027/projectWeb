<?php

namespace Classes;

use Model\Proyecto;

/**
 * Cruce de materiales y cronograma: para cada actividad sin terminar compara cuándo se necesita el material (su inicio) con su compra,
 * entrega y submittal. Los renglones de órdenes de cambio con material se cruzan igual contra su fecha prevista.
 */
class CruceMateriales
{
    use AccesoDb;

    public const ESTADOS = [
        'compra_vencida' => ['Compra vencida', 'critica'],
        'sin_fecha' => ['Sin fecha de entrega', 'alta'],
        'tarde' => ['Llega tarde', 'critica'],
        'bloqueado_requisito' => ['Bloqueado por un material prerrequisito', 'alta'],
        'submittal' => ['Submittal sin aprobar', 'alta'],
        'a_tiempo' => ['A tiempo', 'ok'],
        'en_sitio' => ['En sitio', 'ok'],
    ];

    /**
     * Estado de un material frente a la fecha en que se necesita.
     * $disponible es la fecha efectiva (incluye sus materiales prerrequisito); si no se pasa,
     * se usan solo los datos del propio material.
     */
    public static function evaluar(?string $necesidad, array $m, string $hoy, ?string $disponible = null): array
    {
        if (!empty($m['fecha_entrega_real'])) return ['en_sitio', 0];
        if (($m['compra'] ?? '') !== 'comprado' && !empty($m['fecha_compra_prog']) && $m['fecha_compra_prog'] < $hoy) return ['compra_vencida', (int)floor((strtotime($hoy) - strtotime($m['fecha_compra_prog'])) / 86400)];
        if (empty($m['fecha_entrega_prog'])) return ['sin_fecha', 0];
        if ($disponible && !empty($m['fecha_entrega_prog']) && $disponible > $m['fecha_entrega_prog'] && $necesidad && $disponible > $necesidad) {
            return ['bloqueado_requisito', (int)floor((strtotime($disponible) - strtotime($necesidad)) / 86400)];
        }
        if ($necesidad && $m['fecha_entrega_prog'] > $necesidad) return ['tarde', (int)floor((strtotime($m['fecha_entrega_prog']) - strtotime($necesidad)) / 86400)];
        if (isset($m['sub']) && $m['sub'] !== null && !in_array($m['sub'], ['aprobado', 'aprobado_an'], true)) return ['submittal', 0];
        return ['a_tiempo', 0];
    }

    /** @return array{filas: list<array>, resumen: array} lo más urgente primero */
    public static function cruzar(Proyecto $p): array
    {
        $pid = (int)$p->id;
        $hoy = date('Y-m-d');
        $disp = DependenciasMateriales::disponibilidad($pid);
        $sql = "SELECT a.id actividad_id, a.codigo, a.nombre actividad, a.fecha_inicio, m.id material_id, m.nombre material, m.cantidad, m.unidad, m.compra, m.fecha_compra_prog, m.fecha_entrega_prog, m.fecha_entrega_real,
                (SELECT s.estatus FROM submittal_materiales sm JOIN submittals s ON s.id = sm.submittal_id WHERE sm.material_id = m.id ORDER BY (s.estatus IN ('aprobado','aprobado_an')) DESC, s.fecha DESC LIMIT 1) sub
            FROM actividad_materiales am JOIN materiales m ON m.id = am.material_id JOIN actividades a ON a.id = am.actividad_id JOIN partidas pa ON pa.id = a.partida_id
            WHERE pa.proyecto_id = $pid AND a.progreso < 100 AND a.sin_material = 0 ORDER BY a.fecha_inicio, a.codigo";
        $filas = [];
        foreach (self::filas($sql) as $f) {
            $ef = $disp['materiales'][(int)$f['material_id']]['entrega_ef'] ?? null;
            [$e, $dias] = self::evaluar($f['fecha_inicio'], $f, $hoy, $ef);
            $filas[] = ['origen' => 'programa', 'ref' => $f['codigo'], 'actividad_id' => (int)$f['actividad_id'], 'actividad' => $f['actividad'], 'material' => $f['material'], 'cantidad' => $f['cantidad'], 'unidad' => $f['unidad'],
                'necesidad' => $f['fecha_inicio'], 'compra' => $f['compra'], 'entrega' => $f['fecha_entrega_real'] ?: $f['fecha_entrega_prog'], 'entrega_real' => (bool)$f['fecha_entrega_real'], 'estado' => $e, 'dias' => $dias, 'submittal' => $f['sub']];
        }
        foreach (Cambios::materialesDeCambios($pid) as $f) {
            $nec = $f['fecha_prevista'] ?: $f['act_inicio'];
            $ef = $disp['materiales'][(int)($f['material_id'] ?? 0)]['entrega_ef'] ?? null;
            [$e, $dias] = self::evaluar($nec, $f, $hoy, $ef);
            $filas[] = ['origen' => 'cambio', 'ref' => $f['numero'], 'actividad_id' => null, 'actividad' => $f['descripcion'], 'material' => $f['material'], 'cantidad' => $f['cantidad'], 'unidad' => $f['unidad'],
                'necesidad' => $nec, 'compra' => $f['compra'], 'entrega' => $f['fecha_entrega_real'] ?: $f['fecha_entrega_prog'], 'entrega_real' => (bool)$f['fecha_entrega_real'], 'estado' => $e, 'dias' => $dias, 'submittal' => null];
        }
        $peso = ['tarde' => 0, 'bloqueado_requisito' => 1, 'compra_vencida' => 2, 'sin_fecha' => 3, 'submittal' => 4, 'a_tiempo' => 5, 'en_sitio' => 6];
        usort($filas, fn($x, $y) => [$peso[$x['estado']], $x['necesidad'] ?? '9999'] <=> [$peso[$y['estado']], $y['necesidad'] ?? '9999']);
        $res = array_fill_keys(array_keys(self::ESTADOS), 0);
        foreach ($filas as $f) $res[$f['estado']]++;
        $res['total'] = count($filas);
        $res['en_riesgo'] = $res['tarde'] + $res['bloqueado_requisito'] + $res['compra_vencida'] + $res['sin_fecha'] + $res['submittal'];
        return ['filas' => $filas, 'resumen' => $res];
    }

    /** Ids de actividades con algún material que llega tarde, con la compra vencida o sin fecha (para atribuir desviaciones) */
    public static function actividadesConMaterialEnRiesgo(array $cruce): array
    {
        $ids = [];
        foreach ($cruce['filas'] as $f) if ($f['actividad_id'] && in_array($f['estado'], ['tarde', 'bloqueado_requisito', 'compra_vencida'], true)) $ids[$f['actividad_id']] = true;
        return $ids;
    }
}
