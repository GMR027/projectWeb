<?php

namespace Classes;

use Model\Bitacora;
use Model\Proyecto;

/**
 * Historial del asistente y borradores.
 *
 * El chat se escribe en la app Android (modelo local); aquí se guarda la copia para
 * consultarlo en la web, buscar y auditar. La web es de solo lectura: no tiene chat
 * ni modelo. Retención: conversaciones 6 meses desde su creación; borradores 30 días.
 */
class Asistente
{
    use AccesoDb;

    public const ROLES = ['user', 'assistant', 'system', 'tool'];
    public const TIPOS = ['punch', 'rfi', 'bitacora', 'reporte', 'minuta'];
    public const TIPOS_APLICABLES = ['punch', 'rfi', 'bitacora'];

    public static function disponible(): bool
    {
        static $hay = null;
        return $hay ??= (bool)self::filas("SHOW TABLES LIKE 'asistente_conversaciones'");
    }

    public static function ultimaConversacion(int $usuarioId, int $proyectoId): int
    {
        if (!self::disponible() || $usuarioId <= 0 || $proyectoId <= 0) return 0;
        $f = self::filas("SELECT id FROM asistente_conversaciones
            WHERE usuario_id = $usuarioId AND proyecto_id = $proyectoId AND eliminada_en IS NULL
            ORDER BY actualizado DESC, id DESC LIMIT 1")[0] ?? null;
        if ($f) return (int)$f['id'];
        return self::crearConversacion($usuarioId, $proyectoId);
    }

    public static function crearConversacion(int $usuarioId, int $proyectoId, string $titulo = 'Asistente'): int
    {
        if (!self::disponible()) return 0;
        $t = self::esc(mb_substr(trim($titulo) ?: 'Asistente', 0, 200));
        self::db()->query("INSERT INTO asistente_conversaciones (proyecto_id, usuario_id, titulo) VALUES ($proyectoId, $usuarioId, '$t')");
        return (int)self::db()->insert_id;
    }

    public static function mensajes(int $conversacionId, int $limite = 200): array
    {
        if (!self::disponible() || $conversacionId <= 0) return [];
        $limite = max(1, min(500, $limite));
        $filas = self::filas("SELECT id, uuid, rol, contenido, datos_json, modelo, creado
            FROM asistente_mensajes WHERE conversacion_id = $conversacionId AND eliminado_en IS NULL
            ORDER BY id DESC LIMIT $limite");
        $out = array_reverse($filas);
        foreach ($out as &$m) {
            $m['datos'] = $m['datos_json'] ? (json_decode((string)$m['datos_json'], true) ?: null) : null;
            unset($m['datos_json']);
        }
        return $out;
    }

    public static function agregarMensaje(int $conversacionId, string $rol, string $contenido, ?array $datos = null, string $modelo = '', ?string $uuid = null): int
    {
        if (!self::disponible() || $conversacionId <= 0) return 0;
        $rol = in_array($rol, self::ROLES, true) ? $rol : 'user';
        $contenido = trim($contenido);
        if ($contenido === '') return 0;
        $contenido = self::esc(mb_substr($contenido, 0, 20000));
        $modelo = self::esc(mb_substr($modelo, 0, 150));
        $json = $datos !== null ? "'" . self::esc(mb_substr(json_encode($datos, JSON_UNESCAPED_UNICODE) ?: 'null', 0, 20000)) . "'" : 'NULL';
        $u = $uuid && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $uuid) ? "'" . self::esc($uuid) . "'" : 'NULL';
        self::db()->query("INSERT IGNORE INTO asistente_mensajes (conversacion_id, uuid, rol, contenido, datos_json, modelo)
            VALUES ($conversacionId, $u, '$rol', '$contenido', $json, '$modelo')");
        $id = (int)self::db()->insert_id;
        if ($id) self::db()->query("UPDATE asistente_conversaciones SET actualizado = NOW() WHERE id = $conversacionId");
        return $id;
    }

    /** Conversaciones visibles para la web: el admin ve todas; el usuario solo las suyas. */
    public static function conversaciones(int $usuarioId, int $proyectoId = 0, bool $admin = false, string $q = ''): array
    {
        if (!self::disponible()) return [];
        if (random_int(1, 100) === 1) self::limpiar();
        $where = ["c.eliminada_en IS NULL"];
        if (!$admin) $where[] = 'c.usuario_id = ' . $usuarioId;
        if ($proyectoId > 0) $where[] = 'c.proyecto_id = ' . $proyectoId;
        if (trim($q) !== '') {
            $e = self::esc(trim($q));
            $where[] = "(c.titulo LIKE '%$e%' OR EXISTS (SELECT 1 FROM asistente_mensajes m WHERE m.conversacion_id = c.id AND m.contenido LIKE '%$e%'))";
        }
        $filas = self::filas("SELECT c.*, p.nombre proyecto, p.codigo, u.nombre usuario, u.email,
                (SELECT COUNT(*) FROM asistente_mensajes m WHERE m.conversacion_id = c.id AND m.eliminado_en IS NULL) mensajes
            FROM asistente_conversaciones c
            JOIN proyectos p ON p.id = c.proyecto_id
            JOIN usuarios u ON u.id = c.usuario_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.actualizado DESC, c.id DESC LIMIT 150");
        return $filas;
    }

    public static function conversacion(int $id, int $usuarioId, bool $admin = false): ?array
    {
        if (!self::disponible() || $id <= 0) return null;
        $w = $admin ? '' : ' AND c.usuario_id = ' . $usuarioId;
        return self::filas("SELECT c.*, p.nombre proyecto, p.codigo, u.nombre usuario, u.email
            FROM asistente_conversaciones c JOIN proyectos p ON p.id = c.proyecto_id JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.id = $id AND c.eliminada_en IS NULL$w LIMIT 1")[0] ?? null;
    }

    public static function borradores(int $usuarioId = 0, int $proyectoId = 0, bool $admin = false, bool $soloPendientes = true): array
    {
        if (!self::disponible()) return [];
        $where = ["b.estado <> 'expirado'"];
        if ($soloPendientes) $where[] = "b.estado = 'pendiente'";
        if (!$admin) $where[] = 'b.usuario_id = ' . $usuarioId;
        if ($proyectoId > 0) $where[] = 'b.proyecto_id = ' . $proyectoId;
        $filas = self::filas("SELECT b.*, p.nombre proyecto, p.codigo, u.nombre usuario
            FROM asistente_borradores b JOIN proyectos p ON p.id = b.proyecto_id JOIN usuarios u ON u.id = b.usuario_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY b.creado DESC LIMIT 100");
        foreach ($filas as &$f) $f['datos'] = json_decode((string)$f['datos_json'], true) ?: [];
        return $filas;
    }

    public static function crearBorrador(int $usuarioId, int $proyectoId, string $tipo, array $datos, ?int $conversacionId = null, ?string $uuid = null): int
    {
        if (!self::disponible() || !in_array($tipo, self::TIPOS, true)) return 0;
        $json = self::esc(mb_substr(json_encode($datos, JSON_UNESCAPED_UNICODE) ?: '{}', 0, 20000));
        $conv = $conversacionId ? (int)$conversacionId : 'NULL';
        $u = $uuid && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $uuid) ? "'" . self::esc($uuid) . "'" : 'NULL';
        self::db()->query("INSERT IGNORE INTO asistente_borradores
            (proyecto_id, usuario_id, conversacion_id, uuid, tipo, datos_json, expira_en)
            VALUES ($proyectoId, $usuarioId, $conv, $u, '$tipo', '$json', DATE_ADD(NOW(), INTERVAL 30 DAY))");
        return (int)self::db()->insert_id;
    }

    /** @return array{ok:bool,mensaje:string,entidad_id:int} */
    public static function aplicarBorrador(int $id, int $usuarioId, bool $admin = false): array
    {
        if (!self::disponible()) return ['ok' => false, 'mensaje' => 'El asistente no está habilitado en este servidor.', 'entidad_id' => 0];
        $b = self::filas("SELECT * FROM asistente_borradores WHERE id = " . (int)$id . " AND estado = 'pendiente' LIMIT 1")[0] ?? null;
        if (!$b) return ['ok' => false, 'mensaje' => 'El borrador no existe o ya se aplicó.', 'entidad_id' => 0];
        if (!$admin && (int)$b['usuario_id'] !== $usuarioId) return ['ok' => false, 'mensaje' => 'No puedes aplicar este borrador.', 'entidad_id' => 0];
        $pid = (int)$b['proyecto_id'];
        $p = Proyecto::find($pid);
        if (!$p || !puede_gestionar_proyecto($p)) return ['ok' => false, 'mensaje' => 'No tienes permiso para modificar esta obra.', 'entidad_id' => 0];
        $datos = json_decode((string)$b['datos_json'], true) ?: [];
        $tipo = (string)$b['tipo'];
        if (!in_array($tipo, self::TIPOS_APLICABLES, true)) {
            return ['ok' => false, 'mensaje' => 'Este borrador se completa en su formulario; no se aplica solo.', 'entidad_id' => 0];
        }
        $entidad = 0; $msg = '';
        if ($tipo === 'punch') {
            [$ok, $msg, $entidad] = Punch::guardar($pid, $datos, null, 'ia');
            if (!$ok) return ['ok' => false, 'mensaje' => $msg, 'entidad_id' => 0];
        } elseif ($tipo === 'rfi') {
            $err = Rfi::guardar($pid, $datos, (string)($_SESSION['nombre'] ?? 'Asistente'));
            if ($err !== null) return ['ok' => false, 'mensaje' => $err, 'entidad_id' => 0];
            $entidad = (int)Rfi::$ultimoId;
            $msg = 'RFI creado.';
        } elseif ($tipo === 'bitacora') {
            $bit = new Bitacora([
                'proyecto_id' => $pid,
                'fecha' => (string)($datos['fecha'] ?? date('Y-m-d')),
                'departamento' => (string)($datos['departamento'] ?? ''),
                'descripcion' => (string)($datos['descripcion'] ?? ''),
                'importancia' => (string)($datos['importancia'] ?? 'media'),
            ]);
            $alertas = $bit->validar();
            if ($alertas) return ['ok' => false, 'mensaje' => 'El borrador de bitácora está incompleto.', 'entidad_id' => 0];
            $r = $bit->guardar();
            if (empty($r['resultado'])) return ['ok' => false, 'mensaje' => 'No se pudo guardar la bitácora.', 'entidad_id' => 0];
            $entidad = (int)($r['id'] ?? $bit->id);
            $msg = 'Nota de bitácora creada.';
        }
        self::db()->query("UPDATE asistente_borradores SET estado = 'aplicado', aplicado_en = NOW(), entidad_id = " . ($entidad ?: 'NULL') . " WHERE id = " . (int)$id);
        return ['ok' => true, 'mensaje' => $msg ?: 'Borrador aplicado.', 'entidad_id' => $entidad];
    }

    public static function rechazarBorrador(int $id, int $usuarioId, bool $admin = false): bool
    {
        if (!self::disponible()) return false;
        $b = self::filas("SELECT * FROM asistente_borradores WHERE id = " . (int)$id . " AND estado = 'pendiente' LIMIT 1")[0] ?? null;
        if (!$b || (!$admin && (int)$b['usuario_id'] !== $usuarioId)) return false;
        self::db()->query("UPDATE asistente_borradores SET estado = 'rechazado', datos_json = '{}' WHERE id = " . (int)$id);
        return true;
    }

    public static function eliminarConversacion(int $id, int $usuarioId, bool $admin = false): bool
    {
        if (!self::disponible()) return false;
        $w = $admin ? '' : ' AND usuario_id = ' . $usuarioId;
        self::db()->query("UPDATE asistente_conversaciones SET eliminada_en = NOW() WHERE id = " . (int)$id . $w);
        return self::db()->affected_rows > 0;
    }

    /** Retención: 6 meses de conversaciones y 30 días de borradores. */
    public static function limpiar(): void
    {
        if (!self::disponible()) return;
        try {
            self::db()->query("DELETE FROM asistente_conversaciones WHERE creado < NOW() - INTERVAL 6 MONTH");
            self::db()->query("UPDATE asistente_borradores SET estado = 'expirado', datos_json = '{}'
                WHERE estado = 'pendiente' AND (creado < NOW() - INTERVAL 1 MONTH OR (expira_en IS NOT NULL AND expira_en < NOW()))");
            self::db()->query("DELETE FROM asistente_borradores WHERE estado IN ('expirado','rechazado') AND creado < NOW() - INTERVAL 90 DAY");
        } catch (\Throwable $e) {
        }
    }
}
