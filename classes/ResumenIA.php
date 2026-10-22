<?php

namespace Classes;

use Model\Proyecto;

/**
 * Resumen diario del administrador (una pantalla, no un correo): qué cambió la IA, qué espera aprobación, qué herramienta está
 * desactualizada, qué detectó la vigilancia y cuánto se usó el modelo. Solo junta datos que ya calculan las demás clases.
 */
class ResumenIA
{
    use AccesoDb;

    public const PERIODOS = [24 => 'Últimas 24 horas', 72 => 'Últimos 3 días', 168 => 'Última semana'];

    public static function datos(Proyecto $p, int $horas = 24): array
    {
        $pid = (int)$p->id;
        $desde = date('Y-m-d H:i:s', time() - $horas * 3600);
        $d = "'" . self::esc($desde) . "'";

        $porEvento = [];
        foreach (self::filas("SELECT evento, herramienta, COUNT(*) n FROM auditoria_cambios WHERE proyecto_id = $pid AND creado >= $d GROUP BY evento, herramienta") as $f) {
            $porEvento[$f['evento']][$f['herramienta']] = (int)$f['n'];
        }
        $cambios = [
            'conteo' => array_map('array_sum', $porEvento),
            'por_evento' => $porEvento,
            'recientes' => self::filas("SELECT evento, herramienta, resumen, entidad_nombre, usuario, creado FROM auditoria_cambios WHERE proyecto_id = $pid AND creado >= $d ORDER BY id DESC LIMIT 8"),
            'revertibles' => count(TandaIA::candidatos($pid, $desde)),
        ];

        $pend = CentroIA::pendientes($p);
        $items = $pend['items'];
        $pendientes = [
            'total' => count($items),
            'por_herramienta' => $pend['conteo'],
            'bajo_riesgo' => $pend['bajo_riesgo'],
            'graves' => array_slice(array_values(array_filter($items, fn($x) => in_array($x['severidad'], ['critica', 'alta'], true))), 0, 5),
        ];

        $herr = array_values(array_filter(Revisiones::herramientas($pid), fn($h) => $h['estado'] !== 'al_dia'));

        $uso = UsoIA::resumen($pid, max(1, (int)ceil($horas / 24)));

        return ['desde' => $desde, 'horas' => $horas, 'cambios' => $cambios, 'pendientes' => $pendientes, 'herramientas' => $herr,
            'vigilancia' => Inconsistencias::estadoVigilancia($pid), 'vigilancia_servidor' => VigilanciaServidor::config($pid), 'uso' => $uso['total']];
    }
}
