<?php

namespace Model;

/** Vínculo subactividad ↔ material (independiente de los materiales de su actividad) */
class SubactividadMaterial extends ActiveRecord
{
    protected static $tabla = 'subactividad_materiales';
    protected static $columnasDB = ['subactividad_id', 'material_id', 'rol'];

    public const ROLES = ['instala' => 'Instala', 'consume' => 'Consume'];

    /** @return int[] ids de los materiales de la subactividad */
    public static function deSubactividad(int $subactividadId): array
    {
        $out = [];
        $res = self::$db->query("SELECT material_id FROM subactividad_materiales WHERE subactividad_id = $subactividadId");
        while ($r = $res->fetch_row()) $out[] = (int)$r[0];
        return $out;
    }

    /** @return array<int,string> material_id => rol (instala|consume) */
    public static function rolesDeSubactividad(int $subactividadId): array
    {
        $out = [];
        $res = self::$db->query("SELECT material_id, rol FROM subactividad_materiales WHERE subactividad_id = $subactividadId");
        while ($r = $res->fetch_assoc()) $out[(int)$r['material_id']] = (string)($r['rol'] ?: 'instala');
        return $out;
    }

    /** Reemplaza los materiales de la subactividad (solo del mismo proyecto) */
    public static function guardarDeSubactividad(int $subactividadId, int $proyectoId, array $ids, array $roles = []): void
    {
        $db = self::$db;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids) {
            $res = $db->query("SELECT id FROM materiales WHERE proyecto_id = $proyectoId AND id IN (" . implode(',', $ids) . ")");
            $ids = [];
            while ($r = $res->fetch_row()) $ids[] = (int)$r[0];
        }
        $db->query("DELETE FROM subactividad_materiales WHERE subactividad_id = $subactividadId" . ($ids ? ' AND material_id NOT IN (' . implode(',', $ids) . ')' : ''));
        foreach ($ids as $mid) {
            $rol = isset($roles[$mid]) && isset(self::ROLES[$roles[$mid]]) ? $roles[$mid] : 'instala';
            $db->query("INSERT INTO subactividad_materiales (subactividad_id, material_id, rol) VALUES ($subactividadId, $mid, '" . $db->real_escape_string($rol) . "')
                        ON DUPLICATE KEY UPDATE rol = VALUES(rol)");
        }
    }
}
