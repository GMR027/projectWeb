<?php

namespace Classes;

use Model\Proyecto;

/**
 * Buscador global de un proyecto: una sola caja que busca en el programa, materiales, submittals, minutas, bitácora, reportes, archivo, punch,
 * órdenes de cambio, permisos, documentos del Redactor y normativa. Cada palabra escrita debe aparecer (sin importar acentos ni mayúsculas).
 * Respeta las herramientas ocultas al visitante.
 */
class Busqueda
{
    use AccesoDb;

    public const POR_GRUPO = 8;
    public const MIN_LETRAS = 2;

    /**
     * Fuentes: clave => [título, icono, ruta base (para decidir si el usuario la puede ver), SQL con {W} (condición de palabras) y {P} (proyecto),
     *                    función que arma el resultado a partir de la fila]
     */
    private static function fuentes(int $pid, bool $gestiona): array
    {
        $u = "proyecto=$pid";
        return [
            'actividades' => ['Actividades del programa', 'fa-bars-staggered', '/programa',
                "SELECT a.id, a.codigo, a.nombre, a.descripcion, p.nombre partida FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE p.proyecto_id = {P} AND {W:a.codigo,a.nombre,a.descripcion,p.nombre,a.responsable} ORDER BY a.fecha_inicio",
                fn($f) => [$f['codigo'] . ' · ' . $f['nombre'], $f['partida'], ($gestiona ? "/programa/actividades/editar?$u&id={$f['id']}" : "/programa?$u")]],
            'materiales' => ['Materiales', 'fa-boxes-stacked', '/msr',
                "SELECT id, nombre, referencia, marca, modelo FROM materiales WHERE proyecto_id = {P} AND {W:nombre,referencia,marca,modelo,especialidad}",
                fn($f) => [$f['nombre'], trim($f['referencia'] . ' ' . $f['marca'] . ' ' . $f['modelo']), "/materiales?$u&q=" . rawurlencode($f['nombre'])]],
            'submittals' => ['Submittals', 'fa-file-circle-check', '/msr',
                "SELECT id, numero_submittal, para, especialidad, estatus FROM submittals WHERE proyecto_id = {P} AND {W:numero_submittal,para,especialidad,departamento,numero_trabajo}",
                fn($f) => ['Submittal ' . $f['numero_submittal'], trim($f['especialidad'] . ' · ' . $f['para'], ' ·'), "/submittals?$u"]],
            'minutas' => ['Minutas', 'fa-people-group', '/minutas',
                "SELECT id, nombre, fecha FROM minutas WHERE proyecto_id = {P} AND {W:nombre,departamento,temas_json,compromisos_json}",
                fn($f) => [$f['nombre'], self::fecha($f['fecha']), "/minutas/ver?$u&id={$f['id']}"]],
            'bitacora' => ['Bitácora', 'fa-book', '/bitacora',
                "SELECT id, descripcion, departamento, fecha FROM bitacora WHERE proyecto_id = {P} AND {W:descripcion,departamento} ORDER BY fecha DESC",
                fn($f) => [self::corto($f['descripcion']), self::fecha($f['fecha']) . ($f['departamento'] ? ' · ' . $f['departamento'] : ''), "/bitacora?$u"]],
            'reportes' => ['Reportes diarios', 'fa-clipboard-list', '/reportes',
                "SELECT id, area, comentarios, fecha FROM reportes_diarios WHERE proyecto_id = {P} AND {W:area,comentarios} ORDER BY fecha DESC",
                fn($f) => ['Reporte del ' . self::fecha($f['fecha']) . ($f['area'] ? ' · ' . $f['area'] : ''), self::corto($f['comentarios']), "/reportes/ver?$u&id={$f['id']}"]],
            'archivos' => ['Archivo', 'fa-folder-open', '/archivos',
                "SELECT id, nombre, archivo_original, numero_revision FROM archivos WHERE proyecto_id = {P} AND {W:nombre,archivo_original,numero_revision}",
                fn($f) => [$f['nombre'], $f['archivo_original'], "/archivos/ver?$u&id={$f['id']}"]],
            'punch' => ['Punch list', 'fa-list-check', '/punch',
                "SELECT id, titulo, ubicacion, estado FROM punch_items WHERE proyecto_id = {P} AND {W:titulo,descripcion,ubicacion,categoria,contratista,responsable}",
                fn($f) => [$f['titulo'], trim($f['ubicacion'] . ' · ' . ($f['estado']), ' ·'), "/punch/ver?$u&id={$f['id']}"]],
            'cambios' => ['Órdenes de cambio', 'fa-file-signature', '/cambios',
                "SELECT id, numero, titulo, estado FROM ordenes_cambio WHERE proyecto_id = {P} AND {W:numero,titulo,descripcion,solicitante}",
                fn($f) => [trim($f['numero'] . ' ' . $f['titulo']), $f['estado'], "/cambios/ver?$u&id={$f['id']}"]],
            'permisos' => ['Permisos de trabajo', 'fa-helmet-safety', '/seguridad',
                "SELECT id, titulo, tipo, estado FROM permisos_trabajo WHERE proyecto_id = {P} AND {W:titulo,nombre_permiso,descripcion,area,responsable,contratista}",
                fn($f) => [$f['titulo'], $f['tipo'] . ' · ' . $f['estado'], "/seguridad?$u"]],
            'redactor' => ['Documentos del Redactor', 'fa-file-lines', '/redactor',
                "SELECT id, nombre, fecha FROM redactor_documentos WHERE proyecto_id = {P} AND {W:nombre,departamento}",
                fn($f) => [$f['nombre'], self::fecha($f['fecha']), "/redactor/ver?$u&id={$f['id']}"]],
            'normativa' => ['Normativa y especificaciones', 'fa-scale-balanced', '/normativa',
                "SELECT d.id, d.titulo, d.codigo, MIN(f.pagina) pagina FROM normativa_documentos d LEFT JOIN normativa_fragmentos f ON f.documento_id = d.id AND {W:f.titulo,f.texto}
                 WHERE d.proyecto_id = {P} AND ({W:d.titulo,d.codigo} OR f.id IS NOT NULL) GROUP BY d.id, d.titulo, d.codigo",
                fn($f) => [trim($f['codigo'] . ' ' . $f['titulo']), $f['pagina'] ? 'Aparece en la página ' . $f['pagina'] : 'Coincide con el título', "/normativa/documento?$u&id={$f['id']}"]],
        ];
    }

