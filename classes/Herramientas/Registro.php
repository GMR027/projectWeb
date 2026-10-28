<?php

namespace Classes\Herramientas;

/** Herramientas de IA que generan informes. Para sumar una: crear su clase e incluirla aquí (y nada más: el menú de escritorio las descubre). */
class Registro
{
    /** @return array<string, class-string<Herramienta>> */
    public static function todas(): array
    {
        $clases = [ReporteDiario::class, ResumenSemanal::class, DictadoBitacora::class, ComandosVoz::class, Minuta::class, NormativaAplicar::class, Lookahead::class, CausasRetraso::class, RiesgosPatron::class, Simulador::class, ClimaCalendario::class, FotosAvance::class, SeguridadCharla::class, PunchDictado::class, PunchAnalisis::class, CierreProyecto::class, ResumenEjecutivoDiario::class, PruebasCalidad::class, PrediccionAtrasos::class, RedactarRfi::class, Contradicciones::class, ItpCalidad::class, ComprasMateriales::class];
        $out = [];
        foreach ($clases as $c) $out[$c::id()] = $c;
        return $out;
    }

    public static function obtener(string $id): ?string
    {
        return self::todas()[$id] ?? null;
    }

    /** Fichas para el menú de escritorio y los formularios web */
    public static function fichas(): array
    {
        $out = [];
        foreach (self::todas() as $id => $c) $out[] = ['id' => $id] + $c::ficha();
        return $out;
    }

    /** Valida y completa los parámetros recibidos según la ficha de la herramienta */
    public static function parametros(string $clase, array $recibidos): array
    {
        $out = [];
        foreach ($clase::ficha()['parametros'] ?? [] as $p) {
            $v = $recibidos[$p['nombre']] ?? ($p['defecto'] ?? '');
            $v = is_string($v) ? trim($v) : $v;
            switch ($p['tipo']) {
                case 'fecha':
                    $d = \DateTime::createFromFormat('Y-m-d', (string)$v);
                    $v = $d && $d->format('Y-m-d') === $v ? $v : ($p['defecto'] ?? date('Y-m-d'));
                    break;
                case 'numero':
                    $v = is_numeric($v) ? max($p['min'] ?? 0, min($p['max'] ?? 1000000, (float)$v)) : ($p['defecto'] ?? 0);
                    if (floor($v) == $v) $v = (int)$v;
                    break;
                case 'opcion':
                    $v = array_key_exists($v, $p['opciones'] ?? []) ? $v : ($p['defecto'] ?? array_key_first($p['opciones']));
                    break;
                default:
                    $v = mb_substr((string)$v, 0, (int)($p['max'] ?? 4000));
            }
            $out[$p['nombre']] = $v;
        }
        return $out;
    }
}
