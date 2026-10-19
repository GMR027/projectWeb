// ============================================================
// App JS principal
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
  // ============ TOGGLE DE TEMA ============
  // El tema lo elige el usuario con el botón y se guarda en localStorage.
  // Por defecto: oscuro.
  const toggleTheme = document.getElementById('toggle-theme');
  const themeIcon = toggleTheme?.querySelector('i');

  const savedTheme = localStorage.getItem('theme') || 'dark';
  document.documentElement.setAttribute('data-theme', savedTheme);

  // Actualizar icono según tema actual
  const updateThemeIcon = () => {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    if (themeIcon) {
      themeIcon.className = currentTheme === 'light' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
    }
  };
  updateThemeIcon();

  // Toggle manual
  toggleTheme?.addEventListener('click', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcon();
  });

  // ============ MENÚ HAMBURGUESA (modo móvil) ============
  const btnMenu = document.getElementById('btn-menu');
  const fondoMenu = document.getElementById('sidebar-fondo');
  const setMenu = (abierto) => {
    document.body.classList.toggle('menu-abierto', abierto);
    btnMenu?.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    const i = btnMenu?.querySelector('i');
    if (i) i.className = abierto ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
  };
  btnMenu?.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-abierto')));
  fondoMenu?.addEventListener('click', () => setMenu(false));
  document.querySelectorAll('.sidebar .nav-link').forEach(a => a.addEventListener('click', () => setMenu(false)));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });

  // ============ MODAL DE CONFIRMACIÓN ============
  // Un solo modal para toda la aplicación (en lugar de confirm() / alert() del navegador):
  //  - Formularios con data-confirm="titulo|mensaje|texto del botón (opcional)".
  //  - window.confirmarModal({ titulo, mensaje, aceptar, icono, tono: 'peligro'|'aviso'|'info', extra: 'Texto' }) -> Promise<true|false|'extra'>
  //    (con `extra` aparece un tercer botón que resuelve 'extra', p. ej. «Guardar y salir»)
  //  - window.avisoModal(titulo, mensaje) -> Promise (solo botón "Entendido")
  const modal = document.getElementById('modal-confirm');
  const modalTitulo = document.getElementById('modal-confirm-titulo');
  const modalMensaje = document.getElementById('modal-confirm-mensaje');
  const modalCancelar = document.getElementById('modal-confirm-cancelar');
  const modalAceptar = document.getElementById('modal-confirm-aceptar');
  const modalExtra = document.getElementById('modal-confirm-extra');
  const modalIcono = modal?.querySelector('.modal-icono i');
  const aceptarPorDefecto = modalAceptar ? modalAceptar.innerHTML : '';
  let resolverModal = null;

  const cerrarModal = (ok = false) => {
    if (!modal) return;
    modal.classList.remove('abierto');
    modal.setAttribute('aria-hidden', 'true');
    const r = resolverModal;
    resolverModal = null;
    if (r) r(ok);
  };

  window.confirmarModal = (opciones = {}) => new Promise((resolve) => {
    if (!modal) { resolve(true); return; }
    if (resolverModal) cerrarModal(false);   // cierra uno anterior sin resolver en "aceptar"
    modalTitulo.textContent = opciones.titulo || '¿Eliminar registro?';
    modalMensaje.textContent = opciones.mensaje || 'Esta acción no se puede deshacer.';
    modalAceptar.innerHTML = opciones.aceptar
      ? '<i class="fa-solid ' + (opciones.iconoBoton || 'fa-check') + '"></i> ' + opciones.aceptar
      : aceptarPorDefecto;
    if (modalIcono) modalIcono.className = 'fa-solid ' + (opciones.icono || (opciones.tipo === 'aviso' ? 'fa-circle-info' : 'fa-triangle-exclamation'));
    const tono = opciones.tono || (opciones.tipo === 'aviso' ? 'info' : 'peligro');
    modal.dataset.tono = tono;
    modalAceptar.className = 'btn ' + (tono === 'peligro' || opciones.extra ? 'btn-danger' : 'btn-primary');
    if (modalExtra) {
      modalExtra.hidden = !opciones.extra;
      modalExtra.innerHTML = opciones.extra ? '<i class="fa-solid ' + (opciones.iconoExtra || 'fa-floppy-disk') + '"></i> ' + opciones.extra : '';
    }
    modalCancelar.innerHTML = '<i class="fa-solid fa-xmark"></i> ' + (opciones.cancelar || 'Cancelar');
    modalCancelar.style.display = opciones.soloAceptar ? 'none' : '';
    resolverModal = resolve;
    modal.classList.add('abierto');
    modal.setAttribute('aria-hidden', 'false');
    modalAceptar.focus();
  });

  window.avisoModal = (titulo, mensaje) => window.confirmarModal({
    titulo, mensaje, aceptar: 'Entendido', tipo: 'aviso', soloAceptar: true,
  });

  modalCancelar?.addEventListener('click', () => cerrarModal(false));
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) cerrarModal(false);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal?.classList.contains('abierto')) cerrarModal(false);
  });
  modalAceptar?.addEventListener('click', () => cerrarModal(true));
  modalExtra?.addEventListener('click', () => cerrarModal('extra'));

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const partes = (form.dataset.confirm || '').split('|');
      window.confirmarModal({ titulo: partes[0]?.trim(), mensaje: partes[1]?.trim(), aceptar: partes[2]?.trim() }).then((ok) => {
        if (ok) form.submit();
      });
    });
  });

  // Formularios con data-confirmar="mensaje": confirmación con el modal y título genérico.
  document.querySelectorAll('form[data-confirmar]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const enviador = e.submitter;
      window.confirmarModal({ titulo: '¿Continuar?', mensaje: form.dataset.confirmar }).then((ok) => {
        if (!ok) return;
        if (enviador && enviador.name) {
          const h = document.createElement('input');
          h.type = 'hidden'; h.name = enviador.name; h.value = enviador.value;
          form.appendChild(h);
        }
        form.submit();
      });
    });
  });

  // ============ VISOR DE IMÁGENES ============
  // Las fotos adjuntas (enlaces dentro de .bj-fotos) se amplían sobre la página, con botón X arriba a la derecha.
  let visor = null;
  const cerrarVisor = () => { if (visor) { visor.remove(); visor = null; } };
  document.addEventListener('click', (e) => {
    const enlace = e.target.closest('.bj-fotos a, img[data-visor]');
    if (!enlace || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    cerrarVisor();
    visor = document.createElement('div');
    visor.className = 'visor-img';
    visor.setAttribute('role', 'dialog');
    visor.setAttribute('aria-modal', 'true');
    visor.innerHTML = '<button type="button" class="visor-img-cerrar" aria-label="Cerrar imagen"><i class="fa-solid fa-xmark"></i></button><img alt="">';
    visor.querySelector('img').src = enlace.dataset.visor || enlace.href;
    visor.addEventListener('click', (ev) => { if (ev.target !== visor.querySelector('img')) cerrarVisor(); });
    document.body.appendChild(visor);
    visor.querySelector('button').focus();
  });
  // En fase de captura para que Esc cierre solo la imagen y no el modal que hay debajo
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && visor) { e.stopImmediatePropagation(); cerrarVisor(); }
  }, true);

  // Auto-ocultar alertas después de 5 segundos
  const alertas = document.querySelectorAll('.alerta');
  alertas.forEach(alerta => {
    setTimeout(() => {
      alerta.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
      alerta.style.opacity = '0';
      alerta.style.transform = 'translateY(-1rem)';
      setTimeout(() => alerta.remove(), 400);
    }, 5000);
  });

  // Validación de fechas en formularios de proyecto
  const fechaInicio = document.getElementById('fecha_inicio');
  const fechaFin = document.getElementById('fecha_fin');
  if (fechaInicio && fechaFin) {
    const validarRango = () => {
      if (fechaInicio.value && fechaFin.value && fechaFin.value < fechaInicio.value) {
        fechaFin.setCustomValidity('La fecha de fin no puede ser anterior a la de inicio');
      } else {
        fechaFin.setCustomValidity('');
      }
    };
    fechaInicio.addEventListener('change', validarRango);
    fechaFin.addEventListener('change', validarRango);
  }

  // ============ EDICIÓN INLINE DE FECHAS EN TABLA DE PROGRAMA ============
  const inputsFecha = document.querySelectorAll('.input-fecha');
  inputsFecha.forEach(input => {
    const valorOriginal = input.value;

    input.addEventListener('change', async (e) => {
      const actividadId = e.target.dataset.actividadId;
      const tipo = e.target.dataset.tipo; // 'inicio' o 'fin'
      const nuevaFecha = e.target.value;

      if (!nuevaFecha || nuevaFecha === valorOriginal) return;

      // Validar que fecha fin no sea menor a inicio
      const fila = e.target.closest('tr');
      const inputInicio = fila.querySelector('.fecha-inicio-input');
      const inputFin = fila.querySelector('.fecha-fin-input');

      if (inputInicio && inputFin) {
        if (inputFin.value < inputInicio.value) {
          window.avisoModal('Fechas no válidas', 'La fecha de fin no puede ser anterior a la fecha de inicio.');
          e.target.value = valorOriginal;
          return;
        }
      }

      // Mostrar indicador de guardando
      e.target.style.opacity = '0.5';
      e.target.disabled = true;

      try {
        const formData = new FormData();
        formData.append('actividad_id', actividadId);
        formData.append('tipo', tipo);
        formData.append('fecha', nuevaFecha);

        const response = await fetch('/api/actividad/actualizar-fecha', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          // Actualizar valores en la fila
          if (inputInicio) inputInicio.value = data.fecha_inicio;
          if (inputFin) inputFin.value = data.fecha_fin;

          // Actualizar duración
          const duracionCell = fila.querySelector('.duracion-cell');
          if (duracionCell) {
            duracionCell.textContent = parseFloat(data.duracion_real).toFixed(1) + ' d';
          }

          // Efecto visual de éxito
          e.target.style.borderColor = '#10b981';
          setTimeout(() => {
            e.target.style.borderColor = '';
          }, 2000);

          // Recargar página después de 1 segundo para actualizar Gantt
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          window.avisoModal('No se pudo actualizar', data.error || 'No se pudo actualizar la actividad.');
          e.target.value = valorOriginal;
        }
      } catch (error) {
        console.error('Error:', error);
        window.avisoModal('Error de conexión', 'Intenta de nuevo en unos segundos.');
        e.target.value = valorOriginal;
      } finally {
        e.target.style.opacity = '1';
        e.target.disabled = false;
      }
    });
  });

  // Preview de logo al seleccionar archivo
  const inputLogo = document.getElementById('logo');
  if (inputLogo) {
    inputLogo.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (ev) => {
        let preview = document.querySelector('.logo-preview img');
        if (!preview) {
          const cont = document.createElement('div');
          cont.className = 'logo-preview';
          preview = document.createElement('img');
          preview.style.maxWidth = '8rem';
          preview.style.marginTop = '0.5rem';
          cont.appendChild(preview);
          inputLogo.parentElement.appendChild(cont);
        }
        preview.src = ev.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  // Tooltip nativo mejorado para barras de gantt
  document.querySelectorAll('.gantt-barra').forEach(barra => {
    barra.addEventListener('mouseenter', () => {
      barra.style.zIndex = '10';
    });
    barra.addEventListener('mouseleave', () => {
      barra.style.zIndex = '1';
    });
  });
});


