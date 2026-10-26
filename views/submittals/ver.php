<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-boxes-packing"></i> Submittal <?php echo s($submittal->numero_submittal); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?></p>
    </div>
    <div class="la-controles">
        <a href="/submittals/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($submittal->id); ?>" class="btn btn-ghost">Editar</a>
        <form method="POST" action="/exportar/generar" style="display:inline">
            <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
            <input type="hidden" name="formato" value="submittal">
            <input type="hidden" name="submittal_id" value="<?php echo s($submittal->id); ?>">
            <input type="hidden" name="nombre_base" value="Submittal_<?php echo s(slug_archivo($submittal->numero_submittal)); ?>">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> Exportar PDF</button>
        </form>
        <?php if (puede_gestionar_proyecto($proyecto)): ?>
            <button type="button" class="btn btn-ghost" id="btn-tabla-materiales"
                data-url="/submittals/tabla-materiales"
                data-proyecto="<?php echo s($proyecto->id); ?>"
                data-id="<?php echo s($submittal->id); ?>"
                data-ayuda="Genera en SheetFlow una tabla con la imagen, nombre, marca, modelo, referencia y especialidad de los materiales de este submittal. Si ya existe, puedes reescribirla o ir a verla."
                data-manual="/manual#submittals">
                <i class="fa-solid fa-table-cells"></i> Generar tabla de materiales
            </button>
        <?php endif; ?>
        <a href="/submittals?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Paquetes</a>
    </div>
</div>
<div class="card">
    <dl class="ficha-datos">
        <div><dt>Fecha</dt><dd><?php echo formatear_fecha($submittal->fecha); ?></dd></div>
        <div><dt>Número de trabajo</dt><dd><?php echo s($submittal->numero_trabajo); ?></dd></div>
        <div><dt>Proyecto</dt><dd><?php echo s($proyecto->nombre); ?></dd></div>
        <div><dt>Departamento</dt><dd><?php echo s($submittal->departamento); ?></dd></div>
        <div><dt>Para</dt><dd><?php echo s($submittal->para); ?></dd></div>
        <div><dt>Especialidad</dt><dd><?php echo s($submittal->especialidad); ?></dd></div>
    </dl>
</div>
<div class="card">
    <table class="tabla">
        <thead><tr><th>Imagen</th><th>Material</th><th>Especialidad</th></tr></thead>
        <tbody>
        <?php foreach ($materiales as $m): ?>
            <tr>
                <td><?php if ($m->imagen): ?><img class="mat-thumb" src="/uploads/materiales/<?php echo s($m->imagen); ?>" alt=""><?php endif; ?></td>
                <td><?php echo s($m->nombre); ?> <?php if (is_admin()): $v = $verif[(int)$m->id] ?? null; echo \Classes\MaterialesInteligentes::badge($v, \Classes\MaterialesInteligentes::vigente($v, $m), false); endif; ?></td>
                <td><?php echo s($m->especialidad); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$materiales): ?><tr><td colspan="3" class="text-muted">Sin materiales.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (!empty($laminas)): ?>
<div class="card">
    <h3><i class="fa-solid fa-pen-ruler"></i> Láminas adjuntas</h3>
    <p class="text-muted text-sm">Láminas del Editor (/graficos) que se incluyen debajo de los materiales en el PDF exportado.</p>
    <div class="gr-laminas-galeria">
        <?php foreach ($laminas as $l): ?>
            <figure>
                <?php if ($l->marcas_png): ?>
                    <img src="/uploads/vplan/<?php echo s($l->marcas_png); ?>" alt="<?php echo s($l->grafico_nombre . ' · ' . $l->titulo((int)$l->orden)); ?>" loading="lazy">
                <?php else: ?>
                    <span class="text-muted">Sin imagen guardada</span>
                <?php endif; ?>
                <figcaption><?php echo s($l->grafico_nombre . ' · ' . $l->titulo((int)$l->orden)); ?><?php echo $l->categoria_nombre ? ' · ' . s($l->categoria_nombre) : ''; ?></figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php if (puede_gestionar_proyecto($proyecto)): ?>
<script>
(() => {
    const btn = document.getElementById('btn-tabla-materiales');
    if (!btn) return;
    const datos = (accion) => new URLSearchParams({ proyecto: btn.dataset.proyecto, id: btn.dataset.id, accion });
    const enviar = async (accion) => {
        const r = await fetch(btn.dataset.url, { method: 'POST', body: datos(accion), headers: { Accept: 'application/json' } });
        return r.json();
    };
    btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
            const estado = await enviar('estado');
            if (!estado.success) {
                await window.avisoModal('No se pudo generar la tabla', estado.error || 'Inténtalo de nuevo.');
                return;
            }
            if (!estado.existe) {
                const creada = await enviar('crear');
                if (creada.success) { location.href = creada.url; return; }
                await window.avisoModal('No se pudo generar la tabla', creada.error || 'Inténtalo de nuevo.');
                return;
            }
            const elegido = await window.confirmarModal({
                titulo: 'La tabla de materiales ya existe',
                mensaje: 'Este submittal ya tiene una tabla de materiales en SheetFlow. Puedes reescribirla con los materiales actuales (se pierden los cambios hechos en la tabla) o ir a verla y seguir editándola.',
                aceptar: 'Reescribir la tabla',
                iconoBoton: 'fa-rotate',
                extra: 'Ir a ver la tabla',
                iconoExtra: 'fa-arrow-right',
                tono: 'aviso',
                icono: 'fa-table-cells',
            });
            if (elegido === 'extra') { location.href = estado.url; return; }
            if (!elegido) return;
            const reescrita = await enviar('reescribir');
            if (reescrita.success) { location.href = reescrita.url; return; }
            await window.avisoModal('No se pudo reescribir la tabla', reescrita.error || 'Inténtalo de nuevo.');
        } catch (e) {
            await window.avisoModal('No se pudo generar la tabla', 'Revisa tu conexión e inténtalo de nuevo.');
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
<?php endif; ?>
