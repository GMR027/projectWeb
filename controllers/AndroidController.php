<?php

namespace Controllers;

use Classes\ApkAndroid;

/** APK de Android: descarga para admin y clientes, y publicación solo para el administrador */
class AndroidController
{
    private static function json(array $datos, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function exigirSesion(): void
    {
        if (is_auth()) return;
        // La app Android descarga su propia actualización con el token pwa_… (sin cookie web).
        $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        $token = '';
        if (preg_match('/^Bearer\s+(pwa_[A-Za-z0-9._-]+)$/i', $auth, $m)) $token = $m[1];
        if ($token === '' && !empty($_SERVER['HTTP_X_APP_TOKEN'])) $token = (string)$_SERVER['HTTP_X_APP_TOKEN'];
        if ($token === '' && !empty($_GET['token'])) $token = (string)$_GET['token'];
        if ($token !== '' && \Classes\Sesiones::verificarToken($token)) return;
        self::json(['success' => false, 'error' => 'Inicia sesión para descargar la app.'], 401);
    }

    public static function version()
    {
        self::exigirSesion();
        $u = ApkAndroid::ultima();
        if (!$u) self::json(['success' => true, 'version' => '', 'versionCode' => 0, 'archivo' => '', 'url' => '', 'notas' => '']);
        self::json([
            'success' => true,
            'version' => $u['version'],
            'versionCode' => $u['version_code'],
            'size' => $u['tam'],
            'sha256' => $u['sha'],
            'fecha' => date('c', $u['fecha']),
            'notas' => $u['notas'],
            'minSdk' => $u['min_sdk'],
            'archivo' => $u['archivo'],
            'url' => '/api/android/descargar',
        ]);
    }

    public static function descargar()
    {
        self::exigirSesion();
        $archivo = texto_param($_GET['archivo'] ?? '');
        if ($archivo === '') {
            $u = ApkAndroid::ultima();
            $archivo = $u['archivo'] ?? '';
        }
        if ($archivo === '' || !ApkAndroid::entregar($archivo)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            exit('No hay un APK publicado con ese nombre.');
        }
        exit;
    }

    public static function subir()
    {
        redirigir_si_no_admin();
        try {
            $r = ApkAndroid::guardar($_FILES['apk'] ?? [], $_POST['reemplazar'] ?? null, texto_param($_POST['notas'] ?? ''));
            $msg = !$r['reemplazo']
                ? "APK publicado: {$r['archivo']} (v{$r['version']})"
                : ($r['cambio'] ? "APK reemplazado: {$r['archivo']} (el contenido cambió)" : "{$r['archivo']}: el archivo es idéntico al anterior");
            header('Location: /sistema?ok=' . urlencode($msg) . '#android');
        } catch (\Throwable $e) {
            header('Location: /sistema?error=' . urlencode($e->getMessage()) . '#android');
        }
        exit;
    }

    public static function eliminar()
    {
        redirigir_si_no_admin();
        ApkAndroid::eliminar(texto_param($_POST['archivo'] ?? ''));
        header('Location: /sistema?ok=' . urlencode('APK eliminado.') . '#android');
        exit;
    }
}
