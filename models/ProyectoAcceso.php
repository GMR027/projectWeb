<?php

namespace Model;

/** Asignación de proyectos a usuarios (visitantes/clientes) hecha por el admin */
class ProyectoAcceso extends ActiveRecord
{
    protected static $tabla = 'proyecto_accesos';
    protected static $columnasDB = ['id', 'proyecto_id', 'usuario_id'];

    public $id;
    public $proyecto_id;
    public $usuario_id;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->usuario_id = $args['usuario_id'] ?? null;
    }

    public static function existe(int $proyectoId, int $usuarioId): bool
    {
        return self::total("proyecto_id = $proyectoId AND usuario_id = $usuarioId") > 0;
    }

    /** @return int[] ids de proyectos asignados al usuario */
    public static function proyectosDe(int $usuarioId): array
    {
        return array_map(fn($a) => (int)$a->proyecto_id, self::whereAll('usuario_id', $usuarioId));
    }

    /** Reemplaza las asignaciones del usuario por la lista dada */
    public static function sincronizarUsuario(int $usuarioId, array $proyectoIds): void
    {
        self::$db->query("DELETE FROM proyecto_accesos WHERE usuario_id = $usuarioId");
        foreach (array_unique(array_map('intval', $proyectoIds)) as $pid) {
            if ($pid > 0 && Proyecto::find($pid)) {
                (new self(['proyecto_id' => $pid, 'usuario_id' => $usuarioId]))->guardar();
            }
        }
    }

    /** Como sincronizarUsuario, pero solo toca los proyectos de $permitidos (los demás accesos del usuario se conservan) */
    public static function sincronizarUsuarioEn(int $usuarioId, array $proyectoIds, array $permitidos): void
    {
        $permitidos = array_values(array_unique(array_map('intval', $permitidos)));
        if (!$permitidos) return;
        self::$db->query("DELETE FROM proyecto_accesos WHERE usuario_id = $usuarioId AND proyecto_id IN (" . implode(',', $permitidos) . ")");
        foreach (array_unique(array_map('intval', $proyectoIds)) as $pid) {
            if (in_array($pid, $permitidos, true)) (new self(['proyecto_id' => $pid, 'usuario_id' => $usuarioId]))->guardar();
        }
    }

    /** Reemplaza los usuarios con acceso a un proyecto (solo cliente/visitante) */
    public static function sincronizarProyecto(int $proyectoId, array $usuarioIds): void
    {
        self::$db->query("DELETE FROM proyecto_accesos WHERE proyecto_id = $proyectoId");
        foreach (array_unique(array_map('intval', $usuarioIds)) as $uid) {
            $u = Usuario::find($uid);
            if ($u && $u->rol !== 'admin') {
                (new self(['proyecto_id' => $proyectoId, 'usuario_id' => $uid]))->guardar();
            }
        }
    }

    /** @return int[] ids de usuarios con acceso asignado al proyecto */
    public static function usuariosDe(int $proyectoId): array
    {
        return array_map(fn($a) => (int)$a->usuario_id, self::whereAll('proyecto_id', $proyectoId));
    }
}