// Menús «Descargar» (details.gr-descargar): el menú se coloca fijo junto al botón para que las tablas con scroll no lo recorten, y se cierra al hacer clic fuera
document.addEventListener('toggle', (e) => {
  const d = e.target;
  if (!(d instanceof HTMLDetailsElement) || !d.classList.contains('gr-descargar') || !d.open) return;
  document.querySelectorAll('details.gr-descargar[open]').forEach((o) => { if (o !== d) o.open = false; });
  const menu = d.querySelector('.gr-descargar-menu'), r = d.querySelector('summary').getBoundingClientRect();
  menu.style.position = 'fixed';
  menu.style.top = (r.bottom + 4) + 'px';
  menu.style.right = Math.max(8, window.innerWidth - r.right) + 'px';
  menu.style.left = 'auto';
}, true);
window.addEventListener('scroll', () => { document.querySelectorAll('details.gr-descargar[open]').forEach((d) => { d.open = false; }); }, true);
document.addEventListener('click', (e) => {
  document.querySelectorAll('details.gr-descargar[open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; });
});


// ============================================================
// Buscador de palabras en las listas
// - Toda tabla .tabla con 5 o más filas recibe su caja de búsqueda (data-sin-buscador la excluye; data-buscable la fuerza con menos filas).
// - Un input con data-filtra="selector" filtra esa tabla mientras se escribe (además de su búsqueda en el servidor, si la tiene).
// - Ignora mayúsculas y acentos; varias palabras = todas deben aparecer. En el programa, la fila de partida se queda si coincide
//   ella (se ven todas sus actividades) o alguna de sus actividades.
// ============================================================
(() => {
  const norm = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

  const filtrar = (tabla, texto, conteo) => {
    const palabras = norm(texto).split(/\s+/).filter(Boolean);
    const filas = [...tabla.querySelectorAll(':scope > tbody > tr')].filter((tr) => !tr.querySelector(':scope > td[colspan]'));
    const coincide = (tr) => { const t = tr._texto ??= norm(tr.textContent); return palabras.every((p) => t.includes(p)); };
    // Agrupa cada actividad con su partida (en las demás tablas no hay grupos)
    const grupoDe = new Map(), partidas = [];
    let grupo = null;
    filas.forEach((tr) => {
      if (tr.classList.contains('fila-partida')) { grupo = { cab: tr, hijas: [], propia: coincide(tr) }; partidas.push(grupo); }
      else if (grupo && tr.classList.contains('fila-actividad')) { grupo.hijas.push(tr); grupoDe.set(tr, grupo); }
      else grupo = null;
    });
    const hechas = filas.filter((tr) => !tr.classList.contains('fila-partida'));
    hechas.forEach((tr) => { tr.hidden = !(!palabras.length || coincide(tr) || grupoDe.get(tr)?.propia); });
    partidas.forEach((g) => { g.cab.hidden = !(!palabras.length || g.propia || g.hijas.some((h) => !h.hidden)); });
    const total = hechas.length, visibles = hechas.filter((tr) => !tr.hidden).length;
    if (conteo) conteo.textContent = palabras.length ? `${visibles} de ${total}` : '';
    let vacio = tabla._vacio;
    if (palabras.length && !visibles) {
      if (!vacio) {
        vacio = tabla._vacio = document.createElement('tr');
        vacio.className = 'sin-resultados';
        vacio.innerHTML = `<td colspan="${tabla.querySelectorAll('thead th').length || 1}" class="text-center text-muted"></td>`;
        tabla.tBodies[0].append(vacio);
      }
      vacio.firstChild.textContent = `Nada coincide con «${texto.trim()}».`;
      vacio.hidden = false;
    } else if (vacio) vacio.hidden = true;
  };

  const conectar = (input, tabla, conteo) => {
    let t;
    input.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => filtrar(tabla, input.value, conteo), 80); });
    input.addEventListener('keydown', (e) => { if (e.key === 'Escape' && input.value) { input.value = ''; filtrar(tabla, '', conteo); } });
    if (input.value) filtrar(tabla, input.value, conteo);
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[data-filtra]').forEach((input) => {
      const tabla = document.querySelector(input.dataset.filtra);
      if (tabla) conectar(input, tabla, null);
    });
    document.querySelectorAll('.main-content table.tabla').forEach((tabla) => {
      if (tabla.hasAttribute('data-sin-buscador') || tabla.closest('.modal-overlay, .modal-card') || !tabla.tBodies[0]) return;
      if (document.querySelector(`input[data-filtra]`) && [...document.querySelectorAll('input[data-filtra]')].some((i) => document.querySelector(i.dataset.filtra) === tabla)) return;
      const n = [...tabla.tBodies[0].rows].filter((tr) => !tr.querySelector(':scope > td[colspan]')).length;
      if (n < (tabla.hasAttribute('data-buscable') ? 2 : 5)) return;
      const caja = document.createElement('div');
      caja.className = 'buscador-tabla';
      caja.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" placeholder="Buscar en esta lista…" aria-label="Buscar en esta lista" autocomplete="off"><span class="conteo" aria-live="polite"></span>';
      (tabla.parentElement.classList.contains('tabla-wrapper') ? tabla.parentElement : tabla).before(caja);
      conectar(caja.querySelector('input'), tabla, caja.querySelector('.conteo'));
    });
  });
})();
