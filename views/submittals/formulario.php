<?php $esNuevo = !$submittal->id; ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo paquete de submittal' : 'Editar submittal ' . s($submittal->numero_submittal); ?></h2>
    <a href="/submittals?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/submittals/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($submittal->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($submittal->fecha); ?>" required></div>
            <div class="campo"><label for="numero_submittal">Número de submittal *</label><input type="text" id="numero_submittal" name="numero_submittal" value="<?php echo s($submittal->numero_submittal); ?>" required></div>
            <div class="campo"><label for="numero_trabajo">Número de trabajo</label><input type="text" id="numero_trabajo" name="numero_trabajo" value="<?php echo s($submittal->numero_trabajo); ?>"></div>
            <div class="campo"><label>Proyecto</label><input type="text" value="<?php echo s($proyecto->nombre); ?>" disabled></div>
            <div class="campo"><label for="departamento">Departamento</label><input type="text" id="departamento" name="departamento" value="<?php echo s($submittal->departamento); ?>"></div>
            <div class="campo"><label for="para">Para</label><input type="text" id="para" name="para" value="<?php echo s($submittal->para); ?>"></div>
            <div class="campo"><label for="especialidad">Especialidad</label><input type="text" id="especialidad" name="especialidad" value="<?php echo s($submittal->especialidad); ?>"></div>
            <div class="campo campo-full">
                <label>Materiales del paquete</label>
                <small class="text-muted">Cada material marcado genera su renglón en el MSR. <a href="/materiales/crear?proyecto=<?php echo s($proyecto->id); ?>">Agregar material al catálogo</a></small>
                <?php
                $items = array_map(fn($m) => ['id' => (int)$m->id, 'titulo' => $m->nombre, 'detalle' => trim($m->especialidad . ' ' . $m->referencia)], $materiales);
                $nombreCampo = 'materiales';
                $marcados = $marcados ?? [];
                include __DIR__ . '/../templates/lista-accesos.php';
                ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar paquete</button>
    </form>
</div>
