<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Classes\Auditoria;
use Classes\Herramientas\Registro;
use Classes\Grabaciones;
use Classes\Informes;

/** Informes de IA: API para la app de escritorio (contexto y resultado de cada herramienta) y páginas web para verlos, aprobarlos, aplicarlos y exportarlos a PDF. Solo admin */
class InformesIAController
{
    private static function admin(): Proyecto
    {
        $proyecto = proyecto_de_peticion();
        if (!is_admin()) { http_response_code(403); exit('Solo el administrador puede usar los informes de IA'); }
        return $proyecto;
    }

    // ---------- API de escritorio ----------

    /** Catálogo de herramientas con informe (el menú de escritorio lo descubre aquí) */
    public static function herramientas()
    {
        if (!is_admin()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        json_respuesta(['success' => true, 'csrf' => csrf_token(), 'herramientas' => Registro::fichas()]);
    }

    private static function herramienta(array $fuente): string
    {
        $c = Registro::obtener((string)($fuente['herramienta'] ?? ''));
        if (!$c) json_respuesta(['success' => false, 'error' => 'Herramienta no válida'], 422);
        return $c;
    }

    public static function contexto()
    {
        $p = proyecto_api();
        $c = self::herramienta($_POST);
        $params = Registro::parametros($c, (array)json_decode((string)($_POST['params'] ?? '{}'), true));
        $r = $c::preparar($p, $params);
        json_respuesta(['success' => true, 'csrf' => csrf_token(), 'proyecto' => $p->nombre, 'titulo' => $c::titulo($p, $params), 'lotes' => $r['lotes']]);
    }

    public static function resultado()
    {
        $p = proyecto_api();
        $c = self::herramienta($_POST);
        $cuerpo = json_decode((string)($_POST['datos'] ?? ''), true);
        if (!is_array($cuerpo)) json_respuesta(['success' => false, 'error' => 'Datos no válidos'], 422);
        $params = Registro::parametros($c, (array)($cuerpo['params'] ?? []));
        $respuestas = array_values(array_filter((array)($cuerpo['respuestas'] ?? []), 'is_array'));
        $r = $c::preparar($p, $params);   // los datos se recalculan en el servidor: el cliente solo aporta lo que redactó la IA
        $contenido = $c::componer($p, $params, $r['datos'], $respuestas);
        $id = Informes::crear($p, $c::ficha()['tipo'], $c::titulo($p, $params), $contenido, $params, $respuestas ? 'ia' : 'sistema', (string)($cuerpo['modelo'] ?? ''));
        json_respuesta(['success' => true, 'informe_id' => $id, 'titulo' => $c::titulo($p, $params), 'con_ia' => (bool)$respuestas, 'url' => '/informes-ia/ver?proyecto=' . (int)$p->id . '&id=' . $id]);
    }

    /** Sube la grabación de una reunión (audio original) con su transcripción; cuenta para la cuota del proyecto */
    public static function grabacion()
    {
        $p = proyecto_api();
        $tmp = $_FILES['audio']['tmp_name'] ?? '';
        $nombre = trim((string)($_POST['nombre'] ?? '')) ?: 'Reunión ' . date('d/m/Y H:i');
        [$id, $error] = Grabaciones::guardar($p, (string)$tmp, $nombre, (int)($_POST['duracion'] ?? 0), isset($_POST['transcripcion']) ? (string)$_POST['transcripcion'] : null);
        if (isset($_POST['volver'])) {   // formulario de la web: vuelve a la lista con el aviso
            $_SESSION['informes_msg'] = ['ok' => (bool)$id, 'texto' => $id ? 'Grabación guardada.' : $error];
            header('Location: /grabaciones?proyecto=' . (int)$p->id);
            exit;
        }
        if (!$id) json_respuesta(['success' => false, 'error' => $error], 422);
        json_respuesta(['success' => true, 'id' => $id]);
    }

    /** Foto de evidencia reducida (máx. 1024 px, JPEG) para los modelos de visión de la app de escritorio */
    public static function evidencia()
    {
        $p = proyecto_api();
        $e = \Model\Evidencia::find((int)($_GET['id'] ?? 0));
        $ruta = $e && (int)$e->proyecto_id === (int)$p->id ? __DIR__ . '/../public/uploads/' . \Model\Evidencia::CARPETA . '/' . basename((string)$e->foto) : '';
        if (!$ruta || !is_file($ruta)) { http_response_code(404); exit('Foto no encontrada'); }
        try {
            $img = (new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver()))->read($ruta)->scaleDown(1024, 1024);
            header('Content-Type: image/jpeg');
            echo (string)$img->toJpeg(82);
        } catch (\Throwable $x) {
            http_response_code(415);
            echo 'No se pudo leer la imagen';
        }
        exit;
    }

