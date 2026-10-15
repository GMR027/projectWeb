<?php

require_once __DIR__ . '/../includes/app.php';

use MVC\Router;
use Controllers\AuthController;
use Controllers\DashboardController;
use Controllers\LandingController;
use Controllers\ContactoController;
use Controllers\SoporteController;
use Controllers\BandejaController;
use Controllers\ProyectoController;
use Controllers\ProgramaController;
use Controllers\AjustesController;
use Controllers\SistemaController;
use Controllers\CalendarioController;
use Controllers\LookaheadController;
use Controllers\MaterialController;
use Controllers\EspecialidadController;
use Controllers\AccionesController;
use Controllers\PlanificadorController;
use Controllers\SubmittalController;
use Controllers\MsrController;
use Controllers\CurvaController;
use Controllers\LastPlannerController;
use Controllers\VplanController;
use Controllers\VplanTablaController;
use Controllers\ReporteController;
use Controllers\BitacoraController;
use Controllers\ArchivoController;
use Controllers\MinutaController;

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

// ========== SOPORTE (cliente) y BANDEJA DE ENTRADA (admin) ==========
$router->get('/soporte', [SoporteController::class, 'index']);
$router->post('/soporte/crear', [SoporteController::class, 'crear']);
$router->get('/soporte/editar', [SoporteController::class, 'editar']);
$router->post('/soporte/editar', [SoporteController::class, 'editar']);
$router->post('/soporte/eliminar', [SoporteController::class, 'eliminar']);
$router->get('/bandeja', [BandejaController::class, 'index']);
$router->get('/bandeja/ver', [BandejaController::class, 'ver']);
$router->post('/bandeja/accion', [BandejaController::class, 'accion']);
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
$router->get('/programa/importar', [ProgramaController::class, 'importar']);
$router->post('/programa/importar', [ProgramaController::class, 'importar']);
$router->post('/programa/importar/confirmar', [ProgramaController::class, 'confirmarImportacion']);
$router->post('/programa/importar/cancelar', [ProgramaController::class, 'cancelarImportacion']);
$router->post('/api/actividad/actualizar-fecha', [ProgramaController::class, 'actualizarFechaAjax']);
$router->post('/api/actividad/actualizar-progreso', [ProgramaController::class, 'actualizarProgresoAjax']);
// Partidas
$router->get('/programa/partidas/crear', [ProgramaController::class, 'crearPartida']);
$router->post('/programa/partidas/crear', [ProgramaController::class, 'crearPartida']);
$router->get('/programa/partidas/editar', [ProgramaController::class, 'editarPartida']);
$router->post('/programa/partidas/editar', [ProgramaController::class, 'editarPartida']);
$router->post('/programa/partidas/eliminar', [ProgramaController::class, 'eliminarPartida']);
// Actividades
$router->get('/programa/actividades/crear', [ProgramaController::class, 'crearActividad']);
$router->post('/programa/actividades/crear', [ProgramaController::class, 'crearActividad']);
$router->get('/programa/actividades/editar', [ProgramaController::class, 'editarActividad']);
$router->post('/programa/actividades/editar', [ProgramaController::class, 'editarActividad']);
$router->post('/programa/actividades/eliminar', [ProgramaController::class, 'eliminarActividad']);
// Restricciones
$router->get('/programa/restricciones/crear', [ProgramaController::class, 'crearRestriccion']);
$router->post('/programa/restricciones/crear', [ProgramaController::class, 'crearRestriccion']);
$router->post('/programa/restricciones/eliminar', [ProgramaController::class, 'eliminarRestriccion']);
$router->post('/programa/restricciones/resolver', [ProgramaController::class, 'resolverRestriccion']);

// ========== LOOKAHEAD 4WLA ==========
$router->get('/lookahead', [LookaheadController::class, 'index']);
$router->post('/api/actividad/pta', [LookaheadController::class, 'togglePta']);

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

