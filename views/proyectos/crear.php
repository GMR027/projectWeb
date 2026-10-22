<div class="page-header">
    <h2><?php echo s(t('Nuevo Proyecto')); ?></h2>
    <a href="/proyectos" class="btn btn-ghost"><?php echo s(t('← Volver')); ?></a>
</div>

<div class="card">
    <form method="POST" enctype="multipart/form-data" class="formulario">
        <?php include __DIR__ . '/formulario.php'; ?>
        <?php if (!empty($plantillas)): ?>
        <div class="campo campo-full">
            <label for="plantilla_id"><?php echo s(t('Basar en una plantilla de proyecto')); ?></label>
            <select id="plantilla_id" name="plantilla_id">
                <option value="0"><?php echo s(t('Proyecto en blanco')); ?></option>
                <?php foreach ($plantillas as $pl): $r = json_decode((string)$pl['resumen_json'], true) ?: []; ?>
                    <option value="<?php echo (int)$pl['id']; ?>"><?php echo s($pl['nombre']); ?> · <?php echo (int)($r['actividades'] ?? 0); ?> actividades</option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted"><?php echo s(t('Carga las partidas, actividades, dependencias, jornada y diseños de un proyecto anterior; las fechas se cuentan desde el inicio de este.')); ?></small>
        </div>
        <?php endif; ?>
        <fieldset class="campo campo-full">
            <legend><?php echo s(t('¿Cómo quieres empezar?')); ?></legend>
            <label class="campo-check"><input type="radio" name="modo_inicio" value="guiado" checked> <?php echo s(t('Guíame paso a paso: una ventana con lo recomendado para arrancar')); ?></label>
            <label class="campo-check"><input type="radio" name="modo_inicio" value="lista"> <?php echo s(t('Muéstrame la lista de pasos completa')); ?></label>
            <label class="campo-check"><input type="radio" name="modo_inicio" value="ninguno"> <?php echo s(t('Solo crear el proyecto (la guía queda en Ajustes del proyecto → Por dónde empezar)')); ?></label>
        </fieldset>
        <button type="submit" class="btn btn-primary"><?php echo s(t('Crear Proyecto')); ?></button>
    </form>
</div>
