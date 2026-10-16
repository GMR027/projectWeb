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
  //  - window.confirmarModal({ titulo, mensaje, aceptar, icono }) -> Promise<boolean>
  //  - window.avisoModal(titulo, mensaje) -> Promise (solo botón "Entendido")
  const modal = document.getElementById('modal-confirm');
  const modalTitulo = document.getElementById('modal-confirm-titulo');
  const modalMensaje = document.getElementById('modal-confirm-mensaje');
  const modalCancelar = document.getElementById('modal-confirm-cancelar');
  const modalAceptar = document.getElementById('modal-confirm-aceptar');
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
    modalAceptar.className = 'btn ' + (opciones.tipo === 'aviso' ? 'btn-primary' : 'btn-danger');
    if (modalIcono) modalIcono.className = 'fa-solid ' + (opciones.icono || (opciones.tipo === 'aviso' ? 'fa-circle-info' : 'fa-triangle-exclamation'));
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

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const partes = (form.dataset.confirm || '').split('|');
      window.confirmarModal({ titulo: partes[0]?.trim(), mensaje: partes[1]?.trim(), aceptar: partes[2]?.trim() }).then((ok) => {
        if (ok) form.submit();
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
