/* Archivo: subida por lotes. Aplica fecha, categoría, carpeta, nombre y revisión comunes o por archivo.
 * Config en window.ARCH_LOTE (lo pinta views/archivos/lote.php). */
(function () {
    'use strict';
    var C = window.ARCH_LOTE;
    if (!C) return;

    var form = document.getElementById('arch-lote-form');
    var input = document.getElementById('arch-lote-input');
    var tbody = document.getElementById('arch-lote-filas');
    var lista = document.getElementById('arch-lote-lista');
    var resumen = document.getElementById('arch-lote-resumen');
    var aviso = document.getElementById('arch-lote-aviso');
    var boton = document.getElementById('arch-lote-subir');
    var limpiar = document.getElementById('arch-lote-limpiar');
    if (!form || !input || !tbody || !boton) return;

    var reglas = {
        fecha: { on: document.getElementById('arch-lote-fecha-on'), valor: document.getElementById('arch-lote-fecha') },
        categoria: { on: document.getElementById('arch-lote-categoria-on'), valor: document.getElementById('arch-lote-categoria') },
        carpeta: { on: document.getElementById('arch-lote-carpeta-on'), valor: document.getElementById('arch-lote-carpeta') },
        revision: { on: document.getElementById('arch-lote-revision-on'), valor: document.getElementById('arch-lote-revision') },
        original: document.getElementById('arch-lote-nombre-original')
    };
    var seleccionados = [];
    var puedeQuitar = true;

    function clave(f) { return f.name + '|' + f.size + '|' + (f.lastModified || 0); }
    function nombreBase(nombre) {
        var n = String(nombre || '');
        var m = /^(.*)\.[^.]+$/.exec(n);
        return (m && m[1] ? m[1] : n).slice(0, 200);
    }
    function tamano(b) {
        if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
        return Math.max(1, Math.round(b / 1024)) + ' KB';
    }
    function hoy() { return C.hoy || new Date().toISOString().slice(0, 10); }
    function avisar(texto, mal) {
        if (!aviso) return;
        aviso.textContent = texto || '';
        aviso.classList.toggle('arch-lote-aviso-error', !!mal);
    }
    function llenar(select, datos, vacio) {
        select.innerHTML = '';
        var o = document.createElement('option');
        o.value = ''; o.textContent = vacio; select.appendChild(o);
        (datos || []).forEach(function (x) {
            var op = document.createElement('option');
            op.value = String(x.id); op.textContent = x.nombre;
            select.appendChild(op);
        });
    }
    function nuevo(file) {
        return { file: file, nombre: nombreBase(file.name), fecha: hoy(), categoria: '', carpeta: C.carpetaInicial || '', revision: '' };
    }
    /** Mantiene el input de archivos sincronizado con la lista (permite quitar uno sin perder el resto) */
    function sincronizarInput() {
        if (!puedeQuitar) return;
        try {
            var dt = new DataTransfer();
            seleccionados.forEach(function (x) { dt.items.add(x.file); });
            input.files = dt.files;
        } catch (e) {
            puedeQuitar = false;
        }
    }
    function celda(tr, etiqueta, nodo) {
        var td = document.createElement('td');
        if (etiqueta) td.dataset.etq = etiqueta;
        td.appendChild(nodo);
        tr.appendChild(td);
        return td;
    }
    function enlazar(el, item, campo) {
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', function () { item[campo] = el.value; });
    }
    function pintar() {
        tbody.innerHTML = '';
        seleccionados.forEach(function (item, i) {
            var tr = document.createElement('tr');
            tr.dataset.idx = String(i);

            var tdF = document.createElement('td');
            tdF.className = 'arch-lote-archivo';
            var cab = document.createElement('div');
            cab.className = 'arch-lote-archivo-cab';
            var ico = document.createElement('i');
            ico.className = 'fa-regular fa-file';
            var nom = document.createElement('strong');
            nom.textContent = item.file.name;
            cab.appendChild(ico); cab.appendChild(nom);
            var info = document.createElement('small');
            info.className = 'text-muted';
            info.textContent = tamano(item.file.size);
            tdF.appendChild(cab); tdF.appendChild(info);
            tr.appendChild(tdF);

            var nombre = document.createElement('input');
            nombre.type = 'text'; nombre.name = 'nombre[]'; nombre.maxLength = 200;
            nombre.value = item.nombre; nombre.dataset.campo = 'nombre';
            enlazar(nombre, item, 'nombre');
            celda(tr, 'Nombre', nombre);

            var cat = document.createElement('select');
            cat.name = 'categoria_id[]'; cat.dataset.campo = 'categoria';
            llenar(cat, C.categorias, '— Sin categoría —');
            cat.value = item.categoria || '';
            enlazar(cat, item, 'categoria');
            celda(tr, 'Categoría', cat);

            var car = document.createElement('select');
            car.name = 'carpeta_id[]'; car.dataset.campo = 'carpeta';
            llenar(car, C.carpetas, '— Sin carpeta —');
            car.value = item.carpeta || '';
            enlazar(car, item, 'carpeta');
            celda(tr, 'Carpeta', car);

            var fecha = document.createElement('input');
            fecha.type = 'date'; fecha.name = 'fecha[]'; fecha.value = item.fecha; fecha.dataset.campo = 'fecha';
            enlazar(fecha, item, 'fecha');
            celda(tr, 'Fecha', fecha);

            var rev = document.createElement('input');
            rev.type = 'text'; rev.name = 'numero_revision[]'; rev.maxLength = 30;
            rev.value = item.revision; rev.dataset.campo = 'revision'; rev.placeholder = '0, A, 02';
            enlazar(rev, item, 'revision');
            celda(tr, 'Rev.', rev);

            var tdQ = document.createElement('td');
            tdQ.className = 'arch-lote-quitar';
            if (puedeQuitar) {
                var b = document.createElement('button');
                b.type = 'button'; b.className = 'btn btn-sm btn-ghost';
                b.title = 'Quitar este archivo';
                b.setAttribute('aria-label', 'Quitar ' + item.file.name);
                b.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                b.addEventListener('click', function () {
                    seleccionados.splice(i, 1);
                    sincronizarInput();
                    pintar();
                });
                tdQ.appendChild(b);
            }
            tr.appendChild(tdQ);
            tbody.appendChild(tr);
        });
        lista.hidden = !seleccionados.length;
        aplicarReglas();
        actualizarResumen();
    }
    /** Pinta cada campo con el valor común (si la casilla está marcada) o con el suyo */
    function aplicarReglas() {
        var bloqueos = {
            fecha: reglas.fecha.on.checked,
            categoria: reglas.categoria.on.checked,
            carpeta: reglas.carpeta.on.checked,
            revision: reglas.revision.on.checked,
            nombre: reglas.original.checked
        };
        var comunes = {
            fecha: reglas.fecha.valor.value,
            categoria: reglas.categoria.valor.value,
            carpeta: reglas.carpeta.valor.value,
            revision: reglas.revision.valor.value
        };
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var item = seleccionados[+tr.dataset.idx];
            if (!item) return;
            ['fecha', 'categoria', 'carpeta', 'revision'].forEach(function (campo) {
                var el = tr.querySelector('[data-campo="' + campo + '"]');
                if (!el) return;
                el.disabled = bloqueos[campo];
                el.value = bloqueos[campo] ? comunes[campo] : (item[campo] || '');
            });
            var nom = tr.querySelector('[data-campo="nombre"]');
            if (nom) {
                nom.disabled = bloqueos.nombre;
                nom.value = bloqueos.nombre ? nombreBase(item.file.name) : item.nombre;
            }
        });
    }
    function actualizarResumen() {
        var n = seleccionados.length;
        var bytes = seleccionados.reduce(function (s, x) { return s + x.file.size; }, 0);
        var mal = '';
        if (!n) {
            resumen.textContent = '';
            boton.disabled = true;
            boton.innerHTML = '<i class="fa-solid fa-upload"></i> Subir archivos';
            limpiar.disabled = true;
            avisar('');
            return;
        }
        resumen.textContent = n + ' archivo' + (n === 1 ? '' : 's') + ' · ' + tamano(bytes) + ' de ' + tamano(C.maxLoteBytes);
        if (n > C.maxArchivos) mal = 'Máximo ' + C.maxArchivos + ' archivos por lote.';
        else if (bytes > C.maxLoteBytes) mal = 'El lote supera ' + tamano(C.maxLoteBytes) + '.';
        else if (seleccionados.some(function (x) { return x.file.size > C.maxBytes; })) mal = 'Hay archivos de más de ' + tamano(C.maxBytes) + '.';
        boton.disabled = !!mal;
        boton.innerHTML = '<i class="fa-solid fa-upload"></i> Subir ' + n + ' archivo' + (n === 1 ? '' : 's');
        limpiar.disabled = false;
        avisar(mal, !!mal);
    }

    input.addEventListener('change', function () {
        var nuevos = Array.prototype.slice.call(input.files);
        if (!puedeQuitar) {   // sin DataTransfer no se pueden quitar archivos: se refleja exactamente lo elegido
            seleccionados = nuevos.map(nuevo);
            pintar();
            return;
        }
        nuevos.forEach(function (f) {
            var k = clave(f);
            if (!seleccionados.some(function (x) { return clave(x.file) === k; })) seleccionados.push(nuevo(f));
        });
        sincronizarInput();
        pintar();
    });
    limpiar.addEventListener('click', function () {
        seleccionados = [];
        input.value = '';
        pintar();
    });
    [reglas.fecha.on, reglas.categoria.on, reglas.carpeta.on, reglas.revision.on, reglas.original].forEach(function (c) {
        if (c) c.addEventListener('change', aplicarReglas);
    });
    [reglas.fecha.valor, reglas.categoria.valor, reglas.carpeta.valor, reglas.revision.valor].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', aplicarReglas);
        el.addEventListener('change', aplicarReglas);
    });
    form.addEventListener('submit', function (e) {
        if (!seleccionados.length) {
            e.preventDefault();
            avisar('Selecciona al menos un archivo.', true);
            return;
        }
        if (boton.disabled) {
            e.preventDefault();
            return;
        }
        sincronizarInput();
        boton.disabled = true;
        boton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Subiendo…';
    });
    pintar();
})();
