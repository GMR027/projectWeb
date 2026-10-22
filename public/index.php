<?php

require_once __DIR__ . '/../includes/app.php';

use MVC\Router;
use Controllers\AuthController;
use Controllers\CarpetaController;
use Controllers\EstampaController;
use Controllers\ComparadorController;
use Controllers\TraductorController;
use Controllers\EscritorioApiController;
use Controllers\EnsambladorController;
use Controllers\EnsambleController;
use Controllers\AnalisisMaterialesController;
use Controllers\CentroIAController;
use Controllers\AppIAController;
use Controllers\ConsultasIAController;
use Controllers\InconsistenciasController;
use Controllers\PlantillasController;
use Controllers\PunchController;
use Controllers\SeguridadController;
use Controllers\MapaAvanceController;
use Controllers\NormativaController;
use Controllers\InformesIAController;
use Controllers\ProgramadorController;
use Controllers\DashboardController;
use Controllers\LandingController;
use Controllers\ContactoController;
use Controllers\SoporteController;
use Controllers\BandejaController;
use Controllers\ProyectoController;
use Controllers\RevisionController;
use Controllers\ProgramaController;
use Controllers\AjustesController;
use Controllers\SistemaController;
use Controllers\CalendarioController;
use Controllers\LookaheadController;
use Controllers\MaterialController;
use Controllers\MaterialesInteligentesController;
use Controllers\EspecialidadController;
use Controllers\DirectorioController;
use Controllers\AccionesController;
use Controllers\PlanificadorController;
use Controllers\SubmittalController;
use Controllers\CambioController;
use Controllers\LineaBaseController;
use Controllers\MsrController;
use Controllers\CurvaController;
use Controllers\LastPlannerController;
use Controllers\VplanController;
use Controllers\VplanTablaController;
use Controllers\VplanNotaController;
use Controllers\EditorController;
use Controllers\SimboloController;
use Controllers\RedactorController;
use Controllers\SheetflowController;
use Controllers\IdiomaController;
use Controllers\DependenciasController;
use Controllers\EvidenciaController;
use Controllers\ReporteController;
use Controllers\BitacoraController;
use Controllers\View360Controller;
use Controllers\ManualController;
use Controllers\ArchivoController;
use Controllers\VisorController;
use Controllers\MinutaController;
use Controllers\DatosObraController;

$router = new Router();

// ========== AUTH ==========
$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/usuarios', [AuthController::class, 'listar']);
$router->get('/usuarios/crear', [AuthController::class, 'crear']);
$router->post('/usuarios/crear', [AuthController::class, 'crear']);
$router->get('/usuarios/editar', [AuthController::class, 'editar']);
$router->post('/usuarios/editar', [AuthController::class, 'editar']);
$router->post('/usuarios/eliminar', [AuthController::class, 'eliminar']);
$router->post('/cuenta/password', [AuthController::class, 'cambiarPassword']);

// ========== DASHBOARD ==========
$router->get('/', [LandingController::class, 'index']);
$router->get('/inicio', [LandingController::class, 'inicio']);
$router->get('/demo', [LandingController::class, 'demo']);
$router->post('/contacto', [ContactoController::class, 'enviar']);
$router->get('/terminos', [LandingController::class, 'terminos']);
$router->get('/servicios', [LandingController::class, 'servicios']);

