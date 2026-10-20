<?php // Variables: $proyecto, $ensamble, $alertas, $disponibles (redactor/archivo), $seleccion
$id = (int)$proyecto->id;
$nombres = [];
foreach ($disponibles as $grupo) foreach ($grupo as $d) $nombres[$d['t'] . $d['id']] = $d;
$inicial = array_values(array_filter(array_map(fn($f) => isset($nombres[$f['t'] . $f['id']]) ? ['t' => $f['t'], 'id' => $f['id']] : null, $ensamble->listaFuentes())));
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$inicial) $inicial = [];
?>
<style>
.ens-cols{display:grid;grid-template-columns:1fr 1fr;gap:1.6rem}
@media (max-width:48rem){.ens-cols{grid-template-columns:1fr}}
.ens-lista{list-style:none;margin:0;padding:0;border:1px solid var(--border,#475569);border-radius:.8rem;min-height:8rem;max-height:46rem;overflow:auto}
.ens-lista li{display:flex;align-items:center;gap:.6rem;padding:.7rem 1rem;border-bottom:1px solid var(--border,#475569)}
.ens-lista li:last-child{border-bottom:0}
.ens-lista li span{flex:1;min-width:0;overflow-wrap:anywhere}
.ens-vacio{padding:1.2rem;color:var(--text-muted,#94a3b8)}
</style>
<div class="page-header">
    <h2><i class="fa-solid fa-object-group"></i> <?php echo $ensamble->id ? 'Editar ensamble' : 'Nuevo ensamble'; ?></h2>
    <a href="/ensamblador-documentos?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
    <form method="POST" action="/ensamblador-documentos/<?php echo $ensamble->id ? 'editar' : 'crear'; ?>" class="formulario" id="ens-form">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if ($ensamble->id): ?><input type="hidden" name="id" value="<?php echo (int)$ensamble->id; ?>"><?php endif; ?>
        <input type="hidden" name="fuentes" id="ens-fuentes" value="[]">
        <div class="campo campo-full"><label for="nombre">Nombre del documento final *</label><input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($ensamble->nombre); ?>" placeholder="Ej: Paquete de entrega: memoria + planos" required autofocus></div>
        <div class="ens-cols">
            <div>
                <h3>Documentos disponibles</h3>
                <p class="text-muted text-sm">Pulsa «Agregar» para sumar un documento del Redactor o un PDF del Archivo. Puedes repetir uno.</p>
                <ul class="ens-lista" id="ens-disp">
                    <?php foreach (['redactor' => ['Redactor', 'fa-file-pen'], 'archivo' => ['PDF del Archivo', 'fa-file-pdf']] as $t => [$titulo, $ico]): ?>
                        <li><strong><i class="fa-solid <?php echo $ico; ?>"></i> <?php echo s($titulo); ?></strong></li>
                        <?php foreach ($disponibles[$t] as $d): ?>
                            <li><span><?php echo s($d['nombre']); ?><?php echo $d['detalle'] !== '' ? ' <small class="text-muted">· ' . s($d['detalle']) . '</small>' : ''; ?></span>
                                <button type="button" class="btn btn-sm btn-ghost" data-add="<?php echo s($t . $d['id']); ?>"><i class="fa-solid fa-plus"></i> Agregar</button></li>
                        <?php endforeach; ?>
                        <?php if (!$disponibles[$t]): ?><li class="text-muted text-sm"><?php echo $t === 'redactor' ? 'No hay documentos en el Redactor.' : 'No hay PDF en el Archivo.'; ?></li><?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h3>Orden del documento final</h3>
                <p class="text-muted text-sm">Aparecen uno tras otro en este orden.</p>
                <ol class="ens-lista" id="ens-sel" style="padding-left:0"></ol>
            </div>
        </div>
        <p class="text-sm text-muted mt-2">El resultado es un PDF no editable. Si después cambias un documento original, el PDF guardado en Archivo se actualiza solo; el que descargas ya no.</p>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar ensamble</button>
    </form>
</div>
<script>
(() => {
    const info = <?php echo json_encode($nombres, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    let sel = <?php echo json_encode($inicial); ?>;
    const ol = document.getElementById('ens-sel'), campo = document.getElementById('ens-fuentes');
    const dibujar = () => {
        ol.innerHTML = '';
        if (!sel.length) { const v = document.createElement('li'); v.className = 'ens-vacio'; v.textContent = 'Aún no agregaste documentos.'; ol.appendChild(v); }
        sel.forEach((f, i) => {
            const d = info[f.t + f.id]; const li = document.createElement('li');
            const n = document.createElement('strong'); n.textContent = (i + 1) + '.';
            const sp = document.createElement('span'); sp.textContent = d.nombre + (f.t === 'redactor' ? ' (Redactor)' : ' (PDF)');
            li.append(n, sp);
            [['▲', -1, 'Subir'], ['▼', 1, 'Bajar']].forEach(([txt, dx, tit]) => {
                const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-ghost'; b.textContent = txt; b.title = tit; b.setAttribute('aria-label', tit);
                b.disabled = i + dx < 0 || i + dx >= sel.length;
                b.onclick = () => { [sel[i], sel[i + dx]] = [sel[i + dx], sel[i]]; dibujar(); };
                li.appendChild(b);
            });
            const q = document.createElement('button'); q.type = 'button'; q.className = 'btn btn-sm btn-danger'; q.innerHTML = '<i class="fa-solid fa-xmark"></i>'; q.title = 'Quitar'; q.setAttribute('aria-label', 'Quitar');
            q.onclick = () => { sel.splice(i, 1); dibujar(); };
            li.appendChild(q); ol.appendChild(li);
        });
        campo.value = JSON.stringify(sel);
    };
    document.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', () => {
        const d = info[b.dataset.add]; sel.push({ t: d.t, id: d.id }); dibujar();
    }));
    dibujar();
})();
</script>
