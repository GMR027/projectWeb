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
                <td><?php echo s($m->nombre); ?> <?php $v = $verif[(int)$m->id] ?? null; echo \Classes\MaterialesInteligentes::badge($v, \Classes\MaterialesInteligentes::vigente($v, $m), false); ?></td>
                <td><?php echo s($m->especialidad); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$materiales): ?><tr><td colspan="3" class="text-muted">Sin materiales.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
