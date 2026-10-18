<div class="programa-header">
    <div>
        <h2>Ordenar y renumerar</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · arrastra las partidas y las actividades (también entre partidas) o usa las flechas. Nada se guarda hasta pulsar «Guardar».</p>
    </div>
</div>

<div class="card ord-barra">
    <label class="campo-check"><input type="checkbox" id="ord-renumerar" checked> Renumerar según el nuevo orden (partidas 1.0, 2.0… · actividades 1.1, 1.2…)</label>
    <div>
        <button type="button" class="btn btn-primary" id="ord-guardar">Guardar orden</button>
        <a href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">Cancelar</a>
    </div>
    <p id="ord-msg" class="text-muted" role="status"></p>
</div>
<?php if (!empty($deshacer)): ?>
    <div class="card ord-barra" id="ord-deshacer">
        <span>Último orden guardado: <?php echo s($deshacer['resumen']); ?> <small class="text-muted">(<?php echo s($deshacer['creado']); ?> · <?php echo s($deshacer['usuario']); ?>)</small></span>
        <button type="button" class="btn btn-ghost" id="ord-deshacer-btn" data-id="<?php echo (int)$deshacer['id']; ?>">↩ Deshacer ese orden</button>
    </div>
<?php endif; ?>
<p class="text-muted">Los códigos escritos como texto en bitácora, notas de láminas o minutas <strong>no se reescriben</strong>: son registros históricos y un «1.2» puede ser un código, una cantidad o una fecha. Pulsa ⓘ en una partida o actividad para ver cuántas menciones tiene. Lo que está ligado por vínculo (dependencias, materiales, evidencias, láminas) sigue unido y muestra el código nuevo.</p>

<div id="ord-lista" class="ord-lista">
    <?php foreach ($estructura as $p): ?>
        <section class="card ord-partida" draggable="true" data-id="<?php echo (int)$p['id']; ?>">
            <header>
                <span class="ord-asa" aria-hidden="true">⠿</span>
                <strong class="mono ord-cod"><?php echo s($p['codigo']); ?></strong>
                <span class="ord-nombre"><?php echo s($p['nombre']); ?></span>
                <span class="ord-flechas"><button type="button" data-info title="Ver detalles">ⓘ</button><button type="button" data-mover="-1" title="Subir">▲</button><button type="button" data-mover="1" title="Bajar">▼</button></span>
            </header>
            <ul class="ord-acts">
                <?php foreach ($p['actividades'] as $a): ?>
                    <li draggable="true" data-id="<?php echo (int)$a['id']; ?>">
                        <span class="ord-asa" aria-hidden="true">⠿</span>
                        <span class="mono ord-cod"><?php echo s($a['codigo']); ?></span>
                        <span class="ord-nombre"><?php echo s($a['nombre']); ?></span>
                        <span class="ord-flechas"><button type="button" data-info title="Ver detalles">ⓘ</button><button type="button" data-mover="-1" title="Subir">▲</button><button type="button" data-mover="1" title="Bajar">▼</button></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>

