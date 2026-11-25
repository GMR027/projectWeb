<?php

namespace Classes;

/**
 * Modelo de ahorro de la landing.
 *
 * Compara el costo anual de referencia de las licencias que ProjectWeb
 * reemplaza contra el plan anual que cubre al equipo y sus obras.
 * Los costos son de referencia y se muestran con sus supuestos en la página.
 */
class Ahorro
{
    /** Costo mensual de referencia por rol (MXN). */
    public const ROLES = [
        'direccion' => [
            'nombre' => 'Dirección / PM',
            'detalle' => 'Programación, ofimática, PDF/planos, tablero y almacenamiento',
            'costo' => 1500,
            'inicial' => 1,
        ],
        'residente' => [
            'nombre' => 'Residentes',
            'detalle' => 'Ofimática, PDF/planos, campo/reportes y almacenamiento',
            'costo' => 1000,
            'inicial' => 2,
        ],
        'administracion' => [
            'nombre' => 'Administración',
            'detalle' => 'Ofimática, PDF/planos, tablero y almacenamiento',
            'costo' => 950,
            'inicial' => 1,
        ],
        'campo' => [
            'nombre' => 'Campo',
            'detalle' => 'Ofimática básica y almacenamiento',
            'costo' => 100,
            'inicial' => 2,
        ],
    ];

    /** Escenarios de referencia que se muestran en la tabla. */
    public const ESCENARIOS = [
        'pequeno' => [
            'nombre' => 'Pequeño',
            'detalle' => 'Contratista con una obra y un equipo compacto',
            'roles' => ['direccion' => 1, 'residente' => 1, 'administracion' => 0, 'campo' => 0],
            'obras' => 1,
        ],
        'medio' => [
            'nombre' => 'Medio',
            'detalle' => 'Empresa con varias obras y equipo de campo',
            'roles' => ['direccion' => 1, 'residente' => 2, 'administracion' => 1, 'campo' => 2],
            'obras' => 3,
        ],
        'constructora' => [
            'nombre' => 'Constructora',
            'detalle' => 'Varias obras, oficina técnica y cuadrillas',
            'roles' => ['direccion' => 2, 'residente' => 4, 'administracion' => 2, 'campo' => 7],
            'obras' => 10,
        ],
        'corporativo' => [
            'nombre' => 'Corporativo',
            'detalle' => 'Portafolio amplio y varios equipos',
            'roles' => ['direccion' => 3, 'residente' => 6, 'administracion' => 3, 'campo' => 8],
            'obras' => 30,
        ],
    ];

    /** Licencias que ProjectWeb reemplaza, para el desglose. */
    public const REEMPLAZOS = [
        ['Programación', 'fa-chart-gantt', 600, 'MS Project, Primavera o ProjectLibre', 'Programa, Gantt, dependencias, ruta crítica, líneas base y curvas'],
        ['Ofimática', 'fa-file-word', 250, 'Microsoft 365 o Google Workspace', 'Redactor, SheetFlow, minutas, documentos y tablas'],
        ['PDF y planos', 'fa-file-pdf', 350, 'Acrobat Pro, Bluebeam o similar', 'Archivo, visor, comparador, anotador y unir PDF'],
        ['Campo y reportes', 'fa-clipboard-list', 300, 'App de campo, reportes y evidencias', 'Reporte diario, bitácora, evidencias, seguridad, calidad y punch'],
        ['Tablero y gestión', 'fa-table-columns', 250, 'Smartsheet, monday o similar', 'Lookahead, restricciones, portafolio, salud y tablero'],
        ['Almacenamiento', 'fa-cloud', 100, 'Drive, SharePoint o similar', 'Archivo, vPlan, View360 y respaldos'],
    ];

    /** Roles listos para la calculadora. */
    public static function roles(): array
    {
        $salida = [];
        foreach (self::ROLES as $clave => $rol) {
            $salida[$clave] = $rol + ['clave' => $clave];
        }
        return $salida;
    }