// ========== VPLAN ==========
$router->get('/vplan', [VplanController::class, 'index']);
$router->get('/vplan/crear', [VplanController::class, 'crear']);
$router->post('/vplan/crear', [VplanController::class, 'crear']);
$router->get('/vplan/editar', [VplanController::class, 'editar']);
$router->post('/vplan/editar', [VplanController::class, 'editar']);
$router->post('/vplan/eliminar', [VplanController::class, 'eliminar']);
$router->get('/vplan/ver', [VplanController::class, 'ver']);
$router->post('/vplan/lamina/tabla', [VplanController::class, 'tablaLamina']);
$router->post('/api/vplan/recovery', [VplanController::class, 'recovery']);
$router->post('/vplan/lamina/recovery', [VplanController::class, 'recoveryCols']);
$router->post('/api/vplan/imagen', [VplanController::class, 'subirImagen']);
$router->get('/vplan/editor', [VplanController::class, 'editor']);
$router->post('/api/vplan/marcas', [VplanController::class, 'guardarMarcas']);
$router->post('/api/vplan/fila', [VplanTablaController::class, 'apiFila']);
$router->get('/vplan/tablas', [VplanTablaController::class, 'index']);
$router->get('/vplan/tablas/crear', [VplanTablaController::class, 'crear']);
$router->post('/vplan/tablas/crear', [VplanTablaController::class, 'crear']);
$router->get('/vplan/tablas/editar', [VplanTablaController::class, 'editar']);
$router->post('/vplan/tablas/editar', [VplanTablaController::class, 'editar']);
$router->post('/vplan/tablas/eliminar', [VplanTablaController::class, 'eliminar']);

// ========== LAST PLANNER ==========
$router->get('/lastplanner', [LastPlannerController::class, 'index']);

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
$router->get('/archivos/descargar', [ArchivoController::class, 'descargar']);
$router->get('/archivos/categorias', [ArchivoController::class, 'categorias']);
$router->get('/archivos/categorias/crear', [ArchivoController::class, 'categoriaCrear']);
$router->post('/archivos/categorias/crear', [ArchivoController::class, 'categoriaCrear']);
$router->get('/archivos/categorias/editar', [ArchivoController::class, 'categoriaEditar']);
$router->post('/archivos/categorias/editar', [ArchivoController::class, 'categoriaEditar']);
$router->post('/archivos/categorias/eliminar', [ArchivoController::class, 'categoriaEliminar']);

// ========== CURVAS DE PRODUCTIVIDAD ==========
$router->get('/curvas', [CurvaController::class, 'index']);
$router->get('/curvas/crear', [CurvaController::class, 'crear']);
$router->post('/curvas/crear', [CurvaController::class, 'crear']);
$router->get('/curvas/editar', [CurvaController::class, 'editar']);
$router->post('/curvas/editar', [CurvaController::class, 'editar']);
$router->post('/curvas/eliminar', [CurvaController::class, 'eliminar']);
$router->get('/curvas/ver', [CurvaController::class, 'ver']);
$router->get('/curvas/maestra', [CurvaController::class, 'maestra']);
$router->post('/api/curvas/real', [CurvaController::class, 'guardarReal']);

// ========== CALENDARIO ==========
$router->get('/calendario', [CalendarioController::class, 'index']);

// ========== AJUSTES Y EXPORTACIÓN ==========
$router->get('/sistema', [SistemaController::class, 'ajustes']);
$router->post('/sistema/respaldo/crear', [SistemaController::class, 'respaldoCrear']);
$router->get('/sistema/respaldo/descargar', [SistemaController::class, 'respaldoDescargar']);
$router->post('/sistema/respaldo/eliminar', [SistemaController::class, 'respaldoEliminar']);
$router->post('/sistema/respaldo/restaurar', [SistemaController::class, 'respaldoRestaurar']);
$router->get('/ajustes', [AjustesController::class, 'index']);
$router->post('/ajustes/jornada', [AjustesController::class, 'guardarJornada']);
$router->post('/ajustes/festivo/agregar', [AjustesController::class, 'agregarFestivo']);
$router->post('/ajustes/festivo/eliminar', [AjustesController::class, 'eliminarFestivo']);
$router->get('/exportar', [AjustesController::class, 'exportarIndex']);
$router->post('/exportar/generar', [AjustesController::class, 'exportar']);
$router->post('/exportar/todo', [AjustesController::class, 'exportarTodo']);

$router->comprobarRutas();
