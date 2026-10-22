<?php

namespace Classes;

/**
 * Novedades de la plataforma: lo que se publica en la campana de notificaciones
 * y en Ajustes → «Novedades de la plataforma».
 *
 * Para publicar una novedad agrega una entrada al inicio de ENTRADAS:
 *
 *   [
 *       'fecha'  => '2026-10-21 17:30',   // Y-m-d H:i (la hora decide qué está «sin ver»)
 *       'tipo'   => 'nuevo',              // nuevo | mejora | arreglo
 *       'titulo' => '…',
 *       'texto'  => '…',
 *       'roles'  => ['admin'],            // opcional: sin esto la ven todos
 *   ]
 *
 * No hay tabla: el historial vive en este archivo y lo que se guarda por usuario
 * es solo la fecha en que abrió las novedades (`usuarios.novedades_vistas`).
 */
class Novedades
{
    public const TIPOS = [
        'nuevo'   => ['Nuevo', 'fa-wand-magic-sparkles'],
        'mejora'  => ['Mejora', 'fa-arrow-trend-up'],
        'arreglo' => ['Arreglo', 'fa-screwdriver-wrench'],
    ];

    /** Roles que existen en la plataforma (una novedad sin `roles` la ven los tres) */
    private const ROLES = ['admin', 'cliente', 'visitante'];

    private const ENTRADAS = [
        [
            'fecha'  => '2026-10-21 17:30',
            'tipo'   => 'nuevo',
            'titulo' => 'Campana de notificaciones',
            'texto'  => 'La barra superior estrena una campana con las novedades de la plataforma, las actividades que tocan hoy y lo que está pendiente en Acciones rápidas. Cada quien ve solo lo que le corresponde: el cliente no recibe avisos de herramientas del administrador.',
        ],
        [
            'fecha'  => '2026-10-21 17:00',
            'tipo'   => 'nuevo',
            'titulo' => 'Color y modo por proyecto',
            'texto'  => 'En Ajustes → General → «Apariencia del proyecto» eliges si el proyecto se abre siempre claro, siempre oscuro o como cada usuario, y su color de acento entre 24 tonos. Se guarda en el proyecto, así que se mantiene al cambiar de navegador o de equipo, y también pinta la carátula del portafolio.',
        ],
        [
            'fecha'  => '2026-10-21 12:00',
            'tipo'   => 'mejora',
            'titulo' => 'El programa en el móvil',
            'texto'  => 'En pantallas pequeñas la tabla del programa queda con el nombre de la actividad y el botón de editar: el resto de columnas se consulta desde la ficha, sin apretujar la lectura.',
        ],
        [
            'fecha'  => '2026-10-20 18:00',
            'tipo'   => 'mejora',
            'titulo' => 'Ordenar y renumerar el programa',
            'texto'  => 'El programa se puede ordenar y renumerar sin tocar las dependencias ni las fechas; sirve para dejar la numeración WBS limpia después de importar o depurar partidas.',
        ],
        [
            'fecha'  => '2026-10-18 10:00',
            'tipo'   => 'nuevo',
            'titulo' => 'Programa configurable',
            'texto'  => 'El programa de obra ahora se diseña: nombre del programa, columnas a mostrar (renombrar y ordenar) e indicador condicional. El PDF del programa respeta ese diseño; el XML para MS Project y el Gantt mantienen su estructura.',
        ],
        [
            'fecha'  => '2026-10-15 09:00',
            'tipo'   => 'nuevo',
            'titulo' => 'Tablas dentro de las láminas de vPlan',
            'texto'  => 'Las láminas de vPlan pueden llevar tablas (SheetFlow) con tareas, materiales o cualquier dato del proyecto, y agregar campos del programa desde el modo diseño.',
        ],
        [
            'fecha'  => '2026-10-10 11:00',
            'tipo'   => 'arreglo',
            'titulo' => 'Fechas de recuperación en el programa',
            'texto'  => 'Las actividades muestran su fecha de recuperación (inicio y fin) cuando van atrasadas, y los campos nuevos del programa se conservan al editar la actividad.',
        ],
        [
            'fecha'  => '2026-10-01 09:00',
            'tipo'   => 'nuevo',
            'titulo' => 'Traductor de texto y documentos',
            'texto'  => 'Traduce texto y documentos completos entre español e inglés sin salir del proyecto; el documento traducido se descarga en su formato original.',
        ],
        [
            'fecha'  => '2026-09-25 09:00',
            'tipo'   => 'nuevo',
            'titulo' => 'Centro de IA y Revisiones',
            'texto'  => 'Las herramientas de IA del administrador (informes, voz, normativa, seguridad, punch…) quedan en un solo centro, y Revisiones resume con ✅⚠❌ qué herramientas está usando cada proyecto.',
            'roles'  => ['admin'],
        ],
    ];

    /** Etiqueta e icono de un tipo de novedad */
    public static function tipo(string $tipo): array
    {
        return self::TIPOS[$tipo] ?? self::TIPOS['nuevo'];
    }

    /** Ancla de una novedad (para enlazar desde la campana a su ficha en /novedades) */
    public static function ancla(array $novedad): string
    {
        return 'n-' . substr(md5($novedad['titulo'] ?? ''), 0, 8);
    }

    /**
     * Novedades visibles para el rol, la más reciente primero.
     * @return array<int, array{fecha:string, tipo:string, titulo:string, texto:string, roles?:array}>
     */
    public static function visibles(string $rol): array
    {
        $rol = in_array($rol, self::ROLES, true) ? $rol : 'visitante';
        $out = [];
        foreach (self::ENTRADAS as $e) {
            if (!in_array($rol, $e['roles'] ?? self::ROLES, true)) continue;
            $out[] = $e;
        }
        usort($out, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        return $out;
    }

    /**
     * Novedades visibles para el rol con la marca `nueva` según lo que ya abrió el usuario.
     * @return array<int, array{fecha:string, tipo:string, titulo:string, texto:string, nueva:bool}>
     */
    public static function para(string $rol, int $usuarioId): array
    {
        $vistas = self::vistas($usuarioId);
        $out = [];
        foreach (self::visibles($rol) as $e) {
            $e['nueva'] = $vistas === null || ($e['fecha'] . ':00') > $vistas;
            $out[] = $e;
        }
        return $out;
    }

    /** Cuántas de esas novedades siguen sin ver */
    public static function sinVer(array $lista): int
    {
        return count(array_filter($lista, fn($e) => !empty($e['nueva'])));
    }

    /** Fecha y hora en que el usuario abrió las novedades por última vez (null = nunca) */
    public static function vistas(int $usuarioId): ?string
    {
        if ($usuarioId <= 0) return null;
        global $db;
        $r = $db->query("SELECT novedades_vistas FROM usuarios WHERE id = $usuarioId");
        $f = $r ? $r->fetch_row() : null;
        $v = $f[0] ?? null;
        return $v ? substr((string)$v, 0, 19) : null;
    }

    /** Marca todas las novedades como vistas (se llama al abrir la campana o la página) */
    public static function marcarVistas(int $usuarioId): void
    {
        if ($usuarioId <= 0) return;
        global $db;
        $db->query("UPDATE usuarios SET novedades_vistas = NOW() WHERE id = $usuarioId");
    }
}
