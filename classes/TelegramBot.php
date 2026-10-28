<?php

namespace Classes;

use Model\Actividad;
use Model\Bitacora;
use Model\Evidencia;
use Model\Proyecto;

/**
 * Conversación del bot de Telegram (chat privado con una persona ya vinculada):
 *   /avance [texto]  elegir actividad con botones y fijar su avance (o escribir el %)     /hoy   actividades de hoy y atrasadas
 *   foto             se guarda como evidencia de la actividad elegida                    /nota  anota en la bitácora
 *   /clima           pronóstico de hoy y mañana                                          /proyecto  cambiar de proyecto
 * Las reglas son las de la web: Actividad::sincronizarStatus(), Almacenamiento::cabe(), permisos de Telegram::puedeGestionar().
 * En grupos el bot solo atiende el código de vinculación y manda avisos; no recibe órdenes.
 */
class TelegramBot
{
    use AccesoDb;

    private const VIGENCIA_ESTADO = 1800;   // 30 min eligiendo actividad antes de olvidarla
    private const MAX_BOTONES = 10;
    private const MAX_FOTO = 15 * 1024 * 1024;
    private const MAX_FOTOS_PENDIENTES = 10;

    public static function procesar(array $u): void
    {
        if (isset($u['callback_query'])) self::callback($u['callback_query'], (int)($u['update_id'] ?? 0));
        elseif (isset($u['my_chat_member'])) self::membresia($u['my_chat_member']);
        elseif (isset($u['message'])) self::mensaje($u['message'], (int)($u['update_id'] ?? 0));
    }

    // ---------- estado de la conversación ----------

    /** true si es un update nuevo (Telegram reintenta si tardamos); lo marca como atendido */
    private static function nuevo(int $chat, int $updateId): bool
    {
        if ($updateId > 0) {
            $f = self::filas("SELECT ultimo_update FROM telegram_estado WHERE chat_id = $chat")[0] ?? null;
            if ($f && $updateId <= (int)$f['ultimo_update']) return false;
            self::db()->query("INSERT INTO telegram_estado (chat_id, ultimo_update, actualizado) VALUES ($chat, $updateId, NOW()) ON DUPLICATE KEY UPDATE ultimo_update = GREATEST(ultimo_update, VALUES(ultimo_update))");
        }
        return true;
    }

    /** @return array{paso: string, datos: array} vacío si no hay o ya venció */
    private static function estado(int $chat): array
    {
        $f = self::filas('SELECT paso, datos, actualizado >= DATE_SUB(NOW(), INTERVAL ' . self::VIGENCIA_ESTADO . " SECOND) AS vigente FROM telegram_estado WHERE chat_id = $chat")[0] ?? null;   // la vigencia la mide MySQL: PHP y MySQL pueden tener zonas horarias distintas
        if (!$f || $f['paso'] === '' || !(int)$f['vigente']) return ['paso' => '', 'datos' => []];
        return ['paso' => $f['paso'], 'datos' => json_decode((string)$f['datos'], true) ?: []];
    }