    /** Duración sugerida para una actividad según otras de nombre parecido (la usa el formulario de actividad) */
    public static function duracionSugerida()
    {
        $p = is_auth() ? Proyecto::find(filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$p || !puede_gestionar_proyecto($p)) json_respuesta(['success' => false], 403);
        json_respuesta(['success' => true, 'sugerencia' => \Classes\Inconsistencias::similares($p, mb_substr((string)($_GET['nombre'] ?? ''), 0, 200), (int)($_GET['excluir'] ?? 0))]);
    }

    /** Días de un periodo que aún no tienen reporte diario (para generar los atrasados por semana o mes); máx. 93 días */
    public static function diasSinReporte()
    {
        $p = proyecto_api();
        $ok = fn($f) => is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f) !== false;
        $desde = $_GET['desde'] ?? '';
        $hasta = min($_GET['hasta'] ?? '', date('Y-m-d'));
        if (!$ok($desde) || !$ok($hasta) || $desde > $hasta) json_respuesta(['success' => false, 'error' => 'Periodo no válido'], 422);
        if ((strtotime($hasta) - strtotime($desde)) / 86400 > 92) json_respuesta(['success' => false, 'error' => 'El periodo no puede pasar de 93 días'], 422);
        $pid = (int)$p->id;
        $d = $GLOBALS['db']->real_escape_string($desde);
        $h = $GLOBALS['db']->real_escape_string($hasta);
        $existen = array_column($GLOBALS['db']->query("SELECT DISTINCT fecha FROM reportes_diarios WHERE proyecto_id = $pid AND fecha BETWEEN '$d' AND '$h'")->fetch_all(MYSQLI_ASSOC), 'fecha');
        $cal = new \Classes\Calendario($pid);
        $con = \Classes\Herramientas\ReporteDiario::diasConCambios($pid, $desde, $hasta);
        $dias = [];
        for ($t = strtotime($desde); $t <= strtotime($hasta); $t += 86400) {
            $f = date('Y-m-d', $t);
            if (!in_array($f, $existen, true)) $dias[] = ['fecha' => $f, 'habil' => $cal->esHabil($f), 'sin_cambios' => !isset($con[$f])];
        }
        // borradores de reporte diario aún sin resolver: se retoman en vez de generarlos otra vez
        $borradores = [];
        foreach (Informes::listar($pid, ['tipo' => 'reporte_diario', 'estado' => 'borrador']) as $b) {
            if ($b['aplicado_en']) continue;
            $inf = Informes::obtener($pid, (int)$b['id']);
            $f = $inf['parametros']['fecha'] ?? '';
            if ($f >= $desde && $f <= $hasta && !isset($borradores[$f])) $borradores[$f] = (int)$b['id'];
        }
        json_respuesta(['success' => true, 'dias' => $dias, 'con_reporte' => count($existen), 'borradores' => (object)$borradores]);
    }

    /** Contenido de un informe (secciones) para revisarlo dentro de la app de escritorio */
    public static function informe()
    {
        $p = proyecto_api();
        $inf = Informes::obtener((int)$p->id, (int)($_GET['id'] ?? 0));
        if (!$inf) json_respuesta(['success' => false, 'error' => 'Informe no encontrado'], 404);
        json_respuesta(['success' => true, 'id' => $inf['id'], 'titulo' => $inf['titulo'], 'estado' => $inf['estado'], 'aplicado' => (bool)$inf['aplicado_en'], 'origen' => $inf['origen'], 'sin_cambios' => !empty($inf['contenido']['aplicar']['sin_cambios']), 'secciones' => \Classes\Informes::sinVacias($inf['contenido'])['secciones']]);
    }

    /** Acepta (aplica: crea el reporte diario) o rechaza (descarta) un informe desde la app de escritorio */
    public static function resolver()
    {
        $p = proyecto_api();
        $id = (int)($_POST['id'] ?? 0);
        $inf = Informes::obtener((int)$p->id, $id);
        if (!$inf) json_respuesta(['success' => false, 'error' => 'Informe no encontrado'], 404);
        if ($inf['aplicado_en']) json_respuesta(['success' => false, 'error' => 'Este informe ya se aplicó.'], 409);
        if (($_POST['accion'] ?? '') === 'rechazar') {
            Informes::estado((int)$p->id, $id, 'descartado');
            Auditoria::registrar((int)$p->id, 'informes', 'rechazado', 'rechazar_informe', 'Informe descartado: ' . $inf['titulo'], null, ['informe_id' => $id, 'tipo' => $inf['tipo']], $id, (string)$inf['modelo']);
            json_respuesta(['success' => true, 'estado' => 'descartado', 'mensaje' => 'Informe descartado.']);
        }
        [$ok, $msg, $ref] = \Classes\AplicarInforme::aplicar($p, $inf);
        if (!$ok) json_respuesta(['success' => false, 'error' => $msg], 422);
        Informes::marcarAplicado((int)$p->id, $id, $ref);
        Auditoria::registrar((int)$p->id, 'informes', 'aplicado', 'aplicar_informe', $msg, null, ['informe_id' => $id, 'tipo' => $inf['tipo']], $id, (string)$inf['modelo'], ['informe', $id, $inf['titulo']]);
        json_respuesta(['success' => true, 'estado' => 'aprobado', 'mensaje' => $msg]);
    }

    // ---------- web ----------

    public static function grabaciones(Router $router)
    {
        $proyecto = self::admin();
        $router->render('informes-ia/grabaciones', ['titulo' => 'Grabaciones de reuniones - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'lista' => Grabaciones::listar((int)$proyecto->id), 'mensaje' => $_SESSION['informes_msg'] ?? null]);
        unset($_SESSION['informes_msg']);
    }

    /** Entrega el audio con soporte de rangos (para poder adelantar en el reproductor) */
    public static function audio()
    {
        $p = self::admin();
        $g = Grabaciones::obtener((int)$p->id, (int)($_GET['id'] ?? 0));
        $ruta = $g ? Grabaciones::ruta($g) : '';
        if (!$g || !is_file($ruta)) { http_response_code(404); exit('Grabación no encontrada'); }
        $tam = filesize($ruta);
        [$ini, $fin] = [0, $tam - 1];
        if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] !== '') { $ini = (int)$m[1]; if ($m[2] !== '') $fin = min((int)$m[2], $tam - 1); } elseif ($m[2] !== '') { $ini = max(0, $tam - (int)$m[2]); }
            if ($ini > $fin || $ini >= $tam) { http_response_code(416); exit; }
            http_response_code(206);
            header("Content-Range: bytes $ini-$fin/$tam");
        }
        header('Content-Type: ' . $g['mime']);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($fin - $ini + 1));
        header('X-Content-Type-Options: nosniff');
        $f = fopen($ruta, 'rb');
        fseek($f, $ini);
        $resta = $fin - $ini + 1;
        while ($resta > 0 && !feof($f)) { $b = fread($f, min(65536, $resta)); echo $b; $resta -= strlen($b); }
        fclose($f);
        exit;
    }

    public static function eliminarGrabacion()
    {
        $p = proyecto_api();
        Grabaciones::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        $_SESSION['informes_msg'] = ['ok' => true, 'texto' => 'Grabación eliminada.'];
        header('Location: /grabaciones?proyecto=' . (int)$p->id);
        exit;
    }


    public static function index(Router $router)
    {
        $proyecto = self::admin();
        $f = ['tipo' => $_GET['tipo'] ?? '', 'estado' => $_GET['estado'] ?? ''];
        $router->render('informes-ia/index', ['titulo' => 'Informes de IA - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'filtros' => $f, 'lista' => Informes::listar((int)$proyecto->id, $f), 'fichas' => Registro::fichas(), 'mensaje' => $_SESSION['informes_msg'] ?? null]);
        unset($_SESSION['informes_msg']);
    }

    /** Genera el informe sin IA (solo con lo que calcula el sistema y texto de plantilla) */
    public static function generar()
    {
        $p = proyecto_api();
        $c = self::herramienta($_POST);
        $params = Registro::parametros($c, (array)($_POST['p'] ?? []));
        $r = $c::preparar($p, $params);
        $id = Informes::crear($p, $c::ficha()['tipo'], $c::titulo($p, $params), $c::componer($p, $params, $r['datos'], []), $params, 'sistema');
        header('Location: /informes-ia/ver?proyecto=' . (int)$p->id . '&id=' . $id);
        exit;
    }

    public static function ver(Router $router)
    {
        $proyecto = self::admin();
        $inf = Informes::obtener((int)$proyecto->id, (int)($_GET['id'] ?? 0));
        if (!$inf) { header('Location: /informes-ia?proyecto=' . (int)$proyecto->id); exit; }
        $router->render('informes-ia/ver', ['titulo' => $inf['titulo'] . ' - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'inf' => $inf, 'mensaje' => $_SESSION['informes_msg'] ?? null]);
        unset($_SESSION['informes_msg']);
    }

    public static function pdf()
    {
        $p = self::admin();
        $inf = Informes::obtener((int)$p->id, (int)($_GET['id'] ?? 0));
        if (!$inf) { http_response_code(404); exit('Informe no encontrado'); }
        enviar_descarga(Informes::pdf($p, $inf), 'application/pdf', date('Y_m_d') . '_' . slug_archivo($inf['titulo']) . '.pdf');
    }

    public static function estado()
    {
        $p = proyecto_api();
        $id = (int)($_POST['id'] ?? 0);
        $ok = Informes::estado((int)$p->id, $id, (string)($_POST['estado'] ?? ''));
        $_SESSION['informes_msg'] = ['ok' => $ok, 'texto' => $ok ? 'Estado actualizado.' : 'No se pudo actualizar.'];
        header('Location: /informes-ia/ver?proyecto=' . (int)$p->id . '&id=' . $id);
        exit;
    }

    public static function eliminar()
    {
        $p = proyecto_api();
        Informes::eliminar((int)$p->id, (int)($_POST['id'] ?? 0));
        $_SESSION['informes_msg'] = ['ok' => true, 'texto' => 'Informe eliminado.'];
        header('Location: /informes-ia?proyecto=' . (int)$p->id);
        exit;
    }

    /** Lleva el informe a su módulo (crea el reporte diario, la minuta…) y lo deja en el historial de cambios */
    public static function aplicar()
    {
        $p = proyecto_api();
        $id = (int)($_POST['id'] ?? 0);
        $inf = Informes::obtener((int)$p->id, $id);
        [$ok, $msg, $ref] = $inf && !$inf['aplicado_en'] ? \Classes\AplicarInforme::aplicar($p, $inf) : [false, $inf ? 'Este informe ya se aplicó.' : 'Informe no encontrado.', ''];
        if ($ok) {
            Informes::marcarAplicado((int)$p->id, $id, $ref);
            Auditoria::registrar((int)$p->id, 'informes', 'aplicado', 'aplicar_informe', $msg, null, ['informe_id' => $id, 'tipo' => $inf['tipo']], $id, (string)$inf['modelo'], ['informe', $id, $inf['titulo']]);
        }
        $_SESSION['informes_msg'] = ['ok' => $ok, 'texto' => $msg];
        header('Location: /informes-ia/ver?proyecto=' . (int)$p->id . '&id=' . $id);
        exit;
    }
}
