<?php

namespace Classes;

/**
 * Catálogo de planes comerciales y sus límites.
 *
 * La fuente de verdad de los límites es esta clase; la tabla `planes` existe para
 * poder ajustar precios/ límites sin desplegar, pero la app funciona aunque la
 * migración no esté aplicada (modo legacy).
 *
 * Convención: `null` = ilimitado. Los límites de obras y usuarios son de la
 * organización, no del usuario.
 */
class Plan
{
    public const POR_DEFECTO = 'obra';
    public const LEGACY = 'legacy';

    /** Planes comerciales. El orden es el que se muestra en la landing y en el admin. */
    public const PLANES = [
        'obra' => [
            'clave' => 'obra',
            'nombre' => 'Obra',
            'lema' => 'Para el contratista que arranca su control digital.',
            'precio_mensual' => 390,
            'precio_anual' => 3900,
            'obras' => 2,
            'usuarios' => 2,
            'visitantes' => null,
            'almacenamiento_gb' => 5,
            'almacenamiento_por_obra_mb' => 2048,
            'permite_gestores' => false,
            'features' => [],
            'destacado' => false,
        ],
        'pro' => [
            'clave' => 'pro',
            'nombre' => 'Pro',
            'lema' => 'Para empresas con varias obras en paralelo.',
            'precio_mensual' => 990,
            'precio_anual' => 9900,
            'obras' => 8,
            'usuarios' => 6,
            'visitantes' => null,
            'almacenamiento_gb' => 30,
            'almacenamiento_por_obra_mb' => 6144,
            'permite_gestores' => true,
            'features' => ['gestores', 'ia', 'api'],
            'destacado' => true,
        ],
        'constructora' => [
            'clave' => 'constructora',
            'nombre' => 'Constructora',
            'lema' => 'Para grupos con portafolio y varios equipos.',
            'precio_mensual' => 2490,
            'precio_anual' => 24900,
            'obras' => 25,
            'usuarios' => 15,
            'visitantes' => null,
            'almacenamiento_gb' => 100,
            'almacenamiento_por_obra_mb' => 10240,
            'permite_gestores' => true,
            'features' => ['gestores', 'ia', 'api', 'sso', 'marca'],
            'destacado' => false,
        ],
        'corporativo' => [
            'clave' => 'corporativo',
            'nombre' => 'Corporativo',
            'lema' => 'Para grupos constructores y grandes portafolios.',
            'precio_mensual' => 5900,
            'precio_anual' => 59000,
            'obras' => null,
            'usuarios' => null,
            'visitantes' => null,
            'almacenamiento_gb' => 300,
            'almacenamiento_por_obra_mb' => 25600,
            'permite_gestores' => true,
            'features' => ['gestores', 'ia', 'api', 'sso', 'marca', 'multiempresa'],
            'destacado' => false,
        ],
        // Los clientes que ya existían conservan sus límites y todas las funciones.
        self::LEGACY => [
            'clave' => self::LEGACY,
            'nombre' => 'Legacy',
            'lema' => 'Plan heredado de los clientes anteriores.',
            'precio_mensual' => 0,
            'precio_anual' => 0,
            'obras' => null,
            'usuarios' => null,
            'visitantes' => null,
            'almacenamiento_gb' => null,
            'almacenamiento_por_obra_mb' => 300,
            'permite_gestores' => false,
            'features' => ['ia', 'api', 'sso', 'marca', 'multiempresa'],
            'destacado' => false,
        ],
    ];

    /** Planes que se ofrecen en la landing (sin legacy). */
    public static function comerciales(): array
    {
        return array_values(array_filter(self::PLANES, fn($p) => $p['clave'] !== self::LEGACY));
    }

    /** Todos los planes, incluido legacy. */
    public static function todos(): array
    {
        return self::PLANES;
    }

    /** Clave válida; si no existe, el plan por defecto. */
    public static function normalizar(?string $clave): string
    {
        $clave = strtolower(trim((string)$clave));
        return isset(self::PLANES[$clave]) ? $clave : self::POR_DEFECTO;
    }

    /** Definición de un plan (siempre devuelve un array). */
    public static function de(?string $clave): array
    {
        $clave = self::normalizar($clave);
        return self::PLANES[$clave];
    }

    /**
     * Límites efectivos del plan, con overrides opcionales (legacy o acuerdos).
     * Devuelve claves: obras, usuarios, visitantes, almacenamiento_gb,
     * almacenamiento_por_obra_mb, features, permite_gestores.
     */
    public static function limites(?string $clave, array $override = []): array
    {
        $p = self::de($clave);
        $lim = [
            'obras' => $p['obras'],
            'usuarios' => $p['usuarios'],
            'visitantes' => $p['visitantes'],
            'almacenamiento_gb' => $p['almacenamiento_gb'],
            'almacenamiento_por_obra_mb' => $p['almacenamiento_por_obra_mb'],
            'features' => $p['features'],
            'permite_gestores' => $p['permite_gestores'],
        ];
        foreach (['obras', 'usuarios', 'visitantes', 'almacenamiento_gb', 'almacenamiento_por_obra_mb', 'permite_gestores'] as $k) {
            if (array_key_exists($k, $override) && $override[$k] !== null && $override[$k] !== '') {
                $lim[$k] = is_bool($override[$k]) ? $override[$k] : (int)$override[$k];
            }
        }
        if (isset($override['features']) && is_array($override['features'])) {
            $lim['features'] = array_values(array_unique(array_merge($lim['features'], $override['features'])));
        }
        return $lim;
    }

    /** ¿El plan permite una función? */
    public static function permite(?string $clave, string $feature): bool
    {
        return in_array($feature, self::de($clave)['features'], true);
    }

    /** Etiqueta legible del plan. */
    public static function etiqueta(?string $clave): string
    {
        return self::de($clave)['nombre'];
    }

    /** Precio formateado en MXN. */
    public static function precio(?string $clave, bool $anual = false): string
    {
        $p = self::de($clave);
        return '$' . number_format($anual ? $p['precio_anual'] : $p['precio_mensual'], 0, '.', ',');
    }

    /** Texto de un límite: "3 obras", "Ilimitados", etc. */
    public static function textoLimite(?int $n, string $singular, string $plural): string
    {
        if ($n === null) return 'Ilimitados';
        return $n . ' ' . ($n === 1 ? $singular : $plural);
    }

    /** Precio por obra al mes (para la landing). */
    public static function precioPorObra(?string $clave): ?string
    {
        $p = self::de($clave);
        if (empty($p['obras'])) return null;
        return '$' . number_format($p['precio_mensual'] / $p['obras'], 0, '.', ',');
    }
}
