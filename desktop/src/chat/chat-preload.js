'use strict';
const { contextBridge, ipcRenderer } = require('electron');
contextBridge.exposeInMainWorld('chat', {
  abrir: abierto => ipcRenderer.invoke('chat:abrir', !!abierto),
  proyecto: id => ipcRenderer.invoke('chat:proyecto', Number(id) || 0),
  contexto: () => ipcRenderer.invoke('chat:contexto'),
  enviar: (pregunta, historial) => ipcRenderer.invoke('chat:enviar', String(pregunta), Array.isArray(historial) ? historial.slice(-8).map(m => ({ rol: m.rol === 'usuario' ? 'usuario' : 'asistente', texto: String(m.texto).slice(0, 1000) })) : []),
  alAvance: fn => ipcRenderer.on('chat:avance', (_e, t) => fn(String(t))),
});