// ========== SOPORTE (cliente) y BANDEJA DE ENTRADA (admin) ==========
$router->get('/soporte', [SoporteController::class, 'index']);
$router->post('/soporte/crear', [SoporteController::class, 'crear']);
$router->get('/soporte/editar', [SoporteController::class, 'editar']);
$router->post('/soporte/editar', [SoporteController::class, 'editar']);
$router->post('/soporte/eliminar', [SoporteController::class, 'eliminar']);
$router->post('/api/soporte/vistas', [SoporteController::class, 'vistas']);
$router->get('/bandeja', [BandejaController::class, 'index']);
$router->get('/bandeja/ver', [BandejaController::class, 'ver']);
$router->post('/bandeja/accion', [BandejaController::class, 'accion']);
$router->post('/api/bandeja/leer', [BandejaController::class, 'leer']);
$router->post('/api/bandeja/respuesta/guardar', [BandejaController::class, 'respuestaGuardar']);
$router->post('/api/bandeja/respuesta/eliminar', [BandejaController::class, 'respuestaEliminar']);
$router->get('/manual', [ManualController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);

// ========== PROYECTOS ==========
$router->get('/proyectos', [ProyectoController::class, 'listar']);
$router->get('/proyectos/crear', [ProyectoController::class, 'crear']);
$router->post('/proyectos/crear', [ProyectoController::class, 'crear']);
$router->get('/proyectos/editar', [ProyectoController::class, 'editar']);
$router->post('/proyectos/editar', [ProyectoController::class, 'editar']);
$router->get('/proyectos/ver', [ProyectoController::class, 'ver']);
$router->post('/proyectos/eliminar', [ProyectoController::class, 'eliminar']);

// ========== PROGRAMA DE OBRA ==========
$router->get('/programa', [ProgramaController::class, 'index']);
$router->get('/dependencias', [DependenciasController::class, 'index']);
$router->get('/api/dependencias/datos', [DependenciasController::class, 'datos']);
$router->post('/api/dependencias/guardar', [DependenciasController::class, 'guardar']);
$router->post('/api/dependencias/eliminar', [DependenciasController::class, 'eliminar']);
$router->post('/api/dependencias/fijar', [DependenciasController::class, 'fijar']);
$router->post('/api/dependencias/modo', [DependenciasController::class, 'modo']);
$router->post('/api/dependencias/previa', [DependenciasController::class, 'previa']);
$router->post('/api/dependencias/aplicar', [DependenciasController::class, 'aplicar']);
$router->post('/api/dependencias/deshacer', [DependenciasController::class, 'deshacer']);
$router->get('/programa/importar', [ProgramaController::class, 'importar']);
$router->post('/programa/importar', [ProgramaController::class, 'importar']);
$router->post('/programa/importar/confirmar', [ProgramaController::class, 'confirmarImportacion']);
$router->post('/programa/importar/cancelar', [ProgramaController::class, 'cancelarImportacion']);
$router->post('/api/actividad/actualizar-fecha', [ProgramaController::class, 'actualizarFechaAjax']);
$router->post('/api/actividad/actualizar-progreso', [ProgramaController::class, 'actualizarProgresoAjax']);
// Partidas
$router->get('/programa/ordenar', [ProgramaController::class, 'ordenar']);
$router->post('/programa/ordenar', [ProgramaController::class, 'guardarOrden']);
$router->get('/programa/ordenar/detalle', [ProgramaController::class, 'detalleOrden']);
$router->post('/programa/ordenar/deshacer', [ProgramaController::class, 'deshacerOrden']);
$router->get('/programa/partidas/crear', [ProgramaController::class, 'crearPartida']);
$router->post('/programa/partidas/crear', [ProgramaController::class, 'crearPartida']);
$router->get('/programa/partidas/editar', [ProgramaController::class, 'editarPartida']);
$router->post('/programa/partidas/editar', [ProgramaController::class, 'editarPartida']);
$router->post('/programa/partidas/eliminar', [ProgramaController::class, 'eliminarPartida']);
$router->post('/programa/eliminar-varias', [ProgramaController::class, 'eliminarVarias']);
// Actividades
$router->get('/programa/actividades/crear', [ProgramaController::class, 'crearActividad']);
$router->post('/programa/actividades/crear', [ProgramaController::class, 'crearActividad']);
$router->get('/programa/actividades/editar', [ProgramaController::class, 'editarActividad']);
$router->post('/programa/actividades/editar', [ProgramaController::class, 'editarActividad']);
$router->post('/programa/actividades/eliminar', [ProgramaController::class, 'eliminarActividad']);
$router->post('/programa/actividades/rapida', [ProgramaController::class, 'actividadRapida']);
$router->post('/programa/actividades/mover', [ProgramaController::class, 'moverActividad']);
// Restricciones
$router->get('/programa/restricciones/crear', [ProgramaController::class, 'crearRestriccion']);
$router->post('/programa/restricciones/crear', [ProgramaController::class, 'crearRestriccion']);
$router->get('/programa/restricciones/editar', [ProgramaController::class, 'editarRestriccion']);
$router->post('/programa/restricciones/editar', [ProgramaController::class, 'editarRestriccion']);
$router->post('/programa/restricciones/eliminar', [ProgramaController::class, 'eliminarRestriccion']);
$router->post('/programa/restricciones/resolver', [ProgramaController::class, 'resolverRestriccion']);

// ========== LOOKAHEAD 4WLA ==========
$router->get('/lookahead', [LookaheadController::class, 'index']);
$router->post('/api/actividad/pta', [LookaheadController::class, 'togglePta']);
$router->post('/api/lookahead/config', [LookaheadController::class, 'guardarConfig']);
$router->post('/api/lookahead/limitacion', [LookaheadController::class, 'guardarLimitacion']);

// ========== MATERIALES / SUBMITTALS / MSR ==========
$router->get('/materiales', [MaterialController::class, 'index']);
$router->get('/materiales/crear', [MaterialController::class, 'crear']);
$router->post('/materiales/crear', [MaterialController::class, 'crear']);
$router->get('/materiales/editar', [MaterialController::class, 'editar']);
$router->post('/materiales/editar', [MaterialController::class, 'editar']);
$router->post('/materiales/eliminar', [MaterialController::class, 'eliminar']);
$router->get('/materiales/ficha', [MaterialController::class, 'ficha']);
$router->get('/planificador', [PlanificadorController::class, 'index']);
$router->get('/acciones', [AccionesController::class, 'index']);
$router->get('/directorio', [DirectorioController::class, 'index']);
$router->post('/directorio/departamento', [DirectorioController::class, 'departamentoGuardar']);
$router->post('/directorio/departamento/eliminar', [DirectorioController::class, 'departamentoEliminar']);
$router->post('/directorio/categoria', [DirectorioController::class, 'categoriaGuardar']);
$router->post('/directorio/categoria/eliminar', [DirectorioController::class, 'categoriaEliminar']);
$router->get('/especialidades', [EspecialidadController::class, 'index']);
$router->post('/especialidades/guardar', [EspecialidadController::class, 'guardar']);
$router->post('/especialidades/eliminar', [EspecialidadController::class, 'eliminar']);
$router->get('/submittals', [SubmittalController::class, 'index']);
$router->get('/submittals/ver', [SubmittalController::class, 'ver']);
$router->get('/submittals/crear', [SubmittalController::class, 'crear']);
$router->post('/submittals/crear', [SubmittalController::class, 'crear']);
$router->get('/submittals/editar', [SubmittalController::class, 'editar']);
$router->post('/submittals/editar', [SubmittalController::class, 'editar']);
$router->post('/api/materiales/estado', [MaterialController::class, 'estado']);
$router->post('/api/submittals/estatus', [SubmittalController::class, 'estatus']);
$router->post('/submittals/eliminar', [SubmittalController::class, 'eliminar']);

// ========== ÓRDENES DE CAMBIO Y LÍNEAS BASE ==========
$router->get('/cambios', [CambioController::class, 'index']);
$router->get('/cambios/ver', [CambioController::class, 'ver']);
$router->get('/cambios/crear', [CambioController::class, 'crear']);
$router->post('/cambios/crear', [CambioController::class, 'crear']);
$router->get('/cambios/editar', [CambioController::class, 'editar']);
$router->post('/cambios/editar', [CambioController::class, 'editar']);
$router->post('/cambios/eliminar', [CambioController::class, 'eliminar']);
$router->post('/cambios/partida', [CambioController::class, 'guardarPartida']);
$router->post('/cambios/partida/eliminar', [CambioController::class, 'eliminarPartida']);
$router->get('/cambios/cruce', [CambioController::class, 'cruce']);
$router->post('/api/cambios/estado', [CambioController::class, 'estadoOrden']);
$router->post('/api/cambios/partida-estado', [CambioController::class, 'estadoPartida']);
$router->get('/lineas-base', [LineaBaseController::class, 'index']);
$router->post('/lineas-base/capturar', [LineaBaseController::class, 'capturar']);
$router->post('/lineas-base/eliminar', [LineaBaseController::class, 'eliminar']);
$router->get('/lineas-base/comparar', [LineaBaseController::class, 'comparar']);
$router->get('/msr', [MsrController::class, 'index']);
$router->get('/msr/ver', [MsrController::class, 'ver']);
$router->get('/msr/crear', [MsrController::class, 'crear']);
$router->post('/msr/crear', [MsrController::class, 'crear']);
$router->get('/msr/editar', [MsrController::class, 'editar']);
$router->post('/msr/editar', [MsrController::class, 'editar']);
$router->post('/msr/eliminar', [MsrController::class, 'eliminar']);
$router->post('/msr/umbral', [MsrController::class, 'umbral']);
$router->get('/msr/importar', [MsrController::class, 'importar']);
$router->post('/msr/importar', [MsrController::class, 'importar']);
$router->post('/msr/importar/confirmar', [MsrController::class, 'confirmarImportacion']);
$router->post('/msr/importar/cancelar', [MsrController::class, 'cancelarImportacion']);
$router->post('/api/msr/status', [MsrController::class, 'status']);
$router->post('/api/msr/campo', [MsrController::class, 'campo']);
$router->post('/api/msr/orden', [MsrController::class, 'orden']);
$router->post('/api/msr/columna', [MsrController::class, 'columna']);
$router->post('/api/msr/mover', [MsrController::class, 'mover']);
$router->post('/api/msr/partida', [MsrController::class, 'partida']);

// ========== VPLAN ==========
$router->get('/vplan', [VplanController::class, 'index']);
$router->get('/vplan/paquete', [VplanController::class, 'paquete']);
$router->post('/vplan/paquete/crear', [VplanController::class, 'paqueteCrear']);
$router->post('/vplan/paquete/renombrar', [VplanController::class, 'paqueteRenombrar']);
$router->post('/vplan/paquete/eliminar', [VplanController::class, 'paqueteEliminar']);
$router->post('/vplan/paquete/grupo-visible', [VplanController::class, 'paqueteGrupoVisible']);
$router->post('/api/vplan/lamina/ajustar', [VplanController::class, 'ajustarLamina']);
$router->post('/vplan/lamina/mover', [VplanController::class, 'moverLamina']);
$router->post('/vplan/lamina/duplicar', [VplanController::class, 'duplicarLamina']);
$router->get('/vplan/crear', [VplanController::class, 'crear']);
$router->post('/vplan/crear', [VplanController::class, 'crear']);
$router->get('/vplan/editar', [VplanController::class, 'editar']);
$router->post('/vplan/editar', [VplanController::class, 'editar']);
$router->post('/vplan/eliminar', [VplanController::class, 'eliminar']);
$router->get('/vplan/ver', [VplanController::class, 'ver']);
$router->post('/vplan/lamina/tabla', [VplanController::class, 'tablaLamina']);
$router->post('/api/vplan/recovery', [VplanController::class, 'recovery']);
$router->post('/api/vplan/columna-texto', [VplanController::class, 'textoColumna']);
$router->post('/vplan/lamina/recovery', [VplanController::class, 'recoveryCols']);
$router->post('/api/vplan/imagen', [VplanController::class, 'subirImagen']);
$router->get('/vplan/editor', [VplanController::class, 'editor']);
$router->post('/api/vplan/tablas-version', [VplanController::class, 'versionTablas']);
$router->post('/api/vplan/marcas', [VplanController::class, 'guardarMarcas']);
$router->post('/api/vplan/diseno', [VplanController::class, 'guardarDiseno']);
$router->post('/vplan/diseno/restablecer', [VplanController::class, 'restablecerDiseno']);
$router->post('/api/vplan/fila', [VplanTablaController::class, 'apiFila']);
$router->post('/api/vplan/nota/guardar', [VplanNotaController::class, 'guardar']);
$router->post('/api/vplan/nota/eliminar', [VplanNotaController::class, 'eliminar']);
$router->get('/vplan/tablas', [VplanTablaController::class, 'index']);
$router->get('/vplan/tablas/crear', [VplanTablaController::class, 'crear']);
$router->post('/vplan/tablas/crear', [VplanTablaController::class, 'crear']);
$router->get('/vplan/tablas/editar', [VplanTablaController::class, 'editar']);
$router->post('/vplan/tablas/editar', [VplanTablaController::class, 'editar']);
$router->get('/vplan/tablas/documento', [VplanTablaController::class, 'documento']);
$router->post('/api/vplan/documento/leer', [VplanTablaController::class, 'apiDocumentoLeer']);
$router->post('/api/vplan/documento/texto', [VplanTablaController::class, 'apiDocumentoTexto']);
$router->post('/api/vplan/documento/crear', [VplanTablaController::class, 'apiDocumentoCrear']);
$router->post('/vplan/tablas/convertir-punch', [VplanTablaController::class, 'convertirPunch']);
$router->post('/vplan/tablas/a-sheetflow', [VplanTablaController::class, 'aSheetflow']);
$router->post('/vplan/tablas/duplicar', [VplanTablaController::class, 'duplicar']);
$router->post('/vplan/tablas/eliminar', [VplanTablaController::class, 'eliminar']);

// ========== LAST PLANNER ==========
$router->get('/lastplanner', [LastPlannerController::class, 'index']);
$router->post('/lastplanner/plantilla', [LastPlannerController::class, 'plantilla']);

// ========== REPORTE DIARIO Y PERSONAL ==========
$router->get('/reportes', [ReporteController::class, 'index']);
$router->get('/reportes/ver', [ReporteController::class, 'ver']);
$router->get('/reportes/crear', [ReporteController::class, 'crear']);
$router->post('/reportes/crear', [ReporteController::class, 'crear']);
$router->get('/reportes/editar', [ReporteController::class, 'editar']);
$router->post('/reportes/editar', [ReporteController::class, 'editar']);
$router->post('/reportes/eliminar', [ReporteController::class, 'eliminar']);
$router->get('/reportes/personal', [ReporteController::class, 'personal']);
$router->get('/reportes/personal/crear', [ReporteController::class, 'personalCrear']);
$router->post('/reportes/personal/crear', [ReporteController::class, 'personalCrear']);
$router->get('/reportes/personal/editar', [ReporteController::class, 'personalEditar']);
$router->post('/reportes/personal/editar', [ReporteController::class, 'personalEditar']);
$router->post('/reportes/personal/eliminar', [ReporteController::class, 'personalEliminar']);

// ========== BITÁCORA DE OBRA ==========
$router->get('/bitacora', [BitacoraController::class, 'index']);
$router->get('/bitacora/crear', [BitacoraController::class, 'crear']);
$router->post('/bitacora/crear', [BitacoraController::class, 'crear']);
$router->get('/bitacora/editar', [BitacoraController::class, 'editar']);
$router->post('/bitacora/editar', [BitacoraController::class, 'editar']);
$router->post('/bitacora/eliminar', [BitacoraController::class, 'eliminar']);
$router->get('/bitacora/departamentos', [BitacoraController::class, 'departamentos']);
$router->get('/bitacora/departamentos/crear', [BitacoraController::class, 'departamentoCrear']);
$router->post('/bitacora/departamentos/crear', [BitacoraController::class, 'departamentoCrear']);
$router->get('/bitacora/departamentos/editar', [BitacoraController::class, 'departamentoEditar']);
$router->post('/bitacora/departamentos/editar', [BitacoraController::class, 'departamentoEditar']);
$router->post('/bitacora/departamentos/eliminar', [BitacoraController::class, 'departamentoEliminar']);

// ========== MINUTAS ==========
$router->get('/datos-obra', [DatosObraController::class, 'index']);
$router->post('/carpetas/renombrar', [CarpetaController::class, 'renombrar']);
$router->post('/carpetas/mover', [CarpetaController::class, 'mover']);
$router->get('/minutas', [MinutaController::class, 'index']);
$router->get('/minutas/ver', [MinutaController::class, 'ver']);
$router->get('/minutas/pdf', [MinutaController::class, 'pdf']);
$router->get('/minutas/crear', [MinutaController::class, 'crear']);
$router->post('/minutas/crear', [MinutaController::class, 'crear']);
$router->get('/minutas/editar', [MinutaController::class, 'editar']);
$router->post('/minutas/editar', [MinutaController::class, 'editar']);
$router->post('/minutas/eliminar', [MinutaController::class, 'eliminar']);
$router->get('/minutas/contratista', [MinutaController::class, 'contratista']);
$router->get('/minutas/contratista/crear', [MinutaController::class, 'contratistaCrear']);
$router->post('/minutas/contratista/crear', [MinutaController::class, 'contratistaCrear']);
$router->get('/minutas/contratista/editar', [MinutaController::class, 'contratistaEditar']);
$router->post('/minutas/contratista/editar', [MinutaController::class, 'contratistaEditar']);
$router->post('/minutas/contratista/eliminar', [MinutaController::class, 'contratistaEliminar']);

// ========== ARCHIVO ==========
$router->get('/archivos', [ArchivoController::class, 'index']);
$router->get('/archivos/crear', [ArchivoController::class, 'crear']);
$router->post('/archivos/crear', [ArchivoController::class, 'crear']);
$router->get('/archivos/editar', [ArchivoController::class, 'editar']);
$router->post('/archivos/editar', [ArchivoController::class, 'editar']);
$router->post('/archivos/eliminar', [ArchivoController::class, 'eliminar']);
$router->post('/archivos/mover', [ArchivoController::class, 'mover']);
$router->post('/archivos/carpetas/guardar', [ArchivoController::class, 'carpetaGuardar']);
$router->post('/archivos/carpetas/eliminar', [ArchivoController::class, 'carpetaEliminar']);
$router->get('/archivos/descargar', [ArchivoController::class, 'descargar']);
$router->get('/archivos/ver', [ArchivoController::class, 'ver']);
$router->get('/archivos/categorias', [ArchivoController::class, 'categorias']);
$router->get('/archivos/categorias/crear', [ArchivoController::class, 'categoriaCrear']);
$router->post('/archivos/categorias/crear', [ArchivoController::class, 'categoriaCrear']);
$router->get('/archivos/categorias/editar', [ArchivoController::class, 'categoriaEditar']);
$router->post('/archivos/categorias/editar', [ArchivoController::class, 'categoriaEditar']);
$router->post('/archivos/categorias/eliminar', [ArchivoController::class, 'categoriaEliminar']);

// ========== VISOR DE ARCHIVOS ==========
$router->get('/visor', [VisorController::class, 'index']);

// ========== CURVAS DE PRODUCTIVIDAD ==========
$router->get('/curvas', [CurvaController::class, 'index']);
$router->get('/curvas/crear', [CurvaController::class, 'crear']);
$router->post('/curvas/crear', [CurvaController::class, 'crear']);
$router->get('/curvas/editar', [CurvaController::class, 'editar']);
$router->post('/curvas/editar', [CurvaController::class, 'editar']);
$router->post('/curvas/eliminar', [CurvaController::class, 'eliminar']);
$router->get('/curvas/ver', [CurvaController::class, 'ver']);
$router->get('/curvas/s', [CurvaController::class, 'curvaS']);
$router->post('/curvas/s/manual', [CurvaController::class, 'guardarCurvaSManual']);
$router->get('/curvas/maestra', [CurvaController::class, 'maestra']);
$router->post('/api/curvas/real', [CurvaController::class, 'guardarReal']);

// ========== CALENDARIO ==========
$router->get('/calendario', [CalendarioController::class, 'index']);
// Editor de gráficos
$router->get('/graficos', [EditorController::class, 'index']);
$router->get('/graficos/categorias', [EditorController::class, 'categorias']);
$router->get('/graficos/categorias/crear', [EditorController::class, 'categoriaCrear']);
$router->post('/graficos/categorias/crear', [EditorController::class, 'categoriaCrear']);
$router->get('/graficos/categorias/editar', [EditorController::class, 'categoriaEditar']);
$router->post('/graficos/categorias/editar', [EditorController::class, 'categoriaEditar']);
$router->post('/graficos/categorias/eliminar', [EditorController::class, 'categoriaEliminar']);
$router->get('/graficos/crear', [EditorController::class, 'crear']);
$router->post('/graficos/crear', [EditorController::class, 'crear']);
$router->get('/graficos/abrir', [EditorController::class, 'abrir']);
$router->get('/graficos/ver', [EditorController::class, 'ver']);
$router->get('/graficos/descargar', [EditorController::class, 'descargar']);
$router->post('/graficos/duplicar', [EditorController::class, 'duplicar']);
$router->post('/graficos/eliminar', [EditorController::class, 'eliminar']);
$router->post('/graficos/tipo', [EditorController::class, 'cambiarTipo']);
$router->post('/graficos/simbologia-estandar', [EditorController::class, 'simbologiaEstandar']);
$router->post('/api/graficos/renombrar', [EditorController::class, 'renombrar']);
$router->post('/api/graficos/punch', [EditorController::class, 'datosPunch']);
$router->post('/api/graficos/marcas', [EditorController::class, 'guardarMarcas']);
$router->post('/api/graficos/imagen', [EditorController::class, 'subirImagen']);
$router->post('/api/graficos/pdf', [EditorController::class, 'pdf']);
$router->post('/api/graficos/odp', [EditorController::class, 'odp']);
$router->post('/api/graficos/lamina', [EditorController::class, 'lamina']);
$router->get('/graficos/fondo', [EditorController::class, 'fondo']);
$router->post('/graficos/fondo', [EditorController::class, 'fondo']);
$router->post('/idioma', [IdiomaController::class, 'cambiar']);
$router->get('/evidencias', [EvidenciaController::class, 'index']);
$router->post('/evidencias/subir', [EvidenciaController::class, 'subir']);
$router->post('/evidencias/eliminar', [EvidenciaController::class, 'eliminar']);
// Redactor (documentos de texto)
$router->get('/redactor', [RedactorController::class, 'index']);
$router->get('/redactor/crear', [RedactorController::class, 'crear']);
$router->post('/redactor/crear', [RedactorController::class, 'crear']);
$router->get('/redactor/abrir', [RedactorController::class, 'abrir']);
$router->get('/redactor/ver', [RedactorController::class, 'ver']);
$router->get('/redactor/descargar', [RedactorController::class, 'descargar']);
$router->post('/redactor/duplicar', [RedactorController::class, 'duplicar']);
$router->post('/redactor/eliminar', [RedactorController::class, 'eliminar']);
$router->post('/api/redactor/guardar', [RedactorController::class, 'guardar']);
$router->post('/api/redactor/imagen', [RedactorController::class, 'imagen']);
$router->post('/api/redactor/palabra', [RedactorController::class, 'palabra']);
$router->post('/api/redactor/firma', [RedactorController::class, 'firma']);
$router->post('/api/redactor/firma/borrar', [RedactorController::class, 'firmaBorrar']);
$router->post('/api/redactor/sellar', [RedactorController::class, 'sellar']);
$router->post('/api/redactor/plantilla', [RedactorController::class, 'plantilla']);
$router->post('/api/redactor/versiones', [RedactorController::class, 'versiones']);
$router->post('/api/redactor/version', [RedactorController::class, 'version']);
$router->post('/api/redactor/programa', [RedactorController::class, 'programa']);
$router->post('/api/redactor/hoja', [RedactorController::class, 'hoja']);
$router->post('/api/redactor/exportar-seleccion', [RedactorController::class, 'exportarSeleccion']);
$router->post('/redactor/plantilla/eliminar', [RedactorController::class, 'eliminarPlantilla']);

// SheetFlow (tablas reutilizables en vPlan, Editor y Redactor)
$router->get('/sheetflow', [SheetflowController::class, 'index']);
$router->get('/sheetflow/crear', [SheetflowController::class, 'crear']);
$router->post('/sheetflow/crear', [SheetflowController::class, 'crear']);
$router->get('/sheetflow/abrir', [SheetflowController::class, 'abrir']);
$router->get('/sheetflow/ver', [SheetflowController::class, 'ver']);
$router->get('/sheetflow/descargar', [SheetflowController::class, 'descargar']);
$router->post('/sheetflow/duplicar', [SheetflowController::class, 'duplicar']);
$router->post('/sheetflow/eliminar', [SheetflowController::class, 'eliminar']);
$router->post('/sheetflow/a-vplan', [SheetflowController::class, 'aVplan']);
$router->post('/api/sheetflow/guardar', [SheetflowController::class, 'guardar']);
$router->post('/api/sheetflow/version', [SheetflowController::class, 'version']);
$router->post('/api/sheetflow/datos', [SheetflowController::class, 'datos']);
$router->post('/api/sheetflow/imagen', [SheetflowController::class, 'imagen']);

$router->get('/simbolos', [SimboloController::class, 'index']);
$router->post('/simbolos/guardar', [SimboloController::class, 'guardar']);
$router->post('/simbolos/eliminar', [SimboloController::class, 'eliminar']);
$router->post('/simbolos/cajetin', [SimboloController::class, 'guardarCajetin']);
$router->post('/simbolos/cajetin/eliminar', [SimboloController::class, 'eliminarCajetin']);
$router->post('/simbolos/estampa', [EstampaController::class, 'guardar']);
$router->post('/simbolos/estampa/eliminar', [EstampaController::class, 'eliminar']);
$router->post('/simbolos/estampa/duplicar', [EstampaController::class, 'duplicar']);
$router->post('/simbolos/estampa/plantilla', [EstampaController::class, 'plantilla']);
$router->post('/api/estampas/vista', [EstampaController::class, 'vista']);
$router->post('/api/estampas/textos', [EstampaController::class, 'textos']);
$router->get('/simbolos/exportar', [SimboloController::class, 'exportar']);

// View360
$router->get('/inconsistencias', [InconsistenciasController::class, 'index']);
$router->get('/api/inconsistencias/contexto', [InconsistenciasController::class, 'contexto']);
$router->post('/api/inconsistencias/resultado', [InconsistenciasController::class, 'resultado']);
$router->post('/inconsistencias/revisar', [InconsistenciasController::class, 'revisar']);
$router->post('/inconsistencias/vigilar', [InconsistenciasController::class, 'vigilar']);
$router->post('/api/inconsistencias/vigilancia', [InconsistenciasController::class, 'vigilancia']);
$router->post('/inconsistencias/guardar', [InconsistenciasController::class, 'guardarAnalisis']);
$router->post('/inconsistencias/eliminar', [InconsistenciasController::class, 'eliminarAnalisis']);
$router->post('/inconsistencias/aceptar', [InconsistenciasController::class, 'aceptar']);
$router->post('/inconsistencias/rechazar', [InconsistenciasController::class, 'rechazar']);
$router->post('/inconsistencias/reabrir', [InconsistenciasController::class, 'reabrir']);
$router->get('/api/ia/consultas', [ConsultasIAController::class, 'catalogo']);
$router->post('/api/ia/consulta', [ConsultasIAController::class, 'ejecutar']);
$router->get('/api/ia/herramientas', [InformesIAController::class, 'herramientas']);
$router->get('/api/ia/centro', [AppIAController::class, 'centro']);
$router->get('/api/ia/analisis', [AppIAController::class, 'analisis']);
$router->get('/api/ia/pendientes', [AppIAController::class, 'pendientes']);
$router->get('/api/ia/auditoria', [AppIAController::class, 'auditoria']);
$router->post('/api/ia/biblioteca', [AppIAController::class, 'biblioteca']);
$router->post('/api/ia/contexto', [InformesIAController::class, 'contexto']);
$router->post('/api/ia/resultado', [InformesIAController::class, 'resultado']);
$router->get('/api/ia/dias-sin-reporte', [InformesIAController::class, 'diasSinReporte']);
$router->get('/api/ia/informe', [InformesIAController::class, 'informe']);
$router->post('/api/ia/informe-resolver', [InformesIAController::class, 'resolver']);
$router->post('/api/ia/grabacion', [InformesIAController::class, 'grabacion']);
$router->get('/api/ia/evidencia', [InformesIAController::class, 'evidencia']);
$router->get('/api/duracion-sugerida', [InformesIAController::class, 'duracionSugerida']);
$router->get('/grabaciones', [InformesIAController::class, 'grabaciones']);
$router->get('/grabaciones/audio', [InformesIAController::class, 'audio']);
$router->post('/grabaciones/eliminar', [InformesIAController::class, 'eliminarGrabacion']);
$router->get('/informes-ia', [InformesIAController::class, 'index']);
$router->post('/informes-ia/generar', [InformesIAController::class, 'generar']);
$router->get('/informes-ia/ver', [InformesIAController::class, 'ver']);
$router->get('/informes-ia/pdf', [InformesIAController::class, 'pdf']);
$router->post('/informes-ia/estado', [InformesIAController::class, 'estado']);
$router->post('/informes-ia/eliminar', [InformesIAController::class, 'eliminar']);
$router->post('/informes-ia/aplicar', [InformesIAController::class, 'aplicar']);
$router->get('/revisiones', [RevisionController::class, 'index']);
$router->post('/api/revisiones/marcar', [RevisionController::class, 'marcar']);
$router->get('/api/revisiones/resumen', [RevisionController::class, 'resumen']);
$router->get('/normativa', [NormativaController::class, 'index']);
$router->post('/normativa/subir', [NormativaController::class, 'subir']);
$router->get('/normativa/documento', [NormativaController::class, 'documento']);
$router->get('/normativa/pdf', [NormativaController::class, 'pdf']);
$router->post('/normativa/reindexar', [NormativaController::class, 'reindexar']);
$router->post('/normativa/eliminar', [NormativaController::class, 'eliminar']);
$router->get('/normativa/vinculos', [NormativaController::class, 'vinculos']);
$router->post('/normativa/resolver', [NormativaController::class, 'resolver']);
$router->post('/normativa/quitar', [NormativaController::class, 'quitar']);
$router->post('/normativa/agregar', [NormativaController::class, 'agregar']);
$router->post('/normativa/sugerir', [NormativaController::class, 'sugerir']);
$router->get('/mapa-avance', [MapaAvanceController::class, 'index']);
$router->post('/mapa-avance/ajustes', [MapaAvanceController::class, 'ajustes']);
$router->post('/mapa-avance/mapear', [MapaAvanceController::class, 'mapear']);
$router->post('/mapa-avance/calcular', [MapaAvanceController::class, 'calcular']);
$router->get('/seguridad', [SeguridadController::class, 'index']);
$router->post('/seguridad/permiso', [SeguridadController::class, 'crear']);
$router->post('/seguridad/tipo', [SeguridadController::class, 'crearTipo']);
$router->post('/seguridad/tipo/eliminar', [SeguridadController::class, 'eliminarTipo']);
$router->post('/seguridad/permiso/estado', [SeguridadController::class, 'estado']);
$router->post('/seguridad/medidas', [SeguridadController::class, 'medidas']);
$router->post('/seguridad/permiso/eliminar', [SeguridadController::class, 'eliminar']);
$router->get('/seguridad/permiso/pdf', [SeguridadController::class, 'pdf']);
$router->post('/seguridad/incidente', [SeguridadController::class, 'incidente']);
$router->post('/seguridad/incidente/cerrar', [SeguridadController::class, 'cerrarIncidente']);
$router->get('/punch', [PunchController::class, 'index']);
$router->get('/punch/ver', [PunchController::class, 'ver']);
$router->post('/punch/guardar', [PunchController::class, 'guardar']);
$router->post('/punch/estado', [PunchController::class, 'estado']);
$router->post('/punch/eliminar', [PunchController::class, 'eliminar']);
$router->post('/punch/categoria', [PunchController::class, 'categoriaGuardar']);
$router->post('/punch/categoria/eliminar', [PunchController::class, 'categoriaEliminar']);
$router->post('/punch/categorias/sugeridas', [PunchController::class, 'categoriasSugeridas']);
$router->get('/punch/inspeccion', [PunchController::class, 'inspeccion']);
$router->post('/punch/inspeccion', [PunchController::class, 'inspeccionar']);
$router->get('/punch/pdf', [PunchController::class, 'pdf']);
$router->get('/plantillas', [PlantillasController::class, 'index']);
$router->post('/plantillas/elegir', [PlantillasController::class, 'elegir']);
$router->post('/plantillas/aplicar', [PlantillasController::class, 'aplicar']);
$router->get('/propuestas-ia', [CentroIAController::class, 'bandeja']);
$router->post('/propuestas-ia/aceptar', [CentroIAController::class, 'aceptar']);
$router->post('/propuestas-ia/rechazar', [CentroIAController::class, 'rechazar']);
$router->get('/historial-cambios', [CentroIAController::class, 'historial']);
$router->get('/historial-cambios/exportar', [CentroIAController::class, 'exportarHistorial']);
$router->get('/biblioteca-ia', [CentroIAController::class, 'biblioteca']);
$router->post('/biblioteca-ia/guardar', [CentroIAController::class, 'guardarAnalisis']);
$router->post('/biblioteca-ia/eliminar', [CentroIAController::class, 'eliminarAnalisis']);
$router->get('/api/materiales-inteligentes/estado', [MaterialesInteligentesController::class, 'estado']);
$router->post('/api/materiales-inteligentes/activar', [MaterialesInteligentesController::class, 'activar']);
$router->get('/api/materiales-inteligentes/contexto', [MaterialesInteligentesController::class, 'contexto']);
$router->post('/api/materiales-inteligentes/resultado', [MaterialesInteligentesController::class, 'resultado']);
$router->get('/analisis-materiales', [AnalisisMaterialesController::class, 'index']);
$router->get('/api/analisis-materiales/contexto', [AnalisisMaterialesController::class, 'contexto']);
$router->post('/api/analisis-materiales/resultado', [AnalisisMaterialesController::class, 'resultado']);
$router->post('/analisis-materiales/revisar', [AnalisisMaterialesController::class, 'revisar']);
$router->post('/analisis-materiales/guardar', [AnalisisMaterialesController::class, 'guardarAnalisis']);
$router->post('/analisis-materiales/eliminar', [AnalisisMaterialesController::class, 'eliminarAnalisis']);
$router->post('/analisis-materiales/aceptar', [AnalisisMaterialesController::class, 'aceptar']);
$router->post('/analisis-materiales/rechazar', [AnalisisMaterialesController::class, 'rechazar']);
$router->post('/analisis-materiales/reabrir', [AnalisisMaterialesController::class, 'reabrir']);
$router->get('/api/ensamblador/estado', [EnsambladorController::class, 'estado']);
$router->post('/api/ensamblador/subir', [EnsambladorController::class, 'subir']);
$router->post('/api/ensamblador/quitar', [EnsambladorController::class, 'quitar']);
$router->post('/api/ensamblador/contexto', [EnsambladorController::class, 'contexto']);
$router->post('/api/ensamblador/resultado', [EnsambladorController::class, 'resultado']);
$router->get('/api/ensamblador/propuesta', [EnsambladorController::class, 'propuesta']);
$router->post('/api/ensamblador/aplicar', [EnsambladorController::class, 'aplicar']);
$router->post('/api/ensamblador/revertir', [EnsambladorController::class, 'revertir']);
$router->post('/api/ensamblador/rechazar', [EnsambladorController::class, 'rechazar']);
$router->get('/api/ordenar-ia/propuesta', [EnsambladorController::class, 'ordenarPropuesta']);
$router->post('/api/ordenar-ia/aplicar', [EnsambladorController::class, 'ordenarAplicar']);
$router->post('/api/ordenar-ia/rechazar', [EnsambladorController::class, 'ordenarRechazar']);
$router->post('/api/ordenar-ia/deshacer', [EnsambladorController::class, 'ordenarDeshacer']);
$router->get('/programador', [ProgramadorController::class, 'index']);
$router->get('/api/programador/proyectos', [ProgramadorController::class, 'proyectos']);
$router->get('/api/programador/contexto', [ProgramadorController::class, 'contexto']);
$router->post('/api/programador/integracion', [ProgramadorController::class, 'integracion']);
$router->post('/api/programador/revision', [ProgramadorController::class, 'revision']);
$router->post('/api/programador/propuesta', [ProgramadorController::class, 'propuesta']);
$router->post('/programador/guardar', [ProgramadorController::class, 'guardar']);
$router->post('/programador/aceptar', [ProgramadorController::class, 'aceptar']);
$router->post('/programador/deshacer', [ProgramadorController::class, 'deshacer']);
$router->post('/programador/seguimiento', [ProgramadorController::class, 'seguimiento']);
$router->get('/programador/exportar', [ProgramadorController::class, 'exportar']);
$router->post('/programador/eliminar', [ProgramadorController::class, 'eliminar']);
// Ensamblador de documentos (PDF que une Redactor y Archivo y se mantiene al día)
$router->get('/ensamblador-documentos', [EnsambleController::class, 'index']);
$router->get('/ensamblador-documentos/crear', [EnsambleController::class, 'formulario']);
$router->post('/ensamblador-documentos/crear', [EnsambleController::class, 'formulario']);
$router->get('/ensamblador-documentos/editar', [EnsambleController::class, 'formulario']);
$router->post('/ensamblador-documentos/editar', [EnsambleController::class, 'formulario']);
$router->post('/ensamblador-documentos/eliminar', [EnsambleController::class, 'eliminar']);
$router->post('/ensamblador-documentos/archivo', [EnsambleController::class, 'guardarEnArchivo']);
$router->get('/ensamblador-documentos/descargar', [EnsambleController::class, 'descargar']);
$router->get('/comparador', [ComparadorController::class, 'index']);
$router->get('/comparador/archivo', [ComparadorController::class, 'archivo']);
$router->get('/traductor', [TraductorController::class, 'index']);
$router->post('/api/traductor/texto', [TraductorController::class, 'texto']);
$router->post('/api/traductor/documento', [TraductorController::class, 'documento']);
$router->get('/view360', [View360Controller::class, 'index']);
$router->get('/view360/ver', [View360Controller::class, 'ver']);
$router->get('/view360/crear', [View360Controller::class, 'crear']);
$router->post('/view360/crear', [View360Controller::class, 'crear']);
$router->get('/view360/editar', [View360Controller::class, 'editar']);
$router->post('/view360/editar', [View360Controller::class, 'editar']);
$router->post('/api/view360/nota/guardar', [View360Controller::class, 'notaGuardar']);
$router->post('/api/view360/nota/eliminar', [View360Controller::class, 'notaEliminar']);
$router->post('/api/view360/panorama', [View360Controller::class, 'panorama']);
$router->post('/view360/eliminar', [View360Controller::class, 'eliminar']);
$router->post('/api/simbolos/importar', [SimboloController::class, 'importar']);


// ========== AJUSTES Y EXPORTACIÓN ==========
$router->get('/sistema', [SistemaController::class, 'ajustes']);
$router->post('/sistema/respaldo/crear', [SistemaController::class, 'respaldoCrear']);
$router->post('/sistema/respaldo/programar', [SistemaController::class, 'respaldoProgramar']);
$router->get('/sistema/respaldo/descargar', [SistemaController::class, 'respaldoDescargar']);
$router->post('/sistema/herramienta', [SistemaController::class, 'herramientaGuardar']);
$router->post('/sistema/herramienta/eliminar', [SistemaController::class, 'herramientaEliminar']);
$router->post('/sistema/instalador/subir', [SistemaController::class, 'instaladorSubir']);
$router->post('/sistema/instalador/eliminar', [SistemaController::class, 'instaladorEliminar']);
$router->get('/api/escritorio/version', [EscritorioApiController::class, 'version']);
$router->get('/api/escritorio/descargar', [EscritorioApiController::class, 'descargar']);
$router->get('/sistema/instalador', [SistemaController::class, 'instalador']);
$router->post('/sistema/respaldo/eliminar', [SistemaController::class, 'respaldoEliminar']);
$router->post('/sistema/respaldo/restaurar', [SistemaController::class, 'respaldoRestaurar']);
$router->get('/ajustes', [AjustesController::class, 'index']);
$router->post('/ajustes/jornada', [AjustesController::class, 'guardarJornada']);
$router->post('/ajustes/registros', [AjustesController::class, 'guardarRegistros']);
$router->post('/ajustes/tema', [AjustesController::class, 'guardarTema']);
$router->post('/ajustes/festivo/agregar', [AjustesController::class, 'agregarFestivo']);
$router->post('/ajustes/festivo/eliminar', [AjustesController::class, 'eliminarFestivo']);
$router->post('/ajustes/respaldo/crear', [AjustesController::class, 'respaldoCrear']);
$router->post('/ajustes/respaldo/restaurar', [AjustesController::class, 'respaldoRestaurar']);
$router->post('/ajustes/respaldo/eliminar', [AjustesController::class, 'respaldoEliminar']);
$router->post('/ajustes/respaldo/automatico', [AjustesController::class, 'respaldoAutomatico']);
$router->get('/ajustes/respaldo/descargar', [AjustesController::class, 'respaldoDescargar']);
$router->get('/api/empezar/estado', [Controllers\EmpezarController::class, 'estado']);
$router->post('/api/empezar/festivo', [Controllers\EmpezarController::class, 'festivo']);
$router->post('/api/empezar/partida', [Controllers\EmpezarController::class, 'partida']);
$router->post('/api/empezar/persona', [Controllers\EmpezarController::class, 'persona']);
$router->post('/api/empezar/material', [Controllers\EmpezarController::class, 'material']);
$router->post('/api/empezar/contrato', [Controllers\EmpezarController::class, 'contrato']);
$router->post('/api/empezar/documento', [Controllers\EmpezarController::class, 'documento']);
$router->get('/exportar', [AjustesController::class, 'exportarIndex']);
$router->post('/exportar/generar', [AjustesController::class, 'exportar']);
$router->post('/exportar/todo', [AjustesController::class, 'exportarTodo']);

csrf_verificar();   // todo POST necesita el código de la sesión
// Un visitante solo entra a las herramientas que se le permitieron (también por enlace directo o API)
if (is_auth() && is_visitante() && !herramienta_permitida(herramienta_de_ruta($_SERVER['REQUEST_URI'] ?? '/'))) {
    if (str_starts_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '', '/api/')) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No tienes acceso a esta herramienta']);
    } else {
        header('Location: /proyectos?error=' . urlencode('No tienes acceso a esa herramienta'));
    }
    exit;
}
// Respaldo diario sin cron: si el admin entra después de la hora programada y falta el de hoy, se hace al terminar la petición
if (is_auth() && is_admin() && \Classes\Respaldo::pendiente()) {
    register_shutdown_function(function () {
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        try { \Classes\Respaldo::ejecutarSiToca(); } catch (\Throwable $e) { error_log('Respaldo programado: ' . $e->getMessage()); }
    });
}
// Respaldo nocturno de cada proyecto (23:30): lo dispara la primera petición de cualquier usuario después de esa hora si no hay cron
if (is_auth() && \Classes\RespaldoProyecto::pendiente()) {
    register_shutdown_function(function () {
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        try { \Classes\RespaldoProyecto::ejecutarSiToca(); } catch (\Throwable $e) { error_log('Respaldo de proyectos: ' . $e->getMessage()); }
    });
}
$router->comprobarRutas();
