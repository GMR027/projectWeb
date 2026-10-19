<div class="page-header">
    <h2><i class="fa-solid fa-table-cells"></i> Nueva tabla</h2>
    <a href="/sheetflow?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
    <form method="POST" action="/sheetflow/crear" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <div class="form-grid">
            <div class="campo campo-full"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($hoja->nombre); ?>" placeholder="Ej: Control de materiales · Cimentación" required autofocus></div>
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($hoja->fecha); ?>" required></div>
            <div class="campo">
                <label for="departamento">Departamento</label>
                <input type="text" id="departamento" name="departamento" maxlength="100" list="sf-deptos" value="<?php echo s($hoja->departamento); ?>" placeholder="Ej: Compras" autocomplete="off">
                <datalist id="sf-deptos"><?php foreach ($departamentos as $dp): ?><option value="<?php echo s($dp); ?>"><?php endforeach; ?></datalist>
                <small class="text-muted">Las tablas se agrupan por departamento.</small>
            </div>
            <div class="campo campo-full"><label for="descripcion">Descripción</label><input type="text" id="descripcion" name="descripcion" maxlength="300" value="<?php echo s($hoja->descripcion); ?>" placeholder="Opcional: para qué sirve la tabla"></div>
        </div>
        <h3 class="mt-2">Punto de partida</h3>
        <div class="rd-plantillas">
            <label class="rd-plantilla"><input type="radio" name="plantilla" value="" checked><span><i class="fa-regular fa-file"></i><strong>En blanco</strong><small>4 columnas × 10 renglones</small></span></label>
            <?php foreach ($plantillas as $k => [$n, $desc, $ico]): ?>
                <label class="rd-plantilla"><input type="radio" name="plantilla" value="<?php echo s($k); ?>"><span><i class="fa-solid <?php echo s($ico); ?>"></i><strong><?php echo s($n); ?></strong><small><?php echo s($desc); ?></small></span></label>
            <?php endforeach; ?>
        </div>
        <div class="campo campo-full mt-2">
            <label for="archivo">…o importa un archivo (.sheetflow, .xlsx, .ods o .csv)</label>
            <input type="file" id="archivo" name="archivo" accept=".sheetflow,.xlsx,.ods,.csv,text/csv">
            <small class="text-muted">Se usa la primera hoja; la primera fila son los encabezados. Si eliges un archivo, se ignora la plantilla. Máx. 25 MB, 30 columnas y 1 000 renglones.</small>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-table-cells"></i> Crear y abrir</button>
    </form>
</div>
