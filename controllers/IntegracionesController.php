<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\ApiTokens;
use Classes\Telegram;
use Classes\ClimaAuto;
use Classes\Clima;

/** Integraciones: tokens de la API REST, bot de Telegram y clima automático */
class IntegracionesController
{
    private static function usuario(): array
    {
        redirigir_si_no_auth();
        if (is_visitante()) { header('Location: /proyectos'); exit; }
        return usuario_actual();
    }

    private static function volver(?int $pid, bool $ok, string $texto, string $ancla = ''): never
    {
        $_SESSION['integ_msg'] = ['ok' => $ok, 'texto' => $texto];
        header('Location: /integraciones' . ($pid ? '?proyecto=' . $pid : '') . $ancla);
        exit;
    }

    public static function index(Router $router)
    {
        $u = self::usuario();
        $proyectos = array_values(array_filter(Proyecto::visiblesPara($u), fn($p) => puede_gestionar_proyecto($p)));
        $sel = null;
        foreach ($proyectos as $p) if ((int)$p->id === (int)($_GET['proyecto'] ?? 0)) $sel = $p;
        $sel ??= $proyectos[0] ?? null;
        $tg = self::datosTelegram($u, $sel);
        $router->render('integraciones/index', [
            'titulo' => 'Integraciones', 'usuario' => $u, 'disponible' => ApiTokens::disponible(),
            'tokens' => ApiTokens::disponible() ? ApiTokens::lista((int)$u['id']) : [], 'proyectos' => $proyectos, 'proyecto' => $sel,
            'tg' => $tg, 'tokenNuevo' => $_SESSION['api_token_nuevo'] ?? null, 'mensaje' => $_SESSION['integ_msg'] ?? null, 'url' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
        ]);
        unset($_SESSION['api_token_nuevo'], $_SESSION['integ_msg'], $_SESSION['tg_enlace']);
    }

    public static function crearToken()
    {
        $u = self::usuario();
        $pid = (int)($_POST['proyecto_id'] ?? 0);
        if ($pid && !($p = Proyecto::find($pid)) || ($pid && !puede_ver_proyecto($p))) self::volver(null, false, 'Proyecto no válido.');
        [$ok, $r] = ApiTokens::crear((int)$u['id'], (string)($_POST['nombre'] ?? ''), !empty($_POST['escritura']), $pid, (int)($_POST['dias'] ?? 0));
        if ($ok) $_SESSION['api_token_nuevo'] = $r;
        self::volver(null, $ok, $ok ? 'Token creado. Cópialo ahora: no se vuelve a mostrar.' : $r, '#tokens');
    }

    public static function revocarToken()
    {
        $u = self::usuario();
        ApiTokens::revocar((int)$u['id'], (int)($_POST['id'] ?? 0));
        self::volver(null, true, 'Token revocado.', '#tokens');
    }

    private static function proyectoGestionable(): Proyecto
    {
        self::usuario();
        return proyecto_de_peticion(true);
    }

    // ---------- Telegram y clima automático ----------

    private static function baseUrl(): string
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    private static function datosTelegram(array $u, ?Proyecto $p): array
    {
        $pid = $p ? (int)$p->id : 0;
        $tg = ['tablas' => Telegram::disponible(), 'configurado' => Telegram::configurado(), 'bot' => '', 'vinculo' => null, 'destinos' => [], 'enlace' => $_SESSION['tg_enlace'] ?? null,
            'webhook' => null, 'eventos' => Telegram::EVENTOS, 'clima' => false, 'lugar' => '', 'ubicacion' => $p ? (string)$p->ubicacion : '', 'esAdmin' => is_admin(),
            'resumen' => false, 'resumenHora' => \Classes\ResumenObra::hora(),
            'config' => Telegram::disponibleConfig() ? Telegram::configGuardada() : []];
        if (!$tg['tablas']) return $tg;
        $tg['clima'] = $pid && ClimaAuto::activo($pid);
        $tg['resumen'] = $pid && \Classes\ResumenObra::activo($pid);
        if ($pid) $tg['lugar'] = (string)(Clima::lugarGuardado($pid) ?? '');
        if (!$tg['configurado']) return $tg;
        $tg['bot'] = Telegram::usuarioBot();
        $tg['vinculo'] = Telegram::vinculoDeUsuario((int)$u['id']);
        if ($pid) $tg['destinos'] = Telegram::destinos($pid);
        if (is_admin()) $tg['webhook'] = Telegram::estadoWebhook();
        return $tg;
    }

    public static function telegramConectar()
    {
        $u = self::usuario();
        $pid = (int)($_POST['proyecto'] ?? 0);
        if (!Telegram::configurado() || !Telegram::disponible() || !Telegram::usuarioBot()) self::volver($pid, false, 'Telegram aún no está configurado en este servidor.', '#telegram');
        $_SESSION['tg_enlace'] = ['tipo' => 'usuario', 'url' => Telegram::enlace(Telegram::crearCodigo((int)$u['id'], 'usuario'), 'usuario')];
        self::volver($pid, true, 'Abre el enlace de abajo en tu celular (o escritorio) y pulsa «Iniciar» en Telegram. El enlace dura 15 minutos.', '#telegram');
    }

    public static function telegramDesconectar()
    {
        $u = self::usuario();
        Telegram::desvincular((int)$u['id']);
        self::volver((int)($_POST['proyecto'] ?? 0), true, 'Telegram desconectado. Ya no recibirás avisos en tu chat ni podrás reportar desde él.', '#telegram');
    }

