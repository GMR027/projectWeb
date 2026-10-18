'use strict';
// Conversión de audio grabado a WAV PCM 16 bits mono (lo que esperan los motores de transcripción), sin depender de ffmpeg.
(function (raiz) {
  function mezclarMono(canales) {
    if (canales.length === 1) return canales[0];
    const n = canales[0].length, out = new Float32Array(n);
    for (let c = 0; c < canales.length; c++) for (let i = 0; i < n; i++) out[i] += canales[c][i] / canales.length;
    return out;
  }
  function codificarWav(muestras, rate) {
    const buf = new ArrayBuffer(44 + muestras.length * 2), v = new DataView(buf);
    const t = (o, s) => { for (let i = 0; i < s.length; i++) v.setUint8(o + i, s.charCodeAt(i)); };
    t(0, 'RIFF'); v.setUint32(4, 36 + muestras.length * 2, true); t(8, 'WAVE'); t(12, 'fmt '); v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
    v.setUint32(24, rate, true); v.setUint32(28, rate * 2, true); v.setUint16(32, 2, true); v.setUint16(34, 16, true); t(36, 'data'); v.setUint32(40, muestras.length * 2, true);
    for (let i = 0; i < muestras.length; i++) { const s = Math.max(-1, Math.min(1, muestras[i])); v.setInt16(44 + i * 2, s < 0 ? s * 0x8000 : s * 0x7fff, true); }
    return new Uint8Array(buf);
  }
  /** Blob grabado (webm/opus…) → WAV 16 kHz mono + duración en segundos */
  async function blobAWav(blob) {
    const ctx = new (raiz.AudioContext || raiz.webkitAudioContext)({ sampleRate: 16000 });
    try {
      const audio = await ctx.decodeAudioData(await blob.arrayBuffer());   // el contexto remuestrea a 16 kHz
      const canales = []; for (let c = 0; c < audio.numberOfChannels; c++) canales.push(audio.getChannelData(c));
      return { wav: codificarWav(mezclarMono(canales), 16000), segundos: Math.round(audio.duration) };
    } finally { ctx.close(); }
  }
  const api = { mezclarMono, codificarWav, blobAWav };
  if (typeof module !== 'undefined' && module.exports) module.exports = api; else raiz.Wav = api;
})(typeof window !== 'undefined' ? window : globalThis);
