<?php

namespace Controllers;

use MVC\Router;
use Model\Grafico;
use Model\GraficoLamina;
use Model\GraficoCategoria;
use Model\Simbolo;
use Model\Cajetin;
use Model\Proyecto;
use Classes\Imagenes;
use Classes\Almacenamiento;
use Classes\EnlacesLamina;
use Classes\Exportadores\OdpExporter;

/** Editor: gráficos libres con el mismo editor de marcas del vPlan (lista, alta, edición, descarga, copia y baja) */
class EditorController
{
    private static function graficoDe(Proyecto $proyecto, $id): ?Grafico
    {
        $g = Grafico::find(filter_var($id, FILTER_VALIDATE_INT));
        return $g && (int)$g->proyecto_id === (int)$proyecto->id ? $g : null;
    }

    private static function fuente(): array
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $router->render('graficos/index', [
            'titulo' => 'Editor - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'graficos' => Grafico::deProyecto((int)$proyecto->id),
            'puedeEditar' => puede_gestionar_proyecto($proyecto), 'puedeDescargar' => puede_descargar(),
        ]);
    }

    public static function crear(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $tipo = ($_REQUEST['tipo'] ?? '') === 'simbologia' ? 'simbologia' : 'grafico';
        $g = new Grafico(['proyecto_id' => $proyecto->id, 'nombre' => '', 'tipo' => $tipo] + ($tipo === 'simbologia' ? ['ancho' => 1200, 'alto' => 700] : []));
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $g = new Grafico([
                'proyecto_id' => $proyecto->id, 'tipo' => $tipo, 'nombre' => $_POST['nombre'] ?? '', 'categoria_id' => GraficoCategoria::valida((int)$proyecto->id, $_POST['categoria_id'] ?? null), 'ancho' => $_POST['ancho'] ?? 0, 'alto' => $_POST['alto'] ?? 0,
                'fondo' => $_POST['fondo'] ?? 'liso', 'fondo_color' => $_POST['fondo_color'] ?? '#ffffff',
            ]);
            $alertas = $g->validar();
            // Fondo personalizado: imagen propia y sus ajustes
            $archivo = null;
            if ($g->fondo === 'imagen') {
                $f = $_FILES['fondo_imagen'] ?? null;
                $valida = $f && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name']) && $f['size'] <= 20 * 1024 * 1024
                    && in_array(mime_content_type($f['tmp_name']), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
                if (!$valida) $alertas['error'][] = 'Elige una imagen para el fondo personalizado (jpg, png, webp o gif de hasta 20 MB)';
                elseif (!Almacenamiento::cabe((int)$proyecto->id, (int)$f['size'])) $alertas['error'][] = Almacenamiento::mensajeLleno((int)$proyecto->id);
                else $archivo = $f['tmp_name'];
                $g->fondo_config = json_encode(Grafico::limpiarConfigFondo($_POST['fondo_config'] ?? ''));
            }
            if (!$alertas) {
                if ($archivo) {
                    $g->fondo_imagen = Imagenes::guardarGrande($archivo, 4000);
                    if (!$g->fondo_imagen) $alertas['error'][] = 'No se pudo procesar la imagen de fondo';
                }
            }
            if (!$alertas) {
                $g->id = $g->guardar()['id'];
                $g->crearLaminaInicial();
                header('Location: /graficos/abrir?proyecto=' . $proyecto->id . '&id=' . $g->id);
                exit;
            }
        }
        $router->render('graficos/crear', ['titulo' => $tipo === 'simbologia' ? 'Nueva simbología' : 'Nuevo gráfico', 'proyecto' => $proyecto, 'grafico' => $g, 'alertas' => $alertas, 'categorias' => GraficoCategoria::deProyecto((int)$proyecto->id)]);
    }

    /** Configuración para el editor de marcas (Konva) */
    private static function configEditor(Proyecto $proyecto, Grafico $g): array
    {
        $laminas = $g->laminas();
        $q = 'proyecto=' . $proyecto->id . '&id=' . $g->id;
        return [
            'lamina' => (int)$g->id, 'proyecto' => (int)$proyecto->id, 'plano' => null, 'ancho' => (int)$g->ancho, 'alto' => (int)$g->alto,
            'fondo' => $g->fondo, 'fondoColor' => $g->fondo_color, 'nombre' => $g->nombre,
            'fondoImagen' => $g->fondo_imagen ? '/uploads/vplan/' . $g->fondo_imagen : null,
            'fondoConfig' => $g->fondo === 'imagen' ? Grafico::limpiarConfigFondo($g->fondo_config) : null,
            'marcas' => null,
            'laminas' => array_map(fn($l, $i) => [
                'id' => (int)$l->id, 'nombre' => $l->nombre, 'version' => (string)$l->actualizado, 'png' => $l->marcas_png ? '/uploads/vplan/' . $l->marcas_png : '',
                'marcas' => $l->marcas_json ? json_decode($l->marcas_json, true) : null,
            ], $laminas, array_keys($laminas)),
            'laminaActiva' => (int)($_GET['hoja'] ?? 0), 'regen' => !empty($_GET['regen']),
            'simbolos' => Simbolo::paraEditor((int)$proyecto->id), 'graficos' => Grafico::paraInsertar((int)$proyecto->id, (int)$g->id),
            'urlSimbolos' => '/simbolos?proyecto=' . $proyecto->id,
            'sheetflow' => \Model\SheetflowHoja::resumen((int)$proyecto->id), 'cajetines' => Cajetin::paraEditor((int)$proyecto->id), 'proyectoNombre' => $proyecto->nombre, 'usuario' => (string)($_SESSION['nombre'] ?? ''),
            'destinos' => EnlacesLamina::destinos((int)$proyecto->id),
            'urls' => ['descargar' => '/graficos/descargar?' . $q],
            'actividades' => [], 'restricciones' => [], 'hash' => '', 'auto' => false,
            'modo' => 'grafico', 'guardado' => 'png',
            'volver' => $g->tipo === 'simbologia' ? '/simbolos?proyecto=' . $proyecto->id . '#simbologias' : (\Model\View360Grafico::dePorGrafico((int)$g->id) ? '/view360?proyecto=' . $proyecto->id : '/graficos/ver?' . $q),
            'tipoGrafico' => $g->tipo,
            'punchCols' => array_map(fn($k, $v) => ['k' => $k, 'etq' => $v[0]], array_keys(\Classes\PunchVista::COLUMNAS), \Classes\PunchVista::COLUMNAS), 'punchBase' => \Classes\PunchVista::POR_DEFECTO,
            'punchEstados' => \Classes\Punch::ESTADOS, 'punchSev' => \Classes\Punch::SEVERIDADES,
            'api' => ['punch' => '/api/graficos/punch', 'sheetflow' => '/api/sheetflow/datos', 'marcas' => '/api/graficos/marcas', 'imagen' => '/api/graficos/imagen', 'pdf' => '/api/graficos/pdf', 'odp' => '/api/graficos/odp', 'lamina' => '/api/graficos/lamina'],
        ];
    }

    public static function abrir(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $g = self::graficoDe($proyecto, $_GET['id'] ?? null);
        if (!$g) {
            header('Location: /graficos?proyecto=' . $proyecto->id);
            exit;
        }
        $router->render('graficos/abrir', [
            'titulo' => 'Editor - ' . $g->nombre, 'proyecto' => $proyecto, 'grafico' => $g, 'config' => self::configEditor($proyecto, $g),
        ]);
    }

    public static function ver(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $g = self::graficoDe($proyecto, $_GET['id'] ?? null);
        if (!$g) {
            header('Location: /graficos?proyecto=' . $proyecto->id);
            exit;
        }
        $router->render('graficos/ver', [
            'titulo' => 'Editor - ' . $g->nombre, 'proyecto' => $proyecto, 'grafico' => $g, 'laminas' => $g->laminas(),
            'puedeEditar' => puede_gestionar_proyecto($proyecto), 'puedeDescargar' => puede_descargar(),
        ]);
    }

    /** PNG de cada lámina para descargar: [['bin' => binario, 'nombre' => ...], ...]. Las láminas que nunca se guardaron salen en blanco (con el color liso del fondo) */
    private static function pngsGuardados(Grafico $g): array
    {
        $out = [];
        $hayAlguna = false;
        foreach ($g->laminas() as $l) $hayAlguna = $hayAlguna || ($l->marcas_png && is_file(Imagenes::CARPETA_VPLAN . '/' . basename($l->marcas_png)));
        if (!$hayAlguna) return [];
        foreach ($g->laminas() as $i => $l) {
            $ruta = $l->marcas_png ? Imagenes::CARPETA_VPLAN . '/' . basename($l->marcas_png) : null;
            $out[] = ['bin' => $ruta && is_file($ruta) ? (string)file_get_contents($ruta) : self::pngEnBlanco($g), 'nombre' => $l->titulo($i + 1), 'enlaces' => EnlacesLamina::externos(EnlacesLamina::de($l->marcas_json))];
        }
        return $out;
    }

    private static function pngEnBlanco(Grafico $g): string
    {
        $k = min(1, 1200 / max(1, (int)$g->ancho));
        $im = imagecreatetruecolor(max(16, (int)round($g->ancho * $k)), max(16, (int)round($g->alto * $k)));
        $hex = $g->fondo === 'liso' && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$g->fondo_color) ? $g->fondo_color : '#ffffff';
        imagefill($im, 0, 0, imagecolorallocate($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))));
        ob_start();
        imagepng($im);
        return (string)ob_get_clean();
    }

    private static function enviar(string $tipo, string $nombre, string $datos): void
    {
        header_remove('Content-Type');
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($datos));
        echo $datos;
        exit;
    }

    /** Descarga lo guardado con la nomenclatura Y_m_d_Nombre.ext: png (zip si hay varias láminas), pdf u odp */
    public static function descargar()
    {
        $proyecto = proyecto_de_peticion();
        $g = self::graficoDe($proyecto, $_GET['id'] ?? null);
        $lams = $g ? self::pngsGuardados($g) : [];
        if (!puede_descargar() || !$lams) {
            header('Location: /graficos?proyecto=' . $proyecto->id . '&error=' . urlencode('No hay imagen para descargar. Abre el gráfico y guárdalo primero.'));
            exit;
        }
        $base = date('Y_m_d') . '_' . slug_archivo($g->nombre);
        $formato = in_array($_GET['formato'] ?? 'png', ['png', 'pdf', 'odp'], true) ? ($_GET['formato'] ?? 'png') : 'png';
        @ini_set('memory_limit', '1024M');
        @set_time_limit(120);
        if ($formato === 'png') {
            if (count($lams) === 1) self::enviar('image/png', $base . '.png', $lams[0]['bin']);
            $tmp = tempnam(sys_get_temp_dir(), 'gzp');
            $zip = new \ZipArchive();
            $zip->open($tmp, \ZipArchive::OVERWRITE);
            foreach ($lams as $i => $l) $zip->addFromString(sprintf('%02d_%s.png', $i + 1, slug_archivo($l['nombre'])), $l['bin']);
            $zip->close();
            $datos = (string)file_get_contents($tmp);
            @unlink($tmp);
            self::enviar('application/zip', $base . '.zip', $datos);
        }
        if ($formato === 'pdf') {
            self::enviar('application/pdf', $base . '.pdf', self::pdfDe(array_map(fn($l) => $l['bin'], $lams), (int)$g->ancho, (int)$g->alto, array_map(fn($l) => $l['enlaces'], $lams)));
        }
        self::enviar('application/vnd.oasis.opendocument.presentation', $base . '.odp', OdpExporter::generar(
            array_map(fn($l) => ['png' => $l['bin'], 'nombre' => $l['nombre']], $lams), (int)$g->ancho, (int)$g->alto, $g->nombre
        ));
    }

    public static function duplicar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($g = self::graficoDe($proyecto, $_POST['id'] ?? null)) {
            if (!Almacenamiento::cabe((int)$proyecto->id, $g->bytes())) {
                header('Location: /graficos?proyecto=' . $proyecto->id . '&error=' . urlencode(Almacenamiento::mensajeLleno((int)$proyecto->id)));
                exit;
            }
            $fondo = null;
            if ($g->fondo_imagen && is_file(Imagenes::CARPETA_VPLAN . '/' . basename($g->fondo_imagen))) {
                $fondo = bin2hex(random_bytes(16)) . '.' . pathinfo($g->fondo_imagen, PATHINFO_EXTENSION);
                copy(Imagenes::CARPETA_VPLAN . '/' . basename($g->fondo_imagen), Imagenes::CARPETA_VPLAN . '/' . $fondo);
            }
            $nueva = new Grafico([
                'proyecto_id' => $proyecto->id, 'nombre' => mb_substr($g->nombre, 0, 140) . ' (copia)', 'tipo' => $g->tipo, 'categoria_id' => $g->categoria_id, 'ancho' => $g->ancho, 'alto' => $g->alto,
                'fondo' => $g->fondo, 'fondo_color' => $g->fondo_color, 'fondo_imagen' => $fondo, 'fondo_config' => $g->fondo_config,
            ]);
            $nueva->id = $nueva->guardar()['id'];
            foreach ($g->laminas() as $l) {
                $c = $l->copiarArchivos();
                (new GraficoLamina(['grafico_id' => $nueva->id, 'orden' => $l->orden, 'nombre' => $l->nombre, 'marcas_json' => $c['json'], 'marcas_png' => $c['png']]))->guardar();
            }
            header('Location: ' . ($g->tipo === 'simbologia' ? '/simbolos?proyecto=' . $proyecto->id . '&ok=' . urlencode('Simbología duplicada') . '#simbologias' : '/graficos?proyecto=' . $proyecto->id . '&ok=1'));
            exit;
        }
        header('Location: /graficos?proyecto=' . $proyecto->id);
        exit;
    }

    /** Marca un gráfico como simbología (o lo devuelve a gráfico normal) */
    public static function cambiarTipo()
    {
        $proyecto = proyecto_de_peticion(true);
        $a = 'graficos';
        if ($g = self::graficoDe($proyecto, $_POST['id'] ?? null)) {
            $g->tipo = ($_POST['tipo'] ?? '') === 'simbologia' ? 'simbologia' : 'grafico';
            $g->guardar();
            $a = $g->tipo === 'simbologia' ? 'simbolos' : 'graficos';
        }
        header('Location: /' . $a . '?proyecto=' . $proyecto->id . ($a === 'simbolos' ? '&ok=' . urlencode('Guardado como simbología') . '#simbologias' : ''));
        exit;
    }

    /** Crea una simbología con las 4 filas del SYMBOLOGY FOR SCHEDULE estándar, lista para editar (forma: rectangular o cuadrada) */
    public static function simbologiaEstandar()
    {
        $proyecto = proyecto_de_peticion(true);
        $cuadrada = ($_POST['forma'] ?? '') === 'cuadrada';
        // [lienzo ancho, alto, tamaño del título, primera fila y: paso, caja de color ancho/alto, x del texto, tamaño del texto]
        [$W, $H, $tf, $y0, $paso, $cw, $ch, $tx, $fs] = $cuadrada ? [800, 800, 46, 190, 150, 120, 100, 190, 60] : [1200, 700, 72, 180, 125, 150, 95, 230, 66];
        $g = new Grafico(['proyecto_id' => $proyecto->id, 'tipo' => 'simbologia', 'nombre' => 'Schedule estándar · ' . ($cuadrada ? 'cuadrada' : 'rectangular'), 'ancho' => $W, 'alto' => $H]);
        $g->id = $g->guardar()['id'];
        $id = 0;
        $f = function (array $d) use (&$id) { return ['id' => 'sd' . (++$id), 'rotation' => 0, 'opacity' => 100] + $d; };
        $formas = [
            $f(['type' => 'rect', 'x' => 10, 'y' => 10, 'w' => $W - 20, 'h' => $H - 20, 'fill' => '#ffffff', 'fillAlpha' => 100, 'stroke' => '#000000', 'strokeWidth' => 4]),
            $f(['type' => 'text', 'x' => 30, 'y' => 40, 'text' => 'SYMBOLOGY FOR SCHEDULE', 'fontSize' => $tf, 'fill' => '#000000', 'bold' => true, 'underline' => true]),
            $f(['type' => 'line', 'x' => 0, 'y' => 0, 'points' => [10, $y0 - 30, $W - 10, $y0 - 30], 'stroke' => '#000000', 'strokeWidth' => 4]),
        ];
        $filas = [['COMPLETE', '#059669'], ['IN PROGRESS', '#d97706'], ['ON HOLD', '#dc2626'], ['CONSTRAIN', null]];
        foreach ($filas as $i => [$texto, $color]) {
            $y = $y0 + $i * $paso;
            $formas[] = $color
                ? $f(['type' => 'rect', 'x' => 40, 'y' => $y, 'w' => $cw, 'h' => $ch, 'fill' => $color, 'fillAlpha' => 100, 'stroke' => '#000000', 'strokeWidth' => 3])
                : $f(['type' => 'diamond', 'x' => 40 + ($cw - $ch) / 2, 'y' => $y, 'w' => $ch, 'h' => $ch, 'fill' => '#dc2626', 'fillAlpha' => 100, 'stroke' => '#991b1b', 'strokeWidth' => 4]);
            $formas[] = $f(['type' => 'text', 'x' => $tx, 'y' => $y + ($ch - $fs) / 2, 'text' => $texto, 'fontSize' => $fs, 'fill' => '#000000']);
        }
        (new GraficoLamina(['grafico_id' => $g->id, 'orden' => 1, 'marcas_json' => json_encode(['shapes' => $formas])]))->guardar();
        header('Location: /graficos/abrir?proyecto=' . $proyecto->id . '&id=' . $g->id . '&regen=1');   // el editor genera la imagen al abrirla
        exit;
    }

    /** Cambiar el tamaño y el fondo de un gráfico ya creado (la misma plantilla de «Nuevo gráfico») */
    public static function fondo(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $g = self::graficoDe($proyecto, self::fuente()['id'] ?? null);
        if (!$g) {
            header('Location: /graficos?proyecto=' . $proyecto->id);
            exit;
        }
        $alertas = [];
        $form = $g;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = new Grafico([
                'id' => $g->id, 'proyecto_id' => $proyecto->id, 'nombre' => $g->nombre, 'categoria_id' => GraficoCategoria::valida((int)$proyecto->id, $_POST['categoria_id'] ?? null), 'ancho' => $_POST['ancho'] ?? 0, 'alto' => $_POST['alto'] ?? 0,
                'fondo' => $_POST['fondo'] ?? 'liso', 'fondo_color' => $_POST['fondo_color'] ?? '#ffffff', 'fondo_imagen' => $g->fondo_imagen, 'fondo_config' => $g->fondo_config,
            ]);
            $alertas = $form->validar();
            $archivo = null;
            if ($form->fondo === 'imagen') {
                $f = $_FILES['fondo_imagen'] ?? null;
                $hay = $f && !empty($f['tmp_name']) && is_uploaded_file($f['tmp_name']);
                if ($hay) {
                    $valida = $f['size'] <= 20 * 1024 * 1024 && in_array(mime_content_type($f['tmp_name']), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
                    if (!$valida) $alertas['error'][] = 'La imagen de fondo no es válida (jpg, png, webp o gif de hasta 20 MB)';
                    elseif (!Almacenamiento::cabe((int)$proyecto->id, (int)$f['size'])) $alertas['error'][] = Almacenamiento::mensajeLleno((int)$proyecto->id);
                    else $archivo = $f['tmp_name'];
                } elseif (!$g->fondo_imagen) {
                    $alertas['error'][] = 'Elige una imagen para el fondo personalizado';
                }
                $form->fondo_config = json_encode(Grafico::limpiarConfigFondo($_POST['fondo_config'] ?? ($g->fondo_config ?? '')));
            }
            if (!$alertas) {
                if ($archivo) {
                    $nuevo = Imagenes::guardarGrande($archivo, 4000);
                    if (!$nuevo) $alertas['error'][] = 'No se pudo procesar la imagen de fondo';
                    else { Imagenes::eliminarVplan($g->fondo_imagen); $form->fondo_imagen = $nuevo; }
                } elseif ($form->fondo !== 'imagen') {
                    Imagenes::eliminarVplan($g->fondo_imagen);
                    $form->fondo_imagen = null;
                    $form->fondo_config = null;
                }
            }
            if (!$alertas) {
                // Al cambiar el tamaño, el contenido se centra en el lienzo nuevo o se queda en la esquina superior izquierda
                $centrar = ($_POST['ancla'] ?? 'centro') !== 'esquina';
                $dx = $centrar ? ($form->ancho - $g->ancho) / 2 : 0;
                $dy = $centrar ? ($form->alto - $g->alto) / 2 : 0;
                if ($dx || $dy) {
                    foreach ($g->laminas() as $l) {
                        $d = $l->marcas_json ? json_decode($l->marcas_json, true) : null;
                        if (!is_array($d) || empty($d['shapes'])) continue;
                        foreach ($d['shapes'] as &$sh) {
                            $sh['x'] = round(($sh['x'] ?? 0) + $dx, 2);
                            $sh['y'] = round(($sh['y'] ?? 0) + $dy, 2);
                        }
                        unset($sh);
                        $d['w'] = (int)$form->ancho;
                        $d['h'] = (int)$form->alto;
                        $l->marcas_json = json_encode($d);
                        $l->guardar();
                    }
                }
                $form->guardar();
                header('Location: /graficos/abrir?proyecto=' . $proyecto->id . '&id=' . $g->id . '&regen=1');
                exit;
            }
        }
        $router->render('graficos/crear', ['titulo' => 'Tamaño y fondo - ' . $g->nombre, 'proyecto' => $proyecto, 'grafico' => $form, 'alertas' => $alertas, 'edicion' => true, 'categorias' => GraficoCategoria::deProyecto((int)$proyecto->id)]);
    }

    // ---------- categorías de gráficos ----------

    public static function categorias(Router $router)
    {
        $proyecto = proyecto_de_peticion();
        $lista = [];
        foreach (GraficoCategoria::deProyecto((int)$proyecto->id) as $c) $lista[] = ['cat' => $c, 'uso' => $c->enUso()];
        $router->render('graficos/categorias', ['titulo' => 'Categorías de gráficos - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'lista' => $lista]);
    }

    public static function categoriaCrear(Router $router)
    {
        self::categoriaFormulario($router, new GraficoCategoria());
    }

    public static function categoriaEditar(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $c = GraficoCategoria::find(filter_var(self::fuente()['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$c || (int)$c->proyecto_id !== (int)$proyecto->id) {
            header('Location: /graficos/categorias?proyecto=' . $proyecto->id);
            exit;
        }
        self::categoriaFormulario($router, $c);
    }

    private static function categoriaFormulario(Router $router, GraficoCategoria $c)
    {
        $proyecto = proyecto_de_peticion(true);
        $alertas = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $c->proyecto_id = $proyecto->id;
            $c->nombre = trim($_POST['nombre'] ?? '');
            $alertas = $c->validar();
            if (!$alertas) {
                $c->guardar();
                header('Location: /graficos/categorias?proyecto=' . $proyecto->id);
                exit;
            }
        }
        $router->render('graficos/categoria-formulario', ['titulo' => $c->id ? 'Editar categoría' : 'Nueva categoría', 'proyecto' => $proyecto, 'cat' => $c, 'alertas' => $alertas]);
    }

    public static function categoriaEliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $c = GraficoCategoria::find(filter_var(self::fuente()['id'] ?? null, FILTER_VALIDATE_INT));
        $error = '';
        if ($c && (int)$c->proyecto_id === (int)$proyecto->id) {
            $uso = $c->enUso();
            if ($uso > 0) $error = '&error=' . urlencode("No se puede eliminar «{$c->nombre}»: la usan $uso gráfico(s).");
            else $c->eliminar();
        }
        header('Location: /graficos/categorias?proyecto=' . $proyecto->id . $error);
        exit;
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $simb = false;
        if ($g = self::graficoDe($proyecto, $_POST['id'] ?? null)) {
            $simb = $g->tipo === 'simbologia';
            $g->eliminarArchivos();
            $g->eliminar();   // las láminas del vPlan que la usaban vuelven a la tabla estándar (ON DELETE SET NULL)
        }
        header('Location: ' . ($simb ? '/simbolos?proyecto=' . $proyecto->id . '&ok=' . urlencode('Simbología eliminada') . '#simbologias' : '/graficos?proyecto=' . $proyecto->id));
        exit;
    }

    // ---------- JSON ----------

    /** Gráfico de la petición (campo «lamina», igual que el editor del vPlan) si el usuario puede gestionarlo (o verlo/descargarlo) */
    private static function graficoApi(bool $soloVer = false): array
    {
        header('Content-Type: application/json');
        $g = $_SERVER['REQUEST_METHOD'] === 'POST' && is_auth() ? Grafico::find(filter_var($_POST['lamina'] ?? null, FILTER_VALIDATE_INT)) : null;
        $p = $g ? Proyecto::find($g->proyecto_id) : null;
        $ok = $p && ($soloVer ? (puede_ver_proyecto($p) && puede_descargar()) : puede_gestionar_proyecto($p));
        if (!$ok) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
        return [$g, $p];
    }

    public static function renombrar()
    {
        [$g] = self::graficoApi();
        $g->nombre = trim((string)($_POST['nombre'] ?? ''));
        if ($alertas = $g->validar()) json_respuesta(['success' => false, 'error' => $alertas['error'][0]], 422);
        $g->guardar();
        json_respuesta(['success' => true, 'nombre' => $g->nombre]);
    }

    /** Lámina del gráfico indicada por «hoja» (o la primera si no se indica) */
    private static function laminaDe(Grafico $g, $id = null): ?GraficoLamina
    {
        $todas = $g->laminas();
        if (!$id) return $todas[0] ?? null;
        foreach ($todas as $l) {
            if ((int)$l->id === (int)$id) return $l;
        }
        return null;
    }

    /** Pendientes del Punch list según una vista (columnas y filtros); las fotos salen como «Sí» porque las celdas del Editor son de texto */
    public static function datosPunch()
    {
        [, $p] = self::graficoApi();
        $cfg = json_decode((string)($_POST['cfg'] ?? ''), true);
        $cfg = \Classes\PunchVista::normalizar(is_array($cfg) ? $cfg : []);
        $filas = [];
        $fotos = [];   // [renglón, columna, src, ancho, alto]: miniaturas que el Editor coloca sobre la tabla (máx. 20)
        foreach (\Classes\PunchVista::filas((int)$p->id, $cfg) as $r => $f) {
            $fila = [];
            foreach ($cfg['columnas'] as $i => $k) {
                if ($f['c' . $i] === 'foto') {
                    $src = count($fotos) < 20 ? \Classes\PunchVista::rutaMiniatura($f['v' . $i]) : null;
                    $tam = $src ? @getimagesize(__DIR__ . '/../public' . $src) : false;
                    if ($tam) { $fotos[] = [$r, $i, $src, $tam[0], $tam[1]]; $fila[] = ''; } else $fila[] = 'Sí';
                } else $fila[] = $f['c' . $i];
            }
            $filas[] = $fila;
        }
        json_respuesta(['success' => true, 'cfg' => $cfg, 'columnas' => \Classes\PunchVista::etiquetas($cfg), 'filas' => $filas, 'fotos' => $fotos]);
    }

    public static function guardarMarcas()
    {
        [$g, $p] = self::graficoApi();
        $l = self::laminaDe($g, $_POST['hoja'] ?? null);
        if (!$l) json_respuesta(['success' => false, 'error' => 'Lámina no encontrada'], 404);
        $json = (string)($_POST['json'] ?? '');
        $bin = \Classes\MarcasLienzo::binarioDeDataUrl((string)($_POST['png'] ?? ''));
        if (!\Classes\MarcasLienzo::leer($json, '#^/uploads/(?:vplan/[a-f0-9]{32}\.(?:png|jpg)|punch/min/[a-f0-9]{32}\.jpg)$#', $bin, 60_000_000)) {
            json_respuesta(['success' => false, 'error' => 'Datos no válidos'], 422);
        }
        $ver = (string)($_POST['version'] ?? '');
        if ($ver !== '' && empty($_POST['forzar']) && $ver !== (string)$l->actualizado) {
            json_respuesta(['success' => false, 'conflicto' => true, 'error' => 'Esta lámina se guardó desde otra sesión después de que la abrieras.'], 409);
        }
        $anterior = $l->marcas_png ? (int)@filesize(Imagenes::CARPETA_VPLAN . '/' . basename($l->marcas_png)) : 0;
        if (!Almacenamiento::cabe((int)$p->id, max(0, strlen($bin) - $anterior))) json_respuesta(['success' => false, 'error' => Almacenamiento::mensajeLleno((int)$p->id)], 422);
        $nuevo = \Classes\MarcasLienzo::guardarBinario($bin, Grafico::LADO_MAX);
        if (!$nuevo) json_respuesta(['success' => false, 'error' => 'Imagen no válida'], 422);
        Imagenes::eliminarVplan($l->marcas_png);
        $enUso = array_merge(Grafico::imagenesDe($json), $l->imagenesDeHermanas());
        foreach (array_diff(Grafico::imagenesDe($l->marcas_json), $enUso) as $viejo) Imagenes::eliminarVplan($viejo);
        $l->marcas_png = $nuevo;
        $l->marcas_json = $json;
        $l->guardar();
        $fresca = GraficoLamina::find($l->id);
        json_respuesta(['success' => true, 'src' => '/uploads/vplan/' . $nuevo, 'version' => $fresca ? (string)$fresca->actualizado : '']);
    }

    /** Altas, bajas, copias, nombres y orden de las láminas de un gráfico */
    public static function lamina()
    {
        [$g, $p] = self::graficoApi();
        $todas = $g->laminas();
        $accion = (string)($_POST['accion'] ?? '');
        $buscar = function ($id) use ($todas): ?GraficoLamina {
            foreach ($todas as $l) if ((int)$l->id === (int)$id) return $l;
            return null;
        };
        // Abre un hueco justo después de la lámina «despues» y devuelve el orden libre
        $hueco = function (?GraficoLamina $despues) use ($g, $todas): int {
            $orden = $despues ? (int)$despues->orden + 1 : count($todas) + 1;
            Grafico::ejecutar("UPDATE editor_laminas SET orden = orden + 1 WHERE grafico_id = " . (int)$g->id . " AND orden >= $orden");
            return $orden;
        };
        if ($accion === 'crear' || $accion === 'duplicar') {
            if (count($todas) >= 60) json_respuesta(['success' => false, 'error' => 'Máximo 60 láminas por gráfico'], 422);
        }
        switch ($accion) {
            case 'crear':
                $nueva = new GraficoLamina(['grafico_id' => $g->id, 'orden' => $hueco($buscar($_POST['despues'] ?? 0))]);
                $nueva->id = $nueva->guardar()['id'];
                $f = GraficoLamina::find($nueva->id);
                json_respuesta(['success' => true, 'id' => (int)$nueva->id, 'nombre' => '', 'version' => $f ? (string)$f->actualizado : '']);
            case 'duplicar':
                $o = $buscar($_POST['hoja'] ?? 0);
                if (!$o) json_respuesta(['success' => false, 'error' => 'Lámina no encontrada'], 404);
                if (!Almacenamiento::cabe((int)$p->id, $o->bytes())) json_respuesta(['success' => false, 'error' => Almacenamiento::mensajeLleno((int)$p->id)], 422);
                $c = $o->copiarArchivos();
                $nueva = new GraficoLamina(['grafico_id' => $g->id, 'orden' => $hueco($o), 'nombre' => $o->nombre !== '' ? mb_substr($o->nombre, 0, 52) . ' (copia)' : '', 'marcas_json' => $c['json'], 'marcas_png' => $c['png']]);
                $nueva->id = $nueva->guardar()['id'];
                $f = GraficoLamina::find($nueva->id);
                json_respuesta(['success' => true, 'version' => $f ? (string)$f->actualizado : '', 'id' => (int)$nueva->id, 'nombre' => $nueva->nombre, 'marcas' => $nueva->marcas_json ? json_decode($nueva->marcas_json, true) : null, 'png' => $nueva->marcas_png ? '/uploads/vplan/' . $nueva->marcas_png : '']);
            case 'eliminar':
                $o = $buscar($_POST['hoja'] ?? 0);
                if (!$o) json_respuesta(['success' => false, 'error' => 'Lámina no encontrada'], 404);
                if (count($todas) < 2) json_respuesta(['success' => false, 'error' => 'El gráfico debe conservar al menos una lámina'], 422);
                $o->eliminarArchivos();
                $o->eliminar();
                json_respuesta(['success' => true]);
            case 'renombrar':
                $o = $buscar($_POST['hoja'] ?? 0);
                if (!$o) json_respuesta(['success' => false, 'error' => 'Lámina no encontrada'], 404);
                $o->nombre = mb_substr(trim((string)($_POST['nombre'] ?? '')), 0, 60);
                $o->guardar();
                json_respuesta(['success' => true, 'nombre' => $o->nombre]);
            case 'ordenar':
                $ids = array_map('intval', array_filter(explode(',', (string)($_POST['orden'] ?? '')), 'strlen'));
                $actuales = array_map(fn($l) => (int)$l->id, $todas);
                $a = $ids; $b = $actuales; sort($a); sort($b);
                if ($a !== $b) json_respuesta(['success' => false, 'error' => 'Orden no válido'], 422);
                foreach ($ids as $i => $id) Grafico::ejecutar("UPDATE editor_laminas SET orden = " . ($i + 1) . " WHERE id = $id AND grafico_id = " . (int)$g->id);
                json_respuesta(['success' => true]);
        }
        json_respuesta(['success' => false, 'error' => 'Acción no válida'], 422);
    }

    /** Imagen insertada dentro del gráfico (misma regla que en el vPlan) */
    public static function subirImagen()
    {
        [, $p] = self::graficoApi();
        \Classes\MarcasLienzo::subirImagen($p);
    }

    /** PDF con una página por imagen, del tamaño exacto de la imagen (px a 96 dpi → puntos). $pngs: binarios PNG */
    /** $enlaces: por página, lista de {x,y,w,h,url} en píxeles de la imagen (solo externos); se vuelven áreas clicables del PDF */
    private static function pdfDe(array $pngs, int $w, int $h, array $enlaces = []): string
    {
        $wpt = round($w * 0.75, 2);
        $hpt = round($h * 0.75, 2);
        $html = '<html><head><meta charset="utf-8"><style>@page{margin:0}html,body{margin:0;padding:0}.pag{position:relative;width:' . $wpt . 'pt;height:' . $hpt . 'pt}img{display:block;width:' . $wpt . 'pt;height:' . $hpt . 'pt}.pg{page-break-before:always}a.lk{position:absolute;display:block;text-decoration:none}</style></head><body>';
        foreach ($pngs as $i => $bin) {
            $html .= '<div class="pag' . ($i ? ' pg' : '') . '"><img src="data:image/png;base64,' . base64_encode($bin) . '">';
            foreach ($enlaces[$i] ?? [] as $e) {
                $url = EnlacesLamina::limpiar('ext', (string)($e['url'] ?? ''));
                $x = (float)($e['x'] ?? 0); $y = (float)($e['y'] ?? 0); $ew = (float)($e['w'] ?? 0); $eh = (float)($e['h'] ?? 0);
                if ($url === null || $ew < 2 || $eh < 2 || !is_finite($x + $y + $ew + $eh)) continue;
                $html .= '<a class="lk" href="' . htmlspecialchars($url, ENT_QUOTES) . '" style="left:' . round($x * 0.75, 2) . 'pt;top:' . round($y * 0.75, 2) . 'pt;width:' . round($ew * 0.75, 2) . 'pt;height:' . round($eh * 0.75, 2) . 'pt"></a>';
            }
            $html .= '</div>';
        }
        return \Classes\Pdf::generar($html . '</body></html>', [0, 0, $wpt, $hpt], 'portrait', ['isRemoteEnabled' => false, 'defaultFont' => 'serif']);
    }

    /** Medidas y PNG (data URI) enviados por el navegador para PDF/ODP de la lámina actual */
    private static function imagenDeNavegador(): array
    {
        $w = (int)($_POST['ancho'] ?? 0);
        $h = (int)($_POST['alto'] ?? 0);
        $png = (string)($_POST['png'] ?? '');
        if ($w < 16 || $h < 16 || $w > 10000 || $h > 10000 || $w * $h > 64_000_000 || !preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $png) || strlen($png) > 90_000_000) {
            json_respuesta(['success' => false, 'error' => 'Datos no válidos'], 422);
        }
        @ini_set('memory_limit', '1024M');
        @set_time_limit(120);
        return [$w, $h, base64_decode(substr($png, strpos($png, ',') + 1), true) ?: ''];
    }

    public static function pdf()
    {
        [$g] = self::graficoApi(true);
        [$w, $h, $bin] = self::imagenDeNavegador();
        $enlaces = json_decode((string)($_POST['enlaces'] ?? '[]'), true);
        self::enviar('application/pdf', date('Y_m_d') . '_' . slug_archivo($g->nombre) . '.pdf', self::pdfDe([$bin], $w, $h, [is_array($enlaces) ? array_slice($enlaces, 0, 200) : []]));
    }

    public static function odp()
    {
        [$g] = self::graficoApi(true);
        [$w, $h, $bin] = self::imagenDeNavegador();
        self::enviar('application/vnd.oasis.opendocument.presentation', date('Y_m_d') . '_' . slug_archivo($g->nombre) . '.odp', OdpExporter::generar([['png' => $bin, 'nombre' => $g->nombre]], $w, $h, $g->nombre));
    }
}
