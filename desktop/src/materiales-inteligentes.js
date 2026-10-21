'use strict';
// Materiales inteligentes: verifica los materiales nuevos o modificados contra la normativa y especificaciones cargadas en el proyecto.
// Project Web arma los lotes (material + cláusulas candidatas) y guarda el veredicto; aquí solo se consulta al modelo.
const { porLotes } = require('./lotes');
const { leer, enviar, pagina } = require('./api');

/** {activo, documentos, conteos:{aprobado,no_aprobado,revisar,sin_normativa,pendiente}, csrf} */
async function estado({ servidor, sesion, proyectoId }) {
  return leer(sesion, pagina(servidor, '/api/materiales-inteligentes/estado', proyectoId), 'No se pudo leer el estado');
}

async function activar({ servidor, sesion, proyectoId, activo }) {
  const e = await estado({ servidor, sesion, proyectoId });
  return enviar(sesion, servidor + '/api/materiales-inteligentes/activar', { proyecto: String(proyectoId), activo: activo ? '1' : '' }, e.csrf, 'No se pudo cambiar la opción');
}

/**
 * @param alAvanzar fn({texto, hecho, total})
 * @param cancelado fn() → true si el usuario canceló
 * @returns {resumen:{verificados, aprobados, por_revisar, sin_normativa, no_aprobados:[{id,nombre,resumen}], fallidos, motivo}}
 */
async function ejecutar(opc) {
  const { resultado: r, ctx, fallidos } = await porLotes({
    ...opc, rutaApi: '/api/materiales-inteligentes',
    textos: { leyendo: 'Buscando materiales por verificar…', guardando: 'Guardando el resultado en los materiales…', cancelado: 'Verificación cancelada.' },
    // un lote que falla no tira el resto: sus materiales quedan pendientes; sin trabajo no se guarda nada
    resultadoSiVacio: ctx => ctx.motivo || (!ctx.lotes.length && !ctx.conteos?.pendiente),
  });
  if (!r) return { resumen: { verificados: 0, aprobados: 0, por_revisar: 0, sin_normativa: 0, no_aprobados: [], fallidos, motivo: ctx.motivo || 'nada' }, proyecto: ctx.proyecto };
  return { resumen: { verificados: r.verificados, aprobados: r.aprobados, por_revisar: r.por_revisar, sin_normativa: r.sin_normativa, no_aprobados: r.no_aprobados, fallidos, motivo: '' }, proyecto: ctx.proyecto };
}

module.exports = { ejecutar, estado, activar };
