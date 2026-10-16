<div class="page-header">
    <h2>Crear Usuario</h2>
    <a href="/usuarios" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <form method="POST" class="formulario" enctype="multipart/form-data">
        <div class="campo">
            <label for="nombre">Nombre Completo</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo s($usuario->nombre ?? ''); ?>" required>
        </div>
        <div class="campo">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo s($usuario->email ?? ''); ?>" required>
        </div>
        <div class="campo">
            <label for="rol">Rol</label>
            <select id="rol" name="rol" required>
                <option value="">-- Selecciona --</option>
                <option value="admin" <?php echo ($usuario->rol ?? '') === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                <option value="cliente" <?php echo ($usuario->rol ?? '') === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                <option value="visitante" <?php echo ($usuario->rol ?? '') === 'visitante' ? 'selected' : ''; ?>>Visitante</option>
            </select>
        </div>
        <div class="campo" id="campo-limite">
            <label for="limite_proyectos">Límite de proyectos (rol cliente)</label>
            <input type="number" id="limite_proyectos" name="limite_proyectos" min="0" max="999" step="1" value="<?php echo s($usuario->limite_proyectos ?? 1); ?>">
            <small class="text-muted">Número máximo de proyectos que este cliente puede crear. 0 = no puede crear proyectos. Los asignados por el administrador no cuentan.</small>
            <label for="limite_almacenamiento_mb" style="margin-top:1.2rem">Espacio por proyecto en MB (rol cliente)</label>
            <input type="number" id="limite_almacenamiento_mb" name="limite_almacenamiento_mb" min="1" max="100000" step="1" value="<?php echo s($usuario->limite_almacenamiento_mb ?? 300); ?>">
            <small class="text-muted">Almacenamiento que puede ocupar cada proyecto de este cliente (archivos, fotos, planos, fichas y logos). 1024 MB = 1 GB.</small>
        </div>
        <div class="campo" id="campo-logo">
            <label for="logo">Logo de la empresa (rol cliente)</label>
            <input type="file" id="logo" name="logo" accept="image/*">
            <?php if (!empty($usuario->logo)): ?>
                <img src="/build/img/logos/<?php echo s($usuario->logo); ?>" alt="logo" style="max-width:8rem; margin-top:0.5rem;">
            <?php endif; ?>
        </div>
        <div class="campo campo-accesos" id="campo-accesos">
            <label>Proyectos con acceso</label>
            <small class="text-muted">Cliente y visitante verán únicamente los proyectos marcados (el cliente además ve los que él mismo cree). El administrador ve todo.</small>
            <?php
            $items = array_map(fn($p) => ['id' => (int)$p->id, 'titulo' => $p->nombre, 'detalle' => $p->codigo], $proyectos);
            $nombreCampo = 'proyectos';
            $marcados = $asignados ?? [];
            include __DIR__ . '/../templates/lista-accesos.php';
            ?>
        </div>
        <?php $marcadas = $herramientasMarcadas ?? null; include __DIR__ . '/../templates/herramientas-visitante.php'; ?>
        <script>
            (function () {
                const rol = document.getElementById('rol'), caja = document.getElementById('campo-accesos'), lim = document.getElementById('campo-limite');
                const herr = document.getElementById('campo-herramientas');
                const toggle = () => { caja.style.display = rol.value === 'admin' ? 'none' : ''; lim.style.display = rol.value === 'cliente' ? '' : 'none'; herr.style.display = rol.value === 'visitante' ? '' : 'none'; };
                rol.addEventListener('change', toggle); toggle();
            })();
        </script>
        <div class="campo">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="campo">
            <label for="password2">Repetir Password</label>
            <input type="password" id="password2" name="password2" required>
        </div>
        <button type="submit" class="btn btn-primary">Crear Usuario</button>
    </form>
</div>
