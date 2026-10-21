<?php

namespace Classes;

use Model\GraficoLamina;
use Model\Grafico;
use Model\Simbolo;

/**
 * Vínculo de una imagen colocada (bloque «imagen» de vPlan, imagen del Editor) con su origen: una lámina de gráfico/simbología
 * (`g:<gráfico>:<lámina>`) o un símbolo de la biblioteca del proyecto (`s:<símbolo>`). La copia colocada guarda el `token` del origen
 * con que se hizo; si el origen cambia (se vuelve a guardar la simbología, se reemplaza el símbolo), el token es otro y la copia se renueva.
 */
class ImagenVinculada
{
    public const PATRON = '/^(?:g:\d{1,9}:\d{1,9}|s:\d{1,9})$/';

    public static function valida($ref): bool
    {
        return is_string($ref) && (bool)preg_match(self::PATRON, $ref);
    }

    /** @return array{ruta: string, token: string, src: string}|null  null = el origen ya no existe (la copia se queda como está) */
    public static function actual(string $ref, int $proyectoId): ?array
    {
        if (!self::valida($ref)) return null;
        $p = explode(':', $ref);
        try {
            if ($p[0] === 'g') {
                $l = GraficoLamina::find((int)$p[2]);
                $g = $l ? Grafico::find((int)$l->grafico_id) : null;
                if (!$l || !$g || (int)$g->id !== (int)$p[1] || (int)$g->proyecto_id !== $proyectoId || !$l->marcas_png) return null;
                $ruta = Imagenes::CARPETA_VPLAN . '/' . basename($l->marcas_png);
                return is_file($ruta) ? ['ruta' => $ruta, 'token' => basename($l->marcas_png), 'src' => '/uploads/vplan/' . basename($l->marcas_png)] : null;
            }
            $s = Simbolo::find((int)$p[1]);
            if (!$s || (int)$s->proyecto_id !== $proyectoId || !$s->archivo) return null;
            $ruta = __DIR__ . '/../public/uploads/' . Simbolo::CARPETA . '/' . basename($s->archivo);
            return is_file($ruta) ? ['ruta' => $ruta, 'token' => basename($s->archivo), 'src' => $s->url()] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
