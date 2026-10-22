<?php

namespace Classes;

use Model\Actividad;
use Model\Bitacora;
use Model\Proyecto;

/**
 * API REST v1 (Fase 5.1): /api/v1/… con token (Authorization: Bearer pw_…), JSON de entrada y salida.
 * Atiende la petición completa desde public/index.php, antes del Router y sin sesión ni CSRF: el token ya identifica a la persona.
 * Reglas: lo que el usuario del token puede ver o gestionar en la web, eso mismo en la API; un token de solo lectura nunca escribe;
 * un visitante nunca escribe; un token ligado a un proyecto no ve los demás. La lista de rutas (RUTAS) también genera /api/v1/openapi.json.
 */
class ApiRest
{
    use AccesoDb;

    public const VERSION = '1';
    private const POR_PAGINA = 50;
    private const MAX_POR_PAGINA = 200;

    /** [método, expresión de la ruta, manejador, ¿escribe?, resumen] — {id} es numérico */
    private const RUTAS = [
        ['GET', '/', 'info', false, 'Información de la API y de tu token'],
        ['GET', '/proyectos', 'proyectos', false, 'Proyectos que puedes ver'],
        ['GET', '/proyectos/{id}', 'proyecto', false, 'Un proyecto con su resumen'],
        ['GET', '/proyectos/{id}/partidas', 'partidas', false, 'Partidas del programa'],
        ['GET', '/proyectos/{id}/actividades', 'actividades', false, 'Actividades del programa (filtros: status, partida, desde, hasta, atrasadas)'],
        ['GET', '/proyectos/{id}/restricciones', 'restricciones', false, 'Restricciones activas'],
        ['GET', '/proyectos/{id}/bitacora', 'bitacoraLista', false, 'Registros de la bitácora (filtros: importancia, desde, hasta)'],
        ['POST', '/proyectos/{id}/bitacora', 'bitacoraCrear', true, 'Agregar un registro a la bitácora'],
        ['GET', '/proyectos/{id}/punch', 'punchLista', false, 'Pendientes del punch list (filtros: estado, severidad)'],
        ['POST', '/proyectos/{id}/punch', 'punchCrear', true, 'Registrar un pendiente del punch list'],
        ['GET', '/proyectos/{id}/rfis', 'rfiLista', false, 'RFI del proyecto (filtro: estado)'],
        ['POST', '/proyectos/{id}/rfis', 'rfiCrear', true, 'Crear un RFI'],
        ['GET', '/proyectos/{id}/submittals', 'submittals', false, 'Paquetes de submittals'],
        ['GET', '/proyectos/{id}/reportes', 'reportes', false, 'Reportes diarios'],
        ['GET', '/proyectos/{id}/curva-s', 'curvaS', false, 'Curva S: avance programado y real por semana'],
        ['GET', '/actividades/{id}', 'actividad', false, 'Una actividad'],
        ['PATCH', '/actividades/{id}', 'actividadEditar', true, 'Cambiar avance, responsable, prioridad, PTA o campos libres de una actividad'],
        ['PATCH', '/punch/{id}', 'punchEstado', true, 'Cambiar el estado de un pendiente del punch list'],
        ['PATCH', '/rfis/{id}', 'rfiEstado', true, 'Cambiar el estado de un RFI (y responderlo)'],
        ['GET', '/openapi', 'openapi', false, 'Descripción OpenAPI 3 de esta API (también en /openapi.json con Apache)'],
        ['GET', '/openapi.json', 'openapi', false, 'Descripción OpenAPI 3 de esta API'],
    ];

    /** Rutas de la API para documentarlas (método, ruta, ¿escribe?, resumen) */
    public static function catalogo(): array
    {
        return array_map(fn($r) => ['metodo' => $r[0], 'ruta' => '/api/v1' . ($r[1] === '/' ? '' : $r[1]), 'escribe' => $r[3], 'resumen' => $r[4]], array_values(array_filter(self::RUTAS, fn($r) => $r[1] !== '/openapi.json')));
    }

    private static array $usuario = [];
    private static array $token = [];

    // ---------- entrada y salida ----------