    /** Normaliza los conteos de la calculadora. */
    public static function normalizarRoles(array $roles): array
    {
        $salida = [];
        $total = 0;
        foreach (self::ROLES as $clave => $rol) {
            $n = max(0, min(100, (int)($roles[$clave] ?? 0)));
            $salida[$clave] = $n;
            $total += $n;
        }
        // Un equipo sin nadie no tiene sentido: al menos una dirección.
        if ($total === 0) {
            $salida['direccion'] = 1;
        }
        return $salida;
    }

    /** Plan más pequeño que cubre usuarios y obras. */
    public static function planPara(int $usuarios, int $obras): array
    {
        $usuarios = max(1, $usuarios);
        $obras = max(1, $obras);
        foreach (Plan::comerciales() as $plan) {
            $cabeUsuarios = $plan['usuarios'] === null || $usuarios <= (int)$plan['usuarios'];
            $cabeObras = $plan['obras'] === null || $obras <= (int)$plan['obras'];
            if ($cabeUsuarios && $cabeObras) return $plan;
        }
        return Plan::de('corporativo');
    }

    /**
     * Calcula el ahorro de un equipo.
     * Devuelve usuarios, costo tradicional, plan recomendado y ahorro.
     */
    public static function calcular(array $roles, int $obras = 1): array
    {
        $roles = self::normalizarRoles($roles);
        $obras = max(1, min(500, $obras));
        $usuarios = array_sum($roles);
        $tradicionalMes = 0;
        $desglose = [];
        foreach ($roles as $clave => $n) {
            $costo = (int)self::ROLES[$clave]['costo'] * $n;
            if ($n > 0) {
                $desglose[] = [
                    'clave' => $clave,
                    'nombre' => self::ROLES[$clave]['nombre'],
                    'cantidad' => $n,
                    'costo_mes' => $costo,
                ];
            }
            $tradicionalMes += $costo;
        }
        $tradicionalAnio = $tradicionalMes * 12;
        $plan = self::planPara($usuarios, $obras);
        $plan['etiqueta'] = 'Plan ' . $plan['nombre'];
        $pwAnio = (int)$plan['precio_anual'];
        $ahorro = max(0, $tradicionalAnio - $pwAnio);
        $pct = $tradicionalAnio > 0 ? (int)round($ahorro / $tradicionalAnio * 100) : 0;
        return [
            'roles' => $roles,
            'obras' => $obras,
            'usuarios' => $usuarios,
            'tradicional_mes' => $tradicionalMes,
            'tradicional_anio' => $tradicionalAnio,
            'plan' => $plan,
            'pw_mes' => (int)round($pwAnio / 12),
            'pw_anio' => $pwAnio,
            'ahorro_anio' => $ahorro,
            'ahorro_mes' => (int)round($ahorro / 12),
            'ahorro_pct' => $pct,
            'desglose' => $desglose,
        ];
    }

    /** Escenarios con su cálculo ya hecho. */
    public static function escenarios(): array
    {
        $salida = [];
        foreach (self::ESCENARIOS as $clave => $e) {
            $e['clave'] = $clave;
            $e['calculo'] = self::calcular($e['roles'], $e['obras']);
            $salida[$clave] = $e;
        }
        return $salida;
    }

    /** Tabla de reemplazos con formato listo para la vista. */
    public static function reemplazos(): array
    {
        $salida = [];
        foreach (self::REEMPLAZOS as [$nombre, $icono, $costo, $tradicional, $pw]) {
            $salida[] = compact('nombre', 'icono', 'costo', 'tradicional', 'pw');
        }
        return $salida;
    }

    /** Supuestos que se muestran junto a la calculadora. */
    public static function supuestos(): array
    {
        return [
            'Cifras en pesos mexicanos (MXN), con planes anuales de 10 meses.',
            'Una licencia por persona que realmente la necesita; no sumamos una licencia de todo para cada rol.',
            'No incluye hardware, internet, implementación, migración ni impuestos.',
            'Si tu empresa comparte licencias de escritorio, el ahorro real será menor; el orden de magnitud se mantiene.',
            'Los precios de los planes salen de Classes\\Plan; los costos de referencia son promedios de mercado y no una cotización.',
        ];
    }

    /** Etiqueta de precio para la vista. */
    public static function mxn(int $n): string
    {
        return '$' . number_format($n, 0, '.', ',');
    }
}
