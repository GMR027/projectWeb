<div class="page-header">
    <h2>Nuevo Proyecto</h2>
    <a href="/proyectos" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <form method="POST" enctype="multipart/form-data" class="formulario">
        <?php include __DIR__ . '/formulario.php'; ?>
        <fieldset class="campo campo-full">
            <legend>¿Cómo quieres empezar?</legend>
            <label class="campo-check"><input type="radio" name="modo_inicio" value="guiado" checked> Guíame paso a paso: una ventana con lo recomendado para arrancar</label>
            <label class="campo-check"><input type="radio" name="modo_inicio" value="lista"> Muéstrame la lista de pasos completa</label>
            <label class="campo-check"><input type="radio" name="modo_inicio" value="ninguno"> Solo crear el proyecto (la guía queda en Ajustes del proyecto → Por dónde empezar)</label>
        </fieldset>
        <button type="submit" class="btn btn-primary">Crear Proyecto</button>
    </form>
</div>