    private static function fecha(?string $f): string
    {
        return $f ? date('d/m/Y', strtotime($f)) : '';
    }

    private static function corto(?string $t, int $n = 110): string
    {
        $t = trim(preg_replace('/\s+/', ' ', (string)$t));
        return mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
    }

    /** Palabras de la consulta (mínimo 2 letras, máximo 6) */
    public static function palabras(string $q): array
    {
        $p = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_slice(array_values(array_filter($p, fn($w) => mb_strlen($w) >= self::MIN_LETRAS)), 0, 6);
    }

    private static function condicion(array $columnas, array $palabras): string
    {
        $conds = [];
        foreach ($palabras as $w) {
            $like = '%' . addcslashes(self::db()->real_escape_string($w), '%_') . '%';
            $conds[] = "CONCAT_WS(' ', " . implode(', ', array_map(fn($c) => "COALESCE($c, '')", $columnas)) . ") LIKE '$like'";
        }
        return '(' . implode(' AND ', $conds) . ')';
    }

    /**
     * @return array[] grupos ['clave','titulo','icono','total','items'=>[['titulo','detalle','url']]] solo con resultados
     */
    public static function buscar(Proyecto $proyecto, string $q): array
    {
        $palabras = self::palabras($q);
        if (!$palabras) return [];
        $pid = (int)$proyecto->id;
        $out = [];
        $gestiona = puede_gestionar_proyecto($proyecto);
        foreach (self::fuentes($pid, $gestiona) as $clave => [$titulo, $icono, $ruta, $sql, $armar]) {
            if ($clave === 'normativa' && !\is_admin()) continue;   // el módulo de normativa es solo del administrador
            if (!herramienta_permitida(herramienta_de_ruta($ruta))) continue;
            $sql = str_replace('{P}', (string)$pid, $sql);
            $sql = preg_replace_callback('/\{W:([^}]+)\}/', fn($m) => self::condicion(explode(',', $m[1]), $palabras), $sql);
            $filas = self::filas($sql . ' LIMIT ' . (self::POR_GRUPO + 1));
            if (!$filas) continue;
            $items = array_map(function ($f) use ($armar) { [$t, $d, $u] = $armar($f); return ['titulo' => $t, 'detalle' => $d, 'url' => $u]; }, array_slice($filas, 0, self::POR_GRUPO));
            $out[] = ['clave' => $clave, 'titulo' => $titulo, 'icono' => $icono, 'hay_mas' => count($filas) > self::POR_GRUPO, 'items' => $items];
        }
        return $out;
    }
}
