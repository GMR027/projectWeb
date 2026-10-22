<?php

namespace Classes;

/**
 * Diseño configurable (columnas visibles, nombres y orden) de las listas del proyecto, como el del programa:
 * Lookahead, Punch, Submittals, Bitácora y Reporte diario. Vive en disenos_lista (proyecto + herramienta → [{k, n}]).
 * Sin diseño guardado se usa el clásico más los campos personalizados de esa herramienta (CamposPersonalizados).
 */
class ListaDiseno
{
    use AccesoDb;

    /** herramienta => [título, entidad de campos personalizados, ruta de la lista, ordenable?, obligatorias, clave => [nombre genérico, ¿se ve en móvil?]] */
    public const HERRAMIENTAS = [
        'submittals' => ['Submittals', 'submittal', '/submittals', true, ['numero', 'acciones'], [
            'numero' => ['No. submittal', true], 'msr' => ['MSR', false], 'fecha' => ['Fecha', false], 'especialidad' => ['Especialidad', false],
            'departamento' => ['Departamento', false], 'para' => ['Para', false], 'materiales' => ['Materiales', false], 'estatus' => ['Estatus', true], 'acciones' => ['Acciones', false]]],
        'bitacora' => ['Bitácora de obra', 'bitacora', '/bitacora', true, ['descripcion'], [
            'fecha' => ['Fecha', false], 'departamento' => ['Departamento', false], 'descripcion' => ['Descripción', true], 'importancia' => ['Importancia', false], 'acciones' => ['Acciones', true]]],
        'reportes' => ['Reporte diario', 'reporte', '/reportes', true, ['actividades', 'acciones'], [
            'fecha' => ['Fecha', false], 'actividades' => ['Actividad del programa', true], 'acciones' => ['Acciones', true]]],
        'punch' => ['Punch list', 'punch', '/punch', true, ['titulo'], [
            'severidad' => ['Severidad', true], 'titulo' => ['Pendiente', true], 'estado' => ['Estado', true], 'categoria' => ['Categoría', true],
            'ubicacion' => ['Ubicación', true], 'responsable' => ['Responsable', true], 'limite' => ['Fecha límite', true]]],
        'lookahead' => ['Lookahead', 'actividad', '/lookahead', false, ['actividad'], [
            'id' => ['ID (EDT)', true], 'actividad' => ['Actividad', true], 'comp' => ['% Comp', true], 'pta' => ['PTA', false], 'limitaciones' => ['Limitaciones', false]]],
    ];

    public static function existe(string $herramienta): bool
    {
        return isset(self::HERRAMIENTAS[$herramienta]);
    }

    public static function entidad(string $herramienta): string
    {
        return self::HERRAMIENTAS[$herramienta][1];
    }

    private static function clasico(string $herramienta): array
    {
        return array_keys(self::HERRAMIENTAS[$herramienta][5]);
    }

    /** Claves válidas del proyecto: las del catálogo y los campos personalizados de la entidad */
    private static function validas(int $pid, string $herramienta): array
    {
        $v = self::HERRAMIENTAS[$herramienta][5];
        if (self::HERRAMIENTAS[$herramienta][3]) {   // sin orden propio (Lookahead) no hay dónde poner columnas nuevas: ahí los campos solo filtran
            foreach (CamposPersonalizados::deProyecto($pid, self::entidad($herramienta)) as $c) $v['cp_' . $c['id']] = [$c['nombre'], false];
        }
        return $v;
    }

    /** Diseño guardado [{k, n}] o null */
    private static function guardado(int $pid, string $herramienta): ?array
    {
        $f = self::filas("SELECT columnas_json FROM disenos_lista WHERE proyecto_id = $pid AND herramienta = '" . self::esc($herramienta) . "'")[0] ?? null;
        $j = $f ? json_decode((string)$f['columnas_json'], true) : null;
        return is_array($j) && $j ? $j : null;
    }

    /** Diseño vigente: [{k, n (nombre mostrado), generico, propio, movil, campo (campo personalizado o null)}] */
    public static function columnas(int $pid, string $herramienta): array
    {
        if (!self::existe($herramienta)) return [];
        $validas = self::validas($pid, $herramienta);
        $lista = self::guardado($pid, $herramienta);
        if (!$lista) {
            $lista = array_map(fn($k) => ['k' => $k, 'n' => ''], self::clasico($herramienta));
            $cps = !self::HERRAMIENTAS[$herramienta][3] ? [] : array_map(fn($id) => ['k' => 'cp_' . $id, 'n' => ''], array_keys(CamposPersonalizados::deProyecto($pid, self::entidad($herramienta))));
            $pos = count($lista);
            foreach ($lista as $i => $c) if ($c['k'] === 'acciones') $pos = $i;
            array_splice($lista, $pos, 0, $cps);
        }
        $out = [];
        foreach (self::normalizar($pid, $herramienta, $lista) as $c) {
            [$gen, $movil] = $validas[$c['k']];
            $out[] = ['k' => $c['k'], 'n' => $c['n'] !== '' ? $c['n'] : t($gen), 'generico' => t($gen), 'propio' => $c['n'] !== '', 'movil' => $movil,
                'campo' => str_starts_with($c['k'], 'cp_') ? CamposPersonalizados::deProyecto($pid)[(int)substr($c['k'], 3)] : null];
        }
        return $out;
    }

