<?php

namespace Classes;

use Model\Minuta;
use Model\Proyecto;
use Model\ReporteDiario;

/** Lleva un informe aprobado a su módulo. Solo los tipos que traen un bloque `aplicar` */
class AplicarInforme
{
    /** @return array{0: bool, 1: string, 2: string} ok, mensaje y referencia al registro creado */
    public static function aplicar(Proyecto $p, array $inf): array
    {
        $a = $inf['contenido']['aplicar'] ?? null;
        if (!is_array($a)) return [false, 'Este tipo de informe no se aplica a ningún módulo.', ''];
        return match ($a['tipo'] ?? '') {
            'reporte_diario' => self::reporte($p, $a),
            'minuta' => self::minuta($p, $a),
            'bitacora' => self::bitacora($p, $a),
            'punch' => self::punch($p, $a),
            default => [false, 'Este tipo de informe no se aplica a ningún módulo.', ''],
        };
    }

    private static function reporte(Proyecto $p, array $a): array
    {
        $pid = (int)$p->id;
        $ids = array_map('intval', (array)($a['actividades'] ?? []));
        // solo actividades del proyecto
        if ($ids) {
            $ok = array_column($GLOBALS['db']->query('SELECT a.id FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = ' . $pid . ' AND a.id IN (' . implode(',', $ids) . ')')->fetch_all(MYSQLI_ASSOC), 'id');
            $ids = array_map('intval', $ok);
        }
        $r = new ReporteDiario(['proyecto_id' => $pid, 'fecha' => $a['fecha'] ?? date('Y-m-d'), 'area' => mb_substr((string)($a['area'] ?? ''), 0, 150), 'comentarios' => (string)($a['comentarios'] ?? '')]);
        $r->setActividades($ids);
        if (!empty($a['personal'])) {
            $pers = array_column($GLOBALS['db']->query("SELECT id FROM personal WHERE proyecto_id = $pid AND id IN (" . implode(',', array_map('intval', $a['personal'])) . ')')->fetch_all(MYSQLI_ASSOC), 'id');
            $r->setPersonal(array_map('intval', $pers));
        }
        $alertas = $r->validar();
        if (!empty($alertas)) return [false, implode(' ', array_merge(...array_values($alertas))), ''];
        $res = $r->guardar();
        $id = is_array($res) ? ($res['id'] ?? $r->id) : $r->id;
        return [true, 'Reporte diario creado con el borrador (revísalo y complétalo en Reportes: personal y fotos).', 'reporte:' . $id];
    }

    private static function punch(Proyecto $p, array $a): array
    {
        $n = 0;
        foreach (array_slice((array)($a['items'] ?? []), 0, 40) as $x) {
            [$ok] = Punch::guardar((int)$p->id, (array)$x, null, 'ia');
            if ($ok) $n++;
        }
        return $n ? [true, "$n pendiente(s) creados en el punch list.", 'punch:' . $n] : [false, 'No había pendientes que crear.', ''];
    }

    private static function bitacora(Proyecto $p, array $a): array
    {
        $b = new \Model\Bitacora(['proyecto_id' => (int)$p->id, 'fecha' => $a['fecha'] ?? date('Y-m-d'), 'departamento' => mb_substr((string)($a['departamento'] ?? 'Obra'), 0, 100), 'descripcion' => (string)($a['descripcion'] ?? ''), 'importancia' => $a['importancia'] ?? 'media']);
        $alertas = $b->validar();
        if (!empty($alertas)) return [false, implode(' ', array_merge(...array_values($alertas))), ''];
        $res = $b->guardar();
        $id = is_array($res) ? ($res['id'] ?? $b->id) : $b->id;
        return [true, 'Entrada de bitácora creada.', 'bitacora:' . $id];
    }

    private static function minuta(Proyecto $p, array $a): array
    {
        $m = new Minuta(['proyecto_id' => (int)$p->id, 'fecha' => $a['fecha'] ?? date('Y-m-d'), 'nombre' => mb_substr((string)($a['nombre'] ?? 'Minuta'), 0, 200), 'importancia' => $a['importancia'] ?? 'media']);
        $m->setItems('temas_json', (array)($a['temas'] ?? []));
        $m->setItems('compromisos_json', (array)($a['compromisos'] ?? []));
        $alertas = $m->validar();
        if (!empty($alertas)) return [false, implode(' ', array_merge(...array_values($alertas))), ''];
        $res = $m->guardar();
        $id = is_array($res) ? ($res['id'] ?? $m->id) : $m->id;
        return [true, 'Minuta creada con los temas y compromisos (completa contratistas y asistentes en Minutas).', 'minuta:' . $id];
    }
}
