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

    /** Nombre debajo del icono en la barra superior de herramientas (proyectos.nombres_herramientas) */
    public const NOMBRES = [
        '1' => 'Sí',
        '0' => 'No',
    ];

    /** ¿La barra superior de herramientas del proyecto escribe el nombre de cada una? */
    public static function nombresHerramientas($valor): bool
    {
        return (int)$valor === 1;
    }

    /**
     * Mensajes emergentes de ayuda (burbujas de src/js/ayuda.js) en la interfaz del proyecto:
     * proyectos.ayuda_burbujas. Se guarda en la BD, así que vale para todos los que abran el proyecto.
     */
    public const AYUDAS = [
        '1' => 'Sí, mostrar los mensajes de ayuda',
        '0' => 'No, ocultarlos',
    ];

    /** ¿El proyecto muestra las burbujas de ayuda? (por defecto sí) */
    public static function ayudasVisibles($valor): bool
    {
        return (int)$valor === 1;
    }

    /**
     * ¿Existe la columna `ayuda_burbujas`? (migración database/migracion_ayuda_burbujas.sql).
     * Sin ella la app sigue funcionando: se muestran las ayudas y el control queda desactivado.
     */
    public static function disponibleAyuda(): bool
    {
        static $hay = null;
        if ($hay !== null) return $hay;
        $db = $GLOBALS['db'] ?? null;
        if (!is_object($db)) return $hay = false;
        try {
            $r = $db->query("SHOW COLUMNS FROM proyectos LIKE 'ayuda_burbujas'");
        } catch (\Throwable $e) {
            return $hay = false;
        }
        return $hay = (bool)($r && $r->num_rows);
    }

    /**
     * Apariencia de la barra superior de herramientas (proyectos.menu_superior):
     * clásico (iconos por sección), panel (sin barra, menú lateral fijo) o pestañas (una pestaña por sección).
     */
    public const MENUS = [
        'clasico'  => 'Modo clásico',
        'panel'    => 'Modo panel',
        'pestanas' => 'Modo pestaña',
    ];

    /** Texto de ayuda de cada modo (bajo el selector de Apariencia del proyecto) */
    public const MENUS_AYUDA = [
        'clasico'  => 'Los iconos de las herramientas se agrupan por sección en una sola barra, como hasta ahora.',
        'panel'    => 'Oculta la barra de iconos y deja fijo el menú lateral con todas las herramientas del proyecto.',
        'pestanas' => 'Los iconos se acomodan por sección en pestañas: eliges la sección y ves sus herramientas.',
    ];

    /** Modo válido o 'clasico' */
    public static function normMenu($menu): string
    {
        $menu = (string)$menu;
        return isset(self::MENUS[$menu]) ? $menu : 'clasico';
    }

    public static function esPanel($valor): bool
    {
        return self::normMenu($valor) === 'panel';
    }

    public static function esPestanas($valor): bool
    {
        return self::normMenu($valor) === 'pestanas';
    }

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

    /**
     * Texturas del fondo del proyecto (Ajustes → Apariencia).
     * id => [nombre, descripción]
     */
    public const TEXTURAS = [
        'ninguna'    => ['Ninguna', 'Fondo limpio, solo el color del tema.'],
        'grano'      => ['Grano', 'Papel impreso: un ruido fino y neutro en toda la pantalla.'],
        'puntos'     => ['Puntos', 'Retícula discreta de puntos, como una hoja de dibujo.'],
        'vetas'      => ['Vetas', 'Líneas diagonales muy suaves, tipo papel técnico.'],
        'trama'      => ['Trama', 'Cuadrícula fina horizontal y vertical.'],
        'cuadricula' => ['Cuadrícula', 'Cuadrícula amplia, útil para planos y croquis.'],
        'diagonal'   => ['Diagonal', 'Rayado diagonal espaciado, con aire industrial.'],
        'papel'      => ['Papel', 'Grano más grueso y cálido, como un plano impreso.'],
    ];

    public const HALO_INTENSIDAD_DEFECTO = 45;
    public const HALO_EXPANSION_DEFECTO = 100;
    public const TEXTURA_INTENSIDAD_DEFECTO = 35;

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
    public static function css(string $paleta, array $apariencia = []): string
    {
        $id = self::normPaleta($paleta);
        $css = '';

        if ($id !== self::POR_DEFECTO) {
            [, $claro, $oscuro] = self::PALETAS[$id];
            $rc = self::rgb($claro);
            $ro = self::rgb($oscuro);
            // Texto legible encima del color primario (blanco u oscuro), calculado por contraste
            $onClaro = self::textoSobre($claro);
            $onOscuro = self::textoSobre($oscuro);
            $onClaroHover = self::textoSobre(self::oscurecer($claro, 0.12));
            $onOscuroHover = self::textoSobre(self::oscurecer($oscuro, 0.12));
            // Tinta del acento para TEXTO (pestañas activas, enlaces de acento): el color de la
            // paleta no siempre llega a 4.5:1 sobre la superficie, así que se ajusta hasta lograrlo.
            $inkClaro = self::tinta($claro, '#f5f7fa');
            $inkOscuro = self::tinta($oscuro, '#1e293b');

            $css .= ':root{--primary:' . $oscuro
                . ';--primary-ink:' . $inkOscuro
                . ';--on-primary:' . $onOscuro
                . ';--on-primary-hover:' . $onOscuroHover
                . ';--primary-hover:' . self::oscurecer($oscuro, 0.12)
                . ';--primary-glow:rgba(' . implode(',', $ro) . ',.25)}'
                . ':root[data-theme="light"]{--primary:' . $claro
                . ';--primary-ink:' . $inkClaro
                . ';--on-primary:' . $onClaro
                . ';--on-primary-hover:' . $onClaroHover
                . ';--primary-hover:' . self::oscurecer($claro, 0.12)
                . ';--primary-glow:rgba(' . implode(',', $rc) . ',.15)}'
                . ':root[data-theme="dark"]{--primary:' . $oscuro
                . ';--primary-ink:' . $inkOscuro
                . ';--on-primary:' . $onOscuro
                . ';--on-primary-hover:' . $onOscuroHover
                . ';--primary-hover:' . self::oscurecer($oscuro, 0.12)
                . ';--primary-glow:rgba(' . implode(',', $ro) . ',.25)}';
        }

        return $css . self::cssFondo($id, $apariencia);
    }

    /**
     * Variables CSS del fondo: halo radial (color, intensidad y expansión) + textura.
     * El color se resuelve con variables por tema para que al cambiar claro/oscuro se
     * adapte solo; si hay un color propio, manda ese en ambos temas.
     */
    public static function cssFondo(string $paleta, array $ap = []): string
    {
        [, $claro, $oscuro] = self::PALETAS[self::normPaleta($paleta)];
        $textura = self::normTextura($ap['textura'] ?? 'ninguna');
        $texInt = self::rango($ap['textura_intensidad'] ?? self::TEXTURA_INTENSIDAD_DEFECTO, 0, 100, self::TEXTURA_INTENSIDAD_DEFECTO);
        $haloInt = self::rango($ap['halo_intensidad'] ?? self::HALO_INTENSIDAD_DEFECTO, 0, 100, self::HALO_INTENSIDAD_DEFECTO);
        $haloExp = self::rango($ap['halo_expansion'] ?? self::HALO_EXPANSION_DEFECTO, 50, 250, self::HALO_EXPANSION_DEFECTO) / 100;
        $propio = self::normHex($ap['halo_color'] ?? '');
        $rc = $propio !== '' ? $propio : $claro;
        $ro = $propio !== '' ? $propio : $oscuro;

        $factor = $haloInt / 100;
        $a1 = self::alpha(0.13 * $factor);
        $a2 = self::alpha(0.15 * $factor);
        $a3 = self::alpha(0.08 * $factor);

        $capas = [];
        $tamanos = [];
        if ($haloInt > 0) {
            $capas[] = 'radial-gradient(calc(1100px * var(--pw-halo-exp, 1)) calc(620px * var(--pw-halo-exp, 1)) at 8% -12%, rgba(var(--pw-halo-rgb), ' . $a1 . '), transparent 60%)';
            $capas[] = 'radial-gradient(calc(900px * var(--pw-halo-exp, 1)) calc(520px * var(--pw-halo-exp, 1)) at 102% 4%, rgba(var(--pw-halo-rgb), ' . $a2 . '), transparent 58%)';
            $capas[] = 'radial-gradient(calc(700px * var(--pw-halo-exp, 1)) calc(420px * var(--pw-halo-exp, 1)) at 50% 112%, rgba(var(--pw-halo-rgb), ' . $a3 . '), transparent 65%)';
            $tamanos = ['auto', 'auto', 'auto'];
        }

        if ($textura !== 'ninguna' && $texInt > 0) {
            [$capa, $tam] = self::texturaCapa($textura, $texInt);
            if ($capa !== '') {
                $capas[] = $capa;
                $tamanos[] = $tam;
            }
        }

        $fondo = $capas ? implode(', ', $capas) : 'none';
        $size = $tamanos ? implode(', ', $tamanos) : 'auto';

        return ':root{--pw-halo-rgb:' . implode(',', self::rgb($ro))
            . ';--pw-tex-rgb:226,232,240'
            . ';--pw-halo-exp:' . $haloExp
            . ';--pw-halo-a1:' . $a1
            . ';--pw-halo-a2:' . $a2
            . ';--pw-halo-a3:' . $a3
            . ';--pw-fondo:' . $fondo
            . ';--pw-fondo-size:' . $size . '}'
            . ':root[data-theme="light"]{--pw-halo-rgb:' . implode(',', self::rgb($rc)) . ';--pw-tex-rgb:15,23,42}'
            . ':root[data-theme="dark"]{--pw-halo-rgb:' . implode(',', self::rgb($ro)) . ';--pw-tex-rgb:226,232,240}';
    }

    /** Textura de fondo: devuelve [capa CSS, tamaño de la capa] */
    private static function texturaCapa(string $tipo, int $intensidad): array
    {
        $a = $intensidad / 100;
        $rgb = 'var(--pw-tex-rgb)';

        switch ($tipo) {
            case 'grano':
            case 'papel':
                $op = self::alpha($tipo === 'papel' ? 0.22 * $a : 0.16 * $a);
                $frec = $tipo === 'papel' ? '.95' : '.85';
                $opacidad = self::alpha(($tipo === 'papel' ? 0.55 : 0.45) * $a);
                $svg = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='{$frec}' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='{$opacidad}'/%3E%3C/svg%3E\")";
                return [$svg, $tipo === 'papel' ? '240px 240px' : '180px 180px'];

            case 'puntos':
                $c = self::alpha(0.30 * $a);
                return ['radial-gradient(rgba(' . $rgb . ', ' . $c . ') 1px, transparent 1.35px)', '22px 22px'];

            case 'vetas':
                $c = self::alpha(0.075 * $a);
                return ['repeating-linear-gradient(115deg, rgba(' . $rgb . ', ' . $c . ') 0 1px, transparent 1px 8px)', 'auto'];

            case 'trama':
                $c = self::alpha(0.055 * $a);
                return ['repeating-linear-gradient(0deg, rgba(' . $rgb . ', ' . $c . ') 0 1px, transparent 1px 26px), repeating-linear-gradient(90deg, rgba(' . $rgb . ', ' . $c . ') 0 1px, transparent 1px 26px)', 'auto, auto'];

            case 'cuadricula':
                $c = self::alpha(0.07 * $a);
                return ['linear-gradient(rgba(' . $rgb . ', ' . $c . ') 1px, transparent 1px), linear-gradient(90deg, rgba(' . $rgb . ', ' . $c . ') 1px, transparent 1px)', '32px 32px, 32px 32px'];

            case 'diagonal':
                $c = self::alpha(0.07 * $a);
                return ['repeating-linear-gradient(45deg, rgba(' . $rgb . ', ' . $c . ') 0 1.5px, transparent 1.5px 13px)', 'auto'];
        }

        return ['', ''];
    }

    /** Opciones de apariencia normalizadas de un proyecto (texturas + halo). */
    public static function opciones($proyecto): array
    {
        return [
            'textura' => self::normTextura($proyecto->tema_textura ?? 'ninguna'),
            'textura_intensidad' => self::rango($proyecto->tema_textura_intensidad ?? self::TEXTURA_INTENSIDAD_DEFECTO, 0, 100, self::TEXTURA_INTENSIDAD_DEFECTO),
            'halo_color' => self::normHex($proyecto->tema_halo_color ?? ''),
            'halo_intensidad' => self::rango($proyecto->tema_halo_intensidad ?? self::HALO_INTENSIDAD_DEFECTO, 0, 100, self::HALO_INTENSIDAD_DEFECTO),
            'halo_expansion' => self::rango($proyecto->tema_halo_expansion ?? self::HALO_EXPANSION_DEFECTO, 50, 250, self::HALO_EXPANSION_DEFECTO),
        ];
    }

    /** Intensidad 0-100 (textura o halo) */
    public static function intensidad($valor, int $defecto = 45): int
    {
        return self::rango($valor, 0, 100, $defecto);
    }

    /** Expansión del halo 50-250 (%) */
    public static function expansion($valor): int
    {
        return self::rango($valor, 50, 250, self::HALO_EXPANSION_DEFECTO);
    }

    public static function normTextura($textura): string
    {
        $textura = (string)$textura;
        return isset(self::TEXTURAS[$textura]) ? $textura : 'ninguna';
    }

    /** #rgb o #rrggbb válido, normalizado a #rrggbb; cualquier otra cosa se descarta. */
    public static function normHex($color): string
    {
        $color = trim((string)$color);
        if (!preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color, $m)) return '';
        $hex = $m[1];
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        return '#' . strtolower($hex);
    }

    /** @return bool ¿Existen las columnas de textura/halo? (migración database/migracion_tema_texturas.sql) */
    public static function disponibleTexturas(): bool
    {
        static $hay = null;
        if ($hay !== null) return $hay;
        $db = $GLOBALS['db'] ?? null;
        if (!is_object($db)) return $hay = false;
        try {
            $r = $db->query("SHOW COLUMNS FROM proyectos LIKE 'tema_textura'");
        } catch (\Throwable $e) {
            return $hay = false;
        }
        return $hay = (bool)($r && $r->num_rows);
    }

    private static function rango($valor, int $min, int $max, int $defecto): int
    {
        if (!is_numeric($valor)) return $defecto;
        return max($min, min($max, (int)$valor));
    }

    private static function alpha(float $valor): string
    {
        return number_format(max(0, min(1, $valor)), 3, '.', '');
    }

    /**
     * Tinta legible del acento: devuelve un tono de la misma familia que llega a 4.5:1
     * sobre la superficie indicada (oscurece sobre fondos claros, aclara sobre oscuros).
     */
    private static function tinta(string $hex, string $fondo): string
    {
        $rgb = self::rgb($hex);
        $fondoRgb = self::rgb($fondo);
        $luz = static function (array $c): float {
            $lin = static function (int $v): float {
                $v /= 255;
                return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
            };
            return 0.2126 * $lin($c[0]) + 0.7152 * $lin($c[1]) + 0.0722 * $lin($c[2]);
        };
        $contraste = static function (array $a, array $b) use ($luz): float {
            $la = $luz($a);
            $lb = $luz($b);
            return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
        };
        $hacia = $luz($fondoRgb) > 0.35 ? 0 : 255;   // fondo claro → oscurecer; oscuro → aclarar
        for ($i = 0; $i < 12 && $contraste($rgb, $fondoRgb) < 4.5; $i++) {
            $rgb = array_map(static fn ($v) => (int)round($v + ($hacia - $v) * 0.1), $rgb);
        }
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
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

    /**
     * Color de texto que se lee mejor encima de $hex: blanco o el texto oscuro de la app.
     * Usa el contraste WCAG (4.5:1) para no dejar botones ilegibles con paletas claras (ámbar, oro, lima…).
     */
    private static function textoSobre(string $hex): string
    {
        $lum = function (int $v): float {
            $c = $v / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = self::rgb($hex);
        $L = 0.2126 * $lum($r) + 0.7152 * $lum($g) + 0.0722 * $lum($b);
        $conBlanco = 1.05 / ($L + 0.05);
        $conOscuro = ($L + 0.05) / 0.05;   // #000 aproximado; el texto de la app (#0f172a) da casi lo mismo
        return $conBlanco >= $conOscuro ? '#ffffff' : '#0f172a';
    }

    /** Mezcla el color con negro (0 = igual, 0.5 = mitad de brillo) */
    private static function oscurecer(string $hex, float $cantidad): string
    {
        $factor = 1 - max(0.0, min(1.0, $cantidad));
        $partes = array_map(fn($v) => (int)round($v * $factor), self::rgb($hex));
        return sprintf('#%02x%02x%02x', ...$partes);
    }
}