    public static function telegramGrupo()
    {
        $u = self::usuario();
        $p = self::proyectoGestionable();
        if (!Telegram::configurado() || !Telegram::disponible() || !Telegram::usuarioBot()) self::volver((int)$p->id, false, 'Telegram aún no está configurado en este servidor.', '#telegram');
        $_SESSION['tg_enlace'] = ['tipo' => 'grupo', 'url' => Telegram::enlace(Telegram::crearCodigo((int)$u['id'], 'grupo', (int)$p->id), 'grupo')];
        self::volver((int)$p->id, true, 'Abre el enlace de abajo, elige el grupo de la obra y confirma. El bot avisará ahí cuando quede agregado.', '#telegram');
    }

    public static function telegramMiChat()
    {
        $u = self::usuario();
        $p = self::proyectoGestionable();
        [$ok, $msg] = Telegram::activarMiChat((int)$p->id, $u, !empty($_POST['activo']));
        self::volver((int)$p->id, $ok, $msg, '#telegram');
    }

    public static function telegramDestino()
    {
        $p = self::proyectoGestionable();
        $pid = (int)$p->id;
        $id = (int)($_POST['id'] ?? 0);
        switch ($_POST['accion'] ?? '') {
            case 'quitar': Telegram::quitarDestino($pid, $id); self::volver($pid, true, 'Destino eliminado.', '#telegram');
            case 'pausar': Telegram::pausarDestino($pid, $id, false); self::volver($pid, true, 'Avisos pausados en ese destino.', '#telegram');
            case 'activar': Telegram::pausarDestino($pid, $id, true); self::volver($pid, true, 'Avisos activados.', '#telegram');
            case 'probar': [$ok, $msg] = Telegram::probar($pid, $id); self::volver($pid, $ok, $msg, '#telegram');
            case 'eventos': Telegram::guardarEventos($pid, $id, $_POST['eventos'] ?? []); self::volver($pid, true, 'Avisos guardados.', '#telegram');
        }
        self::volver($pid, false, 'Acción no válida.', '#telegram');
    }

    public static function telegramRegistrar()
    {
        self::usuario();
        if (!is_admin()) { header('Location: /integraciones'); exit; }
        [$ok, $msg] = Telegram::registrarWebhook(self::baseUrl());
        self::volver((int)($_POST['proyecto'] ?? 0), $ok, $msg, '#telegram');
    }

    /** Guarda el token del bot (cifrado en la base) y registra el webhook. Solo admin, sin tocar el clúster. */
    public static function telegramGuardar()
    {
        self::usuario();
        $pid = (int)($_POST['proyecto'] ?? 0);
        if (!is_admin()) self::volver($pid, false, 'Solo el administrador puede guardar el token del bot.', '#telegram');
        $token = trim((string)($_POST['token'] ?? ''));
        if ($token === '') self::volver($pid, false, 'Pega el token que te dio @BotFather.', '#telegram');
        [$ok, $msg] = Telegram::guardarToken($token, (string)(usuario_actual()['nombre'] ?? ''));
        if (!$ok) self::volver($pid, false, $msg, '#telegram');
        // el secreto del webhook se deriva del token: al cambiarlo hay que registrarlo otra vez
        if (str_starts_with(self::baseUrl(), 'https://')) [, $msg] = Telegram::registrarWebhook(self::baseUrl());
        else $msg .= ' Con servidor local (sin https) se activa con «php bin/telegram.php --sondeo».';
        self::volver($pid, true, $msg, '#telegram');
    }

    /** Quita el token de la base (si había uno en el entorno, el bot sigue con ese) */
    public static function telegramQuitar()
    {
        self::usuario();
        $pid = (int)($_POST['proyecto'] ?? 0);
        if (!is_admin()) self::volver($pid, false, 'Solo el administrador puede quitar el token.', '#telegram');
        $ok = Telegram::borrarToken();
        $enEntorno = Telegram::configGuardada()['origen'] === 'entorno';
        self::volver($pid, $ok, !$ok ? 'No se pudo eliminar el token.'
            : ($enEntorno ? 'Token de la base eliminado: el bot sigue con el del entorno (TELEGRAM_BOT_TOKEN).' : 'Token eliminado. El bot queda apagado hasta que guardes uno nuevo.'), '#telegram');
    }

    public static function climaAuto()
    {
        $p = self::proyectoGestionable();
        $pid = (int)$p->id;
        switch ($_POST['accion'] ?? '') {
            case 'activar': ClimaAuto::cambiarActivo($pid, true); self::volver($pid, true, 'Clima automático activado: cada tarde se anotará el clima del día en la bitácora.', '#clima');
            case 'desactivar': ClimaAuto::cambiarActivo($pid, false); self::volver($pid, true, 'Clima automático desactivado.', '#clima');
            case 'probar': $r = ClimaAuto::procesar($p, true); self::volver($pid, $r['ok'], $r['mensaje'], '#clima');
        }
        self::volver($pid, false, 'Acción no válida.', '#clima');
    }

    /** Resumen de la obra cada mañana (evento de Telegram resumen.matutino) */
    public static function resumenObra()
    {
        $p = self::proyectoGestionable();
        $pid = (int)$p->id;
        switch ($_POST['accion'] ?? '') {
            case 'activar': \Classes\ResumenObra::cambiarActivo($pid, true); self::volver($pid, true, 'Resumen activado: cada mañana (después de las ' . \Classes\ResumenObra::hora() . ':00) llegará el resumen a los destinos que marquen «Resumen de la obra cada mañana» en sus avisos.', '#resumen');
            case 'desactivar': \Classes\ResumenObra::cambiarActivo($pid, false); self::volver($pid, true, 'Resumen de la obra desactivado.', '#resumen');
            case 'probar': $r = \Classes\ResumenObra::procesar($p, true); \Classes\Telegram::vaciar(); self::volver($pid, $r['ok'], $r['mensaje'], '#resumen');
        }
        self::volver($pid, false, 'Acción no válida.', '#resumen');
    }
}
