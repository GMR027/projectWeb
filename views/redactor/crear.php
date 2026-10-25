<?php use Model\RedactorDocumento; ?>
<div class="page-header">
    <h2><i class="fa-solid fa-file-pen"></i> Nuevo documento</h2>
    <a href="/redactor?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
    <form method="POST" action="/redactor/crear" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <div class="form-grid">
            <div class="campo campo-full"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($documento->nombre); ?>" placeholder="Ej: Memoria de cálculo de cimentación" required autofocus></div>
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($documento->fecha); ?>" required></div>
            <div class="campo">
                <label for="departamento"<?php echo glosario('departamento'); ?>>Departamento</label>
                <input type="text" id="departamento" name="departamento" maxlength="100" list="rd-deptos" value="<?php echo s($documento->departamento); ?>" placeholder="Ej: Ingeniería" autocomplete="off">
                <datalist id="rd-deptos"><?php foreach ($departamentos as $dp): ?><option value="<?php echo s($dp); ?>"><?php endforeach; ?></datalist>
                <small class="text-muted">Elige uno de la lista o escribe uno nuevo; los documentos se agrupan por departamento.</small>
            </div>
            <div class="campo"><label for="idioma">Corrector ortográfico</label>
                <select id="idioma" name="idioma"><?php foreach (RedactorDocumento::IDIOMAS as $k => $n): ?><option value="<?php echo s($k); ?>"<?php echo $documento->idioma === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></div>
            <div class="campo"><label for="papel">Tamaño de hoja</label>
                <select id="papel" name="papel"><?php foreach (RedactorDocumento::PAPELES as $k => $p): ?><option value="<?php echo s($k); ?>"<?php echo $documento->papel === $k ? ' selected' : ''; ?>><?php echo s($p[0]); ?></option><?php endforeach; ?></select></div>
            <div class="campo"><label for="orientacion">Orientación</label>
                <select id="orientacion" name="orientacion"><option value="vertical">Vertical</option><option value="horizontal"<?php echo $documento->orientacion === 'horizontal' ? ' selected' : ''; ?>>Horizontal</option></select></div>
        </div>
        <h3 class="mt-2"<?php echo glosario('plantilla'); ?>>Plantilla</h3>
        <p class="text-muted text-sm">Parte de una plantilla o de una hoja en blanco. Las marcas (proyecto, fecha, departamento, tu nombre) se llenan solas.</p>
        <div class="rd-plantillas">
            <label class="rd-plantilla"><input type="radio" name="plantilla" value="" checked><span><i class="fa-regular fa-file"></i><strong>En blanco</strong><small>Hoja vacía</small></span></label>
            <?php foreach ($incluidas as $k => [$n, $desc, $ico]): ?>
                <label class="rd-plantilla"><input type="radio" name="plantilla" value="inc:<?php echo s($k); ?>"><span><i class="fa-solid <?php echo s($ico); ?>"></i><strong><?php echo s($n); ?></strong><small><?php echo s($desc); ?></small></span></label>
            <?php endforeach; ?>
            <?php foreach ($propias as $pl): ?>
                <label class="rd-plantilla rd-plantilla-propia"><input type="radio" name="plantilla" value="pro:<?php echo (int)$pl['id']; ?>"><span><i class="fa-solid fa-star"></i><strong><?php echo s($pl['nombre']); ?></strong><small><?php echo s($pl['descripcion'] !== '' ? $pl['descripcion'] : 'Plantilla del proyecto'); ?></small></span>
                    <button type="submit" class="btn-icono" formaction="/redactor/plantilla/eliminar" formnovalidate name="eliminar_plantilla" value="<?php echo (int)$pl['id']; ?>" onclick="this.form.querySelector('[name=plantilla][value=\'pro:<?php echo (int)$pl['id']; ?>\']').checked=true; var f=this.form,b=this; window.confirmarModal({titulo:'¿Eliminar la plantilla?',mensaje:'Esta acción no se puede deshacer.',aceptar:'Eliminar'}).then(function(ok){ if(!ok) return; var h=document.createElement('input'); h.type='hidden'; h.name=b.name; h.value=b.value; f.appendChild(h); f.action=b.getAttribute('formaction'); f.submit(); }); return false" title="Eliminar plantilla" aria-label="Eliminar plantilla"><i class="fa-solid fa-trash-can"></i></button></label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-pen"></i> Crear y abrir</button>
    </form>
</div>
