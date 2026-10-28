'use strict';
// Catálogo de Herramientas IA de la app de escritorio: grupo del menú, qué exige cada una al modelo y a dónde lleva.
// `sec` = sección propia dentro de la app; `web` = página de Project Web que se abre en la vista (con ?proyecto= del proyecto abierto).
const GRUPOS = [
  { id: 'control', titulo: 'Resultados y control' },
  { id: 'planeacion', titulo: 'Planeación' },
  { id: 'ensamblador', titulo: 'Ensamblador' },
  { id: 'campo', titulo: 'Campo y reportes' },
  { id: 'datos', titulo: 'Revisión de datos' },
  { id: 'compras', titulo: 'Compras y materiales' },
  { id: 'documentos', titulo: 'Documentos' },
  { id: 'seguridad', titulo: 'Seguridad y calidad' },
  { id: 'cierre', titulo: 'Entrega y conocimiento' },
  { id: 'centro', titulo: 'Páginas del proyecto' },
];

const HERRAMIENTAS = [
  { id: 'programador', grupo: 'planeacion', titulo: 'Programador de actividades', sec: 'programador', tarea: 'avanzada', requiere: { minimo: 'basico', recomendado: 'medio' } },
  { id: 'ensamblador', grupo: 'ensamblador', titulo: 'Ensamblador de documentos', sec: 'ensamblador', tarea: 'avanzada', requiere: { minimo: 'basico', recomendado: 'medio' } },
  { id: 'tabla-doc', grupo: 'ensamblador', titulo: 'Tabla desde documento', sec: 'tabla-doc', tarea: 'avanzada', requiere: { minimo: 'basico', recomendado: 'medio' } },
  { id: 'ordenar-ia', grupo: 'ensamblador', titulo: 'Ordenar y renumerar por fechas', sec: 'ordenar-ia', tarea: 'avanzada', requiere: { minimo: 'basico', recomendado: 'basico' } },
  // «Calidad de datos» reúne Inconsistencias, Análisis de materiales y Materiales inteligentes en una sola entrada del menú; las tres siguen aquí (`oculto`) para calcular su capacidad y su marca de uso
  { id: 'calidad', grupo: 'datos', titulo: 'Calidad de datos', sec: 'calidad', hijas: ['inconsistencias', 'materiales', 'inteligentes'], requiere: { minimo: 'basico', recomendado: 'basico' } },
  { id: 'inconsistencias', grupo: 'datos', oculto: true, titulo: 'Inconsistencias de proyecto', sec: 'inconsistencias', requiere: { minimo: 'basico', recomendado: 'basico' } },
  { id: 'materiales', grupo: 'datos', oculto: true, titulo: 'Análisis de materiales', sec: 'materiales', requiere: { minimo: 'basico', recomendado: 'basico' } },
  { id: 'inteligentes', grupo: 'datos', oculto: true, titulo: 'Materiales inteligentes', sec: 'inteligentes', requiere: { minimo: 'basico', recomendado: 'medio' } },
  { id: 'propuestas', grupo: 'control', titulo: 'Análisis inteligente (propuestas e historial)', sec: 'propuestas', requiere: null },
  { id: 'centro-trabajo', grupo: 'control', titulo: 'Centro de trabajo (pendientes y alertas)', web: '/resumen-ia', requiere: null },
  { id: 'revisiones', grupo: 'control', titulo: 'Revisiones del proyecto', web: '/revisiones', requiere: null },
  { id: 'informes', grupo: 'centro', titulo: 'Informes de IA', web: '/informes-ia', requiere: null },
  { id: 'normativa', grupo: 'centro', titulo: 'Normativa y especificaciones', web: '/normativa', requiere: null },
  { id: 'mapa', grupo: 'centro', titulo: 'Mapa de avance (planos)', web: '/mapa-avance', requiere: null },
  { id: 'seguridad', grupo: 'centro', titulo: 'Seguridad y permisos', web: '/seguridad', requiere: null },
  { id: 'punch', grupo: 'centro', titulo: 'Punch list', web: '/punch', requiere: null },
  { id: 'cambios', grupo: 'centro', titulo: 'Órdenes de cambio y líneas base', web: '/cambios', requiere: null },
  { id: 'tipoobra', grupo: 'centro', titulo: 'Tipo de obra (plantillas)', web: '/plantillas', requiere: null },
  { id: 'grabaciones', grupo: 'centro', titulo: 'Grabaciones de reuniones', web: '/grabaciones', requiere: null },
];

/** Ficha de una herramienta que ofrece el servidor (informes) → entrada del catálogo. Se ejecutan con el ejecutor genérico (src/informes.js) */
function desdeServidor(f) {
  return { id: 'gen:' + f.id, gen: f.id, grupo: GRUPOS.some(g => g.id === f.grupo) ? f.grupo : 'campo', titulo: f.titulo, descripcion: f.descripcion || '', parametros: f.parametros || [], tarea: 'avanzada', voz: !!f.voz, grabacion: !!f.grabacion, requiere: f.requiere || { minimo: 'basico', recomendado: 'basico' } };
}

module.exports = { GRUPOS, HERRAMIENTAS, desdeServidor };
