'use strict';
// Inconsistencias de proyecto: manda al modelo los lotes que arma Project Web y devuelve sus hallazgos para guardarlos
const { porLotes } = require('./lotes');

/**
 * @param sesion  fetch con la sesión web del admin (session.fetch de la vista de Project Web)
 * @param alAvanzar fn({texto, hecho, total})
 * @param cancelado fn() → true si el usuario canceló
 */
async function ejecutar(opc) {
  const { resultado: r, ctx, fallidos } = await porLotes({
    ...opc, rutaApi: '/api/inconsistencias', registrar: true,
    textos: { leyendo: 'Leyendo el proyecto y aplicando las reglas del sistema…', verbo: 'Revisando ', guardando: 'Guardando las inconsistencias…', cancelado: 'Análisis cancelado.' },
  });
  return { resumen: { corrida: r.corrida, del_sistema: r.del_sistema, de_la_ia: r.de_la_ia, nuevos: r.nuevos, ya_resueltos: r.ya_resueltos, fallidos }, proyecto: ctx.proyecto };
}

module.exports = { ejecutar };
