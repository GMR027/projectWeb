'use strict';
const $ = s => document.querySelector(s);
const historial = [];
let abierto = false, ocupado = false;
const RAPIDAS = ['¿Qué actividades están atrasadas?', '¿Qué sigue esta semana?', '¿Qué materiales llegan tarde?', 'Resumen del proyecto'];

function burbuja(rol, texto, nota, clase) {
  const m = document.createElement('div'); m.className = 'msg ' + rol + (clase ? ' ' + clase : ''); m.textContent = texto;
  if (nota) { const s = document.createElement('small'); s.textContent = nota; m.append(s); }
  $('#mensajes').append(m); $('#mensajes').scrollTop = $('#mensajes').scrollHeight; return m;
}
function sugerencias(lista) {
  const c = $('#sugerencias'); c.replaceChildren();
  for (const t of lista) { const b = document.createElement('button'); b.type = 'button'; b.textContent = t; b.addEventListener('click', () => preguntar(t)); c.append(b); }
}
async function abrir(v) {
  abierto = v; $('#panel').hidden = !v; $('#fab').hidden = v;
  await window.chat.abrir(v);
  if (v) {
    const c = await window.chat.contexto();
    const sel = $('#proyecto'), o0 = document.createElement('option'); o0.value = ''; o0.textContent = 'Elige un proyecto…';
    sel.replaceChildren(o0, ...c.proyectos.map(p => { const o = document.createElement('option'); o.value = p.id; o.textContent = p.texto; return o; }));
    sel.value = c.proyectoId && c.proyectos.some(p => p.id === c.proyectoId) ? String(c.proyectoId) : '';
    $('#conexion').textContent = c.conexion ? 'IA: ' + c.conexion + ' · solo consulta, no modifica datos' : 'Sin conexión de IA: se muestran los datos consultados (configura una en Herramientas IA)';
    if (!$('#mensajes').children.length) { burbuja('asistente', 'Hola, elige arriba el proyecto y pregúntame sobre él: avance, actividades atrasadas, materiales, submittals, restricciones, bitácora…'); sugerencias(RAPIDAS); }
    $('#texto').focus();
  }
}
async function preguntar(texto) {
  const t = String(texto || '').trim();
  if (!t || ocupado) return;
  ocupado = true; $('#enviar').disabled = true; $('#texto').value = ''; sugerencias([]);
  burbuja('usuario', t); historial.push({ rol: 'usuario', texto: t });
  const espera = burbuja('asistente', 'Pensando…', '', 'pensando');
  try {
    const r = await window.chat.enviar(t, historial.slice(0, -1));
    espera.remove();
    burbuja('asistente', r.respuesta, (r.sinIA ? 'Datos sin redactar con IA · ' : '') + (r.consultas.length ? 'Consultado: ' + r.consultas.join(', ') : ''));
    historial.push({ rol: 'asistente', texto: r.respuesta }); sugerencias(r.sugerencias && r.sugerencias.length ? r.sugerencias : RAPIDAS.slice(0, 3));
  } catch (x) { espera.remove(); burbuja('asistente', String(x.message || x).replace(/^Error invoking remote method '[^']+': (Error: )?/, ''), '', 'error'); }
  ocupado = false; $('#enviar').disabled = false; $('#texto').focus();
}
window.chat.alAvance(t => { const e = document.querySelector('.msg.pensando'); if (e) e.textContent = t + '…'; });
$('#fab').addEventListener('click', () => abrir(true));
$('#cerrar').addEventListener('click', () => abrir(false));
$('#limpiar').addEventListener('click', () => { historial.length = 0; $('#mensajes').replaceChildren(); abrir(true); });
$('#proyecto').addEventListener('change', async e => { await window.chat.proyecto(e.target.value); historial.length = 0; $('#mensajes').replaceChildren(); abrir(true); });
$('#forma').addEventListener('submit', e => { e.preventDefault(); preguntar($('#texto').value); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && abierto) abrir(false); });
