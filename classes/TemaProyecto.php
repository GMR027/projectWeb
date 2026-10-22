<?php

namespace Classes;

/**
 * Tema visual de un proyecto (Ajustes → Apariencia del proyecto).
 *
 * - El modo (usuario / claro / oscuro) se guarda en `proyectos.tema_modo`.
 * - El acento se guarda en `proyectos.tema_paleta` y se aplica con las mismas
 *   variables CSS de la paleta ya cargada (`--primary`, `--primary-hover`,
 *   `--primary-glow`): no hay colores nuevos, cada paleta es el tono claro y
 *   oscuro de una familia que la app ya usa.
 *
 * Se guarda en la base de datos, así que se mantiene al cambiar de navegador,
 * de equipo o de usuario. Las exportaciones (PDF, ZIP) no dependen de esto.
 */
class TemaProyecto
{
    public const POR_DEFECTO = 'naranja';

    public const MODOS = [
        'usuario' => 'Como cada usuario (claro u oscuro)',
        'claro'   => 'Siempre claro',
        'oscuro'  => 'Siempre oscuro',
    ];

    /** id => [nombre, tono para tema claro, tono para tema oscuro] */
    public const PALETAS = [
        'naranja'   => ['Naranja CivilTech', '#ea580c', '#f97316'],
        'teja'      => ['Teja',              '#c2410c', '#ea580c'],
        'ambar'     => ['Ámbar',             '#d97706', '#f59e0b'],
        'oro'       => ['Oro',               '#ca8a04', '#eab308'],
        'arena'     => ['Arena',             '#a16207', '#ca8a04'],
        'lima'      => ['Lima',              '#65a30d', '#84cc16'],
        'oliva'     => ['Oliva',             '#4d7c0f', '#65a30d'],
        'verde'     => ['Verde',             '#059669', '#10b981'],
        'esmeralda' => ['Esmeralda',         '#0d9488', '#14b8a6'],
        'turquesa'  => ['Turquesa',          '#0f766e', '#0d9488'],
        'cian'      => ['Cian',              '#0891b2', '#06b6d4'],
        'cielo'     => ['Cielo',             '#0284c7', '#0ea5e9'],
        'azul'      => ['Azul',              '#2563eb', '#3b82f6'],
        'indigo'    => ['Índigo',            '#4f46e5', '#6366f1'],
        'violeta'   => ['Violeta',           '#7c3aed', '#8b5cf6'],
        'purpura'   => ['Púrpura',           '#9333ea', '#a855f7'],
        'magenta'   => ['Magenta',           '#c026d3', '#d946ef'],
        'rosa'      => ['Rosa',              '#db2777', '#ec4899'],
        'rojo'      => ['Rojo',              '#dc2626', '#ef4444'],
        'ladrillo'  => ['Ladrillo',          '#b91c1c', '#dc2626'],
        'burdeos'   => ['Burdeos',           '#9f1239', '#be123c'],
        'cafe'      => ['Café',              '#92400e', '#b45309'],
        'pizarra'   => ['Pizarra',           '#475569', '#64748b'],
        'grafito'   => ['Grafito',           '#334155', '#475569'],
    ];

    /** Modo válido o 'usuario' */
    public static function normModo($modo): string
    {
        $modo = (string)$modo;
        return isset(self::MODOS[$modo]) ? $modo : 'usuario';
    }

    /**
     * Tema CSS que fija el proyecto: 'light', 'dark' o null si manda el usuario.
     * Ojo: los valores de MODOS ('claro'/'oscuro') no son los del CSS
     * (`data-theme="light"` / `data-theme="dark"`); este es el único punto donde se traducen.
     */
    public static function temaForzado($modo): ?string
    {
        return match (self::normModo($modo)) {
            'claro'  => 'light',
            'oscuro' => 'dark',
            default  => null,
        };
    }

    /** Paleta válida o la de por defecto */
    public static function normPaleta($paleta): string
    {
        $paleta = (string)$paleta;
        return isset(self::PALETAS[$paleta]) ? $paleta : self::POR_DEFECTO;
    }

    public static function nombre(string $paleta): string
    {
        return self::PALETAS[self::normPaleta($paleta)][0];
    }

    /** Color con el que se pinta la carátula del portafolio (proyectos.color) */
    public static function colorCaratula(string $paleta): string
    {
        return strtoupper(self::PALETAS[self::normPaleta($paleta)][1]);
    }

    /**
     * CSS que aplica la paleta. Devuelve '' para la paleta base, que ya está en app.css.
     * Se emiten los dos temas porque el usuario puede alternar claro/oscuro cuando
     * el proyecto no fija el modo.
     */
    public static function css(string $paleta): string
    {
        $id = self::normPaleta($paleta);
        if ($id === self::POR_DEFECTO) {
            return '';
        }

        [, $claro, $oscuro] = self::PALETAS[$id];
        $rc = self::rgb($claro);
        $ro = self::rgb($oscuro);

        return ':root{--primary:' . $oscuro
            . ';--primary-hover:' . self::oscurecer($oscuro, 0.12)
            . ';--primary-glow:rgba(' . implode(',', $ro) . ',.25)}'
            . ':root[data-theme="light"]{--primary:' . $claro
            . ';--primary-hover:' . self::oscurecer($claro, 0.12)
            . ';--primary-glow:rgba(' . implode(',', $rc) . ',.15)}'
            . ':root[data-theme="dark"]{--primary:' . $oscuro
            . ';--primary-hover:' . self::oscurecer($oscuro, 0.12)
            . ';--primary-glow:rgba(' . implode(',', $ro) . ',.25)}';
    }

    /** @return int[] [r, g, b] */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            (int)hexdec(substr($hex, 0, 2)),
            (int)hexdec(substr($hex, 2, 2)),
            (int)hexdec(substr($hex, 4, 2)),
        ];
    }

    /** Mezcla el color con negro (0 = igual, 0.5 = mitad de brillo) */
    private static function oscurecer(string $hex, float $cantidad): string
    {
        $factor = 1 - max(0.0, min(1.0, $cantidad));
        $partes = array_map(fn($v) => (int)round($v * $factor), self::rgb($hex));
        return sprintf('#%02x%02x%02x', ...$partes);
    }
}
