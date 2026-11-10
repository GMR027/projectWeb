<?php $contextoOrganizacion = $contextoOrganizacion ?? false; ?>
<div class="page-header">
    <h2><?php echo $contextoOrganizacion ? 'Nueva organización' : 'Crear Usuario'; ?></h2>
    <a href="<?php echo $contextoOrganizacion ? '/organizaciones' : '/usuarios'; ?>" class="btn btn-ghost">← Volver</a>
</div>

<?php if ($contextoOrganizacion): ?>
    <p class="text-muted text-sm">La organización se crea junto con su usuario propietario: él administra el equipo, las obras y la facturación. Podrás cambiar el plan y el estado después en Organizaciones.</p>
<?php endif; ?>

<div class="card">
    <form method="POST" class="formulario" enctype="multipart/form-data">
        <?php if ($contextoOrganizacion): ?><input type="hidden" name="contexto" value="organizacion"><?php endif; ?>
        <div class="campo">
            <label for="nombre">Nombre Completo</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo s($usuario->nombre ?? ''); ?>" required>
        </div>
        <div class="campo">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo s($usuario->email ?? ''); ?>" required>
        </div>
        <div class="campo" id="campo-rol" <?php echo $contextoOrganizacion ? 'style="display:none"' : ''; ?>>
            <label for="rol">Rol</label>
            <select id="rol" name="rol" required>
                <option value="">-- Selecciona --</option>
                <option value="admin" <?php echo ($usuario->rol ?? '') === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                <option value="cliente" <?php echo ($usuario->rol ?? '') === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                <option value="visitante" <?php echo ($usuario->rol ?? '') === 'visitante' ? 'selected' : ''; ?>>Visitante</option>
            </select>
        </div>
        <?php include __DIR__ . '/_organizacion-campos.php'; ?>
        <div class="campo" id="campo-limite">
            <label for="limite_proyectos">Límite de proyectos (rol cliente)</label>
            <input type="number" id="limite_proyectos" name="limite_proyectos" min="0" max="999" step="1" value="<?php echo s($usuario->limite_proyectos ?? 1); ?>">
            <small class="text-muted">Solo se usa en cuentas legacy sin organización; con plan, los límites se toman del plan. 0 = no puede crear proyectos.</small>
            <label for="limite_almacenamiento_mb" style="margin-top:1.2rem">Espacio por proyecto en MB (rol cliente)</label>
            <input type="number" id="limite_almacenamiento_mb" name="limite_almacenamiento_mb" min="1" max="100000" step="1" value="<?php echo s($usuario->limite_almacenamiento_mb ?? 300); ?>">
            <small class="text-muted">Almacenamiento que puede ocupar cada proyecto de este cliente (archivos, fotos, planos, fichas y logos). 1024 MB = 1 GB.</small>
            <label for="limite_visitantes" style="margin-top:1.2rem">Cuántos visitantes puede crear (solo lectura)</label>
            <input type="number" id="limite_visitantes" name="limite_visitantes" min="0" max="999" step="1" value="<?php echo s($usuario->limite_visitantes ?? \Model\Usuario::MAX_VISITANTES_CLIENTE); ?>">
            <label for="limite_colaboradores" style="margin-top:1.2rem">Cuántos colaboradores puede crear (pueden editar)</label>
            <input type="number" id="limite_colaboradores" name="limite_colaboradores" min="0" max="999" step="1" value="<?php echo s($usuario->limite_colaboradores ?? \Model\Usuario::MAX_COLABORADORES_CLIENTE); ?>">
            <small class="text-muted">El dueño crea a su equipo desde «Mi equipo» sin pasar del tope. 0 = no puede crear de ese tipo.</small>
        </div>
        <div class="campo oculto" id="campo-nivel">
            <label for="nivel_acceso">Tipo de acceso</label>
            <select id="nivel_acceso" name="nivel_acceso">
                <option value="ver" <?php echo ($usuario->nivel_acceso ?? 'ver') === 'ver' ? 'selected' : ''; ?>>Visitante · solo lectura</option>
                <option value="editar" <?php echo ($usuario->nivel_acceso ?? 'ver') === 'editar' ? 'selected' : ''; ?>>Colaborador · puede editar</option>
            </select>
            <small class="text-muted">El colaborador edita solo en las herramientas marcadas abajo; borrar queda reservado al dueño y al administrador.</small>
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
            <small class="text-muted">Cliente: los proyectos marcados los puede <strong>gestionar</strong>, además de los que él mismo crea. Visitante: solo los <strong>ve</strong>. El administrador ve todo.</small>
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
                const contextoOrg = <?php echo $contextoOrganizacion ? 'true' : 'false'; ?>;
                const rol = document.getElementById('rol'), caja = document.getElementById('campo-accesos'), lim = document.getElementById('campo-limite');
                const herr = document.getElementById('campo-herramientas'), niv = document.getElementById('campo-nivel');
                const plan = document.getElementById('campo-plan'), org = document.getElementById('campo-org'), orgVis = document.getElementById('campo-org-vis');
                const rolOrg = document.getElementById('rol_org'), cajaRolOrg = document.getElementById('campo-rol-org');
                const toggleOrg = () => {
                    const esCliente = rol.value === 'cliente';
                    const esGestor = esCliente && rolOrg && rolOrg.value === 'gestor';
                    if (cajaRolOrg) cajaRolOrg.style.display = esCliente ? '' : 'none';
                    if (plan) plan.style.display = esCliente && !esGestor ? '' : 'none';
                    if (org) org.style.display = esCliente && !esGestor ? '' : 'none';
                    if (orgVis) orgVis.style.display = esGestor || rol.value === 'visitante' ? '' : 'none';
                };
                const toggle = () => {
                    if (contextoOrg) {
                        // Alta de organización: solo datos de la organización, su plan y el propietario
                        if (caja) caja.style.display = 'none';
                        if (lim) lim.style.display = 'none';
                        if (herr) herr.style.display = 'none';
                        if (niv) niv.style.display = 'none';
                        if (cajaRolOrg) cajaRolOrg.style.display = 'none';
                        if (plan) plan.style.display = '';
                        if (org) org.style.display = '';
                        if (orgVis) orgVis.style.display = 'none';
                        return;
                    }
                    caja.style.display = rol.value === 'admin' ? 'none' : '';
                    lim.style.display = rol.value === 'cliente' ? '' : 'none';
                    herr.style.display = rol.value === 'visitante' ? '' : 'none';
                    niv.style.display = rol.value === 'visitante' ? '' : 'none';
                    toggleOrg();
                };
                rol.addEventListener('change', toggle);
                if (rolOrg) rolOrg.addEventListener('change', toggleOrg);
                toggle();
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
        <button type="submit" class="btn btn-primary"><?php echo $contextoOrganizacion ? 'Crear organización' : 'Crear Usuario'; ?></button>
    </form>
</div>
