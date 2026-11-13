<?php

namespace Controllers;

use MVC\Router;
use Model\Proyecto;
use Model\Partida;
use Model\Actividad;
use Model\CalendarioJornada;
use Model\CalendarioFestivo;
use Model\Exportacion;
use Classes\Calendario;
use Classes\TemaProyecto;
use Classes\Calendarios;

class AjustesController
{
    public static function index(Router $router)
    {
        redirigir_si_no_auth();
        $proyectoId = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }

        exigir_gestionar_proyecto($proyecto);   // jornada y festivos solo los ve quien puede editarlos
        $jornada = $proyecto->getJornada();
        $festivos = $proyecto->getFestivos();

        // Accesos del proyecto: el dueño ve a su equipo y marca quién entra a esta obra
        $equipoProyecto = [];
        $equipoDisponible = [];
        if (($_SESSION['rol'] ?? '') === 'cliente') {
            $equipoDisponible = \Model\Usuario::equipoDe((int)$_SESSION['id']);
            $equipoProyecto = array_values(array_intersect(\Model\ProyectoAcceso::usuariosDe((int)$proyecto->id), array_map(fn($u) => (int)$u->id, $equipoDisponible)));
        }

        // Calendario del mes activo
        $mesActual = $_GET['mes'] ?? date('Y-m');
        $calendario = new Calendario($proyectoId);
        $actividades = self::obtenerActividadesDelMes($proyecto, $mesActual);