<style>
    .ord-barra { display: flex; flex-wrap: wrap; gap: 1.2rem; align-items: center; justify-content: space-between; margin-bottom: 1.6rem; }
    .ord-lista { display: grid; gap: 1.2rem; }
    .ord-partida { padding: 0; }
    .ord-partida > header, .ord-acts li { display: flex; align-items: center; gap: .8rem; padding: .7rem 1.2rem; }
    .ord-partida > header { background: color-mix(in srgb, var(--primary, #f97316) 12%, transparent); border-radius: .8rem .8rem 0 0; }
    .ord-acts { list-style: none; margin: 0; padding: 0; min-height: 3rem; }
    .ord-acts li { border-top: 1px solid var(--border, rgba(148, 163, 184, .25)); padding-left: 3rem; }
    .ord-nombre { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ord-asa { cursor: grab; opacity: .6; }
    .ord-flechas button { background: none; border: 1px solid var(--border, rgba(148, 163, 184, .4)); color: inherit; border-radius: .4rem; cursor: pointer; padding: 0 .6rem; }
    .ord-arrastrando { opacity: .4; }
    .ord-destino { outline: 2px dashed var(--primary, #f97316); }
    .ord-partida, .ord-acts li { flex-wrap: wrap; }
    .ord-det { flex-basis: 100%; font-size: 1.3rem; padding: .6rem 1.2rem 1rem 3rem; border-top: 1px dashed var(--border, rgba(148, 163, 184, .3)); }
    .ord-det dl { display: grid; grid-template-columns: max-content 1fr; gap: .2rem 1.2rem; margin: 0 0 .6rem; }
    .ord-det dt { color: var(--text-muted, #94a3b8); } .ord-det dd { margin: 0; }
    .ord-cambio .ord-cod { color: var(--primary, #f97316); }
</style>
<script>
(function () {
    var lista = document.getElementById('ord-lista'), msg = document.getElementById('ord-msg'), arrastrado = null, sucio = false;
    function renumerar() {
        var on = document.getElementById('ord-renumerar').checked;
        lista.querySelectorAll('.ord-partida').forEach(function (p, i) {
            if (on) p.querySelector('header .ord-cod').textContent = (i + 1) + '.0';
            p.querySelectorAll('.ord-acts li').forEach(function (li, j) { if (on) li.querySelector('.ord-cod').textContent = (i + 1) + '.' + (j + 1); });
        });
    }
    document.getElementById('ord-renumerar').addEventListener('change', function () { if (this.checked) renumerar(); else location.reload(); });
    lista.addEventListener('click', function (e) {
        var i = e.target.closest('button[data-info]');
        if (i) { detalle(i.closest('li, .ord-partida')); return; }
        var b = e.target.closest('button[data-mover]'); if (!b) return;
        var el = b.closest('li, .ord-partida'), d = parseInt(b.dataset.mover, 10);
        var sib = d < 0 ? el.previousElementSibling : el.nextElementSibling;
        if (sib) { d < 0 ? el.parentNode.insertBefore(el, sib) : el.parentNode.insertBefore(sib, el); marcar(); }
        else if (el.tagName === 'LI') {   // al llegar al borde pasa a la partida vecina
            var p = el.closest('.ord-partida'), v = d < 0 ? p.previousElementSibling : p.nextElementSibling;
            if (v) { var ul = v.querySelector('.ord-acts'); d < 0 ? ul.appendChild(el) : ul.insertBefore(el, ul.firstChild); marcar(); }
        }
    });
    function detalle(el) {
        var abierto = el.querySelector(':scope > .ord-det, :scope > header + .ord-det');
        if (abierto) { abierto.remove(); return; }
        var esAct = el.tagName === 'LI', caja = document.createElement('div');
        caja.className = 'ord-det'; caja.textContent = 'Cargando…';
        if (esAct) el.appendChild(caja); else el.querySelector('header').after(caja);
        fetch('/programa/ordenar/detalle?proyecto=<?php echo (int)$proyecto->id; ?>&tipo=' + (esAct ? 'actividad' : 'partida') + '&id=' + el.dataset.id, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) { caja.textContent = j.error || 'No se pudo cargar'; return; }
                caja.textContent = '';
                var dl = document.createElement('dl');
                j.lineas.forEach(function (l) { var t = document.createElement('dt'), d = document.createElement('dd'); t.textContent = l[0]; d.textContent = l[1]; dl.append(t, d); });
                caja.appendChild(dl);
                var m = document.createElement('p');
                m.textContent = j.menciones.length ? 'Código escrito como texto en: ' + j.menciones.map(function (x) { return x[0] + ' (' + x[1] + ')'; }).join(', ') + '. Esos textos no cambiarán.' : 'Ningún texto libre menciona este código.';
                caja.appendChild(m);
            })
            .catch(function () { caja.textContent = 'Error de red'; });
    }
    var bd = document.getElementById('ord-deshacer-btn');
    if (bd) bd.addEventListener('click', function () {
        if (!confirm('¿Volver al orden y la numeración que había antes de ese guardado?')) return;
        msg.textContent = 'Deshaciendo…';
        fetch('/programa/ordenar/deshacer', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ proyecto: '<?php echo (int)$proyecto->id; ?>', id: bd.dataset.id }).toString() })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (j.success) { sucio = false; location.reload(); } else msg.textContent = '✘ ' + (j.error || 'No se pudo deshacer'); })
            .catch(function () { msg.textContent = '✘ Error de red'; });
    });
    function marcar() { sucio = true; msg.textContent = 'Hay cambios sin guardar.'; renumerar(); }
    lista.addEventListener('dragstart', function (e) {
        var el = e.target.closest('li, .ord-partida'); if (!el) return;
        e.stopPropagation(); arrastrado = el; el.classList.add('ord-arrastrando'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', el.dataset.id);
    });
    lista.addEventListener('dragend', function () { if (arrastrado) arrastrado.classList.remove('ord-arrastrando'); arrastrado = null; lista.querySelectorAll('.ord-destino').forEach(function (x) { x.classList.remove('ord-destino'); }); });
    lista.addEventListener('dragover', function (e) {
        if (!arrastrado) return;
        var esAct = arrastrado.tagName === 'LI';
        var sobre = e.target.closest(esAct ? 'li, .ord-acts' : '.ord-partida'); if (!sobre || sobre === arrastrado || arrastrado.contains(sobre)) return;
        e.preventDefault();
        if (esAct && sobre.classList.contains('ord-acts')) { if (!sobre.children.length || e.target === sobre) sobre.appendChild(arrastrado); }
        else {
            var r = sobre.getBoundingClientRect(), despues = e.clientY > r.top + r.height / 2;
            sobre.parentNode.insertBefore(arrastrado, despues ? sobre.nextSibling : sobre);
        }
        marcar();
    });
    // Una partida sin actividades acepta soltar sobre su cuerpo
    lista.addEventListener('drop', function (e) { e.preventDefault(); });
    document.getElementById('ord-guardar').addEventListener('click', function () {
        var orden = [].map.call(lista.querySelectorAll('.ord-partida'), function (p) {
            return { id: parseInt(p.dataset.id, 10), actividades: [].map.call(p.querySelectorAll('.ord-acts li'), function (li) { return parseInt(li.dataset.id, 10); }) };
        });
        var cuerpo = new URLSearchParams({ proyecto: '<?php echo (int)$proyecto->id; ?>', orden: JSON.stringify(orden) });
        if (document.getElementById('ord-renumerar').checked) cuerpo.set('renumerar', '1');
        msg.textContent = 'Guardando…';
        fetch('/programa/ordenar', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: cuerpo.toString() })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (j.success) { sucio = false; location.href = '/programa/ordenar?proyecto=<?php echo (int)$proyecto->id; ?>'; } else msg.textContent = '✘ ' + (j.error || 'No se pudo guardar'); })
            .catch(function () { msg.textContent = '✘ Error de red'; });
    });
    window.addEventListener('beforeunload', function (e) { if (sucio) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
