<?php

namespace Model;

/** Vínculo actividad ↔ material (qué materiales necesita cada actividad del programa) */
class ActividadMaterial extends ActiveRecord
{
    protected static $tabla = 'actividad_materiales';
    protected static $columnasDB = ['actividad_id', 'material_id', 'rol'];

    public const ROLES = ['instala' => 'Instala', 'consume' => 'Consume'];

    /** @return int[] ids de los materiales de la actividad */
    public static function deActividad(int $actividadId): array
    {
        $out = [];
        $res = self::$db->query("SELECT material_id FROM actividad_materiales WHERE actividad_id = $actividadId");
        while ($r = $res->fetch_row()) $out[] = (int)$r[0];
        return $out;
    }

    /** @return array<int,string> material_id => rol (instala|consume) */
    public static function rolesDeActividad(int $actividadId): array
    {
        $out = [];
        $res = self::$db->query("SELECT material_id, rol FROM actividad_materiales WHERE actividad_id = $actividadId");
        while ($r = $res->fetch_assoc()) $out[(int)$r['material_id']] = (string)($r['rol'] ?: 'instala');
        return $out;
    }

    /**
     * Reemplaza los materiales de la actividad (solo del mismo proyecto).
     * $roles es [material_id => 'instala'|'consume']; lo que no venga se queda en «instala».
     */
    public static function guardarDeActividad(int $actividadId, int $proyectoId, array $ids, array $roles = []): void
    {
        $db = self::$db;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids) {
            $res = $db->query("SELECT id FROM materiales WHERE proyecto_id = $proyectoId AND id IN (" . implode(',', $ids) . ")");
            $ids = [];
            while ($r = $res->fetch_row()) $ids[] = (int)$r[0];
        }
        $db->query("DELETE FROM actividad_materiales WHERE actividad_id = $actividadId" . ($ids ? ' AND material_id NOT IN (' . implode(',', $ids) . ')' : ''));
        foreach ($ids as $mid) {
            $rol = isset($roles[$mid]) && isset(self::ROLES[$roles[$mid]]) ? $roles[$mid] : 'instala';
            $db->query("INSERT INTO actividad_materiales (actividad_id, material_id, rol) VALUES ($actividadId, $mid, '" . $db->real_escape_string($rol) . "')
                        ON DUPLICATE KEY UPDATE rol = VALUES(rol)");
        }
    }

    /**
     * FECHA DE INSTALACIÓN (PROG) del MSR = fecha en que el material se usa por primera vez:
     * la fecha de inicio más temprana entre las actividades que lo usan. Varias actividades
     * con el mismo material no la reescriben: siempre manda la primera.
     */
    public static function sincronizarInstalacion(int $proyectoId): void
    {
        self::$db->query(
            "UPDATE msr_items i
             JOIN (SELECT am.material_id, MIN(a.fecha_inicio) AS f
                   FROM actividad_materiales am
                   JOIN actividades a ON a.id = am.actividad_id
                   JOIN partidas p ON p.id = a.partida_id
                   WHERE p.proyecto_id = $proyectoId GROUP BY am.material_id) x ON x.material_id = i.material_id
             SET i.fecha_instalacion_prog = x.f
             WHERE i.proyecto_id = $proyectoId"
        );
    }
}
