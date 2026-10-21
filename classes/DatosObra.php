<?php

namespace Classes;

/** Lo que el Ensamblador deja guardado (fechas contractuales, responsables, cantidades y contactos), en lectura, y las alertas de contrato */
class DatosObra
{
    use AccesoDb;

    /** Días de aviso antes de una fecha contractual */
    public const AVISO_DIAS = 30;

    public static function fechasContrato(int $pid): array
    {
        return self::filas("SELECT tipo, nombre, fecha, penalizacion, origen FROM proyecto_fechas_contrato WHERE proyecto_id = $pid ORDER BY fecha ASC, id ASC");
    }

    public static function responsables(int $pid): array
    {
        return self::filas("SELECT a.codigo, a.nombre actividad, p.nombre persona, c.cargo, c.empresa
            FROM actividad_responsables r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id
            JOIN personal p ON p.id = r.personal_id LEFT JOIN personal_contacto c ON c.personal_id = p.id
            WHERE pa.proyecto_id = $pid ORDER BY pa.orden, a.orden");
    }

    public static function cantidades(int $pid): array
    {
        return self::filas("SELECT c.clave, c.concepto, c.cantidad, c.unidad, c.importe, a.codigo, a.nombre actividad
            FROM actividad_cantidades c JOIN actividades a ON a.id = c.actividad_id JOIN partidas pa ON pa.id = a.partida_id
            WHERE c.proyecto_id = $pid ORDER BY pa.orden, a.orden");
    }

    public static function contactos(int $pid): array
    {
        return self::filas("SELECT p.nombre, p.departamento, c.cargo, c.empresa, c.telefono, c.correo
            FROM personal p JOIN personal_contacto c ON c.personal_id = p.id WHERE p.proyecto_id = $pid ORDER BY p.nombre");
    }

    /**
     * Alertas por fechas contractuales frente al programa de hoy (días naturales).
     * Inicio y fin comparan el primer/último día del programa; un hito se liga a la actividad de nombre parecido.
     * @return array<int, array{nivel: string, texto: string, fecha: string}> nivel: critica | aviso | info (ordenadas por gravedad)
     */
    public static function alertas(int $pid, ?string $hoy = null): array
    {
        $fechas = self::fechasContrato($pid);
        if (!$fechas) return [];
        $hoy = $hoy ?: date('Y-m-d');
        $acts = self::filas("SELECT a.id, a.nombre, a.fecha_inicio, a.fecha_fin, a.progreso FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $pid");
        $nombres = array_column($acts, 'nombre', 'id');
        $porId = array_column($acts, null, 'id');
        $ini = $acts ? min(array_column($acts, 'fecha_inicio')) : null;
        $fin = $acts ? max(array_column($acts, 'fecha_fin')) : null;
        $dias = fn(string $a, string $b) => (int)round((strtotime($a) - strtotime($b)) / 86400);
        $fmt = fn(string $f) => date('d/m/Y', strtotime($f));
        $out = [];
        foreach ($fechas as $f) {
            $etq = $f['tipo'] === 'inicio' ? 'inicio del contrato' : ($f['tipo'] === 'fin' ? 'término del contrato' : 'hito «' . $f['nombre'] . '»');
            $pen = $f['penalizacion'] !== '' ? ' Penalización: ' . $f['penalizacion'] . '.' : '';
            $prog = null; $terminado = false;
            if ($f['tipo'] === 'inicio') $prog = $ini;
            elseif ($f['tipo'] === 'fin') $prog = $fin;
            else {
                [$id, $sim] = Ensamblador::mejor($f['nombre'], $nombres);
                if ($id && $sim >= 0.65) { $prog = $porId[$id]['fecha_fin']; $terminado = (float)$porId[$id]['progreso'] >= 100; }
            }
            $falta = $dias($f['fecha'], $hoy);
            if ($prog && $dias($prog, $f['fecha']) > 0) {
                $d = $dias($prog, $f['fecha']);
                $out[] = ['nivel' => 'critica', 'fecha' => $f['fecha'], 'texto' => "El programa termina $d día(s) DESPUÉS del $etq ({$fmt($f['fecha'])}); hoy está en {$fmt($prog)}.$pen"];
            } elseif ($terminado) {
                continue;
            } elseif ($falta < 0 && $f['tipo'] !== 'inicio') {
                $out[] = ['nivel' => 'critica', 'fecha' => $f['fecha'], 'texto' => 'Venció el ' . $etq . ' (' . $fmt($f['fecha']) . ') hace ' . abs($falta) . ' día(s) y no consta como terminado.' . $pen];
            } elseif ($falta >= 0 && $falta <= self::AVISO_DIAS) {
                $out[] = ['nivel' => $falta <= 7 ? 'critica' : 'aviso', 'fecha' => $f['fecha'], 'texto' => ($falta === 0 ? 'Hoy' : "En $falta día(s)") . ' vence el ' . $etq . ' (' . $fmt($f['fecha']) . ').' . $pen];
            }
        }
        $orden = ['critica' => 0, 'aviso' => 1, 'info' => 2];
        usort($out, fn($a, $b) => [$orden[$a['nivel']], $a['fecha']] <=> [$orden[$b['nivel']], $b['fecha']]);
        return $out;
    }
}
