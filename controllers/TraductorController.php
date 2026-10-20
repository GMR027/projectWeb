<?php

namespace Controllers;

use MVC\Router;
use Classes\Traductor;
use Classes\EscanerArchivos;

/** Traductor español ↔ inglés: texto escrito por el usuario o documentos (PDF, Word, Excel, PowerPoint, OpenDocument…) */
class TraductorController
{
    private static function json(array $d, int $codigo = 200): void
    {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function idiomas(): array
    {
        $de = (string)($_POST['de'] ?? '');
        $a = (string)($_POST['a'] ?? '');
        if (!Traductor::idiomaValido($de) || !Traductor::idiomaValido($a) || $de === $a) self::json(['error' => 'Elige el idioma de origen y el de destino (español e inglés).'], 422);
        return [$de, $a];
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('traductor/index', [
            'titulo' => 'Traductor - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'configurado' => Traductor::configurado(),
            'puedeDocumentos' => (puede_descargar() || is_admin()) && !es_demo(),
        ]);
    }

    public static function texto()
    {
        $proyecto = proyecto_de_peticion();
        if (!Traductor::configurado()) self::json(['error' => 'El traductor no está disponible en el servidor.'], 503);
        [$de, $a] = self::idiomas();
        $texto = (string)($_POST['texto'] ?? '');
        if (trim($texto) === '') self::json(['error' => 'Escribe un texto para traducir.'], 422);
        if (mb_strlen($texto) > Traductor::MAX_TEXTO) self::json(['error' => 'El texto es demasiado largo (máx. ' . number_format(Traductor::MAX_TEXTO) . ' caracteres); usa un documento.'], 422);
        try {
            self::json(['texto' => Traductor::traducirTexto($texto, $de, $a)]);
        } catch (\RuntimeException $e) {
            self::json(['error' => $e->getMessage()], 502);
        }
    }

    public static function documento()
    {
        $proyecto = proyecto_de_peticion();
        if (!Traductor::configurado()) self::json(['error' => 'El traductor no está disponible en el servidor.'], 503);
        if ((!puede_descargar() && !is_admin()) || es_demo()) self::json(['error' => 'No tienes permiso para traducir documentos.'], 403);
        [$de, $a] = self::idiomas();
        $f = $_FILES['archivo'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) self::json(['error' => 'No se pudo subir el archivo (¿supera el límite del servidor?).'], 422);
        if ($f['size'] > Traductor::MAX_ARCHIVO) self::json(['error' => 'El archivo es demasiado grande (máx. 20 MB).'], 422);
        $motivos = EscanerArchivos::revisar($f['tmp_name'], (string)$f['name']);
        if ($motivos) self::json(['error' => implode(' ', $motivos)], 422);

        @set_time_limit(600);
        ignore_user_abort(true);
        try {
            [$ruta, $nombre, $mime] = Traductor::documento($f['tmp_name'], (string)$f['name'], $de, $a);
        } catch (\RuntimeException $e) {
            self::json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log('Traductor documento: ' . $e->getMessage());
            self::json(['error' => 'No se pudo traducir el documento. Revisa que no esté dañado ni protegido con contraseña.'], 500);
        }
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($ruta));
        header("Content-Disposition: attachment; filename=\"" . preg_replace('/[^A-Za-z0-9._-]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre) ?: 'traduccion') . "\"; filename*=UTF-8''" . rawurlencode($nombre));
        header('X-Nombre-Archivo: ' . rawurlencode($nombre));
        header('Access-Control-Expose-Headers: X-Nombre-Archivo');
        readfile($ruta);
        @unlink($ruta);
        exit;
    }
}