    private static function fijar(int $chat, string $paso, array $datos = []): void
    {
        $d = self::esc(json_encode($datos, JSON_UNESCAPED_UNICODE));
        self::db()->query("INSERT INTO telegram_estado (chat_id, paso, datos, actualizado) VALUES ($chat, '" . self::esc($paso) . "', '$d', NOW())
            ON DUPLICATE KEY UPDATE paso = VALUES(paso), datos = VALUES(datos), actualizado = NOW()");
    }

    private static function decir(int $chat, string $html, ?array $botones = null): void
    {
        Telegram::enviar($chat, $html, $botones);
    }

    // ---------- mensajes ----------

    private static function mensaje(array $m, int $updateId): void
    {
        $chat = (int)($m['chat']['id'] ?? 0);
        if (!$chat || !self::nuevo($chat, $updateId)) return;
        $privado = ($m['chat']['type'] ?? '') === 'private';
        $cmd = self::comando((string)($m['text'] ?? ''));
        if ($cmd && $cmd[0] === 'start' && preg_match('/^[A-Za-z0-9_-]{6,64}$/', $cmd[1])) { self::vincularCodigo($m, $cmd[1], $privado); return; }
        if (!$privado) {
            if ($cmd && in_array($cmd[0], ['start', 'ayuda', 'help'], true)) self::decir($chat, "Este grupo solo recibe avisos de obra. Para activarlos, abre <b>Integraciones</b> en ProjectWeb y pulsa «Agregar a un grupo» en el proyecto.");
            return;
        }
        $v = Telegram::vinculoDeChat($chat);
        $u = $v ? Telegram::usuarioPorId((int)$v['usuario_id']) : null;
        if (!$v || !$u || $u['rol'] === 'visitante') {
            self::decir($chat, "👋 Aún no sé quién eres.\nEntra a <b>ProjectWeb → Integraciones</b> y pulsa «Conectar mi Telegram»; se abrirá este chat y quedará vinculado.");
            return;
        }
        if (!empty($m['photo']) || (str_starts_with((string)($m['document']['mime_type'] ?? ''), 'image/'))) { self::foto($m, $v, $u); return; }
        if ($cmd) {
            switch ($cmd[0]) {
                case 'start': case 'ayuda': case 'help': self::ayuda($chat); return;
                case 'proyecto': self::elegirProyecto($chat, $u); return;
                case 'avance': self::avance($chat, $v, $u, $cmd[1]); return;
                case 'hoy': self::hoy($chat, $v, $u); return;
                case 'nota': self::nota($chat, $v, $u, $cmd[1]); return;
                case 'clima': self::clima($chat, $v, $u); return;
                case 'salud': self::salud($chat, $v, $u); return;
                case 'pdf': self::pdf($chat, $v, $u, $cmd[1]); return;
                case 'cancelar': self::fijar($chat, ''); self::decir($chat, 'Listo, no hay nada pendiente.'); return;
            }
            self::decir($chat, 'No conozco ese comando. Escribe /ayuda.');
            return;
        }
        $t = trim((string)($m['text'] ?? ''));
        $e = self::estado($chat);
        if ($e['paso'] === 'act' && preg_match('/^(\d{1,3})\s*%?$/', $t, $x)) {
            if ((int)$x[1] > 100) { self::decir($chat, 'El avance va de 0 a 100.'); return; }
            self::decir($chat, self::aplicarAvance((int)($e['datos']['a'] ?? 0), (int)$x[1], $u));
            return;
        }
        if ($e['paso'] === 'rfi' && $t !== '') {   // respondió al RFI que eligió con el botón «Responder»
            self::rfiGuardarRespuesta($chat, $u, (int)($e['datos']['r'] ?? 0), $t);
            return;
        }
        self::decir($chat, "No entendí 🤔. Usa /avance para reportar avance, manda una foto para guardarla como evidencia, o /ayuda.");
    }

    /** «/avance@bot muro» → ['avance', 'muro'] */
    public static function comando(string $texto): ?array
    {
        if (!preg_match('/^\/([A-Za-z_]+)(?:@\w+)?(?:\s+(.*))?$/su', trim($texto), $m)) return null;
        return [strtolower($m[1]), trim($m[2] ?? '')];
    }

    private static function ayuda(int $chat): void
    {
        self::decir($chat, "🏗 <b>ProjectWeb en Telegram</b>\n\n"
            . "/avance — reportar el avance de una actividad\n"
            . "/hoy — actividades de hoy y atrasadas\n"
            . "📷 Manda una <b>foto</b> y la guardo como evidencia de la actividad que elijas\n"
            . "/nota <i>texto</i> — anotar en la bitácora (ej. <code>/nota alta: falta concreto</code>)\n"
            . "/salud — semáforo del proyecto (atrasos, restricciones, punch, RFI…)\n"
            . "/pdf — descargar el PDF del programa o del punch list\n"
            . "/clima — pronóstico de hoy y mañana\n"
            . "/proyecto — cambiar de proyecto\n\n"
            . "Atajo: elige una actividad con /avance y luego escribe solo el número (ej. <code>65</code>).");
    }

    // ---------- vinculación ----------

    private static function vincularCodigo(array $m, string $codigo, bool $privado): void
    {
        $chat = (int)$m['chat']['id'];
        $c = Telegram::codigo($codigo);
        if (!$c) { self::decir($chat, 'Ese código ya no sirve (dura 15 minutos y se usa una sola vez). Genera otro desde <b>Integraciones</b> en ProjectWeb.'); return; }
        $u = Telegram::usuarioPorId((int)$c['usuario_id']);
        if (!$u || $u['rol'] === 'visitante') { self::decir($chat, 'Tu cuenta no puede usar Telegram.'); return; }
        $nombreTg = trim(($m['from']['first_name'] ?? '') . ' ' . ($m['from']['last_name'] ?? '')) ?: (string)($m['from']['username'] ?? '');
        if ($c['tipo'] === 'usuario') {
            if (!$privado) { self::decir($chat, 'Este código es para tu chat personal con el bot, no para un grupo. Ábrelo desde el botón «Conectar mi Telegram».'); return; }
            Telegram::gastarCodigo($codigo);
            Telegram::vincular((int)$u['id'], $chat, $nombreTg);
            $ps = Telegram::proyectosDe($u);
            if (count($ps) === 1) Telegram::proyectoActivo($chat, (int)$ps[0]->id);
            self::decir($chat, '✅ <b>Listo, ' . Telegram::h($u['nombre']) . '.</b> Tu cuenta quedó vinculada.' . (count($ps) === 1 ? "\nProyecto activo: <b>" . Telegram::h($ps[0]->nombre) . '</b>' : "\nElige tu proyecto con /proyecto.") . "\n\nEscribe /ayuda para ver qué puedo hacer.");
            return;
        }
        if ($privado) { self::decir($chat, 'Este código es para agregar el bot a un <b>grupo</b>. Usa el botón «Agregar a un grupo» y elige el grupo en Telegram.'); return; }
        $p = Proyecto::find((int)$c['proyecto_id']);
        if (!$p || !Telegram::puedeGestionar($u, $p)) { self::decir($chat, 'Quien generó el código ya no puede gestionar ese proyecto.'); return; }
        Telegram::gastarCodigo($codigo);
        Telegram::agregarDestino((int)$p->id, $chat, (string)($m['chat']['title'] ?? 'Grupo'), null);
        self::decir($chat, '✅ Este grupo recibirá los avisos de <b>' . Telegram::h($p->nombre) . "</b>.\nEl administrador puede elegir qué avisos llegan desde Integraciones en ProjectWeb.");
    }

    private static function membresia(array $mc): void
    {
        $chat = (int)($mc['chat']['id'] ?? 0);
        $estado = $mc['new_chat_member']['status'] ?? '';
        if (!$chat || !in_array($estado, ['left', 'kicked'], true)) return;
        if (($mc['chat']['type'] ?? '') === 'private') self::db()->query("UPDATE telegram_destinos SET activo = 0 WHERE chat_id = $chat");   // bloqueó al bot
        else self::db()->query("DELETE FROM telegram_destinos WHERE chat_id = $chat AND usuario_id IS NULL");
    }

    // ---------- proyecto activo ----------

    private static function elegirProyecto(int $chat, array $u): void
    {
        $ps = Telegram::proyectosDe($u);
        if (!$ps) { self::decir($chat, 'No tienes proyectos asignados todavía.'); return; }
        $b = [];
        foreach (array_slice($ps, 0, self::MAX_BOTONES) as $p) $b[] = [['text' => mb_substr($p->nombre, 0, 50), 'callback_data' => 'pr:' . (int)$p->id]];
        self::decir($chat, '¿Con qué proyecto trabajamos?', $b);
    }

    /** Proyecto activo del chat (el único que tenga, o el elegido); null si hay que elegir. Para escribir solo cuentan los que puede gestionar */
    private static function activo(int $chat, array $v, array $u, bool $escribir): ?Proyecto
    {
        $ps = Telegram::proyectosDe($u, $escribir);
        foreach ($ps as $p) if ((int)$p->id === (int)$v['proyecto_id']) return $p;
        if (count($ps) === 1) { Telegram::proyectoActivo($chat, (int)$ps[0]->id); return $ps[0]; }
        if (!$ps) {
            self::decir($chat, $escribir && Telegram::proyectosDe($u) ? 'Solo el administrador o el dueño del proyecto puede registrar avance, fotos y notas desde Telegram.' : 'No tienes proyectos asignados todavía.');
            return null;
        }
        self::elegirProyecto($chat, $u);
        return null;
    }

    // ---------- avance ----------

    private static function actividadesDeHoy(int $pid, string $buscar = ''): array
    {
        $hoy = date('Y-m-d');
        if ($buscar !== '') {
            $q = self::esc(addcslashes($buscar, '%_\\'));
            $w = "(a.codigo LIKE '%$q%' OR a.nombre LIKE '%$q%')";
            $orden = 'a.fecha_inicio, a.id';
        } else {
            $w = "a.fecha_inicio <= '$hoy'";
            $orden = "(a.fecha_fin >= '$hoy') DESC, a.fecha_fin, a.id";
        }
        return self::filas("SELECT a.id, a.codigo, a.nombre, a.progreso, a.fecha_inicio, a.fecha_fin FROM actividades a JOIN partidas p ON p.id = a.partida_id
            WHERE p.proyecto_id = $pid AND a.hito = 0 AND a.progreso < 100 AND $w ORDER BY $orden LIMIT " . self::MAX_BOTONES);
    }

    private static function botonesActividades(array $acts, string $prefijo): array
    {
        $b = [];
        foreach ($acts as $a) $b[] = [['text' => mb_substr(trim($a['codigo'] . ' ' . $a['nombre']), 0, 48) . ' · ' . (int)$a['progreso'] . '%', 'callback_data' => $prefijo . ':' . (int)$a['id']]];
        return $b;
    }

    private static function avance(int $chat, array $v, array $u, string $buscar): void
    {
        $p = self::activo($chat, $v, $u, true);
        if (!$p) return;
        $acts = self::actividadesDeHoy((int)$p->id, $buscar);
        if (!$acts) { self::decir($chat, $buscar !== '' ? 'No encontré actividades sin terminar que coincidan con «' . Telegram::h($buscar) . '».' : '🎉 No hay actividades de hoy ni atrasadas por reportar. Busca una con <code>/avance texto</code>.'); return; }
        self::decir($chat, '<b>' . Telegram::h($p->nombre) . "</b>\n" . ($buscar !== '' ? 'Resultados' : 'Actividades de hoy y atrasadas') . ' — elige una:', self::botonesActividades($acts, 'a'));
    }

    private static function hoy(int $chat, array $v, array $u): void
    {
        $p = self::activo($chat, $v, $u, false);
        if (!$p) return;
        $hoy = date('Y-m-d');
        $en = $atr = [];
        foreach (self::actividadesDeHoy((int)$p->id) as $a) {
            $l = '• ' . Telegram::h(trim($a['codigo'] . ' ' . $a['nombre'])) . ' — <b>' . (int)$a['progreso'] . '%</b>';
            if ($a['fecha_fin'] >= $hoy) $en[] = $l; else $atr[] = $l . ' (debía terminar el ' . date('d/m', strtotime($a['fecha_fin'])) . ')';
        }
        $t = '📅 <b>' . Telegram::h($p->nombre) . '</b> · ' . date('d/m/Y') . "\n";
        $t .= $en ? "\n<b>En curso hoy</b>\n" . implode("\n", $en) . "\n" : "\nNada programado en curso hoy.\n";
        if ($atr) $t .= "\n<b>Atrasadas</b>\n" . implode("\n", $atr) . "\n";
        self::decir($chat, $t . "\nReporta avance con /avance.");
    }

    /** Actividad de un proyecto que la persona puede gestionar, o texto de error */
    private static function actividadGestionable(int $aid, array $u): array
    {
        $f = self::filas("SELECT a.id, a.codigo, a.nombre, a.progreso, a.hito, p.proyecto_id FROM actividades a JOIN partidas p ON p.id = a.partida_id WHERE a.id = $aid")[0] ?? null;
        $pr = $f ? Proyecto::find((int)$f['proyecto_id']) : null;
        if (!$f || !$pr || !Telegram::puedeVer($u, $pr)) return [null, 'No encuentro esa actividad.'];
        if (!Telegram::puedeGestionar($u, $pr)) return [null, 'Solo el administrador o el dueño del proyecto puede registrar avance, fotos y notas desde Telegram.'];
        return [$f, null];
    }

    private static function aplicarAvance(int $aid, int $pct, array $u): string
    {
        [$f, $err] = self::actividadGestionable($aid, $u);
        if (!$f) return $err;
        if ((int)$f['hito']) return 'Un hito no lleva porcentaje de avance.';
        $a = Actividad::find($aid);
        if ($a->tieneSubactividades()) return 'El avance de <b>' . Telegram::h(trim($f['codigo'] . ' ' . $f['nombre'])) . '</b> se calcula como promedio de sus subactividades. Edítalas desde la actividad en la web.';
        $antes = (int)$a->progreso;
        $a->progreso = max(0, min(100, $pct));
        $a->sincronizarStatus();
        if (!$a->guardar()) return 'No pude guardar el avance. Inténtalo desde la web.';
        $estado = ['no_iniciada' => 'sin iniciar', 'en_proceso' => 'en proceso', 'completada' => 'completada'][$a->status] ?? $a->status;
        return '✅ <b>' . Telegram::h(trim($f['codigo'] . ' ' . $f['nombre'])) . "</b>\nAvance: $antes% → <b>{$a->progreso}%</b> ($estado)" . ($a->progreso >= 100 ? ' 🎉' : '') . "\n<i>Puedes mandar fotos ahora y se guardan en esta actividad.</i>";
    }

    // ---------- fotos ----------

    private static function foto(array $m, array $v, array $u): void
    {
        $chat = (int)$m['chat']['id'];
        $p = self::activo($chat, $v, $u, true);
        if (!$p) return;
        if (!empty($m['photo'])) { $g = end($m['photo']); $fid = (string)($g['file_id'] ?? ''); $tam = (int)($g['file_size'] ?? 0); }
        else { $fid = (string)($m['document']['file_id'] ?? ''); $tam = (int)($m['document']['file_size'] ?? 0); }
        if ($fid === '') return;
        if ($tam > self::MAX_FOTO) { self::decir($chat, 'Esa foto pesa más de 15 MB. Mándala comprimida (como foto, no como archivo).'); return; }
        $item = ['id' => $fid, 'cap' => mb_substr(trim((string)($m['caption'] ?? '')), 0, 300), 'tam' => $tam];
        $e = self::estado($chat);
        if ($e['paso'] === 'act' && !empty($e['datos']['a'])) {   // ya eligió actividad con /avance: va directo
            self::decir($chat, self::guardarFotos([$item], (int)$e['datos']['a'], $u));
            return;
        }
        $grupo = (string)($m['media_group_id'] ?? '');
        $nueva = $e['paso'] !== 'foto' || ($grupo === '' || ($e['datos']['grupo'] ?? '') !== $grupo);
        $fotos = $e['paso'] === 'foto' && !$nueva ? ($e['datos']['fotos'] ?? []) : [];
        if (count($fotos) < self::MAX_FOTOS_PENDIENTES) $fotos[] = $item;
        self::fijar($chat, 'foto', ['fotos' => $fotos, 'grupo' => $grupo, 'p' => (int)$p->id]);
        if (!$nueva) return;   // otra foto del mismo álbum: ya se preguntó
        $acts = self::actividadesDeHoy((int)$p->id);
        if (!$acts) { self::fijar($chat, ''); self::decir($chat, 'No hay actividades de hoy ni atrasadas. Escribe <code>/avance texto</code> para buscar la actividad y luego manda la foto.'); return; }
        self::decir($chat, '📷 ¿A qué actividad de <b>' . Telegram::h($p->nombre) . '</b> corresponde' . ($grupo !== '' ? 'n estas fotos' : ' esta foto') . '?', self::botonesActividades($acts, 'f'));
    }

    /** Guarda las fotos como evidencia de la actividad. Devuelve el texto de respuesta */
    private static function guardarFotos(array $fotos, int $aid, array $u): string
    {
        [$f, $err] = self::actividadGestionable($aid, $u);
        if (!$f) return $err;
        $pid = (int)$f['proyecto_id'];
        $n = 0; $lleno = false;
        foreach ($fotos as $ft) {
            $tmp = Telegram::descargar((string)$ft['id'], self::MAX_FOTO);
            if (!$tmp) continue;
            try {
                if (!Almacenamiento::cabe($pid, (int)filesize($tmp))) { $lleno = true; break; }
                $nombre = Imagenes::guardarEn(Evidencia::CARPETA, $tmp, 1920, 85);
                if (!$nombre) continue;
                (new Evidencia(['proyecto_id' => $pid, 'actividad_id' => $aid, 'fecha' => date('Y-m-d'), 'descripcion' => $ft['cap'] !== '' ? $ft['cap'] : 'Enviada por Telegram', 'foto' => $nombre, 'usuario_id' => (int)$u['id']]))->guardar();
                $n++;
            } finally {
                @unlink($tmp);
            }
        }
        if ($lleno) return ($n ? "📷 Guardé $n foto(s), pero " : '') . 'el proyecto ya no tiene espacio para más archivos.';
        if (!$n) return 'No pude guardar la foto (debe ser jpg, png, webp o gif de hasta 15 MB). Inténtalo de nuevo.';
        return "📷 <b>$n foto(s) guardada(s)</b> como evidencia de " . Telegram::h(trim($f['codigo'] . ' ' . $f['nombre'])) . '.';
    }

    // ---------- notas y clima ----------

    private static function nota(int $chat, array $v, array $u, string $texto): void
    {
        if ($texto === '') { self::decir($chat, "Escribe la nota después del comando, por ejemplo:\n<code>/nota se detuvo el colado por falta de concreto</code>\nPuedes poner la importancia al inicio: <code>/nota alta: ...</code>"); return; }
        $p = self::activo($chat, $v, $u, true);
        if (!$p) return;
        $imp = 'media';
        if (preg_match('/^(baja|media|alta|cr[ií]tica)\s*:\s*(.+)$/isu', $texto, $x)) { $imp = strtr(mb_strtolower($x[1]), ['í' => 'i']); $texto = $x[2]; }
        $b = new Bitacora(['proyecto_id' => $p->id, 'fecha' => date('Y-m-d'), 'departamento' => '', 'descripcion' => mb_substr($texto, 0, 4900) . ' — vía Telegram (' . $u['nombre'] . ')', 'importancia' => $imp]);
        if ($b->validar() || !($b->guardar()['resultado'] ?? false)) { self::decir($chat, 'No pude guardar la nota. Inténtalo desde la web.'); return; }
        self::decir($chat, '📝 Anotado en la bitácora de <b>' . Telegram::h($p->nombre) . '</b> (importancia ' . $imp . ').');
    }

    private static function clima(int $chat, array $v, array $u): void
    {
        $p = self::activo($chat, $v, $u, false);
        if (!$p) return;
        $r = Clima::actualizar($p);
        $hoy = date('Y-m-d');
        $dias = Clima::dias((int)$p->id, $hoy, date('Y-m-d', strtotime('+1 day')));
        if (!$dias) { self::decir($chat, '🌫 ' . Telegram::h($r['mensaje'])); return; }
        $t = '🌤 <b>' . Telegram::h($r['lugar'] ?: $p->nombre) . "</b>\n";
        foreach ($dias as $d) $t .= "\n" . ($d['fecha'] === $hoy ? '<b>Hoy</b>' : '<b>Mañana</b>') . ': ' . ClimaAuto::resumen($d);
        self::decir($chat, $t);
    }

    // ---------- salud y PDF ----------

    /** Semáforo del proyecto (Salud): indicadores que no están en verde, con su detalle */
    private static function salud(int $chat, array $v, array $u): void
    {
        $p = self::activo($chat, $v, $u, false);
        if (!$p) return;
        $s = Salud::semaforo($p);
        $ico = ['ok' => '🟢', 'aviso' => '🟠', 'critico' => '🔴'];
        $t = $ico[$s['nivel']] . ' <b>Salud de ' . Telegram::h($p->nombre) . '</b> — ' . Telegram::h(Salud::NIVELES[$s['nivel']]) . "\n";
        $mal = array_values(array_filter($s['indicadores'], fn($i) => $i['nivel'] !== 'ok'));
        if (!$mal) $t .= "\nTodo en orden: sin atrasos, restricciones ni pendientes graves.";
        foreach ($mal as $i) {
            $t .= "\n" . $ico[$i['nivel']] . ' <b>' . Telegram::h($i['titulo']) . ':</b> ' . Telegram::h((string)$i['valor']) . ' — ' . Telegram::h(mb_substr((string)$i['detalle'], 0, 180));
        }
        $t .= "\n\n<i>Detalles en la web, sección Salud del proyecto.</i>";
        self::decir($chat, $t);
    }

    /** /pdf [tipo] — manda el PDF del programa o del punch list al chat (sin tipo, pregunta con botones) */
    private static function pdf(int $chat, array $v, array $u, string $tipo): void
    {
        $p = self::activo($chat, $v, $u, false);
        if (!$p) return;
        $tipo = strtolower(trim($tipo));
        if (!in_array($tipo, ['programa', 'punch'], true)) {
            self::decir($chat, '¿Qué PDF quieres de <b>' . Telegram::h($p->nombre) . '</b>?', [
                [['text' => '📄 Programa (Gantt)', 'callback_data' => 'doc:programa']],
                [['text' => '🔧 Punch list', 'callback_data' => 'doc:punch']],
            ]);
            return;
        }
        self::enviarPdf($chat, $v, $u, $tipo);
    }

    /** Genera y manda el PDF; lo borra después (las exportaciones no se quedan en el servidor) */
    private static function enviarPdf(int $chat, array $v, array $u, string $tipo): void
    {
        $p = self::activo($chat, $v, $u, false);
        if (!$p) return;
        self::decir($chat, 'Generando el PDF…');
        $ruta = null;
        try {
            if ($tipo === 'punch') {
                $tmp = tempnam(sys_get_temp_dir(), 'tgpunch');
                $ruta = $tmp . '.pdf';
                @unlink($tmp);   // el que creó tempnam no lleva extensión; el PDF va en su lugar
                file_put_contents($ruta, Punch::pdf($p));
                $pie = '🔧 <b>Punch list</b> · ' . Telegram::h($p->nombre);
            } else {
                $ruta = (new \Classes\Exportadores\PdfExporter($p))->generar('Programa');
                $pie = '📄 <b>Programa</b> · ' . Telegram::h($p->nombre);
            }
        } catch (\Throwable $e) {
            error_log('Telegram /pdf: ' . $e->getMessage());
            if ($ruta) @unlink($ruta);
            self::decir($chat, 'No pude generar el PDF. Inténtalo desde la web.');
            return;
        }
        try {
            [$ok, $err] = Telegram::enviarArchivo($chat, $ruta, basename($ruta), $pie);
            if (!$ok) self::decir($chat, 'No pude enviar el PDF' . ($err ? ' (error de Telegram ' . (int)$err . ')' : '') . '. Inténtalo desde la web.');
        } finally {
            @unlink($ruta);
        }
    }

    // ---------- botones ----------

    private static function callback(array $cq, int $updateId): void
    {
        Telegram::api('answerCallbackQuery', ['callback_query_id' => (string)($cq['id'] ?? '')]);
        $chat = (int)($cq['message']['chat']['id'] ?? 0);
        $data = (string)($cq['data'] ?? '');
        if (!$chat || ($cq['message']['chat']['type'] ?? '') !== 'private' || !self::nuevo($chat, $updateId)) return;
        $v = Telegram::vinculoDeChat($chat);
        $u = $v ? Telegram::usuarioPorId((int)$v['usuario_id']) : null;
        if (!$v || !$u || $u['rol'] === 'visitante') return;
        // Acciones sobre avisos (punch, RFI, submittal) y descarga de PDF: se revalida el permiso en cada una
        if (preg_match('/^doc:(programa|punch)$/', $data, $x)) { self::enviarPdf($chat, $v, $u, $x[1]); return; }
        if (preg_match('/^pu:(\d+):([a-z_]+)$/', $data, $x)) { self::punchEstado($chat, $u, (int)$x[1], $x[2]); return; }
        if (preg_match('/^rs:(\d+)$/', $data, $x)) { self::rfiResponder($chat, $u, (int)$x[1]); return; }
        if (preg_match('/^su:(\d+):([a-z_]+)$/', $data, $x)) { self::submittalEstatus($chat, $u, (int)$x[1], $x[2]); return; }
        if (!preg_match('/^(pr|a|p|f):(\d+)(?::(\d{1,3}))?$/', $data, $x)) return;
        $id = (int)$x[2];
        switch ($x[1]) {
            case 'pr':
                $p = Proyecto::find($id);
                if (!$p || !Telegram::puedeVer($u, $p)) { self::decir($chat, 'No encuentro ese proyecto.'); return; }
                Telegram::proyectoActivo($chat, $id);
                self::decir($chat, 'Proyecto activo: <b>' . Telegram::h($p->nombre) . "</b>\nUsa /avance, /hoy, /nota o manda una foto.");
                return;
            case 'a':   // eligió actividad: botones de porcentaje; lo siguiente que escriba o mande se aplica a ella
                [$f, $err] = self::actividadGestionable($id, $u);
                if (!$f) { self::decir($chat, $err); return; }
                self::fijar($chat, 'act', ['a' => $id]);
                $b = [[0, 25, 50], [75, 90, 100]];
                $b = array_map(fn($fila) => array_map(fn($n) => ['text' => "$n%", 'callback_data' => "p:$id:$n"], $fila), $b);
                self::decir($chat, '<b>' . Telegram::h(trim($f['codigo'] . ' ' . $f['nombre'])) . "</b>\nAvance actual: " . (int)$f['progreso'] . "%\n¿Cuánto lleva? Toca un botón o escribe el número (ej. <code>65</code>). También puedes mandar fotos.", $b);
                return;
            case 'p':
                if (!isset($x[3])) return;
                if ((int)$x[3] > 100) return;
                self::fijar($chat, 'act', ['a' => $id]);
                self::decir($chat, self::aplicarAvance($id, (int)$x[3], $u));
                return;
            case 'f':
                $e = self::estado($chat);
                if ($e['paso'] !== 'foto' || empty($e['datos']['fotos'])) { self::decir($chat, 'Ya no tengo esas fotos. Mándalas otra vez.'); return; }
                $fotos = $e['datos']['fotos'];
                self::fijar($chat, 'act', ['a' => $id]);
                self::decir($chat, self::guardarFotos($fotos, $id, $u));
                return;
        }
    }

    // ---------- acciones sobre los avisos (punch, RFI, submittal) ----------

    /** Proyecto del registro y quién lo puede cambiar; null + mensaje de error si no se puede */
    private static function gestionableDe(string $sql, array $u, string $accion): array
    {
        $f = self::filas($sql)[0] ?? null;
        $p = $f ? Proyecto::find((int)$f['proyecto_id']) : null;
        if (!$f || !$p || !Telegram::puedeVer($u, $p)) return [null, $f, 'No encuentro ese registro.'];
        if (!Telegram::puedeGestionar($u, $p)) return [null, $f, "Solo el administrador o el dueño del proyecto puede $accion desde Telegram."];
        return [$p, $f, null];
    }

    /** Botón «Corregido/En proceso» de un aviso de punch */
    private static function punchEstado(int $chat, array $u, int $id, string $estado): void
    {
        if (!isset(Punch::ESTADOS[$estado])) return;
        [$p, $f, $err] = self::gestionableDe("SELECT i.id, i.proyecto_id, i.titulo, i.estado FROM punch_items i WHERE i.id = $id", $u, 'cambiar pendientes');
        if (!$p) { self::decir($chat, $err); return; }
        if ($f['estado'] === $estado) { self::decir($chat, 'Ese pendiente ya estaba en «' . Telegram::h(Punch::ESTADOS[$estado]) . '».'); return; }
        [$ok, $msg] = Punch::estado($p, $id, $estado);
        self::decir($chat, $ok ? '✅ «' . Telegram::h($f['titulo']) . '»: ' . Telegram::h(Punch::ESTADOS[$f['estado']]) . ' → <b>' . Telegram::h(Punch::ESTADOS[$estado]) . "</b>\n<i>Si requiere verificación, queda como «corregido» hasta que el inspector lo cierre.</i>" : Telegram::h($msg));
    }

    /** Botón «Responder» de un aviso de RFI: fija el paso y el siguiente texto es la respuesta */
    private static function rfiResponder(int $chat, array $u, int $id): void
    {
        [$p, $f, $err] = self::gestionableDe("SELECT id, proyecto_id, numero, asunto, estado FROM rfis WHERE id = $id", $u, 'responder RFI');
        if (!$p) { self::decir($chat, $err); return; }
        if ($f['estado'] === 'cerrado') { self::decir($chat, 'Ese RFI está cerrado; ábrelo en la web si necesita otra respuesta.'); return; }
        self::fijar($chat, 'rfi', ['r' => $id, 'p' => (int)$f['proyecto_id']]);
        self::decir($chat, '✍️ Escribe la respuesta al <b>RFI ' . Telegram::h($f['numero']) . '</b> («' . Telegram::h(mb_substr((string)$f['asunto'], 0, 80)) . "»):\nEl estado pasará a <b>Respondido</b>.");
    }

    /** El texto escrito tras «Responder» se guarda como respuesta del RFI */
    private static function rfiGuardarRespuesta(int $chat, array $u, int $rid, string $texto): void
    {
        [$p, $f, $err] = self::gestionableDe("SELECT * FROM rfis WHERE id = $rid", $u, 'responder RFI');
        if (!$p || !$f) { self::fijar($chat, ''); self::decir($chat, $err ?: 'No encuentro ese RFI.'); return; }
        $d = ['id' => $rid, 'numero' => $f['numero'], 'asunto' => $f['asunto'], 'pregunta' => $f['pregunta'], 'respuesta' => $texto,
            'responsable' => $f['responsable'], 'solicitante' => $f['solicitante'], 'prioridad' => $f['prioridad'], 'estado' => 'respondido',
            'fecha' => $f['fecha'], 'fecha_limite' => $f['fecha_limite'], 'actividad_id' => $f['actividad_id']];
        $e = Rfi::guardar((int)$f['proyecto_id'], $d, $u['nombre']);
        self::fijar($chat, '');
        if ($e) { self::decir($chat, 'No pude guardar la respuesta: ' . Telegram::h($e)); return; }
        self::decir($chat, '✅ Respuesta guardada en el <b>RFI ' . Telegram::h($f['numero']) . '</b>. Estado: <b>Respondido</b>.');
    }

    /** Botones «Aprobado / A/AN / Resubmitar» de un aviso de submittal (mueve también el MSR) */
    private static function submittalEstatus(int $chat, array $u, int $id, string $estatus): void
    {
        if (!\Model\Submittal::esAprobado($estatus) && $estatus !== 'rechazado' && $estatus !== 'enviado') return;
        [$p, $f, $err] = self::gestionableDe("SELECT id, proyecto_id, numero_submittal, estatus FROM submittals WHERE id = $id", $u, 'cambiar submittals');
        if (!$p) { self::decir($chat, $err); return; }
        if ($f['estatus'] === $estatus) { self::decir($chat, 'Ese submittal ya estaba en «' . Telegram::h(\Model\Submittal::ESTATUS[$estatus]) . '».'); return; }
        $s = \Model\Submittal::find($id);
        $anterior = $s->estatus;
        $s->estatus = $estatus;
        if (!$s->guardar()) { self::decir($chat, 'No pude guardar el cambio. Inténtalo desde la web.'); return; }
        $s->sincronizarMsr($anterior);
        self::decir($chat, '📄 Submittal <b>' . Telegram::h($f['numero_submittal']) . '</b>: ' . Telegram::h(\Model\Submittal::ESTATUS[$anterior]) . ' → <b>' . Telegram::h(\Model\Submittal::ESTATUS[$estatus]) . '</b> (MSR actualizado).');
    }
}
