<div class="page-header">
    <div>
        <span class="adm-eyebrow">Administración de cuentas</span>
        <h2><i class="fa-solid fa-user-pen"></i> Editar usuario</h2>
        <p class="text-muted">Actualiza los datos, el rol y los accesos por obra de esta cuenta.</p>
        <div class="adm-meta">
            <span><i class="fa-solid fa-signature"></i> <?php echo s($usuario->nombre); ?></span>
            <span><i class="fa-solid fa-envelope"></i> <?php echo s($usuario->email); ?></span>
            <span><i class="fa-solid fa-arrow-left"></i> <a href="/usuarios" style="color:inherit">Volver al listado</a></span>
        </div>
    </div>
</div>

<div class="ctl-form-grid">
<div>
<div class="card">
    <form method="POST" class="formulario ctl-form" enctype="multipart/form-data">
        <div class="ctl-bloque">
            <div class="ctl-bloque-cab">
                <i class="fa-solid fa-id-card"></i>
                <div><h4>Identidad de la cuenta</h4><p>Nombre y correo con los que la persona entra a ProjectWeb.</p></div>
            </div>
            <div class="ctl-campos">
                <div class="campo">
                    <label for="nombre">Nombre Completo</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo s($usuario->nombre); ?>" required>
                </div>
                <div class="campo">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo s($usuario->email); ?>" required>
                </div>
            </div>
        </div>

        <div class="ctl-bloque">
            <div class="ctl-bloque-cab">
                <i class="fa-solid fa-sitemap"></i>
                <div><h4>Rol y organización</h4><p>Define qué puede administrar y a qué empresa pertenece.</p></div>
            </div>
            <div class="ctl-campos">
                <div class="campo">
                    <label for="rol">Rol</label>
                    <select id="rol" name="rol" required>
                        <option value="admin" <?php echo $usuario->rol === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                        <option value="cliente" <?php echo $usuario->rol === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                        <option value="visitante" <?php echo $usuario->rol === 'visitante' ? 'selected' : ''; ?>>Visitante</option>
                    </select>
                </div>
            </div>
            <?php include __DIR__ . '/_organizacion-campos.php'; ?>
        <?php if (empty($organizacion)): ?>
        <div class="ctl-campos">
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
        </div>
        <?php endif; ?>
        <div class="ctl-campos">
            <div class="campo" id="campo-logo">
                <label for="logo">Logo de la empresa (rol cliente)</label>
                <input type="file" id="logo" name="logo" accept="image/*">
                <?php if (!empty($usuario->logo)): ?>
                    <img src="/build/img/logos/<?php echo s($usuario->logo); ?>" alt="logo" style="max-width:8rem; margin-top:0.5rem;">
                <?php endif; ?>
            </div>
        </div>
        </div>

        <div class="ctl-bloque">
            <div class="ctl-bloque-cab">
                <i class="fa-solid fa-door-open"></i>
                <div><h4>Acceso a obras y herramientas</h4><p>Marca los proyectos que puede abrir y, si es colaborador, lo que puede editar.</p></div>
            </div>
        <div class="campo oculto" id="campo-nivel">
            <label for="nivel_acceso">Tipo de acceso</label>
            <select id="nivel_acceso" name="nivel_acceso">
                <option value="ver" <?php echo ($usuario->nivel_acceso ?? 'ver') === 'ver' ? 'selected' : ''; ?>>Visitante · solo lectura</option>
                <option value="editar" <?php echo ($usuario->nivel_acceso ?? 'ver') === 'editar' ? 'selected' : ''; ?>>Colaborador · puede editar</option>
            </select>
            <small class="text-muted">El colaborador edita solo en las herramientas marcadas abajo; borrar queda reservado al dueño y al administrador.</small>
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
                    caja.style.display = rol.value === 'admin' ? 'none' : '';
                    if (lim) lim.style.display = rol.value === 'cliente' ? '' : 'none';
                    herr.style.display = rol.value === 'visitante' ? '' : 'none';
                    niv.style.display = rol.value === 'visitante' ? '' : 'none';
                    toggleOrg();
                };
                rol.addEventListener('change', toggle);
                if (rolOrg) rolOrg.addEventListener('change', toggleOrg);
                toggle();
            })();
        </script>
        </div>

        <div class="ctl-bloque">
            <div class="ctl-bloque-cab">
                <i class="fa-solid fa-key"></i>
                <div><h4>Cambiar contraseña</h4><p>Déjalo vacío para conservar la contraseña actual. Al cambiarla se cierran las demás sesiones.</p></div>
            </div>
            <div class="ctl-campos">
                <div class="campo">
                    <label for="password">Nuevo Password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password">
                </div>
                <div class="campo">
                    <label for="password2">Repetir Nuevo Password</label>
                    <input type="password" id="password2" name="password2" autocomplete="new-password">
                </div>
            </div>
        </div>

        <div class="ctl-form-pie">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
            <a href="/usuarios" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
</div>
<aside class="ctl-aside">
    <div class="ctl-aside-card">
        <h4><i class="fa-solid fa-user"></i> Cuenta actual</h4>
        <div class="ctl-identidad">
            <span class="ctl-avatar ctl-avatar-lg"><?php echo s(iniciales_nombre($usuario->nombre)); ?></span>
            <div class="ctl-id-txt">
                <strong><?php echo s($usuario->nombre); ?></strong>
                <span class="ctl-id-sub"><?php echo s($usuario->email); ?></span>
                <span class="badge badge-<?php echo s($usuario->rol); ?>"><?php echo s(ucfirst($usuario->rol)); ?></span>
            </div>
        </div>
    </div>
    <div class="ctl-aside-card">
        <h4><i class="fa-solid fa-shield-halved"></i> Antes de guardar</h4>
        <ul>
            <li>Cambiar el rol puede quitar accesos que la persona ya tenía.</li>
            <li>Los visitantes solo consultan; los colaboradores editan lo marcado.</li>
            <li>El borrado siempre queda en manos del dueño o del administrador.</li>
            <li>Si cambias la contraseña, la persona deberá usarla en su próximo acceso.</li>
        </ul>
    </div>
</aside>
</div>
