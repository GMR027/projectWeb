<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Usuario;
use Classes\Portafolio;

/** Tablero de portafolio: compara las obras y avisa por tendencia (solo admin y cliente dueño) */
class PortafolioController
{
    public static function index(Router $router)
    {
        $usuario = self::usuarioPermitido();
        $filtros = self::filtros();
        $filas = Portafolio::filas($usuario, $filtros);
        // Refresca las alertas para la campana con lo ya calculado (sin repetir consultas)
        try { Portafolio::guardarAlertas($filas); } catch (\Throwable $e) {}
        $router->render('portafolio/index', [
            'titulo' => 'Tablero de portafolio',
            'filas' => $filas,
            'resumen' => Portafolio::resumen($filas),
            'filtros' => $filtros,
            'clientes' => self::clientes($usuario),
            'disponible' => Portafolio::disponible(),
        ]);
    }

    public static function exportar()
    {
        $usuario = self::usuarioPermitido();
        if (!puede_descargar()) {
            header('Location: /portafolio?error=' . urlencode('No tienes permiso para descargar archivos.'));
            exit;
        }
        $filtros = self::filtros();
        $filas = Portafolio::filas($usuario, $filtros);
        $proyecto = Proyecto::visiblesPara($usuario)[0] ?? null;
        $formato = ($_GET['formato'] ?? '') === 'ods' ? 'ods' : 'pdf';
        try {
            if (!$proyecto) throw new \Exception('No hay obras para exportar.');
            $exp = $formato === 'ods'
                ? (new \Classes\Exportadores\PortafolioSheetExporter($proyecto))->setFormato('ods')
                : new \Classes\Exportadores\PortafolioPdfExporter($proyecto);
            $ruta = $exp->setDatos($filas, Portafolio::resumen($filas), $filtros)->generar('Tablero-portafolio');
            $mime = $formato === 'ods' ? 'application/vnd.oasis.opendocument.spreadsheet' : 'application/pdf';
            $datos = (string)file_get_contents($ruta);
            $nombre = basename($ruta);
            @unlink($ruta);
            enviar_descarga($datos, $mime, $nombre);
        } catch (\Throwable $e) {
            header('Location: /portafolio?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    private static function usuarioPermitido(): array
    {
        redirigir_si_no_auth();
        $u = usuario_actual();
        if (!in_array($u['rol'] ?? '', ['admin', 'cliente'], true)) {
            header('Location: /dashboard');
            exit;
        }
        return $u;
    }

    private static function filtros(): array
    {
        return [
            'q' => texto_param($_GET['q'] ?? ''),
            'cliente' => (int)($_GET['cliente'] ?? 0),
            'alertas' => !empty($_GET['alertas']),
            'orden' => array_key_exists($_GET['orden'] ?? '', Portafolio::ORDENES) ? $_GET['orden'] : 'alerta',
            'dir' => ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** Clientes con obras (para el filtro del administrador) */
    private static function clientes(array $usuario): array
    {
        $out = [];
        foreach (Proyecto::visiblesPara($usuario) as $p) {
            $uid = (int)$p->usuario_id;
            if ($uid && !isset($out[$uid])) {
                $u = Usuario::find($uid);
                $out[$uid] = $u ? $u->nombre : 'Usuario ' . $uid;
            }
        }
        asort($out);
        return $out;
    }
}
