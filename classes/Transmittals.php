<?php

namespace Classes;

/**
 * Envíos formales (transmittals) de documentos con su revisión congelada y acuse de recibo.
 * Cada destinatario recibe un token individual para firmar el acuse sin crear usuario.
 */
class Transmittals
{
    use AccesoDb;

    public const MEDIOS = ['correo' => 'Correo', 'enlace' => 'Enlace', 'impreso' => 'Impreso', 'otro' => 'Otro'];
    public const ESTADOS = ['borrador' => 'Borrador', 'enviado' => 'Enviado', 'cerrado' => 'Cerrado'];
    public const ACUSES = ['recibido' => 'Recibido conforme', 'observado' => 'Recibido con observaciones', 'rechazado' => 'No recibido'];

    public static function disponible(): bool
    {
        try {
            return (bool)self::db()->query('SELECT 1 FROM transmittals LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function lista(int $pid): array
    {
        return self::filas("SELECT t.*,
                (SELECT COUNT(*) FROM transmittal_documentos d WHERE d.transmittal_id = t.id) documentos,
                (SELECT COUNT(*) FROM transmittal_destinatarios r WHERE r.transmittal_id = t.id) destinatarios,
                (SELECT COUNT(*) FROM transmittal_destinatarios r WHERE r.transmittal_id = t.id AND r.estado = 'pendiente') pendientes,
                (SELECT COUNT(*) FROM transmittal_destinatarios r WHERE r.transmittal_id = t.id AND r.estado = 'observado') observados
            FROM transmittals t WHERE t.proyecto_id = $pid ORDER BY t.fecha DESC, t.id DESC");
    }

    public static function transmittal(int $pid, int $id): ?array
    {
        $t = self::filas("SELECT * FROM transmittals WHERE id = $id AND proyecto_id = $pid")[0] ?? null;
        if (!$t) return null;
        $t['documentos'] = self::filas("SELECT d.*, a.archivo_original FROM transmittal_documentos d
            LEFT JOIN archivos a ON a.id = d.archivo_id WHERE d.transmittal_id = $id ORDER BY d.orden, d.id");
        $t['destinatarios'] = self::filas("SELECT * FROM transmittal_destinatarios WHERE transmittal_id = $id ORDER BY id");
        return $t;
    }

    public static function porToken(string $token): ?array
    {
        $token = preg_replace('/[^a-f0-9]/i', '', $token);
        if (strlen($token) !== 40) return null;
        $r = self::filas("SELECT r.*, t.numero, t.fecha, t.asunto, t.mensaje, t.medio, t.fecha_limite, t.estado transmittal_estado,
                t.proyecto_id, p.nombre proyecto
            FROM transmittal_destinatarios r JOIN transmittals t ON t.id = r.transmittal_id
            JOIN proyectos p ON p.id = t.proyecto_id WHERE r.token = '$token'")[0] ?? null;
        if (!$r) return null;
        $r['documentos'] = self::filas("SELECT nombre, revision FROM transmittal_documentos WHERE transmittal_id = " . (int)$r['transmittal_id'] . ' ORDER BY orden, id');
        return $r;
    }

    public static function siguienteNumero(int $pid): string
    {
        $n = (int)(self::filas("SELECT COUNT(*) n FROM transmittals WHERE proyecto_id = $pid")[0]['n'] ?? 0) + 1;
        return 'TR-' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
    }

    /** Crea o actualiza un envío en borrador. Devuelve [ok, mensaje, id]. */
    public static function guardar(int $pid, array $d): array
    {
        $id = (int)($d['id'] ?? 0);
        $actual = $id ? self::transmittal($pid, $id) : null;
        if ($id && !$actual) return [false, 'El envío no pertenece al proyecto', 0];
        if ($actual && $actual['estado'] !== 'borrador') return [false, 'El envío ya fue enviado; no se puede editar', $id];
        $numero = mb_substr(trim((string)($d['numero'] ?? '')), 0, 40) ?: self::siguienteNumero($pid);
        $fecha = self::fecha($d['fecha'] ?? '') ?: date('Y-m-d');
        $de = mb_substr(trim((string)($d['de'] ?? '')), 0, 150) ?: (string)(usuario_actual()['nombre'] ?? '');
        $asunto = mb_substr(trim((string)($d['asunto'] ?? '')), 0, 250);
        if ($asunto === '') return [false, 'Escribe el asunto del envío', $id];
        $mensaje = mb_substr(trim((string)($d['mensaje'] ?? '')), 0, 5000);
        $medio = isset(self::MEDIOS[$d['medio'] ?? '']) ? $d['medio'] : 'correo';
        $limite = self::fecha($d['fecha_limite'] ?? '');
        $estado = ($d['estado'] ?? '') === 'borrador' ? 'borrador' : 'enviado';

        $docs = [];
        foreach ((array)($d['archivo_id'] ?? []) as $archivoId) {
            $archivoId = (int)$archivoId;
            if (!$archivoId) continue;
            $a = self::filas("SELECT id, nombre, numero_revision FROM archivos WHERE id = $archivoId AND proyecto_id = $pid")[0] ?? null;
            if (!$a) continue;
            $docs[$archivoId] = ['archivo_id' => $archivoId, 'nombre' => $a['nombre'], 'revision' => $a['numero_revision']];
        }
        if (!$docs) return [false, 'Selecciona al menos un documento', $id];

        $dests = [];
        foreach ((array)($d['dest_nombre'] ?? []) as $i => $nombre) {
            $nombre = mb_substr(trim((string)$nombre), 0, 150);
            if ($nombre === '') continue;
            $email = mb_substr(trim((string)($d['dest_email'][$i] ?? '')), 0, 150);
            $dests[] = ['nombre' => $nombre, 'email' => $email];
        }
        if (!$dests) return [false, 'Agrega al menos un destinatario', $id];

        $v = fn($x) => "'" . self::esc($x) . "'";
        $campos = "numero = {$v($numero)}, fecha = '$fecha', de = {$v($de)}, asunto = {$v($asunto)}, mensaje = {$v($mensaje)},
            medio = '$medio', fecha_limite = " . ($limite ? "'$limite'" : 'NULL') . ", estado = '$estado'";
        $usuario = mb_substr((string)(usuario_actual()['nombre'] ?? ''), 0, 150);
        $db = self::db();
        $db->begin_transaction();
        try {
            if ($id) {
                $db->query("UPDATE transmittals SET $campos WHERE id = $id AND proyecto_id = $pid");
                $db->query("DELETE FROM transmittal_documentos WHERE transmittal_id = $id");
                $db->query("DELETE FROM transmittal_destinatarios WHERE transmittal_id = $id");
            } else {
                $db->query("INSERT INTO transmittals SET proyecto_id = $pid, usuario = {$v($usuario)}, $campos");
                $id = (int)$db->insert_id;
            }
            $orden = 0;
            foreach ($docs as $doc) {
                $db->query("INSERT INTO transmittal_documentos (transmittal_id, archivo_id, nombre, revision, orden)
                    VALUES ($id, " . (int)$doc['archivo_id'] . ", {$v($doc['nombre'])}, {$v($doc['revision'])}, " . $orden++ . ')');
            }
            foreach ($dests as $dest) {
                $token = bin2hex(random_bytes(20));
                $db->query("INSERT INTO transmittal_destinatarios (transmittal_id, nombre, email, token)
                    VALUES ($id, {$v($dest['nombre'])}, {$v($dest['email'])}, '$token')");
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            return [false, 'No se pudo guardar el envío', $id];
        }
        self::auditar($pid, $id && $actual ? 'actualizar_envio' : 'crear_envio', 'editar', 'Guardó el envío ' . $numero . ' con ' . count($docs) . ' documento(s)', $id);
        return [true, 'Envío guardado', $id];
    }

    public static function acuse(string $token, array $d): array
    {
        $r = self::porToken($token);
        if (!$r) return [false, 'El enlace de acuse no es válido'];
        if ($r['estado'] !== 'pendiente') return [false, 'Este envío ya tiene un acuse registrado'];
        $estado = isset(self::ACUSES[$d['estado'] ?? '']) ? $d['estado'] : null;
        if (!$estado) return [false, 'Elige el resultado del acuse'];
        $nombre = mb_substr(trim((string)($d['nombre'] ?? '')), 0, 150);
        if ($nombre === '') $nombre = (string)$r['nombre'];
        $comentario = mb_substr(trim((string)($d['comentario'] ?? '')), 0, 5000);
        $ip = function_exists('ip_cliente') ? ip_cliente() : '';
        self::db()->query("UPDATE transmittal_destinatarios SET estado = '$estado', acuse_comentario = '" . self::esc($comentario) . "',
            acuse_nombre = '" . self::esc($nombre) . "', acuse_fecha = NOW(), acuse_ip = '" . self::esc($ip) . "'
            WHERE id = " . (int)$r['id'] . " AND estado = 'pendiente'");
        $pend = (int)(self::filas("SELECT COUNT(*) n FROM transmittal_destinatarios WHERE transmittal_id = " . (int)$r['transmittal_id'] . " AND estado = 'pendiente'")[0]['n'] ?? 0);
        if ($pend === 0) self::db()->query("UPDATE transmittals SET estado = 'cerrado' WHERE id = " . (int)$r['transmittal_id']);
        self::auditar((int)$r['proyecto_id'], 'acuse_envio', 'editar', 'Acuse de ' . $r['numero'] . ': ' . self::ACUSES[$estado] . ' por ' . $nombre, (int)$r['transmittal_id']);
        return [true, 'Acuse registrado. Gracias.'];
    }

    public static function resumen(int $pid): array
    {
        $total = (int)(self::filas("SELECT COUNT(*) n FROM transmittals WHERE proyecto_id = $pid")[0]['n'] ?? 0);
        $sin = (int)(self::filas("SELECT COUNT(*) n FROM transmittal_destinatarios r JOIN transmittals t ON t.id = r.transmittal_id
            WHERE t.proyecto_id = $pid AND t.estado = 'enviado' AND r.estado = 'pendiente'")[0]['n'] ?? 0);
        $venc = (int)(self::filas("SELECT COUNT(*) n FROM transmittal_destinatarios r JOIN transmittals t ON t.id = r.transmittal_id
            WHERE t.proyecto_id = $pid AND t.estado = 'enviado' AND r.estado = 'pendiente' AND t.fecha_limite IS NOT NULL AND t.fecha_limite < CURDATE()")[0]['n'] ?? 0);
        $obs = (int)(self::filas("SELECT COUNT(*) n FROM transmittal_destinatarios r JOIN transmittals t ON t.id = r.transmittal_id
            WHERE t.proyecto_id = $pid AND r.estado = 'observado'")[0]['n'] ?? 0);
        return ['total' => $total, 'sin_acuse' => $sin, 'vencidos' => $venc, 'observados' => $obs];
    }

    private static function fecha($v): ?string
    {
        $v = trim((string)$v);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return null;
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $v : null;
    }

    private static function auditar(int $pid, string $evento, string $accion, string $resumen, int $id): void
    {
        try {
            \Classes\Auditoria::registrar($pid, 'archivos', $evento, $accion, $resumen, null, null, $id, 'Transmittal');
        } catch (\Throwable $e) {
        }
    }
}
