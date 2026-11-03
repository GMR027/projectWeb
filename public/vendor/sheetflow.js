/* SheetFlow: editor de tablas (cuadrícula con selección, fórmulas, formato por celda, imágenes, pegado desde Excel, deshacer y autoguardado).
 * Datos (ver Classes\Sheetflow): { cols:[{n,t,a,tot,w}], rows:[[texto]], calc, tot, estilo:{enc,encTxt,zebra,bordes}, fmt:{"r,c":{b,i,u,fs,tc,bg,al,bt,br,bb,bl,im}} }
 * Una celda que empieza con «=» es una fórmula. El servidor vuelve a calcular todo al guardar (Classes\SheetflowFormulas: mismo lenguaje que aquí).
 * El encabezado es la fila 1 (como en Excel): su formato vive en la fila -1 de fmt. */
(function () {
    'use strict';
    var C = window.SF_CONFIG;
    if (!C) return;
    var $ = function (id) { return document.getElementById(id); };
    var S = C.datos;
    S.calc = S.calc && !Array.isArray(S.calc) ? S.calc : {};
    S.tot = S.tot && !Array.isArray(S.tot) ? S.tot : {};
    S.meta = S.meta && typeof S.meta === 'object' ? S.meta : {};
    S.merges = Array.isArray(S.merges) ? S.merges : [];
    // Vista: congelar, filtros, formato condicional y gráficos (los sanea el servidor en Sheetflow::limpiarVista)
    function normalizarVista() {
        S.vista = S.vista && typeof S.vista === 'object' ? S.vista : {};
        S.vista.filas = Math.max(0, parseInt(S.vista.filas, 10) || 0);
        S.vista.cols = Math.max(0, parseInt(S.vista.cols, 10) || 0);
        S.vista.filtros = S.vista.filtros && typeof S.vista.filtros === 'object' && !Array.isArray(S.vista.filtros) ? S.vista.filtros : {};
        S.vista.cond = Array.isArray(S.vista.cond) ? S.vista.cond : [];
        S.vista.graficos = Array.isArray(S.vista.graficos) ? S.vista.graficos : [];
    }
    normalizarVista();
    // IDs de subactividad por renglón (solo en tablas vinculadas; no se ven en la cuadrícula)
    var ids = (S.meta.subactividad && Array.isArray(S.meta.subactividad.ids)) ? S.meta.subactividad.ids.slice() : S.rows.map(function () { return null; });
    while (ids.length < S.rows.length) ids.push(null);
    ids = ids.slice(0, S.rows.length).map(function (v) { var n = parseInt(v, 10); return n > 0 ? n : null; });
    var grid = $('sf-grid'), area = $('sf-area'), fx = $('sf-fx');
    var version = C.version, sucio = false, guardando = false, temporizador = null;
    var sel = { r0: 0, c0: 0, r1: 0, c1: 0 };   // rango elegido; (r0,c0) es el ancla. r = -1 es el encabezado
    var selExtra = [];                           // celdas sueltas añadidas con Ctrl/⌘+clic (claves «r,c»)
    var editor = null;                           // {r,c,input,previo} mientras se edita una celda
    var apuntando = null;                        // {inp,ini,fin,a} mientras se arrastra una referencia con el ratón
    var fxOrigen = null;                         // celda desde la que se escribe en la barra de fórmulas
    var pasado = [], futuro = [];
    var MAX_C = C.max.cols, MAX_F = C.max.filas;
    var zoom = 100;
    var copiado = null;       // última copia interna: {r0,c0,r1,c1,filas,fmt,texto}
    var rellenando = null;    // {base,destino} mientras se arrastra el asa de relleno
    var busqueda = null;      // {texto,mayus,formulas,ultimo} de Buscar y reemplazar
    // Pantalla táctil: en el celular la celda se edita en una hoja inferior (con el teclado a la vista) y no
    // dentro de la cuadrícula, que quedaría tapada por el teclado del sistema. En tablet se edita en la propia
    // celda, como OnlyOffice/Excel: hay sitio de sobra y el teclado no tapa la tabla.
    var TACTIL = !!(window.GestosLienzo ? GestosLienzo.esTactil() : (navigator.maxTouchPoints || 0) > 0);
    var MOVIL_ESTRECHO = window.matchMedia('(max-width: 44rem)');
    var hojaCelda = null;   // HojaInferior de la celda (solo en el celular)
    var toqueEdito = 0;     // ms del último toque que abrió el editor: ignora el mousedown/click que el navegador emula después
    if (TACTIL) document.body.classList.add('sf-tactil');

    // Formatos por celda en matriz paralela a rows (sty[r][c]) y al encabezado (hsty[c]); en el JSON viajan como mapa disperso fmt
    var sty = [], hsty = [];
    function cargarEstilos() {
        var f = S.fmt && !Array.isArray(S.fmt) ? S.fmt : {};
        sty = S.rows.map(function (fila) { return fila.map(function () { return null; }); });
        hsty = S.cols.map(function () { return null; });
        Object.keys(f).forEach(function (k) {
            var p = k.split(','), r = +p[0], c = +p[1];
            if (r < 0) hsty[c] = f[k]; else if (sty[r]) sty[r][c] = f[k];
        });
    }
    function idsDeMeta() {
        var x = (S.meta.subactividad && Array.isArray(S.meta.subactividad.ids)) ? S.meta.subactividad.ids.slice() : S.rows.map(function () { return null; });
        while (x.length < S.rows.length) x.push(null);
        return x.slice(0, S.rows.length).map(function (v) { var n = parseInt(v, 10); return n > 0 ? n : null; });
    }
    cargarEstilos();
    function fmtMapa() {
        var o = {};
        hsty.forEach(function (f, c) { if (f && Object.keys(f).length) o['-1,' + c] = f; });
        sty.forEach(function (fila, r) { fila.forEach(function (f, c) { if (f && Object.keys(f).length) o[r + ',' + c] = f; }); });
        return o;
    }
    function getSt(r, c) { return r < 0 ? hsty[c] : (sty[r] ? sty[r][c] : null); }
    function setSt(r, c, f) { var vacio = !f || !Object.keys(f).length; if (r < 0) hsty[c] = vacio ? null : f; else sty[r][c] = vacio ? null : f; }

    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function estado(t, mal) { var e = $('sf-estado'); e.textContent = t || ''; e.style.color = mal ? 'var(--danger, #dc2626)' : ''; }
    function letra(i) { var s = ''; for (i++; i > 0; i = Math.floor((i - 1) / 26)) s = String.fromCharCode(65 + (i - 1) % 26) + s; return s; }
    function indiceLetra(l) { var n = 0; for (var i = 0; i < l.length; i++) n = n * 26 + l.charCodeAt(i) - 64; return n - 1; }
    function nombreCelda(r, c) { return letra(c) + (r + 2); }
    /** Celdas combinadas: rectángulo que contiene (r,c), o null */
    function mergeDe(r, c) {
        for (var i = 0; i < S.merges.length; i++) {
            var m = S.merges[i];
            if (r >= m.r0 && r <= m.r1 && c >= m.c0 && c <= m.c1) return m;
        }
        return null;
    }
    function mergeEsSeleccion(m) { return !!m && sel.r0 === m.r0 && sel.c0 === m.c0 && sel.r1 === m.r1 && sel.c1 === m.c1; }
    function mergeToca(m, n) { return m.r0 <= n.r1 && m.r1 >= n.r0 && m.c0 <= n.c1 && m.c1 >= n.c0; }
    var NUM = ['numero', 'moneda', 'porcentaje'];

    // ================= Valores y formato (igual que Classes\Sheetflow::formatear) =================
    function numero(v) {
        var t = String(v == null ? '' : v).replace(/[,$%\s]/g, '');
        return t !== '' && isFinite(t) ? parseFloat(t) : null;
    }
    function miles(n, dec) {
        var s = Math.abs(n).toFixed(dec);
        var p = s.split('.'), e = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        if (p[1]) { p[1] = p[1].replace(/0+$/, ''); }
        return (n < 0 && parseFloat(s) !== 0 ? '-' : '') + e + (p[1] ? '.' + p[1] : '');
    }
    function formatear(v, tipo) {
        v = String(v == null ? '' : v).trim();
        if (v === '') return '';
        var n = numero(v);
        if (tipo === 'numero') return n === null ? v : miles(n, 4);
        if (tipo === 'moneda') return n === null ? v : (n < 0 ? '-' : '') + '$' + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (tipo === 'porcentaje') return n === null ? v : miles(n, 2) + '%';
        if (tipo === 'fecha') { var m = /^(\d{4})-(\d\d)-(\d\d)$/.exec(v); return m ? m[3] + '/' + m[2] + '/' + m[1] : v; }
        if (tipo === 'casilla') return valorEspecial(v, tipo) === '1' ? '\u2714' : '';
        if (tipo === 'semaforo') return SEMAFORO[valorEspecial(v, tipo)] || v;
        return v;
    }
    var SEMAFORO = { verde: 'Verde', amarillo: 'Amarillo', rojo: 'Rojo' };
    /** Valor guardado de una casilla ('1' o '') o un semáforo (verde|amarillo|rojo o ''); igual que Sheetflow::normalizarValor */
    function valorEspecial(v, tipo) {
        v = String(v == null ? '' : v).trim().toLowerCase();
        if (tipo === 'casilla') return ['1', 'si', 's\u00ed', 'x', 'true', 'yes', '\u2714', '\u2713'].indexOf(v) >= 0 ? '1' : '';
        v = { green: 'verde', yellow: 'amarillo', red: 'rojo' }[v] || v;
        return SEMAFORO[v] ? v : '';
    }
    function esFormula(t) { return /^\s*=/.test(t || ''); }
    /** Texto que se guarda al terminar de editar: fechas d/m/a → ISO y números sin formato */
    function normalizar(txt, tipo) {
        txt = String(txt).replace(/\r/g, '');
        if (esFormula(txt) || txt.trim() === '') return esFormula(txt) ? txt.trim() : '';
        if (tipo === 'fecha') {
            var m = /^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})$/.exec(txt.trim());
            if (m) { var a = m[3].length === 2 ? '20' + m[3] : m[3]; return a + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2); }
            return txt.trim();
        }
        if (NUM.indexOf(tipo) >= 0) { var n = numero(txt); return n === null ? txt.trim() : String(n); }
        if (tipo === 'casilla' || tipo === 'semaforo') return valorEspecial(txt, tipo);
        return txt;
    }

    // ================= Formato de número por celda (igual que Classes\Sheetflow::formatoNumero) =================
    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    /**
     * Aplica un código de formato (0.00, #,##0, $#,##0.00, 0.0%, dd/mm/aaaa…).
     * Devuelve null si el código no aplica (se usa el formato del tipo de columna).
     */
    function formatoNumero(v, codigo) {
        codigo = String(codigo == null ? '' : codigo).trim();
        if (codigo === '') return null;
        var s = String(v == null ? '' : v).trim();
        if (/[dma]/i.test(codigo) && !/[#0]/.test(codigo)) {
            var f = /^(\d{4})-(\d\d)-(\d\d)/.exec(s);
            if (!f) return null;
            var fecha = new Date(Date.UTC(+f[1], +f[2] - 1, +f[3], 12));
            var mes = MESES[fecha.getUTCMonth()];
            return codigo.replace(/mmmm|mmm|aaaa|yyyy|yy|dd|d|mm|m/gi, function (tok) {
                var t = tok.toLowerCase();
                if (t === 'mmmm') return mes.charAt(0).toUpperCase() + mes.slice(1);
                if (t === 'mmm') return mes.slice(0, 3);
                if (t === 'aaaa' || t === 'yyyy') return String(fecha.getUTCFullYear());
                if (t === 'yy') return pad2(fecha.getUTCFullYear() % 100);
                if (t === 'dd') return pad2(fecha.getUTCDate());
                if (t === 'd') return String(fecha.getUTCDate());
                if (t === 'mm') return pad2(fecha.getUTCMonth() + 1);
                return String(fecha.getUTCMonth() + 1);
            });
        }
        var n = numero(s);
        if (n === null) return null;
        var m = /([#0,]*[#0](?:\.[0#]+)?)/.exec(codigo);
        if (!m) return null;
        var num = m[1], pref = codigo.slice(0, m.index), suf = codigo.slice(m.index + num.length);
        var pct = codigo.indexOf('%') >= 0;
        if (pct) { n *= 100; suf = suf.replace(/%/g, ''); }
        var dec = num.indexOf('.') >= 0 ? num.length - num.indexOf('.') - 1 : 0;
        var miles = num.split('.')[0].indexOf(',') >= 0;
        var fac = Math.pow(10, dec), x = n * fac;
        x = x >= 0 ? Math.floor(x + 0.5) : Math.ceil(x - 0.5);
        var abs = Math.abs(x / fac).toFixed(dec), partes = abs.split('.');
        if (miles) partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return (x < 0 ? '-' : '') + pref.replace(/"/g, '') + partes.join('.') + suf.replace(/"/g, '') + (pct ? '%' : '');
    }

    // ================= Fórmulas =================
    // Errores de valor (#DIV/0!, #VALOR!…) viajan como valor ({e}); los de sintaxis cortan el análisis. Debe dar lo mismo que Classes\SheetflowFormulas.
    var errores = { ref: '#REF!', div: '#DIV/0!', val: '#VALOR!', ciclo: '#CICLO!', nom: '#NOMBRE?', na: '#N/A', num: '#NUM!' };
    function valorCelda(r, c, visitando) {
        if (r < -1 || c < 0) return { e: errores.ref };
        if (r === -1) return c < S.cols.length ? { s: S.cols[c].n } : { v: 0, vacio: true };   // A1 = encabezado
        if (r >= S.rows.length || c >= S.cols.length) return { v: 0, vacio: true };           // fuera de la tabla = vacía, como en Excel
        var t = S.rows[r][c];
        if (!esFormula(t)) { var n = numero(t); return t === '' ? { v: 0, vacio: true } : n === null ? { s: t } : { v: n }; }
        var k = r + ',' + c;
        if (visitando[k]) return { e: errores.ciclo };
        visitando[k] = true;
        var res = evaluar(t.replace(/^\s*=/, ''), visitando, { r: r, c: c });
        delete visitando[k];
        return res;
    }
    function evaluar(src, visitando, actual) {
        var i = 0;
        function esp() { while (i < src.length && /\s/.test(src[i])) i++; }
        function sintaxis(e) { var x = new Error(e); x.sf = e; throw x; }
        function fallo(e) { var x = new Error(e); x.sf = e; x.valor = true; throw x; }
        function seguro(f) { try { return f(); } catch (e) { if (e.valor) return { e: e.sf }; throw e; } }
        function num1(x) {
            if (x.e) fallo(x.e);
            if (x.rango) fallo(errores.val);
            if (x.s !== undefined) { var n = numero(x.s); if (n === null) fallo(errores.val); return n; }
            return x.v;
        }
        function esTexto(x) { return x.s !== undefined && numero(x.s) === null; }
        function txt(x) {
            if (x.e) fallo(x.e);
            if (x.rango) fallo(errores.val);
            return x.s !== undefined ? x.s : (x.vacio ? '' : String(+x.v.toFixed(10)));
        }
        function comparacion() {
            var a = concat(); esp();
            var m = /^(<=|>=|<>|<|>|=)/.exec(src.slice(i));
            if (!m) return a;
            i += m[1].length;
            var b = concat();
            return seguro(function () {
                var x, y;
                if (esTexto(a) || esTexto(b)) { var s1 = txt(a).toLowerCase(), s2 = txt(b).toLowerCase(); x = s1 < s2 ? -1 : s1 > s2 ? 1 : 0; y = 0; }
                else { x = num1(a); y = num1(b); }
                return { v: ({ '=': x === y, '<>': x !== y, '<': x < y, '>': x > y, '<=': x <= y, '>=': x >= y })[m[1]] ? 1 : 0 };
            });
        }
        function concat() {
            var a = suma(); esp();
            while (src[i] === '&') { i++; var b = suma(); var aa = a; a = seguro(function () { return { s: txt(aa) + txt(b) }; }); esp(); }
            return a;
        }
        function suma() {
            var a = prod(); esp();
            while (i < src.length && (src[i] === '+' || src[i] === '-')) { var op = src[i++], b = prod(), aa = a; a = seguro(function () { return { v: op === '+' ? num1(aa) + num1(b) : num1(aa) - num1(b) }; }); esp(); }
            return a;
        }
        function prod() {
            var a = pot(); esp();
            while (i < src.length && (src[i] === '*' || src[i] === '/')) {
                var op = src[i++], b = pot(), aa = a;
                a = seguro(function () {
                    if (op === '/') { var d = num1(b); if (d === 0) fallo(errores.div); return { v: num1(aa) / d }; }
                    return { v: num1(aa) * num1(b) };
                });
                esp();
            }
            return a;
        }
        function pot() {
            var a = unario(); esp();
            if (src[i] === '^') { i++; var b = pot(), aa = a; a = seguro(function () { return { v: Math.pow(num1(aa), num1(b)) }; }); }
            return a;
        }
        function unario() {
            esp();
            if (src[i] === '-') { i++; var x = unario(); return seguro(function () { return { v: -num1(x) }; }); }
            if (src[i] === '+') { i++; return unario(); }
            return primario();
        }
        function args() {
            var out = []; esp();
            if (src[i] === ')') { i++; return out; }
            for (;;) {
                out.push(comparacion()); esp();
                if (src[i] === ',' || src[i] === ';') { i++; continue; }
                if (src[i] === ')') { i++; return out; }
                sintaxis(errores.val);
            }
        }
        function lista(a) {   // aplana rangos: números sueltos y rangos de celdas
            var nums = [];
            a.forEach(function (x) {
                if (x.rango) x.rango.forEach(function (c) { if (c.e) fallo(c.e); if (c.v !== undefined && !c.vacio) nums.push(c.v); });
                else if (x.e) fallo(x.e);
                else if (x.v !== undefined) nums.push(x.v);
                else if (x.s !== undefined) { var n = numero(x.s); if (n !== null) nums.push(n); }
            });
            return nums;
        }
        function cumple(c, crit) {
            if (c.e) return false;
            var t = crit.s !== undefined ? crit.s : (crit.v !== undefined ? String(+crit.v.toFixed(10)) : ''), op = '=', m = /^(<=|>=|<>|<|>|=)([\s\S]*)$/.exec(t);
            if (m) { op = m[1]; t = m[2]; }
            var valor = c.s !== undefined ? c.s : (c.vacio ? '' : String(+c.v.toFixed(10))), nc = numero(valor), nt = numero(t);
            if (nt !== null && nc !== null && valor !== '') return ({ '=': nc === nt, '<>': nc !== nt, '<': nc < nt, '>': nc > nt, '<=': nc <= nt, '>=': nc >= nt })[op];
            if (op === '=' || op === '<>') {
                var re = new RegExp('^' + t.replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*').replace(/\?/g, '.') + '$', 'i');
                return re.test(valor) === (op === '=');
            }
            if (nt !== null || valor === '') return false;
            var k = valor.toLowerCase() < t.toLowerCase() ? -1 : valor.toLowerCase() > t.toLowerCase() ? 1 : 0;
            return ({ '<': k < 0, '>': k > 0, '<=': k <= 0, '>=': k >= 0 })[op];
        }
        // ---------- Ayudantes de las funciones ----------
        function celdasDe(a) {
            var out = [];
            a.forEach(function (x) { if (x.rango) x.rango.forEach(function (c) { out.push(c); }); else out.push(x); });
            return out;
        }
        function textoDe(c) {
            if (!c) return '';
            if (c.e) return '';
            if (c.s !== undefined) return c.s;
            if (c.vacio) return '';
            return String(+(c.v || 0).toFixed(10));
        }
        function rangoDe(x) {
            if (!x || !x.rango || x.r0 === undefined) fallo(errores.val);
            return { vals: x.rango, r0: x.r0, c0: x.c0, r1: x.r1, c1: x.c1 };
        }
        function leerRango(r0, c0, r1, c1) {
            var out = [];
            for (var r = Math.min(r0, r1); r <= Math.max(r0, r1); r++) for (var c = Math.min(c0, c1); c <= Math.max(c0, c1); c++) out.push(valorCelda(r, c, visitando));
            return { rango: out, r0: Math.min(r0, r1), c0: Math.min(c0, c1), r1: Math.max(r0, r1), c1: Math.max(c0, c1) };
        }
        function enteroNoNeg(x) {
            var n = num1(x);
            if (n < 0) fallo(errores.val);
            return Math.trunc(n);
        }
        function redondear(n, dec, modo) {
            var f = Math.pow(10, dec || 0);
            if (!f) return 0;
            var x = n * f;
            if (modo === 'mas') x = x >= 0 ? Math.ceil(x) : Math.floor(x);
            else if (modo === 'menos') x = x >= 0 ? Math.floor(x) : Math.ceil(x);
            else x = x >= 0 ? Math.floor(x + 0.5) : Math.ceil(x - 0.5);
            return x / f;
        }
        function sumaProducto(a) {
            var grupos = [];
            a.forEach(function (x) {
                var g = [];
                (x.rango || [x]).forEach(function (c) {
                    if (c.e) fallo(c.e);
                    if (c.vacio) { g.push(0); return; }
                    g.push(c.v !== undefined && c.s === undefined ? +c.v : (numero(c.s || '') || 0));
                });
                grupos.push(g);
            });
            if (!grupos.length) return 0;
            var n = Math.max.apply(null, grupos.map(function (g) { return g.length; }));
            grupos.forEach(function (g) { if (g.length !== n && g.length !== 1) fallo(errores.val); });
            var tot = 0;
            for (var i = 0; i < n; i++) {
                var p = 1;
                grupos.forEach(function (g) { p *= g[g.length === 1 ? 0 : i]; });
                tot += p;
            }
            return tot;
        }
        function moda(n) {
            if (!n.length) fallo(errores.na);
            var cuenta = {}, orden = [];
            n.forEach(function (x) { var k = String(+x.toFixed(10)); if (!(k in cuenta)) { cuenta[k] = 0; orden.push(k); } cuenta[k]++; });
            var mejor = null;
            Object.keys(cuenta).forEach(function (k) { if (mejor === null || cuenta[k] > cuenta[mejor]) mejor = k; });
            if (!mejor || cuenta[mejor] < 2) fallo(errores.na);
            return +mejor;
        }
        function varianza(n, muestra, raiz) {
            var k = n.length;
            if (!k || (muestra && k < 2)) fallo(errores.div);
            var media = n.reduce(function (p, q) { return p + q; }, 0) / k, suma = 0;
            n.forEach(function (x) { suma += Math.pow(x - media, 2); });
            var v = suma / (muestra ? k - 1 : k);
            return raiz ? Math.sqrt(v) : v;
        }
        function kEsimo(n, k, mayor) {
            var i = Math.trunc(k);
            if (i < 1 || i > n.length) fallo(errores.num);
            n = n.slice().sort(function (a, b) { return mayor ? b - a : a - b; });
            return n[i - 1];
        }
        function jerarquia(x, n, orden) {
            if (!n.length) fallo(errores.na);
            if (!n.some(function (v) { return v === x; })) fallo(errores.na);
            var r = 0;
            n.forEach(function (v) { if (orden == 0 ? v > x : v < x) r++; });
            return r + 1;
        }
        function percentil(n, k) {
            if (!n.length || k < 0 || k > 1) fallo(errores.num);
            n = n.slice().sort(function (a, b) { return a - b; });
            var pos = (n.length - 1) * k, lo = Math.floor(pos), hi = Math.ceil(pos);
            return lo === hi ? n[lo] : n[lo] + (n[hi] - n[lo]) * (pos - lo);
        }
        function indicesCriterios(a, desde) {
            var rangos = [], crits = [];
            for (var i = desde; a[i] !== undefined && a[i + 1] !== undefined; i += 2) {
                if (!a[i].rango) fallo(errores.val);
                rangos.push(a[i].rango);
                crits.push(a[i + 1]);
            }
            if (!rangos.length) fallo(errores.val);
            var out = [];
            for (var k = 0; k < rangos[0].length; k++) {
                var ok = true;
                for (var j = 0; j < rangos.length; j++) {
                    var c = rangos[j][k] || { v: 0, vacio: true };
                    if (!cumple(c, crits[j])) { ok = false; break; }
                }
                if (ok) out.push(k);
            }
            return out;
        }
        function igualesCeldas(a, b) {
            if (!a || !b || a.e || b.e) return false;
            if (a.v !== undefined && a.s === undefined && b.v !== undefined && b.s === undefined) return +a.v === +b.v;
            return textoDe(a).toLowerCase() === textoDe(b).toLowerCase();
        }
        function cambiar(a) {
            for (var i = 1; i + 1 < a.length; i += 2) if (igualesCeldas(a[0] || { v: 0 }, a[i])) return a[i + 1];
            return a.length > 1 && a.length % 2 === 0 ? a[a.length - 1] : { e: errores.na };
        }
        function elegir(a) {
            var i = Math.trunc(num1(a[0] || { v: 0 }));
            if (i < 1 || i >= a.length) fallo(errores.val);
            return a[i];
        }
        function sustituir(t, viejo, nuevo, n) {
            if (viejo === '') return t;
            if (n <= 0) return t.split(viejo).join(nuevo);
            var off = 0, p = -1;
            for (var i = 0; i < n; i++) {
                p = t.indexOf(viejo, off);
                if (p < 0) return t;
                off = p + viejo.length;
            }
            return t.slice(0, p) + nuevo + t.slice(p + viejo.length);
        }
        function unirCadenas(a) {
            var d = txt(a[0] || { s: '' }), ign = a[1] !== undefined && num1(a[1]) !== 0, out = [];
            for (var i = 2; i < a.length; i++) {
                (a[i].rango || [a[i]]).forEach(function (c) {
                    if (c.e) fallo(c.e);
                    var t = textoDe(c);
                    if (ign && t === '') return;
                    out.push(t);
                });
            }
            return out.join(d);
        }
        function pad2(n) { return ('0' + n).slice(-2); }
        function isoDe(y, m, d) { return String(y).padStart(4, '0') + '-' + pad2(m) + '-' + pad2(d); }
        function fechaISO(x) {
            var t = txt(x).trim(), m = /^(\d{4})-(\d{1,2})-(\d{1,2})/.exec(t);
            if (m) return isoDe(+m[1], +m[2], +m[3]);
            m = /^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2,4})$/.exec(t);
            if (m) { var y = m[3].length === 2 ? '20' + m[3] : m[3]; return isoDe(+y, +m[2], +m[1]); }
            return null;
        }
        function fechaDate(iso) { var p = iso.split('-'); return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2], 12)); }
        function fechaParte(x, metodo) {
            var iso = fechaISO(x);
            if (!iso) fallo(errores.val);
            var d = fechaDate(iso);
            return metodo === 'Y' ? d.getUTCFullYear() : metodo === 'n' ? d.getUTCMonth() + 1 : d.getUTCDate();
        }
        function diasEntre(a, b) {
            if (!a || !b) fallo(errores.val);
            return Math.round((fechaDate(b).getTime() - fechaDate(a).getTime()) / 86400000);
        }
        function sumarMeses(iso, n, fin) {
            if (!iso) fallo(errores.val);
            var p = iso.split('-'), total = +p[0] * 12 + (+p[1] - 1) + n;
            var ny = Math.floor(total / 12), nm = total % 12 + 1;
            if (nm < 1) { nm += 12; ny--; }
            var ultimo = new Date(Date.UTC(ny, nm, 0, 12)).getUTCDate();
            return isoDe(ny, nm, fin ? ultimo : Math.min(+p[2], ultimo));
        }
        function siFecha(a) {
            var i = fechaISO(a[0] || {}), f = fechaISO(a[1] || {});
            if (!i || !f) fallo(errores.val);
            var u = txt(a[2] || { s: 'd' }).toUpperCase().trim();
            var p1 = i.split('-').map(Number), p2 = f.split('-').map(Number);
            if (f < i) fallo(errores.num);
            if (u === 'D') return diasEntre(i, f);
            if (u === 'Y') return p2[0] - p1[0] - ((p2[1] < p1[1] || (p2[1] === p1[1] && p2[2] < p1[2])) ? 1 : 0);
            if (u === 'M') return (p2[0] - p1[0]) * 12 + (p2[1] - p1[1]) - (p2[2] < p1[2] ? 1 : 0);
            if (u === 'YM') { var m = (p2[1] - p1[1] + 12) % 12; if (p2[2] < p1[2]) m = (m + 11) % 12; return m; }
            if (u === 'MD') {
                if (p2[2] >= p1[2]) return p2[2] - p1[2];
                var pm = p2[1] - 1, py = p2[0];
                if (pm < 1) { pm = 12; py--; }
                var diasMes = new Date(Date.UTC(py, pm, 0, 12)).getUTCDate();
                return diasMes - p1[2] + p2[2];
            }
            if (u === 'YD') return diasEntre(isoDe(2000, p1[1], p1[2]), isoDe(2000, p2[1], p2[2]));
            fallo(errores.num);
        }
        function numeroSemana(iso, tipo) {
            if (!iso) fallo(errores.val);
            var w = fechaDate(iso).getUTCDay();
            if (tipo === 2) return w === 0 ? 7 : w;
            if (tipo === 3) return w === 0 ? 6 : w - 1;
            return w + 1;
        }
        function diasLaborables(a) {
            var iso = fechaISO(a[0] || {});
            if (!iso) fallo(errores.val);
            var n = Math.trunc(num1(a[1] || { v: 0 })), feriados = {};
            if (a[2] && a[2].rango) a[2].rango.forEach(function (c) { var f = fechaISO(c); if (f) feriados[f] = true; });
            var d = fechaDate(iso), paso = n >= 0 ? 1 : -1, faltan = Math.abs(n);
            while (faltan > 0) {
                d.setUTCDate(d.getUTCDate() + paso);
                var w = d.getUTCDay(), f = isoDe(d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate());
                if (w === 0 || w === 6 || feriados[f]) continue;
                faltan--;
            }
            return isoDe(d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate());
        }
        function valorComparable(c) {
            if (!c || c.e) return null;
            if (c.v !== undefined && c.s === undefined) return +c.v;
            return textoDe(c);
        }
        function comparar(c, clave) {
            var a = valorComparable(c), b = valorComparable(clave);
            if (a === null || b === null) return 1;
            if (typeof a === 'number' && typeof b === 'number') return a < b ? -1 : a > b ? 1 : 0;
            var sa = String(a).toLowerCase(), sb = String(b).toLowerCase();
            return sa < sb ? -1 : sa > sb ? 1 : 0;
        }
        function filaBusqueda(r, clave, aprox, vertical) {
            var width = r.c1 - r.c0 + 1, height = r.r1 - r.r0 + 1;
            var n = vertical ? height : width, paso = vertical ? width : 1, idx = -1;
            for (var i = 0; i < n; i++) {
                var c = r.vals[i * paso];
                if (!c) continue;
                var cmp = comparar(c, clave);
                if (cmp === 0) return i;
                if (aprox && cmp < 0) idx = i;
            }
            return aprox ? idx : -1;
        }
        function buscarColumna(a) {
            var r = rangoDe(a[1] || {}), col = Math.trunc(num1(a[2] || { v: 0 })), width = r.c1 - r.c0 + 1;
            if (col < 1 || col > width) fallo(errores.ref);
            var aprox = a[3] === undefined || num1(a[3]) !== 0;
            var fila = filaBusqueda(r, a[0] || { s: '' }, aprox, true);
            if (fila < 0) fallo(errores.na);
            return r.vals[fila * width + (col - 1)] || { v: 0, vacio: true };
        }
        function buscarFila(a) {
            var r = rangoDe(a[1] || {}), fila = Math.trunc(num1(a[2] || { v: 0 }));
            var width = r.c1 - r.c0 + 1, height = r.r1 - r.r0 + 1;
            if (fila < 1 || fila > height) fallo(errores.ref);
            var aprox = a[3] === undefined || num1(a[3]) !== 0;
            var col = filaBusqueda(r, a[0] || { s: '' }, aprox, false);
            if (col < 0) fallo(errores.na);
            return r.vals[(fila - 1) * width + col] || { v: 0, vacio: true };
        }
        function coincidir(a) {
            var r = rangoDe(a[1] || {}), tipo = a[2] !== undefined ? Math.trunc(num1(a[2])) : 1;
            var vals = r.vals, clave = a[0] || { s: '' };
            if (tipo === 0) {
                for (var i = 0; i < vals.length; i++) if (comparar(vals[i], clave) === 0) return i + 1;
                fallo(errores.na);
            }
            var idx = -1;
            for (var j = 0; j < vals.length; j++) {
                var cmp = comparar(vals[j], clave);
                if (tipo > 0) { if (cmp <= 0) idx = j; } else if (cmp >= 0) idx = j;
            }
            if (idx < 0) fallo(errores.na);
            return idx + 1;
        }
        function indice(a) {
            var r = rangoDe(a[0] || {}), width = r.c1 - r.c0 + 1, height = r.r1 - r.r0 + 1;
            var fila = a[1] !== undefined ? Math.trunc(num1(a[1])) : 0;
            var col = a[2] !== undefined ? Math.trunc(num1(a[2])) : null;
            if (col === null && height === 1) { col = fila; fila = 1; }
            if (col === null) col = 1;
            if (fila < 1 || col < 1 || fila > height || col > width) fallo(errores.ref);
            return r.vals[(fila - 1) * width + (col - 1)];
        }
        function buscarX(a) {
            var r1 = rangoDe(a[1] || {}), r2 = rangoDe(a[2] || {}), clave = a[0] || { s: '' };
            var modo = a[4] !== undefined ? Math.trunc(num1(a[4])) : 0, idx = -1;
            for (var i = 0; i < r1.vals.length; i++) {
                var cmp = comparar(r1.vals[i], clave);
                if (cmp === 0) { idx = i; break; }
                if (modo === -1 && cmp < 0) idx = i;
                if (modo === 1 && cmp > 0) { idx = i; break; }
            }
            if (idx < 0) return a[3] || { e: errores.na };
            return r2.vals[idx] || a[3] || { e: errores.na };
        }
        function desref(a) {
            var base = a[0] || {}, ref = base.ref || {};
            var r0 = ref.r !== undefined ? ref.r : base.r0, c0 = ref.c !== undefined ? ref.c : base.c0;
            if (r0 === undefined || c0 === undefined) fallo(errores.val);
            var r1 = ref.r !== undefined ? ref.r : (base.r1 !== undefined ? base.r1 : r0);
            var c1 = ref.c !== undefined ? ref.c : (base.c1 !== undefined ? base.c1 : c0);
            var dr = Math.trunc(num1(a[1] || { v: 0 })), dc = Math.trunc(num1(a[2] || { v: 0 }));
            var alto = a[3] !== undefined ? Math.max(1, Math.trunc(num1(a[3]))) : (r1 - r0 + 1);
            var ancho = a[4] !== undefined ? Math.max(1, Math.trunc(num1(a[4]))) : (c1 - c0 + 1);
            return leerRango(r0 + dr, c0 + dc, r0 + dr + alto - 1, c0 + dc + ancho - 1);
        }
        function indirecto(a) {
            var t = txt(a[0] || { s: '' }).trim();
            var m = /^\$?([A-Za-z]{1,2})\$?(\d+)(?::\$?([A-Za-z]{1,2})\$?(\d+))?$/.exec(t);
            if (!m) fallo(errores.ref);
            var c0 = indiceLetra(m[1].toUpperCase()), r0 = parseInt(m[2], 10) - 2;
            if (m[3]) return leerRango(r0, c0, parseInt(m[4], 10) - 2, indiceLetra(m[3].toUpperCase()));
            var x = valorCelda(r0, c0, visitando);
            return Object.assign({}, x, { ref: { r: r0, c: c0 } });
        }
        /** Busca una partida o actividad del programa por código o nombre (config «programa») */
        function delProyecto(clave) {
            var arr = C.programa || [], k = String(clave == null ? '' : clave).trim().toLowerCase();
            if (!k || !arr.length) return null;
            var i, f;
            for (i = 0; i < arr.length; i++) if (String(arr[i].codigo).toLowerCase() === k) return arr[i];
            for (i = 0; i < arr.length; i++) if (String(arr[i].nombre).toLowerCase() === k) return arr[i];
            for (i = 0; i < arr.length; i++) { f = arr[i]; if (String(f.nombre).toLowerCase().indexOf(k) >= 0) return f; }
            return null;
        }
        /** AVANCE("1.2.3"), INICIO(…), FIN(…), DIAS.REST(…), PARTIDA(…) y TIPO(…) */
        function funcionProyecto(f, a) {
            var d = delProyecto(txt(a[0] || { s: '' }));
            if (!d) fallo(errores.na);
            if (f === 'AVANCE' || f === 'DIAS.REST') return { v: +(f === 'AVANCE' ? d.avance : d.dias_rest) };
            if (f === 'INICIO') return { s: String(d.inicio || '') };
            if (f === 'FIN') return { s: String(d.fin || '') };
            if (f === 'TIPO') return { s: String(d.tipo || '') };
            return { s: String(d.nombre || '') };
        }
        function pago(r, n, vp, vf, tipo) {
            vf = vf || 0; tipo = tipo || 0;
            if (n === 0) fallo(errores.div);
            if (r === 0) return -(vp + vf) / n;
            var p = Math.pow(1 + r, n);
            if (!p || p - 1 === 0) fallo(errores.num);
            return -(r * (vp * p + vf)) / ((1 + r * tipo) * (p - 1));
        }
        function valorFuturo(r, n, pagoV, vp, tipo) {
            vp = vp || 0; tipo = tipo || 0;
            if (r === 0) return -(vp + pagoV * n);
            var p = Math.pow(1 + r, n);
            return -(vp * p + pagoV * (1 + r * tipo) * (p - 1) / r);
        }
        function valorActual(r, n, pagoV, vf, tipo) {
            vf = vf || 0; tipo = tipo || 0;
            if (r === 0) return -(vf + pagoV * n);
            var p = Math.pow(1 + r, n);
            return -(vf + pagoV * (1 + r * tipo) * (p - 1) / r) / p;
        }
        function pagoInteres(r, per, n, vp, vf, tipo) {
            vf = vf || 0; tipo = tipo || 0;
            if (per < 1 || per > n) fallo(errores.num);
            if (r === 0) return 0;
            var p = Math.pow(1 + r, per - 1);
            var saldo = vp * p + pago(r, n, vp, vf, tipo) * (1 + r * tipo) * (p - 1) / r;
            return -saldo * r;
        }
        function tasa(nper, pagoV, vp, vf, tipo, guess) {
            vf = vf || 0; tipo = tipo || 0;
            var r = guess > 0 ? guess : 0.1;
            for (var i = 0; i < 100; i++) {
                var f = valorFuturo(r, nper, pagoV, vp, tipo) - vf;
                if (Math.abs(f) < 1e-9) return r;
                var h = 1e-6;
                var d = (valorFuturo(r + h, nper, pagoV, vp, tipo) - vf - f) / h;
                if (!d || !isFinite(d)) break;
                var nr = r - f / d;
                if (nr <= -1) nr = (r - 1) / 2;
                if (Math.abs(nr - r) < 1e-10) return nr;
                r = nr;
            }
            fallo(errores.num);
        }
        function tir(vals, guess) {
            if (!vals.length) fallo(errores.num);
            function f(r) { var t = 0; vals.forEach(function (v, i) { t += v / Math.pow(1 + r, i); }); return t; }
            var lo = -0.9999, hi = 10, flo = f(lo), fhi = f(hi);
            if (flo * fhi > 0) { hi = 1000; fhi = f(hi); if (flo * fhi > 0) fallo(errores.num); }
            for (var i = 0; i < 200; i++) {
                var mid = (lo + hi) / 2, fm = f(mid);
                if (Math.abs(fm) < 1e-9) return mid;
                if (flo * fm <= 0) { hi = mid; fhi = fm; } else { lo = mid; flo = fm; }
            }
            return (lo + hi) / 2;
        }
        function valorNumerico(x, permisivo) {
            if (x.e) fallo(x.e);
            if (x.v !== undefined && x.s === undefined) return +x.v;
            var t = textoDe(x).trim();
            if (t === '') return 0;
            var pct = /%$/.test(t);
            t = t.replace(/[ %$€]/g, '');
            if (t.indexOf(',') >= 0 && t.indexOf('.') < 0) t = t.replace(/,/g, '.');
            var n = numero(t);
            if (n === null) fallo(errores.val);
            if (pct && permisivo) n /= 100;
            return n;
        }
        function textoFormato(x, fmt) {
            fmt = String(fmt || '').trim();
            if (fmt === '') return textoDe(x);
            var iso = fechaISO(x);
            if (iso && /(aaaa|yyyy|yy|mmmm|mmm|mm|dd|m|d)/i.test(fmt)) {
                var d = fechaDate(iso);
                var meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                var mes = meses[d.getUTCMonth()];
                return fmt.replace(/aaaa|yyyy|yy|mmmm|mmm|mm|dd|m|d/g, function (tok) {
                    switch (tok.toLowerCase()) {
                        case 'aaaa': case 'yyyy': return String(d.getUTCFullYear());
                        case 'yy': return pad2(d.getUTCFullYear() % 100);
                        case 'mmmm': return mes;
                        case 'mmm': return mes.slice(0, 3);
                        case 'mm': return pad2(d.getUTCMonth() + 1);
                        case 'dd': return pad2(d.getUTCDate());
                        case 'm': return String(d.getUTCMonth() + 1);
                        default: return String(d.getUTCDate());
                    }
                });
            }
            var num = x.v !== undefined && x.s === undefined ? +x.v : numero(textoDe(x));
            if (num === null) return textoDe(x);
            var mm = fmt.match(/[.,][0#]+/g), dec = 0;
            if (mm) {
                var last = mm[mm.length - 1];
                if (last.charAt(0) === '.' || (mm.length === 1 && /^[.,]0+$/.test(last) && fmt.indexOf('.') < 0)) dec = last.length - 1;
            }
            var miles = fmt.indexOf(',') >= 0 && (fmt.indexOf('.') >= 0 || /^[^,]*#,##/.test(fmt));
            var pct = fmt.indexOf('%') >= 0;
            if (pct) num *= 100;
            var pref = '', pm = /^(.*?)[0#]/.exec(fmt);
            if (pm) pref = pm[1].replace(/"/g, '').trim();
            var neg = num < 0 ? '-' : '', abs = Math.abs(redondear(num, dec)).toFixed(dec), parts = abs.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, miles ? ',' : '');
            return pref + neg + parts.join('.') + (pct ? '%' : '');
        }

        function funcion(f, a) {
            var n, x, r, i;
            switch (f) {
                // ---------- Matemáticas ----------
                case 'SUMA': case 'SUM': return { v: lista(a).reduce(function (p, q) { return p + q; }, 0) };
                case 'PROMEDIO': case 'AVERAGE': case 'PROM': n = lista(a); if (!n.length) fallo(errores.div); return { v: n.reduce(function (p, q) { return p + q; }, 0) / n.length };
                case 'MIN': n = lista(a); return { v: n.length ? Math.min.apply(null, n) : 0 };
                case 'MAX': n = lista(a); return { v: n.length ? Math.max.apply(null, n) : 0 };
                case 'CONTAR': case 'COUNT': return { v: lista(a).length };
                case 'CONTARA': case 'COUNTA':
                    n = 0;
                    celdasDe(a).forEach(function (c) { if (c.e || c.vacio) return; if (c.s !== undefined && c.s === '') return; n++; });
                    return { v: n };
                case 'CONTAR.BLANCO': case 'COUNTBLANK':
                    n = 0;
                    celdasDe(a).forEach(function (c) { if (c.e) return; if (c.vacio || (c.s !== undefined && c.s === '')) n++; });
                    return { v: n };
                case 'SUMAPRODUCTO': case 'SUMPRODUCT': return { v: sumaProducto(a) };
                case 'SUMA.CUADRADOS': case 'SUMSQ': return { v: lista(a).reduce(function (p, q) { return p + q * q; }, 0) };
                case 'ABS': return { v: Math.abs(num1(a[0] || { v: 0 })) };
                case 'ENTERO': case 'INT': return { v: Math.floor(num1(a[0] || { v: 0 })) };
                case 'TRUNCAR': case 'TRUNC': return { v: redondear(num1(a[0] || { v: 0 }), a[1] ? Math.trunc(num1(a[1])) : 0, 'menos') };
                case 'RESIDUO': case 'MOD': {
                    var dm = num1(a[1] || { v: 0 });
                    if (dm === 0) fallo(errores.div);
                    var vm = num1(a[0] || { v: 0 });
                    return { v: vm - dm * Math.floor(vm / dm) };
                }
                case 'POTENCIA': case 'POWER': return { v: Math.pow(num1(a[0] || { v: 0 }), num1(a[1] || { v: 0 })) };
                case 'RAIZ': case 'SQRT': { var rv = num1(a[0] || { v: 0 }); if (rv < 0) fallo(errores.num); return { v: Math.sqrt(rv) }; }
                case 'EXP': return { v: Math.exp(num1(a[0] || { v: 0 })) };
                case 'LN': case 'LOG': {
                    var lv = num1(a[0] || { v: 0 });
                    if (lv <= 0) fallo(errores.num);
                    var base = a[1] !== undefined ? num1(a[1]) : Math.E;
                    if (base <= 0 || base === 1) fallo(errores.num);
                    return { v: Math.log(lv) / Math.log(base) };
                }
                case 'LOG10': { var l10 = num1(a[0] || { v: 0 }); if (l10 <= 0) fallo(errores.num); return { v: Math.log10(l10) }; }
                case 'PI': return { v: Math.PI };
                case 'SIGNO': case 'SIGN': { var sg = num1(a[0] || { v: 0 }); return { v: sg > 0 ? 1 : sg < 0 ? -1 : 0 }; }
                case 'COCIENTE': case 'QUOTIENT': { var cd = num1(a[1] || { v: 0 }); if (cd === 0) fallo(errores.div); return { v: Math.trunc(num1(a[0] || { v: 0 }) / cd) }; }
                case 'REDONDEAR': case 'ROUND': return { v: redondear(num1(a[0] || { v: 0 }), a[1] ? Math.trunc(num1(a[1])) : 0) };
                case 'REDONDEAR.MAS': case 'ROUNDUP': return { v: redondear(num1(a[0] || { v: 0 }), a[1] ? Math.trunc(num1(a[1])) : 0, 'mas') };
                case 'REDONDEAR.MENOS': case 'ROUNDDOWN': return { v: redondear(num1(a[0] || { v: 0 }), a[1] ? Math.trunc(num1(a[1])) : 0, 'menos') };
                case 'REDONDEAR.MULT': case 'MROUND': { var mmu = num1(a[1] || { v: 0 }); if (mmu === 0) return { v: 0 }; return { v: redondear(num1(a[0] || { v: 0 }) / mmu, 0) * mmu }; }
                case 'MULTIPLO.SUPERIOR': case 'CEILING': { var cm = num1(a[1] || { v: 1 }); if (cm === 0) fallo(errores.div); return { v: Math.ceil(num1(a[0] || { v: 0 }) / cm) * cm }; }
                case 'MULTIPLO.INFERIOR': case 'FLOOR': { var fm = num1(a[1] || { v: 1 }); if (fm === 0) fallo(errores.div); return { v: Math.floor(num1(a[0] || { v: 0 }) / fm) * fm }; }
                case 'M.C.D.': case 'GCD': {
                    var gx = Math.abs(Math.trunc(num1(a[0] || { v: 0 }))), gy = Math.abs(Math.trunc(num1(a[1] || { v: 0 })));
                    while (gy) { var gt = gy; gy = gx % gy; gx = gt; }
                    return { v: gx };
                }
                case 'M.C.M.': case 'LCM': {
                    var lx = Math.abs(Math.trunc(num1(a[0] || { v: 0 }))), ly = Math.abs(Math.trunc(num1(a[1] || { v: 0 })));
                    if (lx === 0 || ly === 0) return { v: 0 };
                    var la = lx, lb = ly;
                    while (lb) { var lt = lb; lb = la % lb; la = lt; }
                    return { v: lx / la * ly };
                }
                case 'ALEATORIO': case 'RAND': return { v: Math.random() };
                case 'ALEATORIO.ENTRE': case 'RANDBETWEEN': {
                    var rlo = Math.trunc(num1(a[0] || { v: 0 })), rhi = Math.trunc(num1(a[1] || { v: 1 }));
                    if (rlo > rhi) { var rt = rlo; rlo = rhi; rhi = rt; }
                    return { v: Math.floor(Math.random() * (rhi - rlo + 1)) + rlo };
                }
                case 'SUBTOTALES': case 'SUBTOTAL': {
                    var st = Math.trunc(num1(a[0] || { v: 0 })), sv = lista(a.slice(1));
                    if (st === 1) return { v: sv.length ? sv.reduce(function (p, q) { return p + q; }, 0) / sv.length : 0 };
                    if (st === 2) return { v: sv.length };
                    if (st === 4) return { v: sv.length ? Math.max.apply(null, sv) : 0 };
                    if (st === 5) return { v: sv.length ? Math.min.apply(null, sv) : 0 };
                    if (st === 9) return { v: sv.reduce(function (p, q) { return p + q; }, 0) };
                    fallo(errores.val);
                }

                // ---------- Estadística ----------
                case 'MEDIANA': case 'MEDIAN':
                    n = lista(a); if (!n.length) fallo(errores.num);
                    n = n.slice().sort(function (p, q) { return p - q; });
                    return { v: n.length % 2 ? n[(n.length - 1) / 2] : (n[n.length / 2 - 1] + n[n.length / 2]) / 2 };
                case 'MODA': case 'MODE': return { v: moda(lista(a)) };
                case 'DESVEST': case 'STDEV': return { v: varianza(lista(a), true, true) };
                case 'DESVEST.P': case 'STDEVP': return { v: varianza(lista(a), true, false) };
                case 'VAR': return { v: varianza(lista(a), false, true) };
                case 'VAR.P': case 'VARP': return { v: varianza(lista(a), false, false) };
                case 'K.ESIMO.MAYOR': case 'LARGE': return { v: kEsimo(lista(a), num1(a[1] || { v: 0 }), true) };
                case 'K.ESIMO.MENOR': case 'SMALL': return { v: kEsimo(lista(a), num1(a[1] || { v: 0 }), false) };
                case 'JERARQUIA': case 'RANK': return { v: jerarquia(num1(a[0] || { v: 0 }), lista(a.slice(1, 2)), a[2] !== undefined ? num1(a[2]) : 0) };
                case 'PERCENTIL': case 'PERCENTILE': return { v: percentil(lista(a), num1(a[1] || { v: 0 })) };
                case 'CUARTIL': case 'QUARTILE': { var qq = Math.trunc(num1(a[1] || { v: 0 })); if (qq < 0 || qq > 4) fallo(errores.num); return { v: percentil(lista(a), qq / 4) }; }
                case 'MAX.SI': case 'MAXIFS':
                case 'MIN.SI': case 'MINIFS': {
                    r = rangoDe(a[0] || {});
                    var idxMax = indicesCriterios(a, 1), vmax = [];
                    idxMax.forEach(function (k) {
                        var c = r.vals[k] || { v: 0, vacio: true };
                        if (c.e) fallo(c.e);
                        var xv = c.s !== undefined ? numero(c.s) : c.v;
                        if (xv !== null && xv !== undefined && !c.vacio) vmax.push(+xv);
                    });
                    if (!vmax.length) return { v: 0 };
                    return { v: (f === 'MAX.SI' || f === 'MAXIFS') ? Math.max.apply(null, vmax) : Math.min.apply(null, vmax) };
                }
                case 'SUMAR.SI.CONJUNTO': case 'SUMIFS':
                case 'PROMEDIO.SI.CONJUNTO': case 'AVERAGEIFS':
                case 'CONTAR.SI.CONJUNTO': case 'COUNTIFS': {
                    var esCuenta = f === 'CONTAR.SI.CONJUNTO' || f === 'COUNTIFS';
                    var sumR = esCuenta ? null : rangoDe(a[0] || {});
                    var idxC = indicesCriterios(a, esCuenta ? 0 : 1);
                    if (esCuenta) return { v: idxC.length };
                    var totC = 0;
                    idxC.forEach(function (k) {
                        var c = sumR.vals[k] || { v: 0, vacio: true };
                        if (c.e) fallo(c.e);
                        var xv = c.s !== undefined ? numero(c.s) : c.v;
                        totC += xv === null || xv === undefined ? 0 : +xv;
                    });
                    if (f === 'PROMEDIO.SI.CONJUNTO' || f === 'AVERAGEIFS') { if (!idxC.length) fallo(errores.div); return { v: totC / idxC.length }; }
                    return { v: totC };
                }

                // ---------- Lógica e información ----------
                case 'SI': case 'IF': return num1(a[0] || { v: 0 }) ? (a[1] || { v: 0 }) : (a[2] || { v: 0 });
                case 'Y': case 'AND': case 'O': case 'OR': {
                    if (!a.length) fallo(errores.val);
                    var y = f === 'Y' || f === 'AND';
                    for (var q = 0; q < a.length; q++) {
                        var items = a[q].rango || [a[q]];
                        for (var z = 0; z < items.length; z++) {
                            var v = items[z];
                            if (v.e) fallo(v.e);
                            if (v.vacio) continue;
                            var b = num1(v) !== 0;
                            if (y && !b) return { v: 0 };
                            if (!y && b) return { v: 1 };
                        }
                    }
                    return { v: y ? 1 : 0 };
                }
                case 'NO': case 'NOT': return { v: num1(a[0] || { v: 0 }) === 0 ? 1 : 0 };
                case 'SI.ERROR': case 'IFERROR': return (a[0] || {}).e ? (a[1] || { s: '' }) : (a[0] || { v: 0 });
                case 'SI.ND': case 'IFNA': return (a[0] || {}).e === errores.na ? (a[1] || { s: '' }) : (a[0] || { v: 0 });
                case 'ES.ERROR': case 'ISERROR': return { v: (a[0] || {}).e ? 1 : 0 };
                case 'ES.NUMERO': case 'ISNUMBER': x = a[0] || {}; return { v: x.v !== undefined && x.s === undefined ? 1 : 0 };
                case 'ES.TEXTO': case 'ISTEXT': x = a[0] || {}; return { v: x.s !== undefined ? 1 : 0 };
                case 'ES.BLANCO': case 'ISBLANK': x = a[0] || {}; return { v: (x.vacio || (x.s !== undefined && x.s === '')) ? 1 : 0 };
                case 'ES.PAR': case 'ISEVEN': return { v: Math.trunc(Math.floor(num1(a[0] || { v: 0 }))) % 2 === 0 ? 1 : 0 };
                case 'ES.IMPAR': case 'ISODD': return { v: Math.abs(Math.trunc(Math.floor(num1(a[0] || { v: 0 })))) % 2 === 1 ? 1 : 0 };
                case 'ESLOGICO': case 'ISLOGICAL': x = a[0] || {}; return { v: (x.v !== undefined && (+x.v === 0 || +x.v === 1)) ? 1 : 0 };
                case 'CAMBIAR': case 'SWITCH': return cambiar(a);
                case 'ELEGIR': case 'CHOOSE': return elegir(a);
                case 'SUMAR.SI': case 'SUMIF': case 'CONTAR.SI': case 'COUNTIF': case 'PROMEDIO.SI': case 'AVERAGEIF': {
                    var rango = a[0] && (a[0].rango || [a[0]]);
                    if (!rango || !a[1]) fallo(errores.val);
                    var cuenta = f === 'CONTAR.SI' || f === 'COUNTIF', prom = f === 'PROMEDIO.SI' || f === 'AVERAGEIF';
                    var sumar = cuenta ? null : ((a[2] && a[2].rango) || rango), cnt = 0, tot = 0;
                    rango.forEach(function (c, idx) {
                        if (!cumple(c, a[1])) return;
                        cnt++;
                        if (sumar) { var vv = sumar[idx] || { v: 0, vacio: true }; if (vv.e) fallo(vv.e); var xv = vv.s !== undefined ? numero(vv.s) : vv.v; tot += xv === null || xv === undefined ? 0 : xv; }
                    });
                    if (cuenta) return { v: cnt };
                    if (prom) { if (!cnt) fallo(errores.div); return { v: tot / cnt }; }
                    return { v: tot };
                }

                // ---------- Texto ----------
                case 'IZQUIERDA': case 'LEFT': return { s: txt(a[0] || { s: '' }).slice(0, enteroNoNeg(a[1] || { v: 1 })) };
                case 'DERECHA': case 'RIGHT': { var td = txt(a[0] || { s: '' }), nd = enteroNoNeg(a[1] || { v: 1 }); return { s: nd === 0 ? '' : td.slice(-nd) }; }
                case 'EXTRAE': case 'MID': {
                    var te = txt(a[0] || { s: '' }), ini = Math.trunc(num1(a[1] || { v: 1 }));
                    var ne = a[2] !== undefined ? enteroNoNeg(a[2]) : te.length;
                    if (ini < 1) fallo(errores.val);
                    return { s: te.substr(ini - 1, ne) };
                }
                case 'LARGO': case 'LEN': return { v: txt(a[0] || { s: '' }).length };
                case 'MAYUSC': case 'UPPER': return { s: txt(a[0] || { s: '' }).toUpperCase() };
                case 'MINUSC': case 'LOWER': return { s: txt(a[0] || { s: '' }).toLowerCase() };
                case 'NOMPROPIO': case 'PROPER': return { s: txt(a[0] || { s: '' }).toLowerCase().replace(/(^|[\s\-])([a-záéíóúñü])/g, function (m, a1, a2) { return a1 + a2.toUpperCase(); }) };
                case 'RECORTAR': case 'ESPACIOS': case 'TRIM': return { s: txt(a[0] || { s: '' }).replace(/\s+/g, ' ').trim() };
                case 'SUSTITUIR': case 'SUBSTITUTE': return { s: sustituir(txt(a[0] || { s: '' }), txt(a[1] || { s: '' }), txt(a[2] || { s: '' }), a[3] !== undefined ? Math.trunc(num1(a[3])) : 0) };
                case 'ENCONTRAR': case 'FIND': {
                    var pos = txt(a[1] || { s: '' }).indexOf(txt(a[0] || { s: '' }), a[2] !== undefined ? Math.max(0, Math.trunc(num1(a[2])) - 1) : 0);
                    if (pos < 0) fallo(errores.val);
                    return { v: pos + 1 };
                }
                case 'HALLAR': case 'SEARCH': {
                    var pos2 = txt(a[1] || { s: '' }).toLowerCase().indexOf(txt(a[0] || { s: '' }).toLowerCase(), a[2] !== undefined ? Math.max(0, Math.trunc(num1(a[2])) - 1) : 0);
                    if (pos2 < 0) fallo(errores.val);
                    return { v: pos2 + 1 };
                }
                case 'REPETIR': case 'REPT': { var nr = enteroNoNeg(a[1] || { v: 0 }); if (nr > 1000) fallo(errores.val); return { s: txt(a[0] || { s: '' }).repeat(nr) }; }
                case 'CONCATENAR': case 'CONCATENATE': case 'CONCAT': { var sc = ''; celdasDe(a).forEach(function (c) { sc += textoDe(c); }); return { s: sc }; }
                case 'UNIRCADENAS': case 'TEXTJOIN': return { s: unirCadenas(a) };
                case 'TEXTO': case 'TEXT': return { s: textoFormato(a[0] || { v: 0 }, txt(a[1] || { s: '' })) };
                case 'VALOR': return { v: valorNumerico(a[0] || { s: '' }, false) };
                case 'NUMERO.VALOR': case 'NUMBERVALUE': return { v: valorNumerico(a[0] || { s: '' }, true) };
                case 'T': x = a[0] || {}; return { s: x.s !== undefined ? x.s : '' };

                // ---------- Búsqueda y referencias ----------
                case 'BUSCARV': case 'VLOOKUP': return buscarColumna(a);
                case 'BUSCARH': case 'HLOOKUP': return buscarFila(a);
                case 'COINCIDIR': case 'MATCH': return { v: coincidir(a) };
                case 'INDICE': case 'INDEX': return indice(a);
                case 'BUSCARX': case 'XLOOKUP': return buscarX(a);
                case 'FILA': case 'ROW':
                    if (a[0] === undefined) return { v: ((actual ? actual.r : 0) + 2) };
                    return { v: ((a[0].ref ? a[0].ref.r : a[0].r0 !== undefined ? a[0].r0 : 0) + 2) };
                case 'COLUMNA': case 'COLUMN':
                    if (a[0] === undefined) return { v: ((actual ? actual.c : 0) + 1) };
                    return { v: ((a[0].ref ? a[0].ref.c : a[0].c0 !== undefined ? a[0].c0 : 0) + 1) };
                case 'DIRECCION': case 'ADDRESS': {
                    var rr = Math.trunc(num1(a[0] || { v: 1 })), cc = Math.trunc(num1(a[1] || { v: 1 })), abs = a[2] !== undefined ? Math.trunc(num1(a[2])) : 1;
                    if (rr < 1 || cc < 1) fallo(errores.val);
                    var l = letra(cc - 1);
                    return { s: (abs === 1 || abs === 3 ? '$' : '') + l + (abs === 1 || abs === 2 ? '$' : '') + rr };
                }
                case 'DESREF': case 'OFFSET': return desref(a);
                case 'INDIRECTO': case 'INDIRECT': return indirecto(a);

                // ---------- Fórmulas del proyecto ----------
                case 'AVANCE': case 'INICIO': case 'FIN': case 'DIAS.REST': case 'PARTIDA': case 'NOMBRE.PARTIDA': case 'TIPO':
                    return funcionProyecto(f, a);

                // ---------- Fechas ----------
                case 'HOY': case 'TODAY': {
                    var hoy = new Date();
                    return { s: isoDe(hoy.getFullYear(), hoy.getMonth() + 1, hoy.getDate()) };
                }
                case 'AHORA': case 'NOW': {
                    var ah = new Date();
                    return { s: isoDe(ah.getFullYear(), ah.getMonth() + 1, ah.getDate()) + ' ' + pad2(ah.getHours()) + ':' + pad2(ah.getMinutes()) + ':' + pad2(ah.getSeconds()) };
                }
                case 'FECHA': case 'DATE': {
                    var fy = Math.trunc(num1(a[0] || { v: 0 })), fm = Math.trunc(num1(a[1] || { v: 1 })), fd = Math.trunc(num1(a[2] || { v: 1 }));
                    if (fm < 1 || fm > 12 || fd < 1 || fd > 31) {
                        var ft = new Date(Date.UTC(fy, fm - 1, fd, 12));
                        if (isNaN(ft.getTime())) fallo(errores.num);
                        return { s: isoDe(ft.getUTCFullYear(), ft.getUTCMonth() + 1, ft.getUTCDate()) };
                    }
                    return { s: isoDe(fy, fm, fd) };
                }
                case 'AÑO': case 'ANO': case 'YEAR': return { v: fechaParte(a[0] || {}, 'Y') };
                case 'MES': case 'MONTH': return { v: fechaParte(a[0] || {}, 'n') };
                case 'DIA': case 'DAY': return { v: fechaParte(a[0] || {}, 'j') };
                case 'DIAS': case 'DAYS': return { v: diasEntre(fechaISO(a[1] || {}), fechaISO(a[0] || {})) };
                case 'FECHA.MES': case 'EDATE': return { s: sumarMeses(fechaISO(a[0] || {}), Math.trunc(num1(a[1] || { v: 0 })), false) };
                case 'FIN.MES': case 'EOMONTH': return { s: sumarMeses(fechaISO(a[0] || {}), Math.trunc(num1(a[1] || { v: 0 })), true) };
                case 'SIFECHA': case 'DATEDIF': return { v: siFecha(a) };
                case 'NUM.DE.SEMANA': case 'WEEKDAY': return { v: numeroSemana(fechaISO(a[0] || {}), a[1] !== undefined ? Math.trunc(num1(a[1])) : 1) };
                case 'DIAS.LAB': case 'WORKDAY': return { s: diasLaborables(a) };
                case 'DIAS.LAB.INTL': case 'WORKDAY.INTL': return { s: diasLaborables([a[0] || {}, a[1] || { v: 0 }, a[3] || {}]) };

                // ---------- Finanzas ----------
                case 'PAGO': case 'PMT': return { v: pago(num1(a[0] || { v: 0 }), num1(a[1] || { v: 0 }), num1(a[2] || { v: 0 }), a[3] !== undefined ? num1(a[3]) : 0, a[4] !== undefined ? num1(a[4]) : 0) };
                case 'VA': case 'PV': return { v: valorActual(num1(a[0] || { v: 0 }), num1(a[1] || { v: 0 }), num1(a[2] || { v: 0 }), a[3] !== undefined ? num1(a[3]) : 0, a[4] !== undefined ? num1(a[4]) : 0) };
                case 'VF': case 'FV': return { v: valorFuturo(num1(a[0] || { v: 0 }), num1(a[1] || { v: 0 }), num1(a[2] || { v: 0 }), a[3] !== undefined ? num1(a[3]) : 0, a[4] !== undefined ? num1(a[4]) : 0) };
                case 'PAGOINT': case 'IPMT': return { v: pagoInteres(num1(a[0] || { v: 0 }), num1(a[1] || { v: 1 }), num1(a[2] || { v: 0 }), num1(a[3] || { v: 0 }), a[4] !== undefined ? num1(a[4]) : 0, a[5] !== undefined ? num1(a[5]) : 0) };
                case 'PAGOPRIN': case 'PPMT': {
                    var rp = num1(a[0] || { v: 0 }), pp = num1(a[1] || { v: 1 }), np = num1(a[2] || { v: 0 }), vp = num1(a[3] || { v: 0 });
                    var vfp = a[4] !== undefined ? num1(a[4]) : 0, tp = a[5] !== undefined ? num1(a[5]) : 0;
                    return { v: pago(rp, np, vp, vfp, tp) - pagoInteres(rp, pp, np, vp, vfp, tp) };
                }
                case 'TASA': case 'RATE': return { v: tasa(num1(a[0] || { v: 0 }), num1(a[1] || { v: 0 }), num1(a[2] || { v: 0 }), a[3] !== undefined ? num1(a[3]) : 0, a[4] !== undefined ? num1(a[4]) : 0, a[5] !== undefined ? num1(a[5]) : 0.1) };
                case 'NPER': {
                    var rn = num1(a[0] || { v: 0 }), pn = num1(a[1] || { v: 0 }), vn = num1(a[2] || { v: 0 });
                    var vfn = a[3] !== undefined ? num1(a[3]) : 0, tn = a[4] !== undefined ? num1(a[4]) : 0;
                    if (rn === 0) { if (pn === 0) fallo(errores.div); return { v: -(vn + vfn) / pn }; }
                    var numn = pn * (1 + rn * tn) - vfn * rn, denn = vn * rn + pn * (1 + rn * tn);
                    if (numn === 0 || denn === 0 || numn / denn <= 0) fallo(errores.num);
                    return { v: Math.log(numn / denn) / Math.log(1 + rn) };
                }
                case 'VNA': case 'NPV': {
                    var rv2 = num1(a[0] || { v: 0 }), ts = 0;
                    lista(a.slice(1)).forEach(function (x, k) { ts += x / Math.pow(1 + rv2, k + 1); });
                    return { v: ts };
                }
                case 'TIR': case 'IRR': return { v: tir(lista(a), a[1] !== undefined ? num1(a[1]) : 0.1) };

                default: fallo(errores.nom);
            }
        }

        function primario() {
            esp();
            var rest = src.slice(i), m;
            if ((m = /^(\d+([.,]\d+)?|[.]\d+)/.exec(rest))) { i += m[0].length; var v = parseFloat(m[0].replace(',', '.')); esp(); if (src[i] === '%') { i++; v /= 100; } return { v: v }; }
            if ((m = /^(VERDADERO|FALSO|TRUE|FALSE)\b/i.exec(rest))) { i += m[0].length; return { v: /^(V|T)/i.test(m[1]) ? 1 : 0 }; }
            if ((m = /^#(REF!|N\/A|NUM!|DIV\/0!|VALOR!|CICLO!|NOMBRE\?)/.exec(rest))) { i += m[0].length; return { e: '#' + m[1] }; }
            if (src[i] === '(') { i++; var x = comparacion(); esp(); if (src[i] !== ')') sintaxis(errores.val); i++; return x; }
            if (src[i] === '"') { var j = src.indexOf('"', i + 1); if (j < 0) sintaxis(errores.val); var s = src.slice(i + 1, j); i = j + 1; return { s: s }; }
            if ((m = /^\$?([A-Za-z]{1,2})\$?(\d+)(?::\$?([A-Za-z]{1,2})\$?(\d+))?(?![A-Za-z(])/.exec(rest))) {
                i += m[0].length;
                var c0 = indiceLetra(m[1].toUpperCase()), r0 = parseInt(m[2], 10) - 2;
                if (m[3]) {
                    var c1 = indiceLetra(m[3].toUpperCase()), r1 = parseInt(m[4], 10) - 2, out = [];
                    for (var r = Math.min(r0, r1); r <= Math.max(r0, r1); r++) for (var c = Math.min(c0, c1); c <= Math.max(c0, c1); c++) out.push(valorCelda(r, c, visitando));
                    return { rango: out, r0: Math.min(r0, r1), c0: Math.min(c0, c1), r1: Math.max(r0, r1), c1: Math.max(c0, c1) };
                }
                var xc = valorCelda(r0, c0, visitando);
                xc = Object.assign({}, xc, { ref: { r: r0, c: c0 } });
                return xc;
            }
            if ((m = /^([A-Za-zÁÉÍÓÚáéíóúÑñ.]+)\s*\(/.exec(rest))) {
                i += m[0].length;
                var f = m[1].toUpperCase().replace(/Á/g, 'A').replace(/É/g, 'E').replace(/Í/g, 'I').replace(/Ó/g, 'O').replace(/Ú/g, 'U').replace(/Ñ/g, 'N'), a = args();
                return seguro(function () { return funcion(f, a); });
            }
            sintaxis(errores.val);
        }
        try {
            var r = comparacion(); esp();
            if (r.rango) {
                if (r.rango.length === 1) return r.rango[0];
                sintaxis(errores.val);
            }
            if (i < src.length) sintaxis(errores.val);
            return r;
        } catch (e) { return { e: e.sf || errores.val }; }
    }
    function texto(res) {
        if (res.e) return res.e;
        if (res.s !== undefined) return res.s;
        return String(+res.v.toFixed(10));
    }
    function recalcular() {
        var calc = {};
        S.rows.forEach(function (fila, r) {
            fila.forEach(function (t, c) { if (esFormula(t)) calc[r + ',' + c] = texto(valorCelda(r, c, {})); });
        });
        S.calc = calc;
        var tot = {};
        S.cols.forEach(function (col, c) {
            if (!col.tot) return;
            var vals = [], llenas = 0;
            S.rows.forEach(function (fila, r) {
                var v = esFormula(fila[c]) ? calc[r + ',' + c] : fila[c];
                if (v !== '' && v != null) llenas++;
                var n = numero(v); if (n !== null) vals.push(n);
            });
            var res = col.tot === 'cuenta' ? llenas : !vals.length ? 0 : col.tot === 'suma' ? vals.reduce(function (p, q) { return p + q; }, 0)
                : col.tot === 'promedio' ? vals.reduce(function (p, q) { return p + q; }, 0) / vals.length : col.tot === 'min' ? Math.min.apply(null, vals) : Math.max.apply(null, vals);
            tot[c] = String(+res.toFixed(10));
        });
        S.tot = tot;
    }
    function mostrado(r, c) {
        var t = S.rows[r][c], v = esFormula(t) ? (S.calc[r + ',' + c] || '') : t;
        var f = sty[r] ? sty[r][c] : null;
        if (f && f.nf) { var x = formatoNumero(v, f.nf); if (x !== null) return x; }
        return formatear(v, S.cols[c].t);
    }
    /** ¿La fila pasa los filtros activos? (los filtros comparan el valor mostrado) */
    function filaVisible(r) {
        var f = S.vista.filtros || {};
        for (var k in f) {
            if (!Object.prototype.hasOwnProperty.call(f, k)) continue;
            var filtro = f[k], c = +k;
            if (!filtro || !S.cols[c]) continue;
            var v = mostrado(r, c);
            if (filtro.texto && String(v).toLowerCase().indexOf(String(filtro.texto).toLowerCase()) < 0) return false;
            if (filtro.vals && filtro.vals.length && filtro.vals.indexOf(String(v)) < 0) return false;
        }
        return true;
    }
    function hayFiltros() {
        var f = S.vista.filtros || {};
        return Object.keys(f).some(function (k) { return f[k] && ((f[k].vals && f[k].vals.length) || f[k].texto); });
    }
    function filasVisibles() {
        if (!hayFiltros()) return S.rows.length;
        var n = 0;
        for (var r = 0; r < S.rows.length; r++) if (filaVisible(r)) n++;
        return n;
    }

    // ================= Dibujo =================
    function rangoNorm() { return { r0: Math.min(sel.r0, sel.r1), r1: Math.max(sel.r0, sel.r1), c0: Math.min(sel.c0, sel.c1), c1: Math.max(sel.c0, sel.c1) }; }
    /** Celdas elegidas: el rango más las celdas sueltas de Ctrl/⌘+clic, sin repetir */
    function celdasElegidas() {
        var n = rangoNorm(), o = [], visto = {};
        for (var r = n.r0; r <= n.r1; r++) for (var c = n.c0; c <= n.c1; c++) {
            o.push([r, c]);
            visto[r + ',' + c] = true;
        }
        selExtra.forEach(function (k) {
            if (visto[k]) return;
            visto[k] = true;
            var p = k.split(',');
            o.push([+p[0], +p[1]]);
        });
        return o;
    }
    function limpiarExtras() { selExtra = []; }
    /** Ctrl/⌘+clic: añade o quita una celda de la selección sin perder el resto */
    function alternarCeldaSuelta(r, c) {
        var actuales = celdasElegidas();
        var estaba = actuales.some(function (p) { return p[0] === r && p[1] === c; });
        if (!estaba) {   // añadir: todo lo anterior queda como celdas sueltas y esta pasa a ser el ancla
            var tope = 4000;
            selExtra = actuales.slice(-tope).map(function (p) { return p[0] + ',' + p[1]; });
            sel.r0 = sel.r1 = r; sel.c0 = sel.c1 = c;
            return;
        }
        var resto = actuales.filter(function (p) { return !(p[0] === r && p[1] === c); });
        if (!resto.length) {   // era la única celda: se queda elegida
            sel.r0 = sel.r1 = r; sel.c0 = sel.c1 = c; limpiarExtras(); return;
        }
        var primera = resto[0];
        sel.r0 = sel.r1 = primera[0]; sel.c0 = sel.c1 = primera[1];
        selExtra = resto.slice(1).map(function (p) { return p[0] + ',' + p[1]; });
    }
    var ALIN = { izquierda: 'left', centro: 'center', derecha: 'right' };
    var LADOS = { bt: 'top', br: 'right', bb: 'bottom', bl: 'left' };
    /** Letra negra o blanca según la luminosidad del relleno (igual que Classes\Sheetflow::contraste) */
    function contraste(hex) {
        var r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
        return (0.299 * r + 0.587 * g + 0.114 * b) > 140 ? '#111111' : '#ffffff';
    }
    /** CSS de un formato de celda (mismo criterio que Classes\Sheetflow::cssFmt, pero en px) */
    function cssFmt(f) {
        if (!f) return '';
        var c = '';
        if (f.b) c += 'font-weight:bold;';
        if (f.i) c += 'font-style:italic;';
        if (f.u) c += 'text-decoration:underline;';
        if (f.fs) c += 'font-size:' + f.fs + 'px;';
        if (f.tc) c += 'color:' + f.tc + ';';
        else if (f.bg) c += 'color:' + contraste(f.bg) + ';';   // con relleno y sin color de letra, la letra se ajusta para que se lea
        if (f.bg) c += 'background-color:' + f.bg + ';';
        if (f.al) c += 'text-align:' + ALIN[f.al] + ';';
        Object.keys(LADOS).forEach(function (k) {
            if (!f[k]) return;
            if (f[k] === 'n') { c += 'border-' + LADOS[k] + ':none;'; return; }
            var p = f[k].split('|');
            c += 'border-' + LADOS[k] + ':' + p[0] + 'px ' + p[1] + ' ' + p[2] + ';';
        });
        return c;
    }
    function imgHtml(f) { return f && f.im ? '<img class="sf-img" src="' + esc(f.im) + '" alt="">' : ''; }
    function dibujar() {
        recalcular();
        var e = S.estilo, h = '<colgroup><col class="sf-col-n">';
        var cnd = condicional();
        var conFiltro = hayFiltros();
        var cubierta = {}, ancla = {};
        S.merges.forEach(function (m) {
            ancla[m.r0 + ',' + m.c0] = m;
            for (var rr = m.r0; rr <= m.r1; rr++) for (var cc = m.c0; cc <= m.c1; cc++) {
                if (rr !== m.r0 || cc !== m.c0) cubierta[rr + ',' + cc] = true;
            }
        });
        S.cols.forEach(function (col) { h += '<col' + (col.w ? ' style="width:' + col.w + 'px;min-width:' + col.w + 'px"' : '') + '>'; });
        h += '</colgroup><thead><tr><th class="sf-esq"></th>';
        S.cols.forEach(function (col, c) {
            var activo = S.vista.filtros && S.vista.filtros[c] ? ' sf-filtro-activo' : '';
            h += '<th class="sf-letra' + activo + '" data-letra="' + c + '">' + letra(c) + '<i class="sf-filtro" data-fil="' + c + '" role="button" tabindex="0" aria-label="Filtrar la columna ' + esc(col.n) + '" title="Filtrar esta columna"></i><i class="sf-tirador" data-tir="' + c + '" title="Arrastra para cambiar el ancho · doble clic: automático"></i></th>';
        });
        h += '</tr><tr><th class="sf-num" data-fila="-1">1</th>';
        S.cols.forEach(function (col, c) {
            if (cubierta['-1,' + c]) return;
            var mg = ancla['-1,' + c], span = mg ? ' colspan="' + (mg.c1 - mg.c0 + 1) + '"' : '';
            h += '<th class="sf-enc" data-r="-1" data-c="' + c + '"' + span + ' style="background:' + e.enc + ';color:' + e.encTxt + ';' + cssFmt(hsty[c]) + '">' + imgHtml(hsty[c]) + esc(col.n) + '</th>';
        });
        h += '</tr></thead><tbody>';
        S.rows.forEach(function (fila, r) {
            var cl = (e.zebra && r % 2 ? 'sf-alt' : '');
            if (conFiltro && !filaVisible(r)) cl += (cl ? ' ' : '') + 'sf-oculta';
            h += '<tr data-r="' + r + '"' + (cl ? ' class="' + cl + '"' : '') + '><th class="sf-num" data-fila="' + r + '">' + (r + 2) + '</th>';
            S.cols.forEach(function (col, c) {
                if (cubierta[r + ',' + c]) return;
                var mg = ancla[r + ',' + c], span = '';
                if (mg) {
                    var cs = mg.c1 - mg.c0 + 1, rs = mg.r1 - mg.r0 + 1;
                    if (cs > 1) span += ' colspan="' + cs + '"';
                    if (rs > 1) span += ' rowspan="' + rs + '"';
                }
                var v = mostrado(r, c), err = /^#[A-Z/0?!]+$/.test(v), f = sty[r][c], cf = cnd[r + ',' + c];
                if (cf) {
                    f = Object.assign({}, f || {});
                    if (cf.bg) f.bg = cf.bg;
                    if (cf.tc) f.tc = cf.tc;
                    if (cf.b) f.b = 1;
                }
                var extra = cf && cf.grad ? 'background-image:' + cf.grad + ';' : '';
                h += '<td data-r="' + r + '" data-c="' + c + '"' + span + ' style="text-align:' + ALIN[col.a] + ';' + cssFmt(f) + extra + '"' + (esFormula(fila[c]) ? ' class="sf-f' + (err ? ' sf-err' : '') + '"' : '') + '>' + imgHtml(sty[r][c]) + esc(v) + '</td>';
            });
            h += '</tr>';
        });
        if (S.cols.some(function (col) { return col.tot; })) {
            h += '<tr class="sf-totales"><th class="sf-num">Σ</th>';
            S.cols.forEach(function (col, c) {
                var t = col.tot && S.tot[c] != null ? (col.tot === 'cuenta' ? S.tot[c] : formatear(S.tot[c], col.t)) : '';
                h += '<td style="text-align:' + ALIN[col.a] + '">' + (c === 0 && !t ? 'Total' : esc(t)) + '</td>';
            });
            h += '</tr>';
        }
        grid.className = 'sf-grid' + (e.bordes ? '' : ' sf-sin-bordes');
        grid.style.zoom = zoom / 100;
        grid.innerHTML = h + '</tbody>';
        aplicarCongelado();
        colocarAsa();
        pintarGraficos();
        actualizarFiltroEstado();
        pintarSeleccion();
        sincronizarBarra();
    }
    // ================= Formato condicional (igual que Classes\Sheetflow::condicional) =================
    function mezclar(a, b, k) {
        k = Math.max(0, Math.min(1, k));
        function hx(x, i) { return parseInt(x.slice(1 + i * 2, 3 + i * 2), 16); }
        var r = Math.round(hx(a, 0) * (1 - k) + hx(b, 0) * k), g = Math.round(hx(a, 1) * (1 - k) + hx(b, 1) * k), bl = Math.round(hx(a, 2) * (1 - k) + hx(b, 2) * k);
        return '#' + [r, g, bl].map(function (v) { return ('0' + v.toString(16)).slice(-2); }).join('');
    }
    function condicional() {
        var reglas = S.vista.cond || [], out = {};
        if (!reglas.length) return out;
        reglas.forEach(function (r) {
            var r0 = +r.r0, r1 = +r.r1, c0 = +r.c0, c1 = +r.c1, tipo = r.tipo;
            var textos = {}, nums = [], cuenta = {};
            for (var f = r0; f <= r1; f++) for (var c = c0; c <= c1; c++) {
                var v = mostrado(f, c);
                textos[f + ',' + c] = String(v).trim();
                var n = numero(v);
                if (n !== null) nums.push(n);
            }
            var min = nums.length ? Math.min.apply(null, nums) : null, max = nums.length ? Math.max.apply(null, nums) : null;
            var a = numero(r.a), b = numero(r.b);
            if (tipo === 'duplicados') Object.keys(textos).forEach(function (k) { var t = textos[k].toLowerCase(); if (t) cuenta[t] = (cuenta[t] || 0) + 1; });
            var aguja = String(r.txt || r.a || '').trim().toLowerCase();
            var fondo = r.fondo || '#ffffff', color = r.color || '#2563eb';
            var base = { bg: fondo, tc: color, b: r.b ? 1 : 0 };
            for (var f2 = r0; f2 <= r1; f2++) for (var c2 = c0; c2 <= c1; c2++) {
                var k2 = f2 + ',' + c2, v2 = mostrado(f2, c2), n2 = numero(v2), ok = false;
                if (tipo === 'mayor') ok = n2 !== null && a !== null && n2 > a;
                else if (tipo === 'menor') ok = n2 !== null && a !== null && n2 < a;
                else if (tipo === 'igual') ok = (n2 !== null && a !== null) ? n2 === a : String(v2).trim().toLowerCase() === String(r.a || '').trim().toLowerCase();
                else if (tipo === 'entre') ok = n2 !== null && a !== null && b !== null && n2 >= Math.min(a, b) && n2 <= Math.max(a, b);
                else if (tipo === 'contiene') ok = aguja !== '' && String(v2).toLowerCase().indexOf(aguja) >= 0;
                else if (tipo === 'duplicados') ok = String(v2).trim() !== '' && (cuenta[String(v2).trim().toLowerCase()] || 0) > 1;
                else if (tipo === 'escala') {
                    if (n2 === null || min === null || max === min) continue;
                    var col = mezclar(fondo, color, (n2 - min) / (max - min));
                    out[k2] = { bg: col, tc: contraste(col) };
                    continue;
                } else if (tipo === 'barra') {
                    if (n2 === null || max === null || max <= 0) continue;
                    var pct = Math.max(2, Math.min(100, n2 / max * 100));
                    out[k2] = { grad: 'linear-gradient(90deg,' + color + ' 0%,' + color + ' ' + pct.toFixed(1) + '%,transparent ' + pct.toFixed(1) + '%,transparent 100%)' };
                    continue;
                }
                if (ok) out[k2] = base;
            }
        });
        return out;
    }

    // ================= Inmovilizar filas/columnas =================
    function aplicarCongelado() {
        var fr = Math.min(80, Math.min(S.rows.length, +S.vista.filas || 0));
        var fc = Math.min(10, Math.min(S.cols.length, +S.vista.cols || 0));
        if (!fr && !fc) return;
        var esc = zoom / 100, thead = grid.querySelector('thead');
        var headH = thead ? thead.getBoundingClientRect().height / esc : 0;
        var tops = {}, acc = headH, r, c, tr, th;
        for (r = 0; r < fr; r++) {
            tr = grid.querySelector('tr[data-r="' + r + '"]');
            if (!tr) break;
            tops[r] = acc;
            acc += tr.getBoundingClientRect().height / esc;
        }
        var numTh = grid.querySelector('th.sf-num');
        var lefts = {}, accL = numTh ? numTh.getBoundingClientRect().width / esc : 0;
        for (c = 0; c < fc; c++) {
            th = grid.querySelector('th.sf-letra[data-letra="' + c + '"]');
            if (!th) break;
            lefts[c] = accL;
            accL += th.getBoundingClientRect().width / esc;
        }
        for (r = 0; r < fr; r++) {
            tr = grid.querySelector('tr[data-r="' + r + '"]');
            if (!tr) continue;
            var thn = tr.querySelector('th.sf-num');
            if (thn) { thn.classList.add('sf-cong-r'); thn.style.top = tops[r] + 'px'; }
            tr.querySelectorAll('td').forEach(function (td) {
                td.classList.add(+td.dataset.c < fc ? 'sf-cong-rc' : 'sf-cong-r');
                td.style.top = tops[r] + 'px';
            });
        }
        for (c = 0; c < fc; c++) {
            th = grid.querySelector('th.sf-letra[data-letra="' + c + '"]');
            if (th && fr) { th.classList.add('sf-cong-rc'); th.style.left = lefts[c] + 'px'; }
            var enc = grid.querySelector('th.sf-enc[data-c="' + c + '"]');
            if (enc) { enc.classList.add('sf-cong-c'); enc.style.left = lefts[c] + 'px'; }
            grid.querySelectorAll('td[data-c="' + c + '"]').forEach(function (td) {
                td.classList.add(td.classList.contains('sf-cong-r') ? 'sf-cong-rc' : 'sf-cong-c');
                td.style.left = lefts[c] + 'px';
            });
        }
    }

    // ================= Asa de relleno =================
    /** Coloca el cuadradito de relleno en la esquina inferior derecha de la selección (coordenadas de pantalla) */
    function colocarAsa() {
        var asa = $('sf-asa'); if (!asa) return;
        var n = rangoNorm();
        if (n.r1 < 0) { asa.hidden = true; return; }
        var el = celdaEl(n.r1, n.c1);
        if (!el) { asa.hidden = true; return; }
        var a = el.getBoundingClientRect();
        asa.hidden = false;
        asa.style.left = Math.round(a.right) + 'px';
        asa.style.top = Math.round(a.bottom) + 'px';
    }
    function mod(a, b) { return ((a % b) + b) % b; }
    /** Detecta una serie (números o fechas) en la fila/columna base para extrapolarla */
    function detectarSerie(base, eje) {
        var n = eje === 'r' ? base.r1 - base.r0 + 1 : base.c1 - base.c0 + 1;
        var ancho = eje === 'r' ? base.c1 - base.c0 + 1 : base.r1 - base.r0 + 1;
        if (n < 2 || ancho !== 1) return null;
        var vals = [], i, r, c;
        for (i = 0; i < n; i++) {
            r = eje === 'r' ? base.r0 + i : base.r0;
            c = eje === 'r' ? base.c0 : base.c0 + i;
            var t = S.rows[r][c];
            if (esFormula(t)) return null;
            vals.push(t);
        }
        var nums = vals.map(numero);
        if (nums.every(function (x) { return x !== null; })) {
            var paso = nums[n - 1] - nums[n - 2];
            for (i = 1; i < n; i++) if (Math.abs((nums[i] - nums[i - 1]) - paso) > 0.000001) paso = nums[n - 1] - nums[n - 2];
            return { tipo: 'num', inicio: nums[0], paso: paso, dec: String(vals[0]).indexOf('.') >= 0 };
        }
        var isos = vals.map(function (v) { var m = /^(\d{4})-(\d\d)-(\d\d)$/.exec(String(v).trim()); return m ? Date.UTC(+m[1], +m[2] - 1, +m[3], 12) : null; });
        if (isos.every(function (x) { return x !== null; })) {
            var dias = Math.round((isos[n - 1] - isos[n - 2]) / 86400000);
            return { tipo: 'fecha', inicio: isos[0], paso: dias };
        }
        return null;
    }
    function valorSerie(serie, pos) {
        if (serie.tipo === 'num') {
            var v = serie.inicio + serie.paso * pos;
            return String(+v.toFixed(10));
        }
        var d = new Date(serie.inicio + serie.paso * pos * 86400000);
        return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate());
    }
    /** Aplica una transformación solo fuera de los textos entre comillas de una fórmula */
    function fueraDeTexto(t, fn) {
        return String(t).split(/("[^"]*")/).map(function (parte, i) { return i % 2 ? parte : fn(parte); }).join('');
    }
    /** Ajusta las referencias A1 de una fórmula al copiarla (dr filas, dc columnas); respeta $ y los textos */
    function ajustarFormula(t, dr, dc) {
        if (!esFormula(t) || (!dr && !dc)) return t;
        var re = /(\$?)([A-Za-z]{1,2})(\$?)(\d+)(?::(\$?)([A-Za-z]{1,2})(\$?)(\d+))?(?![A-Za-z(])/g;
        return fueraDeTexto(t, function (trozo) {
            return trozo.replace(re, function (m, d1, l1, d2, n1, d3, l2, d4, n2) {
                var c1 = indiceLetra(l1.toUpperCase()) + (d1 ? 0 : dc);
                var f1 = parseInt(n1, 10) - 2 + (d2 ? 0 : dr);
                if (c1 < 0 || f1 < -1) return '#REF!';
                var out = d1 + letra(c1) + d2 + (f1 + 2);
                if (l2) {
                    var c2 = indiceLetra(l2.toUpperCase()) + (d3 ? 0 : dc);
                    var f2 = parseInt(n2, 10) - 2 + (d4 ? 0 : dr);
                    if (c2 < 0 || f2 < -1) return '#REF!';
                    out += ':' + d3 + letra(c2) + d4 + (f2 + 2);
                }
                return out;
            });
        });
    }
    /** Rellena el destino desde la base repitiendo (o extrapolando la serie) y ajustando las fórmulas */
    function aplicarRelleno(base, dest) {
        antes();
        var eje = (dest.r0 !== base.r0 || dest.r1 !== base.r1) ? 'r' : 'c';
        var bh = base.r1 - base.r0 + 1, bw = base.c1 - base.c0 + 1;
        var serie = detectarSerie(base, eje);
        for (var r = dest.r0; r <= dest.r1; r++) for (var c = dest.c0; c <= dest.c1; c++) {
            if (r >= base.r0 && r <= base.r1 && c >= base.c0 && c <= base.c1) continue;
            var sr, sc;
            if (eje === 'r') {
                sc = c;
                sr = base.r0 + mod(r - base.r0, bh);
            } else {
                sr = r;
                sc = base.c0 + mod(c - base.c0, bw);
            }
            var origen = S.rows[sr][sc], nuevo;
            if (serie) {
                var pos = eje === 'r' ? r - base.r0 : c - base.c0;
                nuevo = valorSerie(serie, pos);
            } else {
                nuevo = esFormula(origen) ? ajustarFormula(origen, r - sr, c - sc) : origen;
            }
            fijar(r, c, nuevo);
            if (sty[sr] && sty[sr][sc]) setSt(r, c, Object.assign({}, sty[sr][sc]));
        }
        limpiarExtras();
        sel.r0 = dest.r0; sel.c0 = dest.c0; sel.r1 = dest.r1; sel.c1 = dest.c1;
        dibujar(); cambio(); area.focus();
    }

    /** A partir de la base y la celda bajo el cursor: rectángulo de destino del relleno (un solo eje) */
    function destinoRelleno(base, r, c) {
        if (r > base.r1) return { r0: base.r0, c0: base.c0, r1: r, c1: base.c1 };
        if (r < base.r0) return { r0: r, c0: base.c0, r1: base.r1, c1: base.c1 };
        if (c > base.c1) return { r0: base.r0, c0: base.c0, r1: base.r1, c1: c };
        if (c < base.c0) return { r0: base.r0, c0: c, r1: base.r1, c1: base.c1 };
        return null;
    }
    function marcarRelleno() {
        grid.querySelectorAll('.sf-relleno').forEach(function (x) { x.classList.remove('sf-relleno'); });
        var d = rellenando && rellenando.destino;
        if (!d) return;
        for (var r = d.r0; r <= d.r1; r++) for (var c = d.c0; c <= d.c1; c++) {
            if (r >= rellenando.base.r0 && r <= rellenando.base.r1 && c >= rellenando.base.c0 && c <= rellenando.base.c1) continue;
            var el = celdaEl(r, c);
            if (el) el.classList.add('sf-relleno');
        }
    }
    (function () {
        var asa = $('sf-asa');
        if (asa) asa.addEventListener('mousedown', function (e) {
            e.preventDefault(); e.stopPropagation();
            rellenando = { base: rangoNorm(), destino: null };
            estado('Arrastra para rellenar y suelta');
        });
        var scroller = area.querySelector('.sf-scroll');
        if (scroller) scroller.addEventListener('scroll', function () { colocarAsa(); });
    })();

    // ================= Gráficos =================
    var COLORES_SERIE = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2'];
    function graficoDatosJS(g) {
        var r0 = +g.r0, r1 = +g.r1, c0 = +g.c0, c1 = +g.c1, etiquetas = [], series = [];
        var soloNum = true;
        for (var f = r0; f <= r1; f++) {
            var v = mostrado(f, c0);
            if (String(v).trim() !== '' && numero(v) === null) soloNum = false;
        }
        var cols = [];
        for (var c = c0; c <= c1; c++) cols.push(c);
        var datos = {};
        cols.forEach(function (c) {
            var vals = [], hay = 0;
            for (var f = r0; f <= r1; f++) { var n = numero(mostrado(f, c)); vals.push(n); if (n !== null) hay++; }
            if (hay) datos[c] = vals;
        });
        for (var f2 = r0; f2 <= r1; f2++) {
            if (soloNum) etiquetas.push('Fila ' + (f2 + 2));
            else { var t = String(mostrado(f2, c0)).trim(); etiquetas.push(t !== '' ? t : 'Fila ' + (f2 + 2)); }
        }
        if (!soloNum) delete datos[c0];
        Object.keys(datos).forEach(function (k) { series.push({ nombre: S.cols[k] ? S.cols[k].n : ('Col. ' + (+k + 1)), vals: datos[k] }); });
        return { tipo: g.tipo, titulo: g.titulo || '', etiquetas: etiquetas, series: series.slice(0, 6) };
    }
    function svgGrafico(dat) {
        var W = 720, H = 280, ml = 54, mr = 16, mt = dat.titulo ? 34 : 14, mb = 42;
        var n = dat.etiquetas.length;
        if (!n) return '';
        var vals = [];
        dat.series.forEach(function (s) { s.vals.forEach(function (v) { if (v !== null) vals.push(v); }); });
        if (!vals.length) return '';
        var max = Math.max.apply(null, vals), min = Math.min.apply(null, vals);
        if (max === min) max = min + 1;
        max = Math.max(0, max); min = Math.min(0, min);
        var rango = (max - min) || 1, pw = W - ml - mr, ph = H - mt - mb;
        function y(v) { return mt + ph - ((v - min) / rango) * ph; }
        var s = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="100%" role="img" aria-label="' + esc(dat.titulo || 'Gráfico') + '">';
        s += '<rect x="0" y="0" width="' + W + '" height="' + H + '" fill="var(--surface, #fff)"/>';
        if (dat.titulo) s += '<text x="' + (W / 2) + '" y="20" text-anchor="middle" font-size="14" font-weight="bold" fill="currentColor">' + esc(dat.titulo) + '</text>';
        for (var i = 0; i <= 4; i++) {
            var vv = min + rango * i / 4, yy = y(vv);
            s += '<line x1="' + ml + '" y1="' + yy.toFixed(1) + '" x2="' + (W - mr) + '" y2="' + yy.toFixed(1) + '" stroke="var(--border, #e2e8f0)" stroke-width="1"/>';
            s += '<text x="' + (ml - 6) + '" y="' + (yy + 3).toFixed(1) + '" text-anchor="end" font-size="9" fill="var(--text-muted, #555)">' + esc(miles(vv, 2)) + '</text>';
        }
        s += '<line x1="' + ml + '" y1="' + y(0).toFixed(1) + '" x2="' + (W - mr) + '" y2="' + y(0).toFixed(1) + '" stroke="#94a3b8" stroke-width="1.2"/>';
        if (dat.tipo === 'barra') {
            var grupo = pw / Math.max(1, n), ancho = Math.max(3, grupo * 0.7 / dat.series.length);
            dat.etiquetas.forEach(function (eti, k) {
                dat.series.forEach(function (se, j) {
                    var v = se.vals[k];
                    if (v === null || v === undefined) return;
                    var x = ml + grupo * k + grupo * 0.15 + ancho * j, y0 = y(0), y1 = y(v);
                    s += '<rect x="' + x.toFixed(1) + '" y="' + Math.min(y0, y1).toFixed(1) + '" width="' + ancho.toFixed(1) + '" height="' + Math.abs(y0 - y1).toFixed(1) + '" fill="' + COLORES_SERIE[j % 6] + '" rx="2"/>';
                });
            });
        } else {
            dat.series.forEach(function (se, j) {
                var pts = [];
                se.vals.forEach(function (v, k) { if (v !== null) pts.push((ml + (pw / Math.max(1, n - 1)) * k).toFixed(1) + ',' + y(v).toFixed(1)); });
                if (pts.length > 1) s += '<polyline points="' + pts.join(' ') + '" fill="none" stroke="' + COLORES_SERIE[j % 6] + '" stroke-width="2.4" stroke-linejoin="round"/>';
                pts.forEach(function (p) { var xy = p.split(','); s += '<circle cx="' + xy[0] + '" cy="' + xy[1] + '" r="3.2" fill="' + COLORES_SERIE[j % 6] + '"/>'; });
            });
        }
        var paso = Math.max(1, Math.ceil(n / 14));
        dat.etiquetas.forEach(function (eti, k) {
            if (k % paso) return;
            var x = dat.tipo === 'barra' ? ml + (pw / Math.max(1, n)) * (k + 0.5) : ml + (pw / Math.max(1, n - 1)) * k;
            s += '<text x="' + x.toFixed(1) + '" y="' + (H - mb + 16) + '" text-anchor="middle" font-size="9" fill="var(--text-muted, #334155)">' + esc(String(eti).slice(0, 18)) + '</text>';
        });
        var lx = ml;
        dat.series.forEach(function (se, j) {
            if (dat.series.length < 2 && dat.titulo) return;
            s += '<rect x="' + lx + '" y="' + (H - 16) + '" width="9" height="9" fill="' + COLORES_SERIE[j % 6] + '"/>';
            s += '<text x="' + (lx + 13) + '" y="' + (H - 8) + '" font-size="9" fill="var(--text-muted, #334155)">' + esc(String(se.nombre).slice(0, 22)) + '</text>';
            lx += 24 + String(se.nombre).slice(0, 22).length * 5;
        });
        return s + '</svg>';
    }
    function pintarGraficos() {
        var cont = $('sf-graficos'); if (!cont) return;
        var gs = S.vista.graficos || [];
        if (!gs.length) { cont.innerHTML = ''; cont.hidden = true; return; }
        cont.hidden = false;
        cont.innerHTML = gs.map(function (g, i) {
            var dat = graficoDatosJS(g);
            return '<figure class="sf-grafico" data-graf="' + i + '">' +
                (dat.series.length ? svgGrafico(dat) : '<p class="text-muted text-sm">El rango elegido no tiene números.</p>') +
                '<button type="button" class="sf-graf-quitar" data-graf-quitar="' + i + '" title="Quitar el gráfico"><i class="fa-solid fa-xmark"></i></button></figure>';
        }).join('');
    }

    // ================= Filtros =================
    function actualizarFiltroEstado() {
        var el = $('sf-filtro-estado'); if (!el) return;
        if (!hayFiltros()) { el.textContent = ''; el.hidden = true; return; }
        el.hidden = false;
        el.innerHTML = '<i class="fa-solid fa-filter"></i> ' + filasVisibles() + ' de ' + S.rows.length + ' renglones <button type="button" id="sf-quitar-filtros">Quitar filtros</button>';
        var b = $('sf-quitar-filtros');
        if (b) b.addEventListener('click', function () { antes(); S.vista.filtros = {}; dibujar(); cambio(); });
    }


    function celdaEl(r, c) {
        var m = mergeDe(r, c);
        if (m) { r = m.r0; c = m.c0; }
        return grid.querySelector(r < 0 ? 'th.sf-enc[data-c="' + c + '"]' : 'td[data-r="' + r + '"][data-c="' + c + '"]');
    }
    function pintarSeleccion() {
        grid.querySelectorAll('.sf-sel,.sf-ancla,.sf-hallado').forEach(function (x) { x.classList.remove('sf-sel', 'sf-ancla', 'sf-hallado'); });
        grid.querySelectorAll('.sf-letra-sel,.sf-num-sel').forEach(function (x) { x.classList.remove('sf-letra-sel', 'sf-num-sel'); });
        var n = rangoNorm();
        for (var r = n.r0; r <= n.r1; r++) for (var c = n.c0; c <= n.c1; c++) { var el = celdaEl(r, c); if (el) el.classList.add('sf-sel'); }
        selExtra.forEach(function (k) { var p = k.split(','), el = celdaEl(+p[0], +p[1]); if (el) el.classList.add('sf-sel'); });
        var an = celdaEl(sel.r0, sel.c0); if (an) an.classList.add('sf-ancla');
        for (var cc = n.c0; cc <= n.c1; cc++) { var l = grid.querySelector('th.sf-letra[data-letra="' + cc + '"]'); if (l) l.classList.add('sf-letra-sel'); }
        for (var rr = n.r0; rr <= n.r1; rr++) { var f = grid.querySelector('th.sf-num[data-fila="' + rr + '"]'); if (f) f.classList.add('sf-num-sel'); }
        var refTxt = nombreCelda(sel.r0, sel.c0) + (n.r0 !== n.r1 || n.c0 !== n.c1 ? ':' + nombreCelda(n.r1, n.c1) : '');
        if (selExtra.length) refTxt += ' +' + selExtra.length;
        $('sf-ref').textContent = refTxt;
        var enFormula = entradaFormula();
        if (enFormula) fx.value = enFormula.value;   // no pisar la fórmula mientras se eligen celdas
        else fx.value = sel.r0 < 0 ? S.cols[sel.c0].n : S.rows[sel.r0][sel.c0];
        sincronizarBarra();
        estadistica();
    }
    /** Resumen de las celdas elegidas (como la barra de estado de Excel) */
    function estadistica() {
        var celdas = celdasElegidas(), vals = [], llenas = 0;
        if (celdas.length < 2) { $('sf-estadistica').textContent = ''; return; }
        celdas.forEach(function (p) {
            var r = p[0], c = p[1];
            if (r < 0 || r >= S.rows.length || c < 0 || c >= S.cols.length) return;
            var t = S.rows[r][c], v = esFormula(t) ? S.calc[r + ',' + c] : t;
            if (v !== '' && v != null) llenas++;
            var x = numero(v); if (x !== null) vals.push(x);
        });
        var s;
        if (vals.length) {
            var suma = vals.reduce(function (p, q) { return p + q; }, 0);
            s = 'Suma: ' + miles(suma, 4) + ' · Promedio: ' + miles(suma / vals.length, 4) + ' · Cuenta: ' + llenas +
                ' · Mín: ' + miles(Math.min.apply(null, vals), 4) + ' · Máx: ' + miles(Math.max.apply(null, vals), 4);
        } else {
            s = 'Cuenta: ' + llenas + (llenas ? ' · sin números que sumar' : '');
        }
        $('sf-estadistica').textContent = s;
    }
    function sincronizarBarra() {
        var cc = Math.min(sel.c0, S.cols.length - 1), col = S.cols[cc] || S.cols[0], f = getSt(sel.r0, cc) || {};
        $('sf-tipo').value = col.t;
        $('sf-opciones').hidden = col.t !== 'lista'; $('sf-opciones').value = (col.op || []).join(', ');
        $('sf-total').value = col.tot || '';
        var nfs = $('sf-nf'); if (nfs) nfs.value = f.nf || '';
        $('sf-c-enc').value = S.estilo.enc; $('sf-c-txt').value = S.estilo.encTxt;
        $('sf-zebra').checked = !!S.estilo.zebra; $('sf-bordes').checked = !!S.estilo.bordes;
        document.querySelectorAll('[data-alin]').forEach(function (b) { b.classList.toggle('activo', b.dataset.alin === (f.al || col.a)); });
        document.querySelectorAll('[data-fmt]').forEach(function (b) { b.classList.toggle('activo', !!f[b.dataset.fmt]); });
        if (document.activeElement !== $('sf-fs')) $('sf-fs').value = f.fs || 13;
        if (f.tc) $('sf-c-letra').value = f.tc;
        $('sf-deshacer').disabled = !pasado.length; $('sf-rehacer').disabled = !futuro.length;
    }
    function visible(r, c) {
        var el = celdaEl(r, c); if (!el) return;
        var cont = area.querySelector('.sf-scroll'), a = el.getBoundingClientRect(), b = cont.getBoundingClientRect();
        if (a.bottom > b.bottom - 4) cont.scrollTop += a.bottom - b.bottom + 40;
        else if (a.top < b.top + 60) cont.scrollTop -= b.top + 60 - a.top;
        if (a.right > b.right - 4) cont.scrollLeft += a.right - b.right + 40;
        else if (a.left < b.left + 50) cont.scrollLeft -= b.left + 50 - a.left;
    }

    // ================= Cambios, deshacer y guardado =================
    function copia() { return JSON.stringify({ cols: S.cols, rows: S.rows, estilo: S.estilo, sty: sty, hsty: hsty, ids: ids, merges: S.merges, vista: S.vista }); }
    function antes() { pasado.push(copia()); if (pasado.length > 100) pasado.shift(); futuro = []; }
    function cambio() { sucio = true; estado('Cambios sin guardar…'); clearTimeout(temporizador); temporizador = setTimeout(function () { guardar(false); }, 1800); }
    function restaurar(json) {
        var d = JSON.parse(json); S.cols = d.cols; S.rows = d.rows; S.estilo = d.estilo; sty = d.sty; hsty = d.hsty;
        S.merges = Array.isArray(d.merges) ? d.merges : [];
        S.vista = d.vista && typeof d.vista === 'object' ? d.vista : S.vista;
        normalizarVista();
        ids = Array.isArray(d.ids) ? d.ids.slice() : S.rows.map(function () { return null; });
        while (ids.length < S.rows.length) ids.push(null);
        ids = ids.slice(0, S.rows.length);
        limpiarExtras();
        sel.r0 = Math.min(sel.r0, S.rows.length - 1); sel.r1 = Math.min(sel.r1, S.rows.length - 1); sel.c0 = Math.min(sel.c0, S.cols.length - 1); sel.c1 = Math.min(sel.c1, S.cols.length - 1);
        dibujar(); cambio();
    }
    $('sf-deshacer').addEventListener('click', deshacer); $('sf-rehacer').addEventListener('click', rehacer);
    function deshacer() { if (!pasado.length) return; futuro.push(copia()); restaurar(pasado.pop()); }
    function rehacer() { if (!futuro.length) return; pasado.push(copia()); restaurar(futuro.pop()); }

    function datosEnvio(forzar, manual) {
        var fd = new FormData();
        fd.append('id', C.id); fd.append('proyecto', C.proyecto); fd.append('version', version);
        fd.append('nombre', $('sf-nombre').value); fd.append('fecha', $('sf-fecha').value); fd.append('departamento', $('sf-depto').value); fd.append('descripcion', $('sf-desc').value);
        while (ids.length < S.rows.length) ids.push(null);
        ids = ids.slice(0, S.rows.length);
        var meta = (S.meta && typeof S.meta === 'object') ? S.meta : {};
        if (meta.subactividad) meta.subactividad.ids = ids.slice();
        fd.append('datos', JSON.stringify({ cols: S.cols, rows: S.rows, calc: S.calc, tot: S.tot, estilo: S.estilo, fmt: fmtMapa(), meta: meta, merges: S.merges, vista: S.vista }));
        if (forzar) fd.append('forzar', '1');
        if (manual) fd.append('manual', '1');
        return fd;
    }
    /** Guarda; devuelve una promesa que se resuelve true si quedó guardado */
    function guardar(manual, forzar) {
        clearTimeout(temporizador);
        if (guardando) { temporizador = setTimeout(function () { guardar(manual, forzar); }, 600); return Promise.resolve(false); }
        if (!sucio && !manual) return Promise.resolve(true);
        guardando = true; estado('Guardando…');
        return fetch(C.api.guardar, { method: 'POST', body: datosEnvio(forzar, manual) }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); }).then(function (x) {
            guardando = false;
            if (x.ok && x.d.success) { version = x.d.version; sucio = false; estado('Guardado ' + new Date().toLocaleTimeString()); return true; }
            if (x.d.conflicto) {
                estado('Conflicto de versión', true);
                return window.confirmarModal({ titulo: 'Conflicto de versión', mensaje: x.d.error + ' ¿Guardar de todos modos y reemplazar esa versión?', aceptar: 'Reemplazar' }).then(function (ok) { return ok ? guardar(true, true) : false; });
            }
            estado(x.d.error || 'No se pudo guardar', true); return false;
        }).catch(function () { guardando = false; estado('Sin conexión: no se pudo guardar', true); return false; });
    }
    $('sf-guardar').addEventListener('click', function () { guardar(true); });
    ['sf-nombre', 'sf-fecha', 'sf-depto', 'sf-desc'].forEach(function (id) { $(id).addEventListener('input', function () { if (id === 'sf-nombre') $('sf-titulo').textContent = this.value; cambio(); }); });
    window.addEventListener('beforeunload', function (e) { if (sucio) { e.preventDefault(); e.returnValue = ''; } });
    // Exportar / usar en vPlan: se guarda primero para que el archivo y las copias lleven lo último
    document.querySelectorAll('[data-guardar-antes]').forEach(function (a) {
        a.addEventListener('click', function (e) { if (!sucio) return; e.preventDefault(); var url = a.href, nueva = a.target === '_blank'; guardar(true).then(function (ok) { if (ok) { if (nueva) window.open(url, '_blank'); else location.href = url; } }); });
    });
    document.querySelectorAll('form[data-sf-guardar]').forEach(function (f) {
        f.addEventListener('submit', function (e) { if (!sucio) return; e.preventDefault(); guardar(true).then(function (ok) { if (ok) f.submit(); }); });
    });

    // ================= Pestañas de la barra =================
    (function () {
        var ls = 'sf-pestana';
        function mostrar(p) {
            document.querySelectorAll('#sf-pestanas [data-pest]').forEach(function (b) { b.classList.toggle('activo', b.dataset.pest === p); });
            document.querySelectorAll('.sf-barra [data-panel]').forEach(function (x) { x.hidden = x.dataset.panel !== p; });
            try { localStorage.setItem(ls, p); } catch (e) { /* sin almacenamiento */ }
        }
        document.querySelectorAll('#sf-pestanas [data-pest]').forEach(function (b) { b.addEventListener('click', function () { mostrar(b.dataset.pest); }); });
        var g = 'inicio'; try { g = localStorage.getItem(ls) || 'inicio'; } catch (e) { /* idem */ }
        if (!document.querySelector('.sf-barra [data-panel="' + g + '"]')) g = 'inicio';
        mostrar(g);
    })();

    // ================= Plegar la cinta (solo las pestañas) =================
    (function () {
        var b = $('sf-plegar'); if (!b) return;
        function aplicar(cerrada) {
            document.body.classList.toggle('sf-cinta-cerrada', cerrada);
            b.setAttribute('aria-expanded', cerrada ? 'false' : 'true');
            b.setAttribute('title', cerrada ? 'Mostrar la barra de herramientas' : 'Ocultar la barra de herramientas');
            b.innerHTML = '<i class="fa-solid fa-chevron-' + (cerrada ? 'down' : 'up') + '" aria-hidden="true"></i>';
        }
        var cerrada = false; try { cerrada = localStorage.getItem('sf-cinta') === '0'; } catch (e) { /* sin almacenamiento */ }
        aplicar(cerrada);
        b.addEventListener('click', function () {
            cerrada = !document.body.classList.contains('sf-cinta-cerrada');
            aplicar(cerrada);
            try { localStorage.setItem('sf-cinta', cerrada ? '0' : '1'); } catch (e) { /* idem */ }
        });
    })();

    // ================= Edición de celdas =================
    // ================= Modo apuntar (como Excel) =================
    // Mientras se escribe una fórmula, hacer clic en celdas inserta sus referencias (B2, B2:C4) sin cerrar el editor.
    function esFormulaTexto(v) { return /^\s*=/.test(String(v || '')); }
    /** Input que está recibiendo una fórmula (la celda en edición o la barra fx) */
    function entradaFormula() {
        if (editor && esFormulaTexto(editor.input.value)) return editor.input;
        if (document.activeElement === fx && esFormulaTexto(fx.value)) return fx;
        return null;
    }
    function refTexto(a, b) {
        var x = nombreCelda(a.r, a.c), y = nombreCelda(b.r, b.c);
        return x === y ? x : x + ':' + y;
    }
    /** Inserta la referencia en el cursor de la fórmula (sustituye la que se acaba de escribir o el texto elegido) */
    function insertarRef(ref) {
        var inp = entradaFormula();
        if (!inp) return null;
        var a = inp.selectionStart == null ? inp.value.length : inp.selectionStart;
        var b = inp.selectionEnd == null ? a : inp.selectionEnd;
        if (a === b) {
            var m = /\$?[A-Za-z]{1,2}\$?\d+(?::\$?[A-Za-z]{1,2}\$?\d+)?$/.exec(inp.value.slice(0, a));
            if (m) a -= m[0].length;   // un clic seguido a una referencia la reemplaza
        }
        inp.value = inp.value.slice(0, a) + ref + inp.value.slice(b);
        var fin = a + ref.length;
        try { inp.setSelectionRange(fin, fin); } catch (e) { /* el campo no admite selección */ }
        fx.value = inp.value;
        return { inp: inp, ini: a, fin: fin };
    }
    /** Al arrastrar, el rango de la referencia se amplía (B2 → B2:C4) sin escribir varias referencias */
    function extenderRef(a, b) {
        if (!apuntando) return;
        var inp = apuntando.inp, ref = refTexto(a, b);
        if (inp.value.slice(apuntando.ini, apuntando.fin) === ref) return;
        inp.value = inp.value.slice(0, apuntando.ini) + ref + inp.value.slice(apuntando.fin);
        apuntando.fin = apuntando.ini + ref.length;
        try { inp.setSelectionRange(apuntando.fin, apuntando.fin); } catch (e) { /* idem */ }
        fx.value = inp.value;
    }
    /** Celda sobre la que se hace clic mientras se escribe una fórmula: {r,c,r2,c2} (letra o número del encabezado = toda la fila/columna) */
    function celdaApuntada(t) {
        var ult = Math.max(0, S.rows.length - 1), ultC = Math.max(0, S.cols.length - 1);
        if (t.classList.contains('sf-letra')) return { r: 0, c: +t.dataset.letra, r2: ult, c2: +t.dataset.letra };
        if (t.dataset.r == null) {
            if (t.classList.contains('sf-num') && t.dataset.fila != null && +t.dataset.fila >= 0) {
                var rf = +t.dataset.fila;
                return { r: rf, c: 0, r2: rf, c2: ultC };   // número del renglón: toda la fila
            }
            return null;
        }
        var r = +t.dataset.r, c = +t.dataset.c;
        if (r < 0) return { r: 0, c: c, r2: ult, c2: c };                                     // encabezado: toda la columna
        if (editor && r === editor.r && c === editor.c) return null;                          // la propia celda daría #CICLO!
        if (!editor && fxOrigen && r === fxOrigen.r && c === fxOrigen.c) return null;         // idem al escribir en la barra fx
        return { r: r, c: c, r2: r, c2: c };
    }

    function fijar(r, c, v) {
        if (r < 0) { S.cols[c].n = String(v).replace(/[\r\n]+/g, ' ').slice(0, 60); return; }
        S.rows[r][c] = normalizar(v, S.cols[c].t).slice(0, 500);
    }
    function empezarEdicion(r, c, inicial) {
        terminarEdicion(true);
        var mg = mergeDe(r, c); if (mg) { r = mg.r0; c = mg.c0; }
        var el = celdaEl(r, c); if (!el) return;
        var inp = document.createElement('input');
        inp.className = 'sf-input'; inp.type = 'text'; inp.spellcheck = false; inp.autocomplete = 'off';
        var crudo = r < 0 ? S.cols[c].n : S.rows[r][c];
        inp.value = inicial != null ? inicial : (r >= 0 && S.cols[c].t === 'semaforo' && SEMAFORO[crudo] ? SEMAFORO[crudo] : (r >= 0 && S.cols[c].t === 'casilla' && crudo === '1' ? 'si' : crudo));
        var tc = r >= 0 ? S.cols[c].t : '';
        if (tc === 'lista' || tc === 'semaforo' || tc === 'casilla') {   // sugerencias de la columna
            var dl = $('sf-dl') || document.body.appendChild(Object.assign(document.createElement('datalist'), { id: 'sf-dl' }));
            dl.innerHTML = (tc === 'lista' ? (S.cols[c].op || []) : tc === 'casilla' ? ['si', 'no'] : ['Verde', 'Amarillo', 'Rojo']).map(function (o) { return '<option value="' + String(o).replace(/"/g, '&quot;') + '">'; }).join('');
            inp.setAttribute('list', 'sf-dl');
        }
        var im = el.querySelector('.sf-img');
        var enHoja = TACTIL && !!window.HojaInferior && MOVIL_ESTRECHO.matches;
        if (!enHoja) { el.textContent = ''; if (im) el.appendChild(im); el.appendChild(inp); }
        editor = { r: r, c: c, input: inp, previo: crudo };
        if (enHoja) {
            // En la hoja inferior la celda se edita con el teclado a la vista y la cuadrícula sigue visible.
            var cont = document.createElement('div'); cont.className = 'sf-celda-hoja';
            var ref = document.createElement('p'); ref.className = 'sf-celda-ref';
            ref.textContent = 'Celda ' + nombreCelda(r, c) + (r < 0 ? ' (encabezado de columna)' : '');
            var tip = document.createElement('p'); tip.className = 'text-sm text-muted';
            tip.textContent = 'Enter guarda y baja de renglón; Mayús + Enter sube. Cierra la hoja para volver a la tabla.';
            inp.classList.add('sf-celda-input');
            cont.appendChild(ref); cont.appendChild(inp); cont.appendChild(tip);
            hojaCelda = hojaCelda || HojaInferior.crear('sf-celda');
            hojaCelda.abrir({
                titulo: 'Editar celda', nodo: cont, posicion: 'peek', modal: false, enfocar: false,
                alCerrar: function () { if (editor && editor.input === inp) terminarEdicion(true); },
            });
            setTimeout(function () {
                inp.focus();
                if (inicial == null) inp.select(); else inp.setSelectionRange(inp.value.length, inp.value.length);
            }, 80);
        } else {
            inp.focus();
            if (inicial == null) inp.select(); else inp.setSelectionRange(inp.value.length, inp.value.length);
            if (TACTIL) setTimeout(function () { if (editor && editor.input === inp && inp.scrollIntoView) inp.scrollIntoView({ block: 'center', inline: 'nearest' }); }, 250);
        }
        inp.addEventListener('input', function () { fx.value = inp.value; });
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); terminarEdicion(true, enHoja); mover(e.shiftKey ? -1 : 1, 0, false); if (enHoja) empezarEdicion(sel.r0, sel.c0); else area.focus(); }
            else if (e.key === 'Tab') { e.preventDefault(); e.stopPropagation(); terminarEdicion(true, enHoja); mover(0, e.shiftKey ? -1 : 1, false); if (enHoja) empezarEdicion(sel.r0, sel.c0); else area.focus(); }
            else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); terminarEdicion(false); area.focus(); }
            else e.stopPropagation();
        });
        inp.addEventListener('blur', function () { if (!enHoja && editor && editor.input === inp) terminarEdicion(true); });
    }
    // `mantener`: en la hoja inferior se reemplaza la celda sin cerrarla (al bajar de renglón con Enter)
    function terminarEdicion(aplicar, mantener) {
        if (!editor) return;
        var e = editor; editor = null; apuntando = null;
        sel.r0 = sel.r1 = e.r; sel.c0 = sel.c1 = e.c;   // al apuntar celdas, la selección vuelve a la celda que se edita
        var nuevo = e.input.value;
        if (aplicar && nuevo.trim() === '=') nuevo = '';   // fórmula a medias: no dejar un #VALOR! en la celda
        if (aplicar && nuevo !== e.previo) { antes(); fijar(e.r, e.c, nuevo); dibujar(); cambio(); }
        else dibujar();
        if (!mantener && hojaCelda && hojaCelda.estaAbierta()) hojaCelda.cerrar();
    }
    function mover(dr, dc, extender) {
        var r = (extender ? sel.r1 : sel.r0) + dr, c = (extender ? sel.c1 : sel.c0) + dc;
        r = Math.max(-1, Math.min(S.rows.length - 1, r)); c = Math.max(0, Math.min(S.cols.length - 1, c));
        if (dr && hayFiltros()) {   // los renglones ocultos por un filtro se saltan
            var guarda = 0;
            while (guarda++ < S.rows.length && r >= 0 && r < S.rows.length && !filaVisible(r)) r += dr;
            r = Math.max(-1, Math.min(S.rows.length - 1, r));
        }
        var mg = mergeDe(r, c);
        if (mg && !extender && mergeEsSeleccion(mg)) {   // salir de una celda combinada en la dirección del cursor
            if (dr > 0) { r = mg.r1 + 1; c = mg.c0; }
            else if (dr < 0) { r = mg.r0 - 1; c = mg.c0; }
            else if (dc > 0) { r = mg.r0; c = mg.c1 + 1; }
            else if (dc < 0) { r = mg.r0; c = mg.c0 - 1; }
            r = Math.max(-1, Math.min(S.rows.length - 1, r)); c = Math.max(0, Math.min(S.cols.length - 1, c));
            mg = mergeDe(r, c);
        }
        if (mg && !extender) { sel.r0 = mg.r0; sel.c0 = mg.c0; sel.r1 = mg.r1; sel.c1 = mg.c1; }
        else if (extender) { sel.r1 = mg ? mg.r1 : r; sel.c1 = mg ? mg.c1 : c; }
        else { sel.r0 = sel.r1 = r; sel.c0 = sel.c1 = c; }
        if (!extender) limpiarExtras();
        pintarSeleccion(); visible(r, c);
    }
    fx.addEventListener('focus', function () { fxOrigen = { r: sel.r0, c: sel.c0 }; });
    fx.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            var destino = fxOrigen || { r: sel.r0, c: sel.c0 };
            var v = fx.value.trim() === '=' ? '' : fx.value;   // fórmula a medias: no dejar un #VALOR! en la celda
            sel.r0 = sel.r1 = destino.r; sel.c0 = sel.c1 = destino.c;
            antes(); fijar(destino.r, destino.c, v); fx.value = v; dibujar(); cambio(); fxOrigen = null; area.focus();
        } else if (e.key === 'Escape') {
            var destino = fxOrigen || { r: sel.r0, c: sel.c0 };
            sel.r0 = sel.r1 = destino.r; sel.c0 = sel.c1 = destino.c; fxOrigen = null;
            area.focus(); pintarSeleccion();
        }
    });
    // Insertar una función de la lista en la celda elegida (queda en la barra de fórmulas para ajustar los rangos)
    $('sf-fn').addEventListener('change', function () {
        var f = this.value; this.value = ''; if (!f || sel.r0 < 0) return;
        antes(); S.rows[sel.r0][sel.c0] = f; dibujar(); cambio(); fx.focus();
        var m = /\$?[A-Za-z]{1,2}\$?\d+(?::\$?[A-Za-z]{1,2}\$?\d+)?/.exec(f);   // deja elegida la primera referencia
        if (m) fx.setSelectionRange(m.index, m.index + m[0].length); else fx.select();
    });

    // ================= Selección con el ratón =================
    var arrastrando = false, tirando = null;    grid.addEventListener('mousedown', function (e) {
        var fil = e.target.closest && e.target.closest('.sf-filtro');
        if (fil) { e.preventDefault(); e.stopPropagation(); abrirFiltro(+fil.dataset.fil, e.clientX, e.clientY); return; }
        var tir = e.target.closest('.sf-tirador');
        if (tir) { var c0 = +tir.dataset.tir, th = tir.parentNode; tirando = { c: c0, x: e.clientX, w: th.getBoundingClientRect().width / (zoom / 100), antes: copia() }; e.preventDefault(); return; }
        if (e.target.classList.contains('sf-input')) return;   // clic dentro del campo en edición: mover el cursor, no apuntar
        // Escribiendo una fórmula: el clic elige la celda que se inserta como referencia, no cierra el editor.
        if (entradaFormula() && e.button === 0 && !MOVIL_ESTRECHO.matches) {
            e.preventDefault();
            var tAp = e.target.closest('td[data-r],th.sf-enc,th.sf-letra,th.sf-num');
            var ap = tAp ? celdaApuntada(tAp) : null;
            if (ap) {
                apuntando = null;
                var pos = insertarRef(refTexto({ r: ap.r, c: ap.c }, { r: ap.r2, c: ap.c2 }));
                if (pos) {
                    apuntando = { inp: pos.inp, ini: pos.ini, fin: pos.fin, a: { r: ap.r, c: ap.c } };
                    sel.r0 = ap.r; sel.c0 = ap.c; sel.r1 = ap.r2; sel.c1 = ap.c2;
                    pintarSeleccion();
                }
            }
            return;
        }
        // Un toque en pantalla táctil también dispara mousedown/click emulados: si acaba de abrirse el editor
        // (en la celda o en la hoja inferior) hay que ignorarlos, o lo cerrarían en el acto.
        if (Date.now() - toqueEdito < 700) { e.preventDefault(); return; }
        var t = e.target.closest('td,th'); if (!t || e.target.classList.contains('sf-input')) return;
        if (editor) terminarEdicion(true);
        if (t.classList.contains('sf-letra')) {
            var c = +t.dataset.letra;
            limpiarExtras();
            if (e.shiftKey) sel.c1 = c; else { sel.c0 = sel.c1 = c; sel.r0 = -1; }
            sel.r1 = S.rows.length - 1; pintarSeleccion(); e.preventDefault(); area.focus(); return;
        }
        if (t.classList.contains('sf-num')) { if (t.dataset.fila == null) return; var r = +t.dataset.fila; limpiarExtras(); if (e.shiftKey) sel.r1 = r; else { sel.r0 = sel.r1 = r; } sel.c0 = 0; sel.c1 = S.cols.length - 1; pintarSeleccion(); e.preventDefault(); area.focus(); return; }
        if (t.dataset.r == null) return;
        var rr = +t.dataset.r, cc = +t.dataset.c, mgSel = mergeDe(rr, cc);
        if ((e.ctrlKey || e.metaKey) && !mgSel) {   // Ctrl/⌘+clic: suma celdas sueltas a la selección
            alternarCeldaSuelta(rr, cc);
            arrastrando = false; pintarSeleccion(); e.preventDefault(); area.focus(); return;
        }
        limpiarExtras();
        if (mgSel) { sel.r0 = mgSel.r0; sel.c0 = mgSel.c0; sel.r1 = mgSel.r1; sel.c1 = mgSel.c1; }
        else if (e.shiftKey) { sel.r1 = rr; sel.c1 = cc; } else { sel.r0 = sel.r1 = rr; sel.c0 = sel.c1 = cc; }
        arrastrando = true; pintarSeleccion(); e.preventDefault(); area.focus();
    });
    grid.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('sf-filtro')) {
            e.preventDefault(); e.stopPropagation();
            var r = e.target.getBoundingClientRect();
            abrirFiltro(+e.target.dataset.fil, r.left, r.bottom);
        }
    });
    grid.addEventListener('mouseover', function (e) {
        if (rellenando) {
            var tr = e.target.closest('td[data-r]');
            if (tr && +tr.dataset.r >= 0) {
                rellenando.destino = destinoRelleno(rellenando.base, +tr.dataset.r, +tr.dataset.c);
                marcarRelleno();
            }
            return;
        }
        if (apuntando) {   // arrastrando para ampliar la referencia (B2 → B2:C4)
            var ta = e.target.closest('td[data-r]');
            if (ta && +ta.dataset.r >= 0) {
                var ra = +ta.dataset.r, ca = +ta.dataset.c;
                if ((editor && ra === editor.r && ca === editor.c) || (!editor && fxOrigen && ra === fxOrigen.r && ca === fxOrigen.c)) return;
                extenderRef(apuntando.a, { r: ra, c: ca });
                sel.r0 = apuntando.a.r; sel.c0 = apuntando.a.c; sel.r1 = ra; sel.c1 = ca;
                pintarSeleccion();
            }
            return;
        }
        if (!arrastrando) return;
        var t = e.target.closest('td,th.sf-enc'); if (!t || t.dataset.r == null) return;
        var mr = +t.dataset.r, mc = +t.dataset.c, mm = mergeDe(mr, mc);
        sel.r1 = mm ? mm.r1 : mr; sel.c1 = mm ? mm.c1 : mc; pintarSeleccion();
    });
    document.addEventListener('mousemove', function (e) {
        if (!tirando) return;
        var w = Math.max(40, Math.min(800, Math.round(tirando.w + (e.clientX - tirando.x) / (zoom / 100))));
        var col = grid.querySelectorAll('colgroup col')[tirando.c + 1];
        if (col) { col.style.width = w + 'px'; col.style.minWidth = w + 'px'; }
        tirando.nuevo = w;
    });
    document.addEventListener('mouseup', function () {
        if (rellenando) {
            var destino = rellenando.destino, base = rellenando.base;
            rellenando = null;
            grid.querySelectorAll('.sf-relleno').forEach(function (x) { x.classList.remove('sf-relleno'); });
            if (destino) aplicarRelleno(base, destino); else colocarAsa();
        }
        arrastrando = false;
        apuntando = null;
        if (tirando && tirando.nuevo) { pasado.push(tirando.antes); futuro = []; S.cols[tirando.c].w = tirando.nuevo; cambio(); dibujar(); }
        tirando = null;
    });
    grid.addEventListener('dblclick', function (e) {
        var tir = e.target.closest('.sf-tirador');
        if (tir) { antes(); S.cols[+tir.dataset.tir].w = 0; dibujar(); cambio(); return; }
        if (entradaFormula()) return;   // apuntando: el doble clic solo elige la referencia, no abre otra celda
        var t = e.target.closest('td,th.sf-enc'); if (!t || t.dataset.r == null) return;
        var dr = +t.dataset.r, dc = +t.dataset.c, dm = mergeDe(dr, dc);
        empezarEdicion(dm ? dm.r0 : dr, dm ? dm.c0 : dc, null);
    });
    // En pantalla táctil no hay doble clic: tocar (sin arrastrar) la celda ya elegida la abre para editar.
    // Se espera al touchend y se descarta si hubo desplazamiento, para no abrir el editor al hacer scroll.
    if (TACTIL) {
        var toqueCelda = null;
        // Mantener pulsada una celda y arrastrar para elegir un rango (como en Excel del celular)
        var toqueRango = null;
        var LARGO_TOQUE = 380;
        function finToqueRango() {
            if (!toqueRango) return false;
            clearTimeout(toqueRango.temporizador);
            var activo = toqueRango.activo;
            toqueRango = null;
            document.body.classList.remove('sf-eleccion-tactil');
            if (activo) { toqueEdito = Date.now(); toqueCelda = null; colocarAsa(); return true; }
            return false;
        }
        grid.addEventListener('touchstart', function (e) {
            if (editor || e.touches.length !== 1) { toqueCelda = null; finToqueRango(); return; }
            var t = e.target.closest('td,th.sf-enc'); if (!t || t.dataset.r == null) { toqueCelda = null; finToqueRango(); return; }
            var r = +t.dataset.r, c = +t.dataset.c, p = e.touches[0];
            toqueCelda = (r === sel.r0 && r === sel.r1 && c === sel.c0 && c === sel.c1)
                ? { r: r, c: c, x: p.clientX, y: p.clientY, t: Date.now() } : null;
            toqueRango = { r: r, c: c, x: p.clientX, y: p.clientY, activo: false, temporizador: 0 };
            if (!entradaFormula()) toqueRango.temporizador = setTimeout(function () {
                if (!toqueRango) return;
                toqueRango.activo = true;
                if (navigator.vibrate) { try { navigator.vibrate(20); } catch (err) { } }
                sel.r0 = sel.r1 = toqueRango.r; sel.c0 = sel.c1 = toqueRango.c;
                limpiarExtras(); pintarSeleccion();
                var asa = $('sf-asa'); if (asa) asa.hidden = true;
                document.body.classList.add('sf-eleccion-tactil');
                estado('Arrastra para elegir varias celdas');
            }, LARGO_TOQUE);
        }, { passive: true });
        grid.addEventListener('touchmove', function (e) {
            if (toqueRango && toqueRango.activo) {
                e.preventDefault();
                var p = e.touches[0];
                var el = document.elementFromPoint(p.clientX, p.clientY);
                var t = el && el.closest ? el.closest('td[data-r]') : null;
                if (t && +t.dataset.r >= 0) {
                    sel.r1 = +t.dataset.r; sel.c1 = +t.dataset.c;
                    var mm = mergeDe(+t.dataset.r, +t.dataset.c);
                    if (mm) { sel.r1 = mm.r1; sel.c1 = mm.c1; }
                    pintarSeleccion();
                }
                return;
            }
            if (!toqueCelda) return;
            var p2 = e.touches[0];
            if (Math.hypot(p2.clientX - toqueCelda.x, p2.clientY - toqueCelda.y) > 10) {
                toqueCelda = null;
                if (toqueRango) { clearTimeout(toqueRango.temporizador); toqueRango = null; }
            }
        }, { passive: false });
        grid.addEventListener('touchend', function () {
            if (finToqueRango()) return;
            if (!toqueCelda) return;
            var r = toqueCelda.r, c = toqueCelda.c, corto = Date.now() - toqueCelda.t < 600;
            toqueCelda = null;
            if (corto) { toqueEdito = Date.now(); setTimeout(function () { if (!editor) empezarEdicion(r, c, null); }, 0); }
        });
        grid.addEventListener('touchcancel', finToqueRango);
    }

    // ================= Teclado, copiar y pegar =================
    area.addEventListener('keydown', function (e) {
        if (editor) return;
        var k = e.key, ctrl = e.ctrlKey || e.metaKey;
        if (ctrl && (k === 'z' || k === 'Z')) { e.preventDefault(); e.shiftKey ? rehacer() : deshacer(); return; }
        if (ctrl && (k === 'y' || k === 'Y')) { e.preventDefault(); rehacer(); return; }
        if (ctrl && (k === 's' || k === 'S')) { e.preventDefault(); guardar(true); return; }
        if (ctrl && (k === 'b' || k === 'B')) { e.preventDefault(); alternar('b'); return; }
        if (ctrl && (k === 'i' || k === 'I')) { e.preventDefault(); alternar('i'); return; }
        if (ctrl && (k === 'u' || k === 'U')) { e.preventDefault(); alternar('u'); return; }
        if (ctrl && (k === 'a' || k === 'A')) { e.preventDefault(); limpiarExtras(); sel.r0 = -1; sel.c0 = 0; sel.r1 = S.rows.length - 1; sel.c1 = S.cols.length - 1; pintarSeleccion(); return; }
        if (ctrl && (k === 'f' || k === 'F')) { e.preventDefault(); panelBusca(true); return; }
        if (ctrl && (k === 'd' || k === 'D')) { e.preventDefault(); rellenarDireccion('abajo'); return; }
        if (ctrl && (k === 'r' || k === 'R')) { e.preventDefault(); rellenarDireccion('derecha'); return; }
        if (k === 'Escape') {
            panelBusca(false);
            var pf = $('sf-filtro'); if (pf) pf.hidden = true;
            var pv = $('sf-versiones'); if (pv) pv.hidden = true;
        }
        if (ctrl) return;   // copiar/cortar/pegar van por sus eventos
        if (k === 'ArrowDown') { e.preventDefault(); mover(1, 0, e.shiftKey); }
        else if (k === 'ArrowUp') { e.preventDefault(); mover(-1, 0, e.shiftKey); }
        else if (k === 'ArrowLeft') { e.preventDefault(); mover(0, -1, e.shiftKey); }
        else if (k === 'ArrowRight') { e.preventDefault(); mover(0, 1, e.shiftKey); }
        else if (k === 'Tab') { e.preventDefault(); mover(0, e.shiftKey ? -1 : 1, false); }
        else if (k === 'Enter' || k === 'F2') { e.preventDefault(); empezarEdicion(sel.r0, sel.c0, null); }
        else if (k === 'Delete' || k === 'Backspace') { e.preventDefault(); vaciar(); }
        else if (k.length === 1 && !e.altKey) { e.preventDefault(); empezarEdicion(sel.r0, sel.c0, k); }
    });
    function vaciar() {
        var celdas = celdasElegidas(); antes();
        celdas.forEach(function (p) { if (p[0] >= 0 && p[0] < S.rows.length && p[1] < S.cols.length) S.rows[p[0]][p[1]] = ''; });
        dibujar(); cambio();
    }
    function textoRango() {
        var n = rangoNorm(), out = [];
        for (var r = n.r0; r <= n.r1; r++) { var f = []; for (var c = n.c0; c <= n.c1; c++) f.push(r < 0 ? S.cols[c].n : S.rows[r][c]); out.push(f.join('\t')); }
        return out.join('\n');
    }
    /** Guarda el rango en el portapapeles interno (valores y formatos) para pegar, pegar solo valores o solo formato */
    function capturarCopia() {
        var n = rangoNorm(), filas = [], fmts = [];
        for (var r = n.r0; r <= n.r1; r++) {
            var f = [], st = [];
            for (var c = n.c0; c <= n.c1; c++) {
                f.push(r < 0 ? S.cols[c].n : S.rows[r][c]);
                var x = getSt(r, c);
                st.push(x ? Object.assign({}, x) : null);
            }
            filas.push(f); fmts.push(st);
        }
        copiado = { r0: n.r0, c0: n.c0, r1: n.r1, c1: n.c1, filas: filas, fmt: fmts, texto: filas.map(function (f) { return f.join('\t'); }).join('\n') };
    }
    area.addEventListener('copy', function (e) { if (editor) return; capturarCopia(); e.clipboardData.setData('text/plain', copiado.texto); e.preventDefault(); });
    area.addEventListener('cut', function (e) { if (editor) return; capturarCopia(); e.clipboardData.setData('text/plain', copiado.texto); e.preventDefault(); vaciar(); });
    /** Pega la copia interna ajustando las fórmulas; $soloValores deja el resultado sin fórmula y $soloFormato no toca el contenido */
    function pegarInterno(t, soloValores, soloFormato) {
        if (!copiado) { estado('Copia primero una celda o un rango', true); return; }
        var h = copiado.r1 - copiado.r0 + 1, w = copiado.c1 - copiado.c0 + 1;
        var r0 = Math.max(0, t.r0), c0 = Math.max(0, t.c0);
        var nf = h, nc = w;
        // si el destino es mayor y múltiplo del origen, se repite (como Excel)
        var th = t.r1 - r0 + 1, tw = t.c1 - c0 + 1;
        if (th > h && tw >= w && th % h === 0 && (tw % w === 0 || tw === 1)) { nf = th; nc = tw > w && tw % w === 0 ? tw : w; }
        else if (tw > w && tw % w === 0 && th <= h) { nc = tw; }
        antes();
        while (S.cols.length < Math.min(MAX_C, c0 + nc)) { S.cols.push({ n: 'Columna ' + (S.cols.length + 1), t: 'texto', a: 'izquierda', tot: '', w: 0 }); hsty.push(null); S.rows.forEach(function (f, r) { f.push(''); sty[r].push(null); }); }
        while (S.rows.length < Math.min(MAX_F, r0 + nf)) { S.rows.push(S.cols.map(function () { return ''; })); sty.push(S.cols.map(function () { return null; })); ids.push(null); }
        for (var i = 0; i < nf; i++) for (var j = 0; j < nc; j++) {
            var r = r0 + i, c = c0 + j;
            if (r >= MAX_F || c >= MAX_C) continue;
            var si = i % h, sj = j % w, sr = copiado.r0 + si, sc = copiado.c0 + sj;
            if (!soloFormato) {
                var raw = copiado.filas[si][sj], v;
                if (soloValores) v = esFormula(raw) ? (S.calc[sr + ',' + sc] || '') : raw;
                else v = esFormula(raw) ? ajustarFormula(raw, r - sr, c - sc) : raw;
                fijar(r, c, v);
            }
            if (soloValores) continue;
            var st = copiado.fmt[si][sj];
            setSt(r, c, st ? Object.assign({}, st) : null);
        }
        limpiarExtras();
        sel.r0 = r0; sel.c0 = c0; sel.r1 = Math.min(S.rows.length - 1, r0 + nf - 1); sel.c1 = Math.min(S.cols.length - 1, c0 + nc - 1);
        dibujar(); cambio(); area.focus();
    }
    area.addEventListener('paste', function (e) {
        if (editor) return;
        var t = e.clipboardData.getData('text/plain'); if (t === '') return;
        e.preventDefault();
        var n = rangoNorm();
        if (copiado && t === copiado.texto) { pegarInterno(n, false, false); return; }   // copia interna: referencias relativas
        var filas = t.replace(/\r\n?/g, '\n').replace(/\n$/, '').split('\n').map(function (l) { return l.split('\t'); });
        var r0 = Math.max(0, n.r0), c0 = n.c0;
        // una sola celda copiada pegada sobre un rango: se repite en todo el rango
        var uno = filas.length === 1 && filas[0].length === 1 && (n.r1 > n.r0 || n.c1 > n.c0);
        antes();
        var nf = uno ? n.r1 - r0 + 1 : filas.length, nc = uno ? n.c1 - c0 + 1 : Math.max.apply(null, filas.map(function (f) { return f.length; }));
        while (S.cols.length < Math.min(MAX_C, c0 + nc)) { S.cols.push({ n: 'Columna ' + (S.cols.length + 1), t: 'texto', a: 'izquierda', tot: '', w: 0 }); hsty.push(null); S.rows.forEach(function (f, r) { f.push(''); sty[r].push(null); }); }
        while (S.rows.length < Math.min(MAX_F, r0 + nf)) { S.rows.push(S.cols.map(function () { return ''; })); sty.push(S.cols.map(function () { return null; })); ids.push(null); }
        var cortado = false;
        for (var i = 0; i < nf; i++) for (var j = 0; j < nc; j++) {
            var r = r0 + i, c = c0 + j;
            if (r >= MAX_F || c >= MAX_C) { cortado = true; continue; }
            fijar(r, c, uno ? filas[0][0] : (filas[i][j] || ''));
        }
        limpiarExtras();
        sel.r0 = r0; sel.c0 = c0; sel.r1 = Math.min(S.rows.length - 1, r0 + nf - 1); sel.c1 = Math.min(S.cols.length - 1, c0 + nc - 1);
        dibujar(); cambio();
        if (cortado) estado('Se pegó solo lo que cabe (máx. ' + MAX_C + ' columnas y ' + MAX_F + ' renglones)', true);
    });

    // ================= Estructura: insertar, eliminar, ordenar =================
    /** Reescribe las referencias A1 de las fórmulas cuando cambia la estructura. `mapa(n)` devuelve el nuevo índice (0 = base) o -1 si se borró */
    function reescribir(eje, mapa) {
        var re = /(\$?)([A-Za-z]{1,2})(\$?)(\d+)(?::(\$?)([A-Za-z]{1,2})(\$?)(\d+))?(?![A-Za-z(])/g;
        S.rows.forEach(function (fila) {
            fila.forEach(function (t, c) {
                if (!esFormula(t)) return;
                fila[c] = fueraDeTexto(t, function (trozo) {
                    return trozo.replace(re, function (m, d1, l1, d2, n1, d3, l2, d4, n2) {
                        var a = { c: indiceLetra(l1.toUpperCase()), r: parseInt(n1, 10) - 2 };
                        var b = l2 ? { c: indiceLetra(l2.toUpperCase()), r: parseInt(n2, 10) - 2 } : null;
                        function nuevo(p, modo) { var v = p[eje]; return v < 0 ? v : mapa(v, modo); }
                        var na = nuevo(a, b ? 'ini' : 'solo'), nb = b ? nuevo(b, 'fin') : na;
                        if (na === -1 || nb === -1 || (b && na > nb)) return errores.ref;
                        var ra = eje === 'r' ? na : a.r, ca = eje === 'c' ? na : a.c;
                        var s = d1 + letra(ca) + d2 + (ra + 2);
                        if (b) { var rb = eje === 'r' ? nb : b.r, cb = eje === 'c' ? nb : b.c; s += ':' + d3 + letra(cb) + d4 + (rb + 2); }
                        return s;
                    });
                });
            });
        });
    }
    /** Mueve los rectángulos combinados al insertar/eliminar renglones o columnas */
    function insertarMerges(eje, en, k) {
        S.merges.forEach(function (m) {
            var a = eje === 'r' ? m.r0 : m.c0, b = eje === 'r' ? m.r1 : m.c1;
            if (en <= a) { a += k; b += k; }
            else if (en <= b) b += k;
            if (eje === 'r') { m.r0 = a; m.r1 = b; } else { m.c0 = a; m.c1 = b; }
        });
    }
    function eliminarMerges(eje, desde, hasta) {
        var k = hasta - desde + 1, out = [];
        S.merges.forEach(function (m) {
            var a = eje === 'r' ? m.r0 : m.c0, b = eje === 'r' ? m.r1 : m.c1;
            if (b < desde || a > hasta) {
                if (a > hasta) { a -= k; b -= k; }
            } else {
                var na = a < desde ? a : desde;
                var nb = b > hasta ? b - k : desde - 1;
                if (nb < na) return;
                a = na; b = nb;
            }
            if (eje === 'r') { m.r0 = a; m.r1 = b; } else { m.c0 = a; m.c1 = b; }
            out.push(m);
        });
        S.merges = out;
    }
    function insertar(eje, en, k) {
        limpiarExtras();
        insertarMerges(eje, en, k);
        reescribir(eje, function (v) { return v >= en ? v + k : v; });
        if (eje === 'r') for (var i = 0; i < k; i++) { S.rows.splice(en, 0, S.cols.map(function () { return ''; })); sty.splice(en, 0, S.cols.map(function () { return null; })); ids.splice(en, 0, null); }
        else for (var j = 0; j < k; j++) { S.cols.splice(en, 0, { n: 'Columna ' + (S.cols.length + 1), t: 'texto', a: 'izquierda', tot: '', w: 0 }); hsty.splice(en, 0, null); S.rows.forEach(function (f, r) { f.splice(en, 0, ''); sty[r].splice(en, 0, null); }); }
    }
    function eliminar(eje, desde, hasta) {
        limpiarExtras();
        var k = hasta - desde + 1;
        eliminarMerges(eje, desde, hasta);
        reescribir(eje, function (v, modo) {
            if (v < desde) return v;
            if (v > hasta) return v - k;
            // dentro de lo borrado: un rango se acorta, una celda suelta se pierde
            return modo === 'solo' ? -1 : modo === 'fin' ? (desde - 1 >= 0 ? desde - 1 : -1) : desde;
        });
        if (eje === 'r') { S.rows.splice(desde, k); sty.splice(desde, k); ids.splice(desde, k); }
        else { S.cols.splice(desde, k); hsty.splice(desde, k); S.rows.forEach(function (f, r) { f.splice(desde, k); sty[r].splice(desde, k); }); }
    }
    document.querySelectorAll('[data-acc]').forEach(function (b) {
        b.addEventListener('click', async function () {
            var a = b.dataset.acc, n = rangoNorm(), k;
            if (a === 'fila-arriba' || a === 'fila-abajo') {
                k = Math.max(1, n.r1 - Math.max(0, n.r0) + 1);
                if (S.rows.length + k > MAX_F) { estado('Máximo ' + MAX_F + ' renglones', true); return; }
                antes(); var en = a === 'fila-arriba' ? Math.max(0, n.r0) : n.r1 + 1; insertar('r', en, k);
                sel.r0 = sel.r1 = en; sel.c1 = sel.c0;
            } else if (a === 'fila-borrar') {
                var d = Math.max(0, n.r0); if (n.r1 < 0) return;
                var vinculadas = 0;
                for (var rr = d; rr <= n.r1; rr++) if (ids[rr]) vinculadas++;
                if (vinculadas && !await window.confirmarModal({ titulo: '¿Eliminar el renglón?', mensaje: 'Está vinculado a una subactividad. Si lo eliminas, también se eliminará ' + vinculadas + ' subactividad' + (vinculadas === 1 ? '' : 'es') + '.', aceptar: 'Eliminar', icono: 'fa-link-slash' })) return;
                antes(); eliminar('r', d, n.r1);
                if (!S.rows.length) { S.rows.push(S.cols.map(function () { return ''; })); sty.push(S.cols.map(function () { return null; })); ids.push(null); }
                sel.r0 = sel.r1 = Math.min(d, S.rows.length - 1);
            } else if (a === 'col-izq' || a === 'col-der') {
                k = n.c1 - n.c0 + 1;
                if (S.cols.length + k > MAX_C) { estado('Máximo ' + MAX_C + ' columnas', true); return; }
                antes(); var ec = a === 'col-izq' ? n.c0 : n.c1 + 1; insertar('c', ec, k);
                sel.c0 = sel.c1 = ec;
            } else if (a === 'col-borrar') {
                if (n.c1 - n.c0 + 1 >= S.cols.length) { estado('La tabla necesita al menos una columna', true); return; }
                for (var cc = n.c0; cc <= n.c1; cc++) if (S.cols[cc] && S.cols[cc].fija) { estado('Las columnas Nombre, Inicio, Fin y Avance están vinculadas a las subactividades y no se pueden eliminar.', true); return; }
                antes(); eliminar('c', n.c0, n.c1); sel.c0 = sel.c1 = Math.min(n.c0, S.cols.length - 1);
            } else if (a === 'congelar') {
                var frC = Math.min(80, Math.max(0, sel.r0));
                var fcC = Math.min(10, Math.max(0, sel.c0));
                if (!frC && !fcC) { estado('Elige una celda que no esté en la primera fila ni en la primera columna', true); return; }
                antes(); S.vista.filas = frC; S.vista.cols = fcC;
                estado('Inmovilizado: ' + frC + ' renglón(es) y ' + fcC + ' columna(s). «Quitar» para soltarlo.');
            } else if (a === 'descongelar') {
                antes(); S.vista.filas = 0; S.vista.cols = 0;
            } else if (a === 'orden-asc' || a === 'orden-desc') {
                if (S.rows.some(function (f) { return f.some(esFormula); })) { estado('No se puede ordenar una tabla con fórmulas (las referencias cambiarían)', true); return; }
                if (S.merges.length) { estado('No se puede ordenar una tabla con celdas combinadas', true); return; }
                var col = sel.c0, tipo = S.cols[col].t, dir = a === 'orden-asc' ? 1 : -1;
                antes();
                var pares = S.rows.map(function (f, r) { return { f: f, s: sty[r], i: ids[r] || null }; });
                var vacio = function (p) { return !p.f.some(function (x) { return x !== ''; }); };
                var llenas = pares.filter(function (p) { return !vacio(p); }), vacias = pares.filter(vacio);
                llenas.sort(function (x, y) {
                    var p = x.f[col], q = y.f[col];
                    if (p === '' && q !== '') return 1; if (q === '' && p !== '') return -1;
                    var np = numero(p), nq = numero(q);
                    if (NUM.indexOf(tipo) >= 0 && np !== null && nq !== null) return (np - nq) * dir;
                    return String(p).localeCompare(String(q), 'es', { numeric: true, sensitivity: 'base' }) * dir;
                });
                var todas = llenas.concat(vacias);
                S.rows = todas.map(function (p) { return p.f; }); sty = todas.map(function (p) { return p.s; }); ids = todas.map(function (p) { return p.i; });
                limpiarExtras();
            } else if (a === 'combinar') {
                var nC = rangoNorm();
                if (nC.r0 < 0 && nC.r1 >= 0) { estado('No se pueden combinar el encabezado con los renglones de datos', true); return; }
                if (nC.r0 === nC.r1 && nC.c0 === nC.c1) { estado('Elige varias celdas para combinar', true); return; }
                for (var cf = nC.c0; cf <= nC.c1; cf++) if (S.cols[cf] && S.cols[cf].fija) { estado('Hay columnas vinculadas a subactividades o materiales que no se pueden combinar.', true); return; }
                antes();
                var mgN = { r0: nC.r0, c0: nC.c0, r1: nC.r0 < 0 ? -1 : Math.max(0, nC.r1), c1: nC.c1 };
                S.merges = S.merges.filter(function (x) { return !mergeToca(x, mgN); });
                for (var rC = mgN.r0; rC <= mgN.r1; rC++) for (var cC = mgN.c0; cC <= mgN.c1; cC++) {
                    if (rC === mgN.r0 && cC === mgN.c0) continue;
                    if (rC < 0) S.cols[cC].n = ''; else S.rows[rC][cC] = '';
                }
                S.merges.push(mgN);
                var fC = Object.assign({}, getSt(mgN.r0, mgN.c0) || {}); fC.al = 'centro'; setSt(mgN.r0, mgN.c0, fC);
                sel.r0 = mgN.r0; sel.c0 = mgN.c0; sel.r1 = mgN.r1; sel.c1 = mgN.c1;
            } else if (a === 'separar') {
                var nS = rangoNorm();
                var toca = S.merges.filter(function (x) { return mergeToca(x, nS); });
                if (!toca.length) { estado('La selección no tiene celdas combinadas', true); return; }
                antes();
                S.merges = S.merges.filter(function (x) { return !mergeToca(x, nS); });
            }
            dibujar(); cambio(); area.focus();
        });
    });

    // ================= Propiedades de columna y estilo de la tabla =================
    function columnasElegidas() {
        var o = [];
        celdasElegidas().forEach(function (p) { if (p[1] >= 0 && o.indexOf(p[1]) < 0) o.push(p[1]); });
        return o.sort(function (a, b) { return a - b; });
    }
    $('sf-tipo').addEventListener('change', function () {
        antes(); var t = this.value;
        columnasElegidas().forEach(function (c) {
            var col = S.cols[c]; col.t = t; col.a = NUM.indexOf(t) >= 0 ? 'derecha' : (t === 'fecha' || t === 'casilla' || t === 'semaforo' ? 'centro' : 'izquierda');
            if (t === 'lista') col.op = col.op || []; else delete col.op;
            if (col.tot && col.tot !== 'cuenta' && NUM.indexOf(t) < 0) col.tot = '';
            S.rows.forEach(function (f, r) { if (!esFormula(f[c])) f[c] = normalizar(f[c], t); });
        });
        sincronizarBarra(); dibujar(); cambio();
    });
    $('sf-opciones').addEventListener('change', function () {
        antes();
        var op = this.value.split(',').map(function (x) { return x.trim().slice(0, 40); }).filter(function (x, i, a) { return x && a.indexOf(x) === i; }).slice(0, 20);
        columnasElegidas().forEach(function (c) { if (S.cols[c].t === 'lista') S.cols[c].op = op; });
        cambio();
    });
    $('sf-total').addEventListener('change', function () {
        var v = this.value;
        antes();
        columnasElegidas().forEach(function (c) { var col = S.cols[c]; col.tot = v === '' || v === 'cuenta' || NUM.indexOf(col.t) >= 0 ? v : ''; });
        if (v && v !== 'cuenta' && columnasElegidas().some(function (c) { return NUM.indexOf(S.cols[c].t) < 0; })) estado('El total solo se aplica a columnas de tipo número, moneda o porcentaje (o «Cuenta»)', true);
        dibujar(); cambio();
    });
    $('sf-ancho-auto').addEventListener('click', function () { antes(); columnasElegidas().forEach(function (c) { S.cols[c].w = 0; }); dibujar(); cambio(); });
    $('sf-c-enc').addEventListener('input', function () { S.estilo.enc = this.value; dibujar(); cambio(); });
    $('sf-c-txt').addEventListener('input', function () { S.estilo.encTxt = this.value; dibujar(); cambio(); });
    $('sf-zebra').addEventListener('change', function () { S.estilo.zebra = this.checked; dibujar(); cambio(); });
    $('sf-bordes').addEventListener('change', function () { S.estilo.bordes = this.checked; dibujar(); cambio(); });
    $('sf-zoom').addEventListener('input', function () { zoom = +this.value; $('sf-zoom-v').textContent = zoom + '%'; grid.style.zoom = zoom / 100; });

    // Pellizco de dos dedos sobre la tabla: mismo zoom que el control de «Vista» (gestos-lienzo.js)
    if (TACTIL && window.GestosLienzo) {
        var sfScroll = document.querySelector('.sf-scroll');
        if (sfScroll) GestosLienzo.montar(sfScroll, {
            zoomActual: function () { return zoom / 100; },
            min: 0.5, max: 2,
            alMover: function (g) {
                zoom = Math.round(g.z * 100);
                grid.style.zoom = zoom / 100;
                $('sf-zoom').value = zoom;
                $('sf-zoom-v').textContent = zoom + '%';
            },
        });
    }

    // ================= Formato de celdas: letra, colores, alineación, bordes, imagen =================
    /** Aplica fn(formato) a cada celda elegida (incluye el encabezado si está en la selección) */
    function aplicar(fn, sinAntes) {
        var celdas = celdasElegidas();
        if (!sinAntes) antes();
        celdas.forEach(function (p) {
            var f = Object.assign({}, getSt(p[0], p[1]) || {});
            fn(f, p[0], p[1]);
            setSt(p[0], p[1], f);
        });
        dibujar(); cambio(); area.focus();
    }
    function alternar(k) {
        var ancla = getSt(sel.r0, sel.c0) || {}, on = !ancla[k];
        aplicar(function (f) { if (on) f[k] = 1; else delete f[k]; });
    }
    document.querySelectorAll('[data-fmt]').forEach(function (b) { b.addEventListener('click', function () { alternar(b.dataset.fmt); }); });
    document.querySelectorAll('[data-alin]').forEach(function (b) {
        b.addEventListener('click', function () {
            var v = b.dataset.alin;
            antes();
            columnasElegidas().forEach(function (c) { S.cols[c].a = v; });   // la alineación de la columna
            aplicar(function (f) { delete f.al; }, true);                    // y se quita la particular de las celdas para que mande la columna
        });
    });
    function cambiarTamano(delta, valor) {
        aplicar(function (f) { f.fs = valor != null ? valor : Math.max(6, Math.min(72, (f.fs || 13) + delta)); });
    }
    $('sf-fs-menos').addEventListener('click', function () { cambiarTamano(-1); });
    $('sf-fs-mas').addEventListener('click', function () { cambiarTamano(1); });
    $('sf-fs').addEventListener('change', function () { var v = parseInt(this.value, 10); if (v >= 6 && v <= 72) cambiarTamano(0, v); });
    $('sf-fs').addEventListener('keydown', function (e) { e.stopPropagation(); if (e.key === 'Enter') { this.dispatchEvent(new Event('change')); } });
    $('sf-c-letra').addEventListener('input', function () { var v = this.value; aplicar(function (f) { f.tc = v; }); });
    $('sf-c-fondo').addEventListener('input', function () { var v = this.value; aplicar(function (f) { f.bg = v; }); });
    $('sf-sin-relleno').addEventListener('click', function () { aplicar(function (f) { delete f.bg; }); });
    $('sf-limpiar-fmt').addEventListener('click', function () { aplicar(function (f) { Object.keys(f).forEach(function (k) { if (k !== 'im') delete f[k]; }); }); });

    /** Borde de un lado de la celda (r,c); también el lado contiguo de la vecina para que no choquen al fundirse las líneas */
    function ponerBorde(r, c, lado, valor) {
        var op = { bt: 'bb', bb: 'bt', bl: 'br', br: 'bl' }[lado];
        var dr = lado === 'bt' ? -1 : lado === 'bb' ? 1 : 0, dc = lado === 'bl' ? -1 : lado === 'br' ? 1 : 0;
        var f = Object.assign({}, getSt(r, c) || {}); f[lado] = valor; setSt(r, c, f);
        var rr = r + dr, cc = c + dc;
        if (rr >= -1 && rr < S.rows.length && cc >= 0 && cc < S.cols.length) { var g = Object.assign({}, getSt(rr, cc) || {}); g[op] = valor; setSt(rr, cc, g); }
    }
    $('sf-b-aplicar').addEventListener('click', function () {
        var lado = $('sf-b-lado').value, n = rangoNorm();
        var val = lado === 'ninguno' ? 'n' : $('sf-b-grosor').value + '|' + $('sf-b-estilo').value + '|' + $('sf-b-color').value;
        antes();
        for (var r = n.r0; r <= n.r1; r++) for (var c = n.c0; c <= n.c1; c++) {
            var arriba = r === n.r0, abajo = r === n.r1, izq = c === n.c0, der = c === n.c1;
            if (lado === 'todos' || lado === 'ninguno') { ['bt', 'br', 'bb', 'bl'].forEach(function (l) { ponerBorde(r, c, l, val); }); continue; }
            if ((lado === 'exterior' || lado === 'arriba') && arriba) ponerBorde(r, c, 'bt', val);
            if ((lado === 'exterior' || lado === 'abajo') && abajo) ponerBorde(r, c, 'bb', val);
            if ((lado === 'exterior' || lado === 'izquierda') && izq) ponerBorde(r, c, 'bl', val);
            if ((lado === 'exterior' || lado === 'derecha') && der) ponerBorde(r, c, 'br', val);
        }
        dibujar(); cambio(); area.focus();
    });

    // Imagen en la celda: se sube y su ruta queda en el formato de la celda
    $('sf-img-poner').addEventListener('click', function () { $('sf-img-archivo').click(); });
    $('sf-img-archivo').addEventListener('change', function () {
        var f = this.files[0]; this.value = ''; if (!f) return;
        subirImagenCelda(f);
    });
    // Símbolo de la biblioteca: se pasa a PNG y se sube igual que una imagen propia
    var selSimbolo = $('sf-img-simbolo');
    if (selSimbolo) selSimbolo.addEventListener('change', function () {
        var s = this.value; this.value = ''; if (!s) return;
        var im = new Image();
        im.onload = function () {
            var k = 256 / Math.max(im.naturalWidth || 256, im.naturalHeight || 256), cv = document.createElement('canvas');
            cv.width = Math.round((im.naturalWidth || 256) * k); cv.height = Math.round((im.naturalHeight || 256) * k);
            cv.getContext('2d').drawImage(im, 0, 0, cv.width, cv.height);
            cv.toBlob(function (b) { if (b) subirImagenCelda(b); else estado('No se pudo preparar el símbolo', true); }, 'image/png');
        };
        im.onerror = function () { estado('No se pudo cargar el símbolo', true); };
        im.src = s;
    });
    function subirImagenCelda(f) {
        var fd = new FormData(); fd.append('id', C.id); fd.append('proyecto', C.proyecto); fd.append('imagen', f, f.name || 'simbolo.png');
        estado('Subiendo imagen…');
        fetch('/api/sheetflow/imagen', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.success) { estado(d.error || 'No se pudo subir la imagen', true); return; }
            var ancla = { r: sel.r0, c: sel.c0 };
            antes();
            var g = Object.assign({}, getSt(ancla.r, ancla.c) || {}); g.im = d.src; setSt(ancla.r, ancla.c, g);
            dibujar(); cambio(); estado('Imagen puesta en ' + nombreCelda(ancla.r, ancla.c));
        }).catch(function () { estado('Sin conexión: no se pudo subir la imagen', true); });
    }
    $('sf-img-quitar').addEventListener('click', function () { aplicar(function (f) { delete f.im; }); });

    // ================= Buscar y reemplazar =================
    function panelBusca(abrir) {
        var p = $('sf-busca'); if (!p) return;
        p.hidden = !abrir;
        if (abrir) { var i = $('sf-busca-txt'); if (i) { i.focus(); i.select(); } }
    }
    function coincideCelda(r, c, txt, mayus, form) {
        var raw = String(S.rows[r][c] == null ? '' : S.rows[r][c]), vis = String(mostrado(r, c));
        function hay(s) { return mayus ? s.indexOf(txt) >= 0 : s.toLowerCase().indexOf(txt.toLowerCase()) >= 0; }
        return hay(vis) || (form && hay(raw));
    }
    function datosBusqueda() {
        var t = $('sf-busca-txt'), info = $('sf-busca-info');
        return {
            texto: t ? t.value : '',
            mayus: !!($('sf-busca-mayus') && $('sf-busca-mayus').checked),
            form: !!($('sf-busca-formulas') && $('sf-busca-formulas').checked),
            info: info,
        };
    }
    function buscarSiguiente() {
        var b = datosBusqueda();
        if (b.texto === '') { b.info.textContent = ''; return false; }
        var filas = S.rows.length, cols = S.cols.length, total = filas * cols;
        var idx = Math.max(0, sel.r1) * cols + sel.c1 + 1;
        for (var k = 0; k < total; k++) {
            var pos = (idx + k) % total, r = Math.floor(pos / cols), c = pos % cols;
            if (!filaVisible(r)) continue;
            if (coincideCelda(r, c, b.texto, b.mayus, b.form)) {
                sel.r0 = sel.r1 = r; sel.c0 = sel.c1 = c;
                pintarSeleccion(); visible(r, c);
                var el = celdaEl(r, c);
                if (el) el.classList.add('sf-hallado');
                b.info.textContent = nombreCelda(r, c);
                return true;
            }
        }
        b.info.textContent = 'Sin coincidencias';
        return false;
    }
    function reemplazarActual() {
        var b = datosBusqueda();
        if (b.texto === '') return;
        if (!coincideCelda(sel.r0, sel.c0, b.texto, b.mayus, b.form)) { buscarSiguiente(); return; }
        var raw = S.rows[sel.r0][sel.c0];
        if (esFormula(raw) && !b.form) { estado('La celda es una fórmula: marca «En fórmulas» para reemplazar dentro de ella', true); return; }
        var rep = $('sf-busca-rep') ? $('sf-busca-rep').value : '';
        var re = new RegExp(b.texto.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), b.mayus ? '' : 'i');
        var nuevo = raw.replace(re, function () { return rep; });
        if (nuevo === raw) { buscarSiguiente(); return; }
        antes(); fijar(sel.r0, sel.c0, nuevo); dibujar(); cambio(); buscarSiguiente();
    }
    function reemplazarTodo() {
        var b = datosBusqueda();
        if (b.texto === '') return;
        var rep = $('sf-busca-rep') ? $('sf-busca-rep').value : '';
        var re = new RegExp(b.texto.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), b.mayus ? 'g' : 'gi');
        var n = 0;
        antes();
        S.rows.forEach(function (fila, r) { fila.forEach(function (raw, c) {
            if (esFormula(raw) && !b.form) return;
            var nuevo = String(raw).replace(re, function () { return rep; });
            if (nuevo !== raw) { fijar(r, c, nuevo); n++; }
        }); });
        dibujar(); cambio();
        b.info.textContent = n ? n + ' celda' + (n === 1 ? '' : 's') + ' reemplazada' + (n === 1 ? '' : 's') : 'Sin coincidencias';
        if (n) estado(n + ' celda' + (n === 1 ? '' : 's') + ' reemplazada' + (n === 1 ? '' : 's'));
    }
    (function () {
        if (!$('sf-busca')) return;
        $('sf-busca-ir').addEventListener('click', buscarSiguiente);
        $('sf-busca-rep1').addEventListener('click', reemplazarActual);
        $('sf-busca-repall').addEventListener('click', reemplazarTodo);
        $('sf-busca-cerrar').addEventListener('click', function () { panelBusca(false); area.focus(); });
        $('sf-busca-txt').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); buscarSiguiente(); } });
        $('sf-busca-rep').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); reemplazarActual(); } });
        $('sf-busca').addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); panelBusca(false); area.focus(); } });
    })();

    // ================= Filtros por columna =================
    function abrirFiltro(c, x, y) {
        var p = $('sf-filtro'); if (!p) return;
        var f = S.vista.filtros[c] || {}, vals = [], vistos = {};
        for (var r = 0; r < S.rows.length && vals.length < 300; r++) {
            var v = mostrado(r, c);
            if (v === '' || vistos[v]) continue;
            vistos[v] = true; vals.push(v);
        }
        vals.sort(function (a, b) { return String(a).localeCompare(String(b), 'es', { numeric: true, sensitivity: 'base' }); });
        var sel = f.vals && f.vals.length ? f.vals : vals;
        p.innerHTML = '<div class="sf-filtro-cab"><strong>Filtrar «' + esc(S.cols[c].n) + '»</strong><button type="button" data-cerrar title="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>' +
            '<input type="text" id="sf-filtro-busca" placeholder="Buscar en la lista…" autocomplete="off">' +
            '<div class="sf-filtro-lista">' + (vals.length ? vals.map(function (v) {
                return '<label><input type="checkbox" value="' + esc(v) + '"' + (sel.indexOf(v) >= 0 ? ' checked' : '') + '> ' + esc(v) + '</label>';
            }).join('') : '<p class="text-muted text-sm">La columna está vacía.</p>') + '</div>' +
            '<div class="sf-filtro-pie"><button type="button" class="btn btn-sm btn-ghost" data-todos>Todo</button><button type="button" class="btn btn-sm btn-ghost" data-ninguno>Ninguno</button>' +
            '<button type="button" class="btn btn-sm btn-primary" data-aplicar>Aplicar</button><button type="button" class="btn btn-sm btn-ghost" data-limpiar>Quitar</button></div>';
        p.hidden = false;
        p.style.left = Math.max(8, Math.min(window.innerWidth - 330, x - 140)) + 'px';
        p.style.top = Math.max(8, Math.min(window.innerHeight - 360, y + 10)) + 'px';
        var lista = p.querySelector('.sf-filtro-lista');
        var cajas = function () { return Array.prototype.slice.call(lista.querySelectorAll('input')); };
        p.querySelector('[data-cerrar]').addEventListener('click', function () { p.hidden = true; });
        p.querySelector('[data-todos]').addEventListener('click', function () { cajas().forEach(function (i) { i.checked = true; }); });
        p.querySelector('[data-ninguno]').addEventListener('click', function () { cajas().forEach(function (i) { i.checked = false; }); });
        p.querySelector('[data-limpiar]').addEventListener('click', function () {
            antes(); delete S.vista.filtros[c]; p.hidden = true; dibujar(); cambio();
        });
        p.querySelector('[data-aplicar]').addEventListener('click', function () {
            var marcados = cajas().filter(function (i) { return i.checked; }).map(function (i) { return i.value; });
            antes();
            if (!marcados.length) S.vista.filtros[c] = { vals: [''], texto: '' };        // nada marcado: no se ve ningún renglón
            else if (marcados.length === vals.length) delete S.vista.filtros[c];
            else S.vista.filtros[c] = { vals: marcados, texto: '' };
            p.hidden = true; dibujar(); cambio();
        });
        var buscador = $('sf-filtro-busca');
        buscador.addEventListener('input', function () {
            var t = this.value.toLowerCase();
            lista.querySelectorAll('label').forEach(function (l) { l.hidden = t !== '' && l.textContent.toLowerCase().indexOf(t) < 0; });
        });
        buscador.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); p.hidden = true; } });
        buscador.focus();
    }

    // ================= Historial de versiones =================
    function recargar(d) {
        S.cols = d.cols; S.rows = d.rows; S.calc = d.calc || {}; S.tot = d.tot || {};
        S.estilo = d.estilo; S.fmt = d.fmt || {}; S.meta = d.meta || {}; S.merges = d.merges || []; S.vista = d.vista || {};
        normalizarVista(); cargarEstilos();
        ids = idsDeMeta();
        limpiarExtras();
        sel = { r0: 0, c0: 0, r1: 0, c1: 0 };
        pasado = []; futuro = [];
        dibujar();
    }
    function abrirHistorial() {
        var p = $('sf-versiones'); if (!p) return;
        p.hidden = false;
        var lista = $('sf-versiones-lista');
        lista.innerHTML = '<p class="text-muted text-sm">Cargando…</p>';
        var fd = new FormData(); fd.append('id', C.id); fd.append('proyecto', C.proyecto);
        fetch(C.api.versiones, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.success) { lista.innerHTML = '<p class="text-muted text-sm">' + esc(d.error || 'No se pudo cargar el historial') + '</p>'; return; }
            if (!d.versiones.length) { lista.innerHTML = '<p class="text-muted text-sm">Todavía no hay versiones. Se guardan al editar la tabla.</p>'; return; }
            lista.innerHTML = d.versiones.map(function (v) {
                var t = String(v.creado || '').replace(' ', 'T');
                return '<div class="sf-ver-item"><div><strong>' + esc(t.replace('T', ' ')) + '</strong> · ' + esc(v.usuario || '—') + '</div>' +
                    '<div class="text-sm text-muted">' + esc(v.nombre) + ' · ' + (Math.max(1, Math.round((+v.bytes || 0) / 1024))) + ' KB</div>' +
                    '<button type="button" class="btn btn-sm btn-ghost" data-restaurar="' + v.id + '"><i class="fa-solid fa-clock-rotate-left"></i> Restaurar</button></div>';
            }).join('');
            lista.querySelectorAll('[data-restaurar]').forEach(function (b) {
                b.addEventListener('click', function () { restaurarVersion(+b.dataset.restaurar); });
            });
        }).catch(function () { lista.innerHTML = '<p class="text-muted text-sm">Sin conexión: no se pudo cargar el historial.</p>'; });
    }
    function restaurarVersion(id) {
        window.confirmarModal({
            titulo: '¿Restaurar esta versión?', aceptar: 'Restaurar', icono: 'fa-clock-rotate-left',
            mensaje: 'La tabla volverá a como estaba en esa fecha. La versión actual se guarda antes en el historial.',
        }).then(function (ok) {
            if (!ok) return;
            var fd = new FormData(); fd.append('id', C.id); fd.append('proyecto', C.proyecto); fd.append('version', id);
            estado('Restaurando…');
            fetch(C.api.restaurar, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
                if (!d.success) { estado(d.error || 'No se pudo restaurar', true); return; }
                version = d.version; recargar(d.datos); sucio = false;
                estado('Versión restaurada'); abrirHistorial();
            }).catch(function () { estado('Sin conexión: no se pudo restaurar', true); });
        });
    }
    (function () {
        var b = $('sf-historial');
        if (b) b.addEventListener('click', abrirHistorial);
        var c = $('sf-versiones-cerrar');
        if (c) c.addEventListener('click', function () { $('sf-versiones').hidden = true; });
    })();

    // ================= Barra: formato de número, pegar especial, congelar y gráficos =================
    (function () {
        var nf = $('sf-nf');
        if (nf) nf.addEventListener('change', function () {
            var v = this.value;
            aplicar(function (f) { if (v === '') delete f.nf; else f.nf = v; });
        });
        document.querySelectorAll('[data-peg]').forEach(function (b) {
            b.addEventListener('click', function () {
                if (!copiado) { estado('Copia primero una celda o un rango (Ctrl + C)', true); return; }
                var n = rangoNorm(), modo = b.dataset.peg;
                if (modo === 'valores') pegarInterno(n, true, false);
                else if (modo === 'formato') pegarInterno(n, false, true);
                else pegarInterno(n, false, false);
            });
        });
        var crear = $('sf-graf-crear');
        if (crear) crear.addEventListener('click', function () {
            var n = rangoNorm();
            if (n.r0 < 0) { estado('Elige el rango de datos (sin el encabezado)', true); return; }
            if (n.r1 - n.r0 < 1 || n.c1 - n.c0 < 1) { estado('Elige al menos dos renglones y dos columnas', true); return; }
            if (S.vista.graficos.length >= 6) { estado('Máximo 6 gráficos por tabla', true); return; }
            antes();
            S.vista.graficos.push({
                tipo: $('sf-graf-tipo') ? $('sf-graf-tipo').value : 'barra',
                r0: n.r0, c0: n.c0, r1: n.r1, c1: n.c1,
                titulo: ($('sf-graf-titulo') ? $('sf-graf-titulo').value : '').trim().slice(0, 80),
            });
            dibujar(); cambio();
        });
        var cont = $('sf-graficos');
        if (cont) cont.addEventListener('click', function (e) {
            var b = e.target.closest('[data-graf-quitar]');
            if (!b) return;
            antes(); S.vista.graficos.splice(+b.dataset.grafQuitar, 1); dibujar(); cambio();
        });
        var bb = $('sf-buscar');
        if (bb) bb.addEventListener('click', function () { panelBusca(true); });
    })();

    // ================= Formato condicional: barra de la cinta =================
    (function () {
        var tipo = $('sf-cond-tipo');
        if (!tipo) return;
        function campos() {
            var t = tipo.value;
            $('sf-cond-a').hidden = !(t === 'mayor' || t === 'menor' || t === 'igual' || t === 'entre');
            $('sf-cond-b').hidden = t !== 'entre';
            $('sf-cond-txt').hidden = t !== 'contiene';
            var soloColor = t === 'escala' || t === 'barra';
            $('sf-cond-color').parentNode.style.display = t === '' ? 'none' : '';
            $('sf-cond-fondo').parentNode.style.display = t === '' || soloColor && t === 'barra' ? 'none' : '';
        }
        tipo.addEventListener('change', campos);
        campos();
        $('sf-cond-aplicar').addEventListener('click', function () {
            var t = tipo.value;
            if (!t) { estado('Elige una regla de formato condicional', true); return; }
            var n = rangoNorm();
            if (n.r0 < 0 || n.r1 < 0) { estado('Elige el rango de datos (sin el encabezado)', true); return; }
            if (t !== 'duplicados' && t !== 'escala' && t !== 'barra' && !$('sf-cond-a').value && !$('sf-cond-txt').value) {
                estado('Escribe el valor de la regla', true); return;
            }
            antes();
            S.vista.cond.push({
                r0: n.r0, c0: n.c0, r1: Math.max(0, n.r1), c1: n.c1, tipo: t,
                a: $('sf-cond-a').value, b: $('sf-cond-b').value, txt: $('sf-cond-txt').value,
                color: $('sf-cond-color').value, fondo: $('sf-cond-fondo').value,
            });
            dibujar(); cambio();
            estado('Regla aplicada al rango elegido');
        });
        $('sf-cond-quitar').addEventListener('click', function () {
            var n = rangoNorm();
            var toca = S.vista.cond.filter(function (r) { return r.r0 <= n.r1 && r.r1 >= n.r0 && r.c0 <= n.c1 && r.c1 >= n.c0; });
            if (!toca.length) { estado('La selección no tiene reglas condicionales', true); return; }
            antes();
            S.vista.cond = S.vista.cond.filter(function (r) { return toca.indexOf(r) < 0; });
            dibujar(); cambio();
        });
    })();

    /** Ctrl+D / Ctrl+R: copia la primera fila/columna del rango al resto */
    function rellenarDireccion(dir) {
        var n = rangoNorm();
        if (dir === 'abajo') {
            if (n.r1 <= n.r0) { estado('Elige un rango de varios renglones', true); return; }
            aplicarRelleno({ r0: n.r0, c0: n.c0, r1: n.r0, c1: n.c1 }, n);
        } else {
            if (n.c1 <= n.c0) { estado('Elige un rango de varias columnas', true); return; }
            aplicarRelleno({ r0: n.r0, c0: n.c0, r1: n.r1, c1: n.c0 }, n);
        }
    }

    // Ctrl+F desde cualquier parte de la página (salvo mientras se escribe en un campo)
    document.addEventListener('keydown', function (e) {
        if (!(e.ctrlKey || e.metaKey) || (e.key !== 'f' && e.key !== 'F')) return;
        var t = e.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
        e.preventDefault();
        panelBusca(true);
    });

    dibujar();
    area.focus();
})();
