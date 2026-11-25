<?php
/** Barra de búsqueda, filtros y cambio de vista. */
$carpetaValor = $carpeta === null ? '' : ($carpeta === 0 ? 'sin' : (string)$carpeta);
?>
<div class="archivo-barra">
    <label class="archivo-busca" for="arch-buscar">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" id="arch-buscar" placeholder="Buscar por nombre, categoría o quién lo subió…" autocomplete="off" aria-label="Buscar archivos">
        <kbd class="archivo-kbd">/</kbd>
    </label>

    <form method="GET" action="/archivos" class="archivo-filtros" aria-label="Filtros de archivos">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if ($carpeta !== null): ?><input type="hidden" name="carpeta" value="<?php echo s($carpetaValor); ?>"><?php endif; ?>
        <?php if ($recientes): ?><input type="hidden" name="vista" value="recientes"><?php endif; ?>
        <select name="categoria" onchange="this.form.submit()" aria-label="Filtrar por categoría">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?php echo s($c->id); ?>" <?php echo (int)$filtro === (int)$c->id ? 'selected' : ''; ?>><?php echo s($c->nombre); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="orden" onchange="this.form.submit()" aria-label="Ordenar archivos">
            <option value="fecha_desc" <?php echo $ordenActual === 'fecha_desc' ? 'selected' : ''; ?>>Fecha (más reciente)</option>
            <option value="fecha_asc" <?php echo $ordenActual === 'fecha_asc' ? 'selected' : ''; ?>>Fecha (más antigua)</option>
            <option value="nombre_asc" <?php echo $ordenActual === 'nombre_asc' ? 'selected' : ''; ?>>Nombre A-Z</option>
            <option value="nombre_desc" <?php echo $ordenActual === 'nombre_desc' ? 'selected' : ''; ?>>Nombre Z-A</option>
            <option value="tamano_desc" <?php echo $ordenActual === 'tamano_desc' ? 'selected' : ''; ?>>Tamaño (mayor)</option>
            <option value="tamano_asc" <?php echo $ordenActual === 'tamano_asc' ? 'selected' : ''; ?>>Tamaño (menor)</option>
            <option value="subida_desc" <?php echo $ordenActual === 'subida_desc' ? 'selected' : ''; ?>>Subida (más reciente)</option>
        </select>
    </form>

    <div class="archivo-vista" role="group" aria-label="Forma de ver los archivos">
        <button type="button" data-vista="cuadricula" aria-pressed="<?php echo $vistaInicial === 'cuadricula' ? 'true' : 'false'; ?>" title="Ver en cuadrícula" aria-label="Ver en cuadrícula">
            <i class="fa-solid fa-grip" aria-hidden="true"></i>
        </button>
        <button type="button" data-vista="lista" aria-pressed="<?php echo $vistaInicial === 'lista' ? 'true' : 'false'; ?>" title="Ver en lista" aria-label="Ver en lista">
            <i class="fa-solid fa-list" aria-hidden="true"></i>
        </button>
    </div>
</div>

<div class="archivo-chips-movil" aria-label="Carpetas del archivo">
    <a href="<?php echo s($urlArchivo(['carpeta' => null, 'vista' => null])); ?>" class="archivo-chip-link <?php echo $carpeta === null && !$recientes ? 'activa' : ''; ?>">
        <i class="fa-solid fa-layer-group" aria-hidden="true"></i> Todos <em><?php echo (int)$totalArchivos; ?></em>
    </a>
    <a href="<?php echo s($urlArchivo(['carpeta' => 'sin', 'vista' => null])); ?>" class="archivo-chip-link <?php echo $carpeta === 0 ? 'activa' : ''; ?>">
        <i class="fa-regular fa-file" aria-hidden="true"></i> Sin carpeta <em><?php echo (int)$sinCarpeta; ?></em>
    </a>
    <a href="<?php echo s($urlArchivo(['carpeta' => null, 'vista' => 'recientes'])); ?>" class="archivo-chip-link <?php echo $recientes ? 'activa' : ''; ?>">
        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Recientes <em><?php echo (int)$totalRecientes; ?></em>
    </a>
    <?php foreach ($carpetas as $k): $kid = (int)$k->id; $kst = $carpetasStats[$kid] ?? ['total' => 0]; ?>
        <a href="<?php echo s($urlCarpeta($kid)); ?>" class="archivo-chip-link <?php echo $carpetaActual && (int)$carpetaActual->id === $kid ? 'activa' : ''; ?>">
            <i class="fa-solid fa-folder" aria-hidden="true"></i> <?php echo s($k->nombre); ?> <em><?php echo (int)$kst['total']; ?></em>
        </a>
    <?php endforeach; ?>
    <?php if ($puede): ?>
        <button type="button" class="archivo-chip-link archivo-chip-nueva arch-nueva">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Carpeta
        </button>
    <?php endif; ?>
</div>