    private static function responder($datos, int $codigo = 200, array $extra = []): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($datos + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private static function error(int $codigo, string $clave, string $mensaje): never
    {
        if ($codigo === 401) header('WWW-Authenticate: Bearer realm="ProjectWeb"');
        self::responder(['error' => ['codigo' => $clave, 'mensaje' => $mensaje]], $codigo);
    }

    private static function cabecera(string $nombre): string
    {
        $k = 'HTTP_' . strtoupper(str_replace('-', '_', $nombre));
        return (string)($_SERVER[$k] ?? $_SERVER['REDIRECT_' . $k] ?? '');
    }

    private static function cuerpo(): array
    {
        $crudo = (string)file_get_contents('php://input');
        if ($crudo !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json')) {
            $j = json_decode($crudo, true);
            if (!is_array($j)) self::error(400, 'json_invalido', 'El cuerpo no es un JSON válido.');
            return $j;
        }
        return $_POST;
    }

    // ---------- punto de entrada ----------

    /** ¿La petición es de la API? */
    public static function esDeLaApi(): bool
    {
        $r = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return $r === '/api/v1' || str_starts_with($r, '/api/v1/');
    }

    /** Atiende la petición y termina */
    public static function despachar(): never
    {
        $ruta = substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', 7);   // quita «/api/v1»
        $ruta = rtrim($ruta, '/');
        if ($ruta === '') $ruta = '/';
        $metodo = $_SERVER['REQUEST_METHOD'];
        if ($metodo === 'OPTIONS') { http_response_code(204); header('Allow: GET, POST, PATCH, OPTIONS'); exit; }

        $manejador = null; $m = []; $permitidos = [];
        foreach (self::RUTAS as [$met, $patron, $fn, $escribe]) {
            if (!preg_match('#^' . str_replace('{id}', '(\d+)', $patron) . '$#', $ruta, $mm)) continue;
            $permitidos[] = $met;
            if ($met === $metodo) { $manejador = [$fn, $escribe]; $m = array_slice($mm, 1); }
        }
        if (!$manejador) {
            if ($permitidos) { header('Allow: ' . implode(', ', $permitidos)); self::error(405, 'metodo_no_permitido', 'Método no permitido en esta ruta.'); }
            self::error(404, 'no_encontrado', 'La ruta no existe. Consulta /api/v1/openapi.');
        }
        if ($manejador[0] === 'openapi') self::responder(self::openapi());

        // autenticación
        $auth = self::cabecera('Authorization');
        $token = preg_match('/^Bearer\s+(\S+)$/i', $auth, $x) ? $x[1] : self::cabecera('X-API-Key');
        if ($token === '') self::error(401, 'sin_token', 'Falta el token: envía «Authorization: Bearer pw_…».');
        if (!ApiTokens::disponible()) self::error(503, 'api_no_disponible', 'La API no está habilitada en este servidor (falta la migración).');
        $r = ApiTokens::verificar($token);
        if (!isset($r['token'])) {
            $msg = ['invalido' => 'Token no válido.', 'revocado' => 'El token fue revocado.', 'caducado' => 'El token caducó.', 'limite' => 'Demasiadas peticiones: máximo ' . ApiTokens::LIMITE_POR_MINUTO . ' por minuto.'][$r[1]] ?? 'No autorizado.';
            if ($r[1] === 'limite') { header('Retry-After: 60'); self::error(429, 'limite', $msg); }
            self::error(401, $r[1], $msg);
        }
        self::$token = $r['token'];
        self::$usuario = $r['usuario'];
        if ($manejador[1] && (!(int)self::$token['escritura'] || self::$usuario['rol'] === 'visitante')) {
            self::error(403, 'solo_lectura', 'Este token es de solo lectura (o tu rol no puede modificar datos).');
        }

        // Los ayudantes de permisos de la web leen la sesión: se les da una de este uso, sin cookie y sin dejar rastro
        ini_set('session.use_cookies', '0');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cache_limiter', '');
        if (session_status() === PHP_SESSION_ACTIVE) session_abort();
        session_id('api' . bin2hex(random_bytes(12)));
        session_start();
        $_SESSION = ['id' => (int)self::$usuario['id'], 'nombre' => self::$usuario['nombre'], 'email' => self::$usuario['email'], 'rol' => self::$usuario['rol']];
        register_shutdown_function(fn() => @session_destroy());

        try {
            $fn = $manejador[0];
            self::$fn(...array_map('intval', $m));
        } catch (\Throwable $e) {
            error_log('API: ' . $e->getMessage());
            self::error(500, 'error_interno', 'Error interno.');
        }
        self::error(500, 'error_interno', 'Sin respuesta.');
    }

    // ---------- permisos y utilidades ----------

    /** Proyecto accesible con este token o corta con 404 (no se distingue «no existe» de «no es tuyo») */
    private static function proyectoDe(int $id, bool $escribir = false): Proyecto
    {
        $p = Proyecto::find($id);
        $lig = (int)(self::$token['proyecto_id'] ?? 0);
        if (!$p || ($lig && $lig !== $id) || !puede_ver_proyecto($p)) self::error(404, 'no_encontrado', 'El proyecto no existe o no tienes acceso.');
        if ($escribir && !puede_gestionar_proyecto($p)) self::error(403, 'sin_permiso', 'No puedes modificar este proyecto.');
        return $p;
    }

    /** Proyecto de una actividad / pendiente / RFI, comprobando el acceso */
    private static function proyectoDeFila(string $sql, bool $escribir): array
    {
        $f = self::filas($sql)[0] ?? null;
        if (!$f) self::error(404, 'no_encontrado', 'No existe.');
        self::proyectoDe((int)$f['proyecto_id'], $escribir);
        return $f;
    }

    private static function pagina(): array
    {
        $por = max(1, min(self::MAX_POR_PAGINA, (int)($_GET['por_pagina'] ?? self::POR_PAGINA)));
        $pag = max(1, (int)($_GET['pagina'] ?? 1));
        return [$por, $pag, ($pag - 1) * $por];
    }

    private static function lista(string $from, string $where, string $orden, string $columnas, array $meta = [], ?callable $fila = null): never
    {
        [$por, $pag, $off] = self::pagina();
        $total = (int)(self::filas("SELECT COUNT(*) n FROM $from WHERE $where")[0]['n'] ?? 0);
        $filas = self::filas("SELECT $columnas FROM $from WHERE $where ORDER BY $orden LIMIT $por OFFSET $off");
        if ($fila) $filas = array_map($fila, $filas);
        self::responder(['data' => $filas, 'meta' => ['pagina' => $pag, 'por_pagina' => $por, 'total' => $total, 'paginas' => (int)ceil($total / $por)] + $meta]);
    }

    private static function fecha($v): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', (string)$v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }

    private static function enteros(array $fila, array $campos): array
    {
        foreach ($campos as $c) if (isset($fila[$c])) $fila[$c] = (int)$fila[$c];
        return $fila;
    }

    // ---------- lectura ----------

    private static function info(): never
    {
        self::responder(['data' => [
            'api' => 'ProjectWeb', 'version' => self::VERSION,
            'usuario' => ['id' => (int)self::$usuario['id'], 'nombre' => self::$usuario['nombre'], 'rol' => self::$usuario['rol']],
            'token' => ['nombre' => self::$token['nombre'], 'escritura' => (bool)(int)self::$token['escritura'], 'proyecto_id' => self::$token['proyecto_id'] ? (int)self::$token['proyecto_id'] : null, 'expira' => self::$token['expira']],
            'limite_por_minuto' => ApiTokens::LIMITE_POR_MINUTO, 'eventos_webhook' => array_keys(Webhooks::EVENTOS),
        ]]);
    }

    private static function proyectos(): never
    {
        $lig = (int)(self::$token['proyecto_id'] ?? 0);
        $out = [];
        foreach (Proyecto::visiblesPara(['id' => self::$usuario['id'], 'rol' => self::$usuario['rol']]) as $p) {
            if ($lig && $lig !== (int)$p->id) continue;
            $out[] = self::resumenProyecto($p);
        }
        self::responder(['data' => $out, 'meta' => ['total' => count($out)]]);
    }

    private static function resumenProyecto(Proyecto $p): array
    {
        return ['id' => (int)$p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre, 'ubicacion' => $p->ubicacion, 'empresa' => $p->empresa, 'contratista' => $p->contratista,
            'fecha_inicio' => $p->fecha_inicio, 'fecha_fin' => $p->fecha_fin, 'progreso' => $p->getProgreso(), 'puede_editar' => puede_gestionar_proyecto($p)];
    }

    private static function proyecto(int $id): never
    {
        $p = self::proyectoDe($id);
        $r = self::resumenProyecto($p);
        $c = self::filas("SELECT COUNT(*) total, SUM(a.progreso >= 100) completadas, SUM(a.progreso < 100 AND a.fecha_fin < CURDATE() AND a.hito = 0) atrasadas,
            SUM(a.progreso < 100 AND CURDATE() BETWEEN a.fecha_inicio AND a.fecha_fin) en_curso FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $id")[0] ?? [];
        $r['actividades'] = self::enteros($c, ['total', 'completadas', 'atrasadas', 'en_curso']);
        $r['restricciones_activas'] = (int)(self::filas("SELECT COUNT(*) n FROM restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id WHERE pa.proyecto_id = $id AND r.estado = 'activa'")[0]['n'] ?? 0);
        $r['punch_abiertos'] = (int)(self::filas("SELECT COUNT(*) n FROM punch_items WHERE proyecto_id = $id AND estado IN ('abierto','en_proceso','corregido')")[0]['n'] ?? 0);
        self::responder(['data' => $r]);
    }

    private static function partidas(int $id): never
    {
        self::proyectoDe($id);
        self::lista('partidas', "proyecto_id = $id", 'orden, id', 'id, codigo, nombre, descripcion, color_bg AS color, orden, fecha_inicio, fecha_fin', [], fn($f) => self::enteros($f, ['id', 'orden']));
    }

    private static function actividades(int $id): never
    {
        self::proyectoDe($id);
        $w = ["pa.proyecto_id = $id"];
        if (in_array($_GET['status'] ?? '', ['no_iniciada', 'en_proceso', 'completada'], true)) $w[] = "a.status = '{$_GET['status']}'";
        if ((int)($_GET['partida'] ?? 0)) $w[] = 'a.partida_id = ' . (int)$_GET['partida'];
        if ($d = self::fecha($_GET['desde'] ?? '')) $w[] = "a.fecha_fin >= '$d'";
        if ($h = self::fecha($_GET['hasta'] ?? '')) $w[] = "a.fecha_inicio <= '$h'";
        if (($_GET['atrasadas'] ?? '') === '1') $w[] = 'a.progreso < 100 AND a.hito = 0 AND a.fecha_fin < CURDATE()';
        $cps = CamposPersonalizados::deProyecto($id, 'actividad');
        $vals = $cps ? CamposPersonalizados::valores($id, 'actividad') : [];
        self::lista('actividades a JOIN partidas pa ON pa.id = a.partida_id', implode(' AND ', $w), 'pa.orden, a.orden, a.id',
            'a.id, a.partida_id, a.codigo, a.nombre, a.descripcion, a.fecha_inicio, a.fecha_fin, a.duracion_dias, a.progreso, a.status, a.prioridad, a.hito, a.pta, a.responsable, a.rec_inicio, a.rec_fin, a.libre_texto, a.libre_numero, a.libre_fecha',
            [], function ($f) use ($cps, $vals) {
                $f = self::enteros($f, ['id', 'partida_id', 'duracion_dias', 'progreso']);
                $f['hito'] = (bool)(int)$f['hito']; $f['pta'] = (bool)(int)$f['pta'];
                if ($cps) { $f['campos'] = []; foreach ($cps as $c) $f['campos'][$c['nombre']] = $vals[$f['id']][$c['id']] ?? null; }
                return $f;
            });
    }

    private static function actividad(int $aid): never
    {
        $f = self::proyectoDeFila("SELECT a.*, pa.proyecto_id FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $aid", false);
        $f['hito'] = (bool)(int)$f['hito']; $f['pta'] = (bool)(int)$f['pta'];
        $f = self::enteros($f, ['id', 'partida_id', 'proyecto_id', 'duracion_dias', 'progreso']);
        unset($f['fecha_fija'], $f['sin_material'], $f['orden']);
        $cps = CamposPersonalizados::deProyecto($f['proyecto_id'], 'actividad');
        if ($cps) { $v = CamposPersonalizados::valores($f['proyecto_id'], 'actividad')[$aid] ?? []; $f['campos'] = []; foreach ($cps as $c) $f['campos'][$c['nombre']] = $v[$c['id']] ?? null; }
        self::responder(['data' => $f]);
    }

    private static function restricciones(int $id): never
    {
        self::proyectoDe($id);
        self::lista('restricciones r JOIN actividades a ON a.id = r.actividad_id JOIN partidas pa ON pa.id = a.partida_id LEFT JOIN tipos_restriccion t ON t.id = r.tipo_id',
            "pa.proyecto_id = $id AND r.estado = 'activa'", 'r.fecha IS NULL, r.fecha, r.id',
            'r.id, r.actividad_id, a.codigo AS actividad_codigo, a.nombre AS actividad, t.nombre AS tipo, r.codigo, r.descripcion, r.fecha, r.estado', [], fn($f) => self::enteros($f, ['id', 'actividad_id']));
    }

    private static function bitacoraLista(int $id): never
    {
        self::proyectoDe($id);
        $w = ["proyecto_id = $id"];
        if (isset(Bitacora::IMPORTANCIAS[$_GET['importancia'] ?? ''])) $w[] = "importancia = '{$_GET['importancia']}'";
        if ($d = self::fecha($_GET['desde'] ?? '')) $w[] = "fecha >= '$d'";
        if ($h = self::fecha($_GET['hasta'] ?? '')) $w[] = "fecha <= '$h'";
        self::lista('bitacora', implode(' AND ', $w), 'fecha DESC, id DESC', 'id, fecha, departamento, descripcion, importancia', [], fn($f) => self::enteros($f, ['id']));
    }

    private static function bitacoraCrear(int $id): never
    {
        $p = self::proyectoDe($id, true);
        $d = self::cuerpo();
        $b = new Bitacora(['proyecto_id' => $p->id, 'fecha' => (string)($d['fecha'] ?? date('Y-m-d')), 'departamento' => mb_substr(trim((string)($d['departamento'] ?? '')), 0, 100),
            'descripcion' => mb_substr(trim((string)($d['descripcion'] ?? '')), 0, 5000), 'importancia' => (string)($d['importancia'] ?? 'media')]);
        $al = $b->validar();
        if ($al) self::error(422, 'datos_invalidos', implode(' ', $al['error'] ?? []));
        $r = $b->guardar();
        if (!$r['resultado']) self::error(500, 'error_interno', 'No se pudo guardar.');
        self::responder(['data' => ['id' => (int)$r['id'], 'fecha' => $b->fecha, 'departamento' => $b->departamento, 'descripcion' => $b->descripcion, 'importancia' => $b->importancia]], 201);
    }

    private static function punchLista(int $id): never
    {
        self::proyectoDe($id);
        $w = ["proyecto_id = $id"];
        if (isset(Punch::ESTADOS[$_GET['estado'] ?? ''])) $w[] = "estado = '{$_GET['estado']}'";
        elseif (($_GET['estado'] ?? '') === 'pendientes') $w[] = "estado IN ('abierto','en_proceso','corregido')";
        if (isset(Punch::SEVERIDADES[$_GET['severidad'] ?? ''])) $w[] = "severidad = '{$_GET['severidad']}'";
        self::lista('punch_items', implode(' AND ', $w), "FIELD(estado,'abierto','en_proceso','corregido','cerrado','rechazado'), FIELD(severidad,'critica','alta','media','baja'), id DESC",
            'id, titulo, descripcion, ubicacion, categoria, severidad, estado, responsable, contratista, fecha_limite, actividad_id, origen, creado', [], fn($f) => self::enteros($f, ['id', 'actividad_id']));
    }

    private static function punchCrear(int $id): never
    {
        $p = self::proyectoDe($id, true);
        [$ok, $msg, $nid] = Punch::guardar((int)$p->id, self::cuerpo());
        if (!$ok) self::error(422, 'datos_invalidos', $msg);
        self::responder(['data' => ['id' => $nid]], 201);
    }

    private static function punchEstado(int $pid): never
    {
        $f = self::proyectoDeFila("SELECT proyecto_id FROM punch_items WHERE id = $pid", true);
        $estado = (string)(self::cuerpo()['estado'] ?? '');
        [$ok, $msg] = Punch::estado(self::proyectoDe((int)$f['proyecto_id'], true), $pid, $estado);
        if (!$ok) self::error(422, 'datos_invalidos', $msg . ' Estados: ' . implode(', ', array_keys(Punch::ESTADOS)) . '.');
        self::responder(['data' => ['id' => $pid, 'estado' => $estado]]);
    }

    private static function rfiLista(int $id): never
    {
        self::proyectoDe($id);
        $w = ["proyecto_id = $id"];
        if (isset(Rfi::ESTADOS[$_GET['estado'] ?? ''])) $w[] = "estado = '{$_GET['estado']}'";
        self::lista('rfis', implode(' AND ', $w), 'id DESC', 'id, numero, asunto, pregunta, respuesta, responsable, solicitante, prioridad, estado, fecha, fecha_limite, fecha_respuesta, actividad_id', [], fn($f) => self::enteros($f, ['id', 'actividad_id']));
    }

    private static function rfiCrear(int $id): never
    {
        $p = self::proyectoDe($id, true);
        $d = self::cuerpo();
        unset($d['id']);
        $err = Rfi::guardar((int)$p->id, $d, 'API · ' . self::$usuario['nombre']);
        if ($err) self::error(422, 'datos_invalidos', $err);
        $f = self::filas("SELECT id, numero, asunto, estado, prioridad, fecha, fecha_limite FROM rfis WHERE proyecto_id = $id ORDER BY id DESC LIMIT 1")[0] ?? [];
        self::responder(['data' => self::enteros($f, ['id'])], 201);
    }

    private static function rfiEstado(int $rid): never
    {
        $f = self::proyectoDeFila("SELECT proyecto_id FROM rfis WHERE id = $rid", true);
        $d = self::cuerpo();
        $pid = (int)$f['proyecto_id'];
        if (isset($d['respuesta'])) {
            self::db()->query("UPDATE rfis SET respuesta = '" . self::esc(mb_substr(trim((string)$d['respuesta']), 0, 5000)) . "' WHERE id = $rid AND proyecto_id = $pid");
        }
        if (isset($d['estado']) && !Rfi::cambiarEstado($pid, $rid, (string)$d['estado'])) self::error(422, 'datos_invalidos', 'Estado no válido: ' . implode(', ', array_keys(Rfi::ESTADOS)) . '.');
        self::responder(['data' => self::filas("SELECT id, numero, asunto, respuesta, estado, fecha_respuesta FROM rfis WHERE id = $rid")[0]]);
    }

    private static function submittals(int $id): never
    {
        self::proyectoDe($id);
        self::lista('submittals', "proyecto_id = $id", 'id DESC', 'id, numero_submittal AS numero, fecha, especialidad, departamento, para, estatus, fecha_respuesta', [], fn($f) => self::enteros($f, ['id']));
    }

    private static function reportes(int $id): never
    {
        self::proyectoDe($id);
        self::lista('reportes_diarios', "proyecto_id = $id", 'fecha DESC, id DESC', 'id, fecha, area, comentarios', [], fn($f) => self::enteros($f, ['id']));
    }

    private static function curvaS(int $id): never
    {
        $c = CurvaS::calcular(self::proyectoDe($id));
        self::responder(['data' => $c]);
    }

    // ---------- escritura ----------

    private static function actividadEditar(int $aid): never
    {
        $f = self::proyectoDeFila("SELECT pa.proyecto_id FROM actividades a JOIN partidas pa ON pa.id = a.partida_id WHERE a.id = $aid", true);
        $a = Actividad::find($aid);
        $d = self::cuerpo();
        $cambios = [];
        if (array_key_exists('progreso', $d)) {
            if (!is_numeric($d['progreso']) || $d['progreso'] < 0 || $d['progreso'] > 100) self::error(422, 'datos_invalidos', 'El progreso debe ser un número de 0 a 100.');
            $a->progreso = (int)round((float)$d['progreso']);
            $a->sincronizarStatus();
            $cambios[] = 'progreso';
        }
        if (isset($d['prioridad'])) {
            if (!in_array($d['prioridad'], ['baja', 'media', 'alta', 'critica'], true)) self::error(422, 'datos_invalidos', 'Prioridad: baja, media, alta o critica.');
            $a->prioridad = $d['prioridad'];
            $cambios[] = 'prioridad';
        }
        if (array_key_exists('pta', $d)) { $a->pta = $d['pta'] ? 1 : 0; $cambios[] = 'pta'; }
        if ($cambios && !$a->guardar()) self::error(500, 'error_interno', 'No se pudo guardar.');
        foreach (array_keys(ProgramaColumnas::EDITABLES) as $campo) {
            if (!array_key_exists($campo, $d)) continue;
            [$ok, $v] = ProgramaColumnas::guardarCampo($aid, $campo, (string)$d[$campo]);
            if (!$ok) self::error(422, 'datos_invalidos', "$campo: $v");
            $cambios[] = $campo;
        }
        if (!$cambios) self::error(422, 'sin_cambios', 'No hay nada que cambiar. Campos: progreso, prioridad, pta, ' . implode(', ', array_keys(ProgramaColumnas::EDITABLES)) . '.');
        if (array_intersect($cambios, array_keys(ProgramaColumnas::EDITABLES))) {   // los campos del programa no pasan por el modelo: se avisa aquí
            Webhooks::emitir((int)$f['proyecto_id'], 'actividad.actualizada', ['id' => $aid, 'cambios' => $cambios, 'origen' => 'api']);
        }
        self::actividad($aid);
    }

    // ---------- OpenAPI ----------

    private static function openapi(): array
    {
        $paths = [];
        foreach (self::RUTAS as [$met, $patron, , $escribe, $resumen]) {
            $ruta = preg_replace('/\{id\}/', '{id}', $patron);
            $op = ['summary' => $resumen, 'tags' => [explode('/', trim($patron, '/'))[0] ?: 'api'], 'responses' => ['200' => ['description' => 'Correcto'], '401' => ['description' => 'Token ausente o no válido'], '404' => ['description' => 'No existe o sin acceso']]];
            if (str_contains($patron, '{id}')) $op['parameters'] = [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]];
            if ($met === 'GET' && !str_contains($patron, 'openapi') && $patron !== '/' && !preg_match('#/(proyectos|actividades)/\{id\}$#', $patron)) {
                $op['parameters'] = ($op['parameters'] ?? []) + [1 => ['name' => 'pagina', 'in' => 'query', 'schema' => ['type' => 'integer']], 2 => ['name' => 'por_pagina', 'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => self::MAX_POR_PAGINA]]];
                $op['parameters'] = array_values($op['parameters']);
            }
            if ($escribe) { $op['requestBody'] = ['content' => ['application/json' => ['schema' => ['type' => 'object']]]]; $op['x-requiere-token-de-escritura'] = true; $op['responses']['403'] = ['description' => 'Token de solo lectura o sin permiso']; }
            $paths[$ruta][strtolower($met)] = $op;
        }
        return [
            'openapi' => '3.0.3',
            'info' => ['title' => 'ProjectWeb API', 'version' => self::VERSION, 'description' => 'Autenticación: «Authorization: Bearer pw_…». Los tokens se crean en Integraciones. Límite: ' . ApiTokens::LIMITE_POR_MINUTO . ' peticiones por minuto. Webhooks: ' . implode(', ', array_keys(Webhooks::EVENTOS)) . '.'],
            'servers' => [['url' => '/api/v1']],
            'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']]],
            'security' => [['bearer' => []]],
            'paths' => $paths,
        ];
    }
}
