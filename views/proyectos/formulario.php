<div class="form-grid">
    <div class="campo">
        <label for="codigo">Código del Proyecto *</label>
        <input type="text" id="codigo" name="codigo" value="<?php echo s($proyecto->codigo); ?>" required placeholder="Ej: SECTOR-7 / ED-04">
    </div>
    <div class="campo">
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" value="<?php echo s($proyecto->nombre); ?>" required>
    </div>
    <div class="campo campo-full">
        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="3"><?php echo s($proyecto->descripcion); ?></textarea>
    </div>
    <div class="campo">
        <label for="ubicacion">Ubicación</label>
        <input type="text" id="ubicacion" name="ubicacion" value="<?php echo s($proyecto->ubicacion); ?>">
    </div>
    <div class="campo">
        <label for="empresa">Nombre de la empresa</label>
        <input type="text" id="empresa" name="empresa" value="<?php echo s($proyecto->empresa); ?>">
    </div>
    <div class="campo">
        <label for="contratista">Contratista</label>
        <input type="text" id="contratista" name="contratista" value="<?php echo s($proyecto->contratista); ?>" placeholder="Cliente de tu cliente (aparece en el MSR)">
    </div>
    <div class="campo">
        <label for="numero_trabajo">Número de trabajo (Job No.)</label>
        <input type="text" id="numero_trabajo" name="numero_trabajo" value="<?php echo s($proyecto->numero_trabajo); ?>">
    </div>
    <div class="campo campo-full">
        <label for="contratista_logo">Logo del Contratista</label>
        <input type="file" id="contratista_logo" name="contratista_logo" accept="image/*">
        <?php if ($proyecto->contratista_logo): ?>
            <div class="logo-preview">
                <img src="/build/img/logos/<?php echo s($proyecto->contratista_logo); ?>" alt="logo contratista" style="max-width:8rem; margin-top:0.5rem;">
            </div>
        <?php endif; ?>
    </div>
    <?php if (is_admin()): ?>
        <div class="campo">
            <label for="usuario_id">Propietario</label>
            <select id="usuario_id" name="usuario_id">
                <?php foreach (\Model\Usuario::all('nombre ASC') as $u): ?>
                    <?php if ($u->rol === 'visitante') continue; ?>
                    <option value="<?php echo s($u->id); ?>" <?php echo (int)($proyecto->usuario_id ?: $_SESSION['id']) === (int)$u->id ? 'selected' : ''; ?>>
                        <?php echo s($u->nombre); ?> (<?php echo s($u->rol); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <?php if (is_admin()): ?>
        <div class="campo campo-full">
            <label>Usuarios con acceso a este proyecto</label>
            <small class="text-muted">Clientes y visitantes marcados podrán ver este proyecto (el propietario siempre lo ve).</small>
            <?php
            $items = [];
            foreach (\Model\Usuario::all('nombre ASC') as $u) {
                if ($u->rol !== 'admin') $items[] = ['id' => (int)$u->id, 'titulo' => $u->nombre . ' (' . $u->email . ')', 'detalle' => ucfirst($u->rol)];
            }
            $nombreCampo = 'usuarios_acceso';
            $marcados = $usuariosAcceso ?? [];
            include __DIR__ . '/../templates/lista-accesos.php';
            ?>
        </div>
    <?php endif; ?>
    <div class="campo">
        <label for="color">Color Identificador</label>
        <input type="color" id="color" name="color" value="<?php echo s($proyecto->color ?: '#FF5A1F'); ?>">
    </div>
    <div class="campo">
        <label for="fecha_inicio">Fecha de Inicio *</label>
        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo s($proyecto->fecha_inicio); ?>" required>
    </div>
    <div class="campo">
        <label for="fecha_fin">Fecha de Fin *</label>
        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo s($proyecto->fecha_fin); ?>" required>
    </div>
    <div class="campo campo-full">
        <label for="logo">Logo del Proyecto</label>
        <input type="file" id="logo" name="logo" accept="image/*">
        <?php if ($proyecto->logo): ?>
            <div class="logo-preview">
                <img src="/build/img/logos/<?php echo s($proyecto->logo); ?>" alt="logo actual" style="max-width:8rem; margin-top:0.5rem;">
            </div>
        <?php endif; ?>
    </div>
    <div class="campo campo-full">
        <label for="caratula">Carátula del Proyecto (imagen destacada)</label>
        <input type="file" id="caratula" name="caratula" accept="image/*">
        <small class="text-muted">Imagen grande que se mostrará en el portafolio de proyectos.</small>
        <?php if ($proyecto->caratula): ?>
            <div class="logo-preview">
                <img src="/build/img/caratulas/<?php echo s($proyecto->caratula); ?>" alt="carátula actual" style="max-width:100%; max-height: 15rem; margin-top:0.5rem; border-radius: 0.8rem;">
            </div>
        <?php endif; ?>
    </div>
</div>