    /** Las columnas ya preparadas para una vista: ['cols' => …, 'por' => [clave => columna], 'nombre' => fn(clave)] */
    public static function para(int $pid, string $herramienta): array
    {
        $cols = self::columnas($pid, $herramienta);
        $por = [];
        foreach ($cols as $c) $por[$c['k']] = $c;
        return ['cols' => $cols, 'por' => $por];
    }

    /** Para el formulario de diseño: todas las columnas posibles, las activas primero y en su orden */
    public static function paraFormulario(int $pid, string $herramienta): array
    {
        $act = self::columnas($pid, $herramienta);
        $usadas = array_column($act, 'k');
        foreach ($act as &$c) $c['activa'] = true;
        unset($c);
        foreach (self::validas($pid, $herramienta) as $k => [$gen, $movil]) {
            if (!in_array($k, $usadas, true)) $act[] = ['k' => $k, 'n' => $gen, 'generico' => $gen, 'propio' => false, 'movil' => $movil, 'campo' => null, 'activa' => false];
        }
        return $act;
    }

    /** Lista blanca: claves válidas sin repetir, nombre ≤ 60, con las obligatorias; si la herramienta no se ordena, queda el orden de siempre */
    public static function normalizar(int $pid, string $herramienta, array $entradas): array
    {
        $validas = self::validas($pid, $herramienta);
        $out = []; $vistas = [];
        foreach ($entradas as $e) {
            $k = (string)($e['k'] ?? '');
            if (!isset($validas[$k]) || isset($vistas[$k])) continue;
            $vistas[$k] = true;
            $n = mb_substr(trim(strip_tags((string)($e['n'] ?? ''))), 0, 60);
            $out[] = ['k' => $k, 'n' => $n === $validas[$k][0] ? '' : $n];
        }
        foreach (self::HERRAMIENTAS[$herramienta][4] as $k) {
            if (isset($vistas[$k])) continue;
            $k === 'acciones' ? $out[] = ['k' => $k, 'n' => ''] : array_unshift($out, ['k' => $k, 'n' => '']);
        }
        if (!self::HERRAMIENTAS[$herramienta][3]) {   // sin orden propio: se respeta el del catálogo
            $pos = array_flip(array_keys($validas));
            usort($out, fn($a, $b) => $pos[$a['k']] <=> $pos[$b['k']]);
        }
        return $out;
    }

    public static function guardar(int $pid, string $herramienta, array $columnas): bool
    {
        if (!self::existe($herramienta)) return false;
        $cols = self::normalizar($pid, $herramienta, $columnas);
        $cpsAct = !self::HERRAMIENTAS[$herramienta][3] ? [] : array_keys(CamposPersonalizados::deProyecto($pid, self::entidad($herramienta)));
        $esClasico = !array_filter(array_column($cols, 'n')) && array_column($cols, 'k') === self::clasico($herramienta) && !$cpsAct;
        $h = self::esc($herramienta);
        if ($esClasico) return (bool)self::db()->query("DELETE FROM disenos_lista WHERE proyecto_id = $pid AND herramienta = '$h'");
        $json = json_encode($cols, JSON_UNESCAPED_UNICODE);
        $st = self::db()->prepare('INSERT INTO disenos_lista (proyecto_id, herramienta, columnas_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE columnas_json = VALUES(columnas_json)');
        $st->bind_param('iss', $pid, $herramienta, $json);
        return $st->execute();
    }

    public static function restablecer(int $pid, string $herramienta): void
    {
        self::db()->query("DELETE FROM disenos_lista WHERE proyecto_id = $pid AND herramienta = '" . self::esc($herramienta) . "'");
    }

    /** Quita una clave (p. ej. un campo personalizado borrado) de todos los diseños del proyecto */
    public static function quitarClave(int $pid, string $clave): void
    {
        foreach (self::filas("SELECT herramienta, columnas_json FROM disenos_lista WHERE proyecto_id = $pid") as $f) {
            $j = json_decode((string)$f['columnas_json'], true);
            if (!is_array($j)) continue;
            $nuevo = array_values(array_filter($j, fn($c) => ($c['k'] ?? '') !== $clave));
            if (count($nuevo) === count($j)) continue;
            $json = self::esc(json_encode($nuevo, JSON_UNESCAPED_UNICODE));
            self::db()->query("UPDATE disenos_lista SET columnas_json = '$json' WHERE proyecto_id = $pid AND herramienta = '" . self::esc($f['herramienta']) . "'");
        }
    }
}
