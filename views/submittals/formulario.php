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
            <div class="campo"><label for="msr_id">MSR</label>
                <select id="msr_id" name="msr_id">
                    <option value="">— Sin MSR —</option>
                    <?php foreach ($msrs as $m): ?>
                        <option value="<?php echo s($m->id); ?>"<?php echo (int)$submittal->msr_id === (int)$m->id ? ' selected' : ''; ?>><?php echo s($m->nombre); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo"><label for="numero_submittal">Número de submittal *</label><input type="text" id="numero_submittal" name="numero_submittal" value="<?php echo s($submittal->numero_submittal); ?>" required></div>
            <div class="campo"><label for="numero_trabajo">Número de trabajo</label><input type="text" id="numero_trabajo" name="numero_trabajo" value="<?php echo s($submittal->numero_trabajo); ?>"></div>
            <div class="campo"><label>Proyecto</label><input type="text" value="<?php echo s($proyecto->nombre); ?>" disabled></div>
            <div class="campo"><label for="departamento">Departamento</label><input type="text" id="departamento" name="departamento" value="<?php echo s($submittal->departamento); ?>"></div>
            <div class="campo"><label for="para">Para</label><input type="text" id="para" name="para" value="<?php echo s($submittal->para); ?>"></div>
            <div class="campo"><label for="especialidad">Especialidad</label><input type="text" id="especialidad" name="especialidad" value="<?php echo s($submittal->especialidad); ?>" list="lista-esp"><datalist id="lista-esp"><?php foreach (\Model\Especialidad::nombres((int)$proyecto->id) as $e): ?><option value="<?php echo s($e); ?>"><?php endforeach; ?></datalist></div>
            <div class="campo campo-full">
                <label>Materiales del paquete</label>
                <small class="text-muted">Cada material marcado genera su renglón en el MSR. <a href="/materiales/crear?proyecto=<?php echo s($proyecto->id); ?>">Agregar material al catálogo</a></small>
                <?php
                $noAprobados = $noAprobados ?? [];
                $items = array_map(fn($m) => ['id' => (int)$m->id, 'titulo' => $m->nombre . (isset($noAprobados[(int)$m->id]) ? ' ⚠ NO APROBADO por normativa: ' . $noAprobados[(int)$m->id]['resumen'] : ''), 'detalle' => trim($m->especialidad . ' ' . $m->referencia)], $materiales);
                $nombreCampo = 'materiales';
                $marcados = $marcados ?? [];
                include __DIR__ . '/../templates/lista-accesos.php';
                ?>
                <?php if ($noAprobados): ?>
                    <div class="norma-aviso" role="alert">
                        <strong><i class="fa-solid fa-triangle-exclamation"></i> Hay materiales no aprobados por las normativas y especificaciones</strong> (marcados con ⚠ en la lista).
                        <label class="campo-check"><input type="checkbox" name="confirmar_no_aprobados" value="1"<?php echo !empty($pedirConfirmacion) ? ' checked' : ''; ?>> Entiendo el riesgo: enviar el paquete aunque incluya materiales no aprobados</label>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar paquete</button>
    </form>
</div>