        $calendarios = [];
        if (Calendarios::disponible()) foreach (Calendarios::lista($proyectoId) as $c) $calendarios[] = Calendarios::calendario($proyectoId, (int)$c['id']);
        $router->render('ajustes/index', [
            'almacenamiento' => ['desglose' => \Classes\Almacenamiento::desglose((int)$proyecto->id)],
            'titulo' => 'Ajustes - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'jornada' => $jornada,
            'festivos' => $festivos,
            'mesActual' => $mesActual,
            'actividades' => $actividades,
            'calendario' => $calendario,
            'equipoDisponible' => $equipoDisponible,
            'equipoProyecto' => $equipoProyecto,
            'calendarios' => $calendarios,
        ]);
    }

    public static function calendarioGuardar()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Calendarios::crear((int)$p->id, texto_param($_POST['nombre'] ?? ''));
        header('Location: /ajustes?proyecto=' . (int)$p->id . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg) . '#calendarios');
        exit;
    }

    public static function calendarioJornada()
    {
        $p = proyecto_de_peticion(true);
        Calendarios::guardarJornada((int)$p->id, (int)($_POST['id'] ?? 0), (array)($_POST['factor'] ?? []));
        header('Location: /ajustes?proyecto=' . (int)$p->id . '&ok=' . urlencode('Jornada del calendario guardada') . '#calendarios');
        exit;
    }

    public static function calendarioFestivo()
    {
        $p = proyecto_de_peticion(true);
        [$ok, $msg] = Calendarios::agregarFestivo((int)$p->id, (int)($_POST['id'] ?? 0), $_POST);
        header('Location: /ajustes?proyecto=' . (int)$p->id . '&' . ($ok ? 'ok=' : 'error=') . urlencode($msg) . '#calendarios');
        exit;
    }

    public static function calendarioFestivoEliminar()
    {
        $p = proyecto_de_peticion(true);
        Calendarios::eliminarFestivo((int)$p->id, (int)($_POST['id'] ?? 0), (int)($_POST['festivo_id'] ?? 0));
        header('Location: /ajustes?proyecto=' . (int)$p->id . '&ok=' . urlencode('Día no laborable eliminado') . '#calendarios');
        exit;
    }

    /** El dueño marca qué miembros de su equipo entran a este proyecto (Ajustes → Accesos) */
    public static function guardarAccesos()
    {
        $proyecto = proyecto_de_peticion(true);
        if (($_SESSION['rol'] ?? '') !== 'cliente' || !puede_gestionar_proyecto($proyecto)) {
            header('Location: /ajustes?proyecto=' . $proyecto->id);
            exit;
        }
        $equipo = \Model\Usuario::equipoDe((int)$_SESSION['id']);
        $permitidos = array_map(fn($u) => (int)$u->id, $equipo);
        \Model\ProyectoAcceso::sincronizarProyectoEn((int)$proyecto->id, $_POST['usuarios'] ?? [], $permitidos);
        header('Location: /ajustes?proyecto=' . $proyecto->id . '&ok=accesos#accesos');
        exit;
    }

    /** Obtiene actividades que tienen días programados en el mes especificado */
    private static function obtenerActividadesDelMes(Proyecto $proyecto, string $mesYmd): array
    {
        $inicioMes = new \DateTime($mesYmd . '-01');
        $finMes = clone $inicioMes;
        $finMes->modify('last day of this month');

        $resultado = [];
        foreach ($proyecto->getPartidasConActividades() as $bloque) {
            $partida = $bloque['partida'];
            foreach ($bloque['actividades'] as $act) {
                $inicioAct = new \DateTime($act->fecha_inicio);
                $finAct = new \DateTime($act->fecha_fin);

                // Si la actividad intersecta con el mes
                if ($inicioAct <= $finMes && $finAct >= $inicioMes) {
                    $resultado[] = [
                        'actividad' => $act,
                        'partida' => $partida,
                        'dias_en_mes' => self::calcularDiasEnMes($act, $inicioMes, $finMes)
                    ];
                }
            }
        }
        return $resultado;
    }

    /** Calcula qué días del mes la actividad está programada */
    private static function calcularDiasEnMes(Actividad $actividad, \DateTime $inicioMes, \DateTime $finMes): array
    {
        $dias = [];
        $inicio = max(new \DateTime($actividad->fecha_inicio), $inicioMes);
        $fin = min(new \DateTime($actividad->fecha_fin), $finMes);

        $curr = clone $inicio;
        while ($curr <= $fin) {
            $dias[] = $curr->format('j'); // día del mes sin ceros
            $curr->modify('+1 day');
        }
        return $dias;
    }

    // ---------- Respaldo de proyecto ----------

    /** Proyecto del POST/GET validado: solo admin o el cliente propietario */
    private static function proyectoRespaldo(): Proyecto
    {
        redirigir_si_no_auth();
        $p = Proyecto::find(filter_var($_POST['proyecto_id'] ?? $_GET['proyecto'] ?? null, FILTER_VALIDATE_INT));
        // Respaldos: solo el propietario y el admin (un cliente con acceso asignado no revierte la obra entera)
        if (!$p || !puede_eliminar_proyecto($p)) {
            header('Location: /proyectos');
            exit;
        }
        return $p;
    }

    private static function volverRespaldo(Proyecto $p, string $tipo, string $msg): void
    {
        header('Location: /ajustes?proyecto=' . (int)$p->id . '&resp=' . $tipo . '&resp_msg=' . urlencode($msg) . '#respaldo-proyecto');
        exit;
    }

    public static function respaldoCrear()
    {
        $p = self::proyectoRespaldo();
        try {
            $f = \Classes\RespaldoProyecto::crear((int)$p->id, 'manual');
            self::volverRespaldo($p, 'ok', "Respaldo creado: $f");
        } catch (\Throwable $e) {
            self::volverRespaldo($p, 'error', $e->getMessage());
        }
    }

    /** Restaura el indicado, o el más reciente («desde el respaldo anterior») si no se indica */
    public static function respaldoRestaurar()
    {
        $p = self::proyectoRespaldo();
        $archivo = texto_param($_POST['archivo'] ?? '');
        if ($archivo === '') $archivo = \Classes\RespaldoProyecto::anterior((int)$p->id)['archivo'] ?? '';
        try {
            \Classes\RespaldoProyecto::restaurar((int)$p->id, $archivo);
            \Classes\Auditoria::registrar((int)$p->id, 'respaldo', 'aplicado', 'restaurar_respaldo', "Proyecto restaurado desde el respaldo $archivo", null, ['archivo' => $archivo], null, '', ['proyecto', (int)$p->id, $p->nombre]);
            self::volverRespaldo($p, 'ok', "Proyecto restaurado desde $archivo. Antes se guardó un respaldo del estado anterior por si quieres deshacerlo.");
        } catch (\Throwable $e) {
            self::volverRespaldo($p, 'error', $e->getMessage());
        }
    }

    public static function respaldoEliminar()
    {
        $p = self::proyectoRespaldo();
        \Classes\RespaldoProyecto::eliminar((int)$p->id, texto_param($_POST['archivo'] ?? ''));
        self::volverRespaldo($p, 'ok', 'Respaldo eliminado.');
    }

    public static function respaldoAutomatico()
    {
        $p = self::proyectoRespaldo();
        \Classes\RespaldoProyecto::guardarAutomatico((int)$p->id, !empty($_POST['auto']));
        self::volverRespaldo($p, 'ok', 'Respaldo automático actualizado.');
    }

    public static function respaldoDescargar()
    {
        $p = self::proyectoRespaldo();
        $ruta = \Classes\RespaldoProyecto::ruta((int)$p->id, texto_param($_GET['archivo'] ?? ''));
        if (!$ruta) { http_response_code(404); exit; }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="proyecto' . (int)$p->id . '_' . basename($ruta) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    /** Guardar configuración de jornada semanal */
    public static function guardarJornada()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$proyectoId) {
            header('Location: /proyectos');
            exit;
        }

        exigir_gestionar_proyecto(Proyecto::find($proyectoId));

        for ($dia = 1; $dia <= 7; $dia++) {
            $factor = isset($_POST["factor_$dia"]) ? floatval($_POST["factor_$dia"]) : 1.0;
            $factor = max(0, min(1, $factor));

            $existe = null;
            $todas = CalendarioJornada::whereAll('proyecto_id', $proyectoId);
            foreach ($todas as $j) {
                if ((int)$j->dia_semana === $dia) {
                    $existe = $j;
                    break;
                }
            }
            if ($existe) {
                $existe->factor = $factor;
                $existe->guardar();
            } else {
                $nueva = new CalendarioJornada([
                    'proyecto_id' => $proyectoId,
                    'dia_semana' => $dia,
                    'factor' => $factor
                ]);
                $nueva->guardar();
            }
        }

        header('Location: /ajustes?proyecto=' . $proyectoId . '&jornada=ok');
        exit;
    }

    /** Cuántos registros se muestran por página en las listas del proyecto */
    public static function guardarRegistros()
    {
        redirigir_si_no_auth();
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = $proyectoId ? Proyecto::find($proyectoId) : null;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_gestionar_proyecto($proyecto);
        $n = max(1, min(500, (int)($_POST['registros_por_pagina'] ?? 10)));
        $GLOBALS['db']->query("UPDATE proyectos SET registros_por_pagina = $n WHERE id = " . (int)$proyecto->id);
        header('Location: /ajustes?proyecto=' . (int)$proyecto->id . '&registros=ok#registros');
        exit;
    }

    /** Apariencia del proyecto: modo (usuario/claro/oscuro), paleta de acento, nombres y barra superior de herramientas */
    public static function guardarTema()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = $proyectoId ? Proyecto::find($proyectoId) : null;
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_gestionar_proyecto($proyecto);

        // Los dos valores se validan contra la lista blanca de TemaProyecto
        $modo = TemaProyecto::normModo($_POST['tema_modo'] ?? 'usuario');
        $paleta = TemaProyecto::normPaleta($_POST['tema_paleta'] ?? 'naranja');
        $nombres = TemaProyecto::nombresHerramientas($_POST['nombres_herramientas'] ?? 0) ? 1 : 0;   // barra superior: nombre bajo el icono
        $menu = TemaProyecto::normMenu($_POST['menu_superior'] ?? 'clasico');   // clásico, panel o pestañas
        $ayuda = TemaProyecto::ayudasVisibles($_POST['ayuda_burbujas'] ?? 1) ? 1 : 0;   // burbujas de ayuda de la interfaz
        $color = TemaProyecto::colorCaratula($paleta);   // la carátula del portafolio usa el mismo acento
        // Fondo: textura + halo radial. El color del halo vacío significa «usa el acento de la paleta».
        $textura = TemaProyecto::normTextura($_POST['tema_textura'] ?? 'ninguna');
        $texturaInt = TemaProyecto::intensidad($_POST['tema_textura_intensidad'] ?? TemaProyecto::TEXTURA_INTENSIDAD_DEFECTO, TemaProyecto::TEXTURA_INTENSIDAD_DEFECTO);
        $haloColor = !empty($_POST['tema_halo_auto']) ? '' : TemaProyecto::normHex($_POST['tema_halo_color'] ?? '');
        $haloInt = TemaProyecto::intensidad($_POST['tema_halo_intensidad'] ?? TemaProyecto::HALO_INTENSIDAD_DEFECTO, TemaProyecto::HALO_INTENSIDAD_DEFECTO);
        $haloExp = TemaProyecto::expansion($_POST['tema_halo_expansion'] ?? TemaProyecto::HALO_EXPANSION_DEFECTO);

        $sqlAyuda = TemaProyecto::disponibleAyuda() ? ", ayuda_burbujas = $ayuda" : '';   // sin la migración se omite
        $sqlFondo = TemaProyecto::disponibleTexturas() ? ", tema_textura = '$textura', tema_textura_intensidad = $texturaInt, tema_halo_color = '$haloColor', tema_halo_intensidad = $haloInt, tema_halo_expansion = $haloExp" : '';
        $GLOBALS['db']->query("UPDATE proyectos SET tema_modo = '$modo', tema_paleta = '$paleta', nombres_herramientas = $nombres, menu_superior = '$menu'$sqlAyuda$sqlFondo, color = '$color' WHERE id = " . (int)$proyecto->id);
        header('Location: /ajustes?proyecto=' . (int)$proyecto->id . '&tema=ok#tema');
        exit;
    }

    /** Mostrar subactividades por defecto en el programa y las vistas de planeación */
    public static function guardarSubactividades()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = $proyectoId ? Proyecto::find($proyectoId) : null;
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_gestionar_proyecto($proyecto);
        $valor = !empty($_POST['mostrar_subactividades']) ? 1 : 0;
        try {
            $GLOBALS['db']->query('UPDATE proyectos SET mostrar_subactividades = ' . $valor . ' WHERE id = ' . (int)$proyecto->id);
            header('Location: /ajustes?proyecto=' . (int)$proyecto->id . '&subactividades=ok#subactividades');
        } catch (\Throwable $e) {
            header('Location: /ajustes?proyecto=' . (int)$proyecto->id . '&subactividades=error#subactividades');
        }
        exit;
    }

    /**
     * Espacio de almacenamiento del proyecto (MB). Solo el administrador puede
     * cambiarlo (los clientes y visitantes no ven el control).
     * Con 0 (o vacío) se vuelve al límite heredado (el del cliente dueño
     * o el predeterminado de 300 MB).
     */
    public static function guardarAlmacenamiento()
    {
        redirigir_si_no_auth();
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = $proyectoId ? Proyecto::find($proyectoId) : null;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        if (!is_admin()) {
            header('Location: /ajustes?proyecto=' . (int)$proyecto->id . '#datos');
            exit;
        }

        $mb = (int)($_POST['limite_almacenamiento_mb'] ?? 0);
        if ($mb < 0 || $mb > 100000) $mb = 0;   // fuera de rango: se deja el límite heredado
        \Classes\Almacenamiento::fijarLimiteProyecto((int)$proyecto->id, $mb);

        header('Location: /ajustes?proyecto=' . (int)$proyecto->id . '&alm=' . ($mb > 0 ? 'ok' : 'auto') . '#datos');
        exit;
    }

    /** Agregar día festivo / paro */
    public static function agregarFestivo()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        $fecha = $_POST['fecha'] ?? '';
        $motivo = trim($_POST['motivo'] ?? '');
        $tipo = $_POST['tipo'] ?? 'festivo';

        exigir_gestionar_proyecto(Proyecto::find($proyectoId));
        if ($proyectoId && $fecha && $motivo) {
            $existe = CalendarioFestivo::whereAll('proyecto_id', $proyectoId);
            $duplicado = false;
            foreach ($existe as $f) {
                if ($f->fecha === $fecha) {
                    $duplicado = true;
                    break;
                }
            }
            if (!$duplicado) {
                $festivo = new CalendarioFestivo([
                    'proyecto_id' => $proyectoId,
                    'fecha' => $fecha,
                    'motivo' => $motivo,
                    'tipo' => $tipo
                ]);
                $festivo->guardar();
            }
        }
        header('Location: /ajustes?proyecto=' . $proyectoId . '&festivo=ok');
        exit;
    }

    public static function eliminarFestivo()
    {
        redirigir_si_no_auth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $festivo = CalendarioFestivo::find($id);
            // El festivo debe pertenecer al proyecto sobre el que se tiene permiso
            if ($festivo) {
                exigir_gestionar_proyecto(Proyecto::find($festivo->proyecto_id));
                $festivo->eliminar();
            }
        }
        header('Location: /ajustes?proyecto=' . $proyectoId);
        exit;
    }

    /** Página de exportación (separada) */
    /** Qué herramienta respalda cada formato del Centro de Exportación (para saber si el usuario puede generarlo) */
    public static function herramientaExportacion(string $formato, string $tipo = ''): ?string
    {
        return match ($formato) {
            'pdf', 'xml', 'pod' => 'programa',
            'lookahead' => 'lookahead',
            'vplan' => 'vplan',
            'lastplanner' => 'lastplanner',
            'curva' => 'curvas',
            'msr' => 'msr',
            'lineatiempo' => 'lineatiempo',
            'presupuesto' => 'conceptos',
            'rfi' => 'rfi',
            'submittal' => 'submittals',
            'reportes' => ([
                'reporte' => 'reportes', 'bitacora' => 'bitacora', 'minuta' => 'minutas',
                'submittal' => 'submittals', 'material' => 'materiales', 'evidencia' => 'evidencias',
            ][$tipo] ?? 'reportes'),
            default => null,
        };
    }

    public static function exportarIndex(Router $router)
    {
        redirigir_si_no_auth();
        if (!puede_descargar()) {
            header('Location: /proyectos');
            exit;
        }
        $proyectoId = filter_var($_GET['proyecto'] ?? null, FILTER_VALIDATE_INT);
        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }

        exigir_ver_proyecto($proyecto);
        $router->render('exportar/index', [
            'titulo' => 'Exportar - ' . $proyecto->nombre,
            'proyecto' => $proyecto,
            'lunes' => \Controllers\LookaheadController::lunesDe(''),
            'curvas' => \Model\Curva::deProyecto((int)$proyecto->id),
            'laminasVplan' => \Model\VplanLamina::deProyecto((int)$proyecto->id),
            'paquetesVplan' => \Model\VplanPaquete::deProyecto((int)$proyecto->id),
            'vplanElegido' => (int)($_GET['vplan'] ?? 0),
            'departamentosBitacora' => array_map(fn($d) => $d->nombre, \Model\BitacoraDepartamento::deProyecto((int)$proyecto->id)),
            'especialidades' => array_map(fn($e) => $e->nombre, \Model\Especialidad::deProyecto((int)$proyecto->id)),
            'submittals' => \Model\Submittal::deProyecto((int)$proyecto->id),
            'rfis' => \Classes\Rfi::disponible() ? \Classes\Rfi::lista((int)$proyecto->id) : [],
            'actividadesEvidencia' => array_merge(...array_values(array_map(fn($b) => array_map(fn($a) => [(int)$a->id, $a->codigo . ' · ' . $a->nombre], $b['actividades']), $proyecto->getPartidasConActividades()))) ?: [],
            'mesesLt' => LineatiempoController::mesesConActividades($proyecto)
        ]);
    }

    /**
     * Exportar todo: un ZIP con todos los entregables del proyecto, excepto Last Planner.
     * Lo que no tenga datos (sin MSR, curvas o láminas) se omite y queda anotado en LEEME.txt.
     */
    public static function exportarTodo()
    {
        redirigir_si_no_auth();
        if (!puede_descargar() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }
        $proyecto = Proyecto::find(filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT));
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_ver_proyecto($proyecto);
        nomenclatura_desde_post($_POST);
        // qué entregables lleva el ZIP (sin la lista se incluyen todos)
        $incluir = isset($_POST['incluir']) ? array_map('strval', (array)$_POST['incluir']) : (isset($_POST['elegir']) ? [] : null);
        $toolDe = ['prog_pdf' => 'programa', 'prog_xml' => 'programa', 'prog_pod' => 'programa',
            'presupuesto' => 'conceptos',
            'la_pdf' => 'lookahead', 'la_xlsx' => 'lookahead', 'msr_pdf' => 'msr', 'msr_xlsx' => 'msr',
            'curvas' => 'curvas', 'vplan' => 'vplan'];
        $quiere = fn(string $k) => ($incluir === null || in_array($k, $incluir, true)) && herramienta_permitida($toolDe[$k] ?? 'programa');
        if ($incluir === []) {
            header('Location: /exportar?proyecto=' . $proyecto->id . '&error=' . urlencode('Marca al menos un formato para exportar'));
            exit;
        }
        if (!class_exists('ZipArchive')) {
            header('Location: /exportar?proyecto=' . $proyecto->id . '&error=' . urlencode('El servidor no tiene habilitada la extensión ZIP de PHP'));
            exit;
        }
        @set_time_limit(600);
        @ini_set('memory_limit', '1024M');

        $nombreBase = trim($_POST['nombre_base'] ?? '') ?: 'Exportacion';
        $lunes = \Controllers\LookaheadController::lunesDe('')->format('Y-m-d');

        $zipRuta = __DIR__ . '/../public/exportaciones/' . generar_nombre_archivo($nombreBase, 'zip', $proyecto);
        if (!is_dir(dirname($zipRuta))) mkdir(dirname($zipRuta), 0775, true);
        $zip = new \ZipArchive();
        $zip->open($zipRuta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $incluidos = [];
        $omitidos = [];
        $temporales = [];
        // Genera un archivo con el exportador y lo agrega al ZIP en la carpeta indicada
        $agregar = function (string $carpeta, string $etiqueta, callable $crear) use ($zip, &$incluidos, &$omitidos, &$temporales) {
            try {
                $ruta = $crear();
                $zip->addFile($ruta, $carpeta . '/' . basename($ruta));
                $temporales[] = $ruta;
                $incluidos[] = "$carpeta/" . basename($ruta) . "  ($etiqueta)";
            } catch (\Throwable $e) {
                $omitidos[] = "$etiqueta: " . $e->getMessage();
            }
        };

        if ($quiere('prog_pdf')) $agregar('01_Programa', 'Programa PDF', fn() => (new \Classes\Exportadores\PdfExporter($proyecto))->generar("Programa"));
        if ($quiere('prog_xml')) $agregar('01_Programa', 'Programa XML (MS Project)', fn() => (new \Classes\Exportadores\MspdiXmlExporter($proyecto))->generar("Programa"));
        if ($quiere('prog_pod')) $agregar('01_Programa', 'Programa POD (ProjectLibre)', fn() => (new \Classes\Exportadores\PodExporter($proyecto))->generar("Programa"));
        if ($quiere('presupuesto') && \Classes\Conceptos::disponible()) {
            $datos = \Classes\Conceptos::presupuesto((int)$proyecto->id);
            if ($datos['grupos'] || $datos['sinPartida']) {
                $agregar('06_Presupuesto', 'Catálogo de conceptos PDF', fn() => (new \Classes\Exportadores\PresupuestoPdfExporter($proyecto))->setDatos($datos)->generar("Presupuesto"));
            } else {
                $omitidos[] = 'Presupuesto: el catálogo está vacío';
            }
        }
        if ($quiere('la_pdf')) $agregar('02_4WLA', '4WLA PDF', fn() => (new \Classes\Exportadores\LookaheadPdfExporter($proyecto))->setFechaReferencia($lunes)->generar("4WLA"));
        if ($quiere('la_xlsx')) $agregar('02_4WLA', '4WLA Excel', fn() => (new \Classes\Exportadores\LookaheadSheetExporter($proyecto))->setFormato('xlsx')->setFechaReferencia($lunes)->generar("4WLA"));

        if ($quiere('msr_pdf') || $quiere('msr_xlsx')) {
            $hayMsr = false;
            foreach (\Model\Msr::deProyecto((int)$proyecto->id) as $msr) {
                if (!$msr->n_renglones) continue;
                $hayMsr = true;
                $base = 'MSR_' . slug_archivo($msr->nombre);
                if ($quiere('msr_pdf')) $agregar('03_MSR', "MSR PDF ($msr->nombre)", fn() => (new \Classes\Exportadores\MsrPdfExporter($proyecto))->setMsr($msr)->generar($base));
                if ($quiere('msr_xlsx')) $agregar('03_MSR', "MSR Excel ($msr->nombre)", fn() => (new \Classes\Exportadores\MsrSheetExporter($proyecto))->setMsr($msr)->setFormato('xlsx')->generar($base));
            }
            if (!$hayMsr) {
                $omitidos[] = 'MSR: el proyecto no tiene renglones';
            }
        }

        $curvas = $quiere('curvas') ? \Model\Curva::deProyecto((int)$proyecto->id) : [];
        if (!$quiere('curvas')) {
            // no se pidieron
        } elseif ($curvas) {
            $agregar('04_Curvas', 'Gráfica maestra PDF', fn() => (new \Classes\Exportadores\CurvaPdfExporter($proyecto))->setCurva(null)->generar("Curva"));
            foreach ($curvas as $c) {
                $agregar('04_Curvas', 'Curva ' . $c->nombre, fn() => (new \Classes\Exportadores\CurvaPdfExporter($proyecto))->setCurva($c)->generar('Curva'));
            }
        } else {
            $omitidos[] = 'Curvas de productividad: el proyecto no tiene curvas';
        }

        if (!$quiere('vplan')) {
            // no se pidió
        } elseif (\Model\VplanLamina::visiblesDeProyecto((int)$proyecto->id)) {
            $agregar('05_vPlan', 'vPlan PDF (todas las láminas)', fn() => (new \Classes\Exportadores\VplanPdfExporter($proyecto))->setLamina('todas')->generar("vPlan"));
        } else {
            $omitidos[] = 'vPlan: el proyecto no tiene láminas visibles';
        }

        $leeme = "Exportación completa de {$proyecto->nombre} ({$proyecto->codigo})\r\nGenerada: " . date('d/m/Y H:i') . "\r\n"
            . "No incluye Last Planner (se exporta aparte).\r\n\r\nArchivos incluidos:\r\n- " . implode("\r\n- ", $incluidos ?: ['(ninguno)'])
            . ($omitidos ? "\r\n\r\nOmitidos:\r\n- " . implode("\r\n- ", $omitidos) : '') . "\r\n";
        $zip->addFromString('LEEME.txt', $leeme);
        $zip->close();
        foreach ($temporales as $t) @unlink($t);

        if (!$incluidos || !is_file($zipRuta)) {
            @unlink($zipRuta);
            header('Location: /exportar?proyecto=' . $proyecto->id . '&error=' . urlencode('No se pudo generar ningún archivo'));
            exit;
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipRuta) . '"');
        header('Content-Length: ' . filesize($zipRuta));
        readfile($zipRuta);
        @unlink($zipRuta);   // sin historial: no se conserva en el servidor
        exit;
    }

    /** Genera varios archivos y los entrega en un ZIP con el nombre de la nomenclatura elegida (devuelve su ruta) */
    private static function empaquetar(array $lista, Proyecto $proyecto, string $nombreBase): string
    {
        if (!class_exists('ZipArchive')) throw new \Exception('El servidor no tiene habilitada la extensión ZIP de PHP');
        @set_time_limit(600);
        $zipRuta = __DIR__ . '/../public/exportaciones/' . generar_nombre_archivo($nombreBase, 'zip', $proyecto);
        if (!is_dir(dirname($zipRuta))) mkdir(dirname($zipRuta), 0775, true);
        $zip = new \ZipArchive();
        $zip->open($zipRuta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $temporales = []; $usados = [];
        try {
            foreach ($lista as [$exp, $base]) {
                $ruta = $exp->generar($base);
                $temporales[] = $ruta;
                $nombre = basename($ruta);
                for ($i = 2; isset($usados[$nombre]); $i++) $nombre = preg_replace('/(\.[^.]+)$/', "-$i$1", basename($ruta));
                $usados[$nombre] = true;
                $zip->addFile($ruta, $nombre);
            }
            $zip->close();
        } catch (\Throwable $e) {
            @unlink($zipRuta);
            throw $e;
        } finally {
            foreach ($temporales as $t) @unlink($t);
        }
        return $zipRuta;
    }

    /** Exportar programa */
    public static function exportar()
    {
        redirigir_si_no_auth();
        if (!puede_descargar()) {
            header('Location: /proyectos');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /proyectos');
            exit;
        }

        nomenclatura_desde_post($_POST);
        $proyectoId = filter_var($_POST['proyecto_id'] ?? null, FILTER_VALIDATE_INT);
        $formato = $_POST['formato'] ?? '';
        $nombreBase = trim($_POST['nombre_base'] ?? 'Programa_Obra');

        $proyecto = Proyecto::find($proyectoId);
        if (!$proyecto) {
            header('Location: /proyectos');
            exit;
        }
        exigir_ver_proyecto($proyecto);
        // El formato debe pertenecer a una herramienta que el usuario vea (colaboradores y visitantes)
        $tool = self::herramientaExportacion((string)$formato, texto_param($_POST['tipo'] ?? ''));
        if ($tool !== null && !herramienta_permitida($tool)) {
            header('Location: /exportar?proyecto=' . $proyecto->id . '&error=' . urlencode('No tienes acceso a esa herramienta, así que no puedes exportar ese formato'));
            exit;
        }

        $varios = [];   // si se eligen varios elementos se entregan en un ZIP: [[exportador, nombre base], …]
        try {
            switch ($formato) {
                case 'pdf':
                    $exporter = new \Classes\Exportadores\PdfExporter($proyecto);
                    break;
                case 'lookahead':
                    $salida = $_POST['salida'] ?? 'pdf';
                    if (in_array($salida, ['xlsx', 'ods'], true)) {
                        $exporter = (new \Classes\Exportadores\LookaheadSheetExporter($proyecto))
                            ->setFormato($salida)
                            ->setFechaReferencia($_POST['fecha_ref'] ?? '');
                    } else {
                        $exporter = (new \Classes\Exportadores\LookaheadPdfExporter($proyecto))
                            ->setFechaReferencia($_POST['fecha_ref'] ?? '');
                    }
                    break;
                case 'vplan':
                    $exporter = (new \Classes\Exportadores\VplanPdfExporter($proyecto))->setLamina($_POST['lamina_id'] ?? 'todas')->setPaquete((int)($_POST['paquete_id'] ?? 0));
                    if (!empty($_POST['lamina_ids'])) {
                        $propias = array_map(fn($l) => (int)$l->id, \Model\VplanLamina::deProyecto((int)$proyecto->id));
                        $exporter->setLaminas(array_intersect(array_map('intval', (array)$_POST['lamina_ids']), $propias));
                    }
                    break;
                case 'lastplanner':
                    $exporter = (new \Classes\Exportadores\LastPlannerPdfExporter($proyecto))->setOpciones(
                        $_POST['partida_id'] ?? 'todas', (int)($_POST['columnas'] ?? 2)
                    )->setFiltros(
                        texto_param($_POST['periodo'] ?? ''), texto_param($_POST['desde'] ?? ''), texto_param($_POST['hasta'] ?? ''),
                        texto_param($_POST['mes'] ?? ''), !empty($_POST['restriccion'])
                    );
                    break;
                case 'curva':
                    $ids = array_values(array_unique(array_map('strval', (array)($_POST['curva_ids'] ?? [$_POST['curva_id'] ?? 'maestra']))));
                    if (!$ids) $ids = ['maestra'];
                    $salida = $_POST['salida'] ?? 'pdf';
                    $lista = [];
                    foreach ($ids as $cid) {
                        $curva = null;
                        if ($cid !== 'maestra') {
                            $curva = \Model\Curva::find(filter_var($cid, FILTER_VALIDATE_INT));
                            if (!$curva || (int)$curva->proyecto_id !== (int)$proyecto->id) throw new \Exception('Curva no encontrada');
                        }
                        $lista[] = [in_array($salida, ['xlsx', 'ods'], true)
                            ? (new \Classes\Exportadores\CurvaSheetExporter($proyecto))->setCurva($curva)->setFormato($salida)
                            : (new \Classes\Exportadores\CurvaPdfExporter($proyecto))->setCurva($curva), $nombreBase];
                    }
                    if (count($lista) > 1) $varios = $lista; else $exporter = $lista[0][0];
                    break;
                case 'msr':
                    $salida = $_POST['salida'] ?? 'pdf';
                    $lista = [];
                    foreach (array_values(array_unique((array)($_POST['msr_ids'] ?? [$_POST['msr_id'] ?? null]))) as $mid) {
                        $msr = \Model\Msr::deProyectoPorId((int)$proyecto->id, $mid);
                        if (!$msr) throw new \Exception('MSR no encontrado');
                        $lista[] = [in_array($salida, ['xlsx', 'ods'], true)
                            ? (new \Classes\Exportadores\MsrSheetExporter($proyecto))->setMsr($msr)->setFormato($salida)
                            : (new \Classes\Exportadores\MsrPdfExporter($proyecto))->setMsr($msr), $nombreBase . '-' . $msr->nombre];
                    }
                    if (count($lista) > 1) $varios = $lista; else $exporter = $lista[0][0];
                    break;
                case 'lineatiempo':
                    $salida = $_POST['salida'] ?? 'pdf';
                    $estadosLt = array_values(array_intersect((array)($_POST['estado'] ?? []), ['no_iniciada', 'en_proceso', 'completada']));
                    if (!$estadosLt) $estadosLt = ['no_iniciada', 'en_proceso', 'completada'];   // sin marcar ninguno: todos
                    $filtrosLt = [
                        'partida' => $_POST['partida_id'] ?? null,
                        'estados' => $estadosLt,
                        'restriccion' => !empty($_POST['restriccion']),
                        'icono' => !empty($_POST['icono']),
                    ];
                    $nuevoExportador = function (array $d) use ($proyecto, $salida) {
                        $e = in_array($salida, ['ods', 'xlsx'], true)
                            ? (new \Classes\Exportadores\LineatiempoSheetExporter($proyecto))->setFormato($salida)
                            : new \Classes\Exportadores\LineatiempoPdfExporter($proyecto);
                        return $e->setDatos($d['lt'], $d['filtros'], $d['partidasSel']);
                    };
                    // Meses marcados: un archivo por mes (con varios, en un ZIP); sin marcar ninguno,
                    // el rango completo del proyecto con los filtros elegidos
                    $meses = array_values(array_intersect(array_map('strval', (array)($_POST['mes'] ?? [])),
                        array_keys(LineatiempoController::mesesConActividades($proyecto))));
                    if ($meses) {
                        $lista = [];
                        foreach ($meses as $m) {
                            $d = LineatiempoController::datos($proyecto, $filtrosLt + ['mes' => $m]);
                            if (!$d['lt']['actividades']) continue;   // mes vacío con estos filtros: se omite
                            $lista[] = [$nuevoExportador($d), $nombreBase . '-' . $m];
                        }
                        if (!$lista) throw new \Exception('No hay actividades con esos filtros en los meses marcados');
                        if (count($lista) > 1) $varios = $lista;
                        else { $exporter = $lista[0][0]; $nombreBase = $lista[0][1]; }   // un mes: el nombre lo lleva
                    } else {
                        $exporter = $nuevoExportador(LineatiempoController::datos($proyecto, $filtrosLt));
                    }
                    break;
                case 'submittal':
                    if (!puede_gestionar_proyecto($proyecto)) throw new \Exception('No autorizado');
                    $sub = \Model\Submittal::find(filter_var($_POST['submittal_id'] ?? null, FILTER_VALIDATE_INT));
                    if (!$sub || (int)$sub->proyecto_id !== (int)$proyecto->id) throw new \Exception('Submittal no encontrado');
                    $exporter = (new \Classes\Exportadores\SubmittalPdfExporter($proyecto))->setSubmittal($sub);
                    break;
                case 'rfi':
                    $lista = [];
                    foreach (array_values(array_unique(array_map('intval', (array)($_POST['rfi_ids'] ?? [])))) as $rid) {
                        $rf = \Classes\Rfi::obtener((int)$proyecto->id, $rid);
                        if (!$rf) continue;
                        $lista[] = [(new \Classes\Exportadores\RfiPdfExporter($proyecto))->setRfi($rf, \Classes\Rfi::laminas($rid)), $nombreBase . '-' . $rf['numero']];
                    }
                    if (!$lista) throw new \Exception('No hay RFI para exportar');
                    if (count($lista) > 1) $varios = $lista;
                    else { $exporter = $lista[0][0]; $nombreBase = $lista[0][1]; }
                    break;
                case 'reportes':
                    $f = $_POST;
                    $f['submittal_ids'] = (array)($_POST['submittal_ids'] ?? []);
                    $exporter = (new \Classes\Exportadores\ReportesPdfExporter($proyecto))->setOpciones(texto_param($_POST['tipo'] ?? ''), $f);
                    break;
                case 'presupuesto':
                    if (!\Classes\Conceptos::disponible()) throw new \Exception('Falta aplicar la migración del catálogo de conceptos');
                    $salida = in_array($_POST['salida'] ?? 'pdf', ['xlsx', 'ods'], true) ? $_POST['salida'] : 'pdf';
                    $datos = \Classes\Conceptos::presupuesto((int)$proyecto->id);
                    if (!$datos['grupos'] && !$datos['sinPartida']) throw new \Exception('El catálogo de conceptos está vacío');
                    $exporter = $salida === 'pdf'
                        ? (new \Classes\Exportadores\PresupuestoPdfExporter($proyecto))->setDatos($datos)
                        : (new \Classes\Exportadores\PresupuestoSheetExporter($proyecto))->setFormato($salida)->setDatos($datos);
                    break;
                case 'xml':
                    $exporter = new \Classes\Exportadores\MspdiXmlExporter($proyecto);
                    break;
                case 'pod':
                    $exporter = new \Classes\Exportadores\PodExporter($proyecto);
                    break;
                default:
                    throw new \Exception('Formato no soportado');
            }

            if ($varios) {
                $ruta = self::empaquetar($varios, $proyecto, $nombreBase);
            } else {
                $ruta = $exporter->generar($nombreBase);
            }
            $nombreArchivo = basename($ruta);

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Content-Length: ' . filesize($ruta));
            readfile($ruta);
            // Sin historial: el archivo no se conserva en el servidor (public/ es accesible por URL)
            @unlink($ruta);
            exit;
        } catch (\Exception $e) {
            header('Location: /exportar?proyecto=' . $proyectoId . '&error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}
