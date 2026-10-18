<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-gear"></i> <?php echo s(t('Ajustes del Proyecto')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s($proyecto->codigo); ?></p>
    </div>
    <div>
        <a href="/calendario?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-secondary">
            <i class="fa-solid fa-calendar-days"></i> Ver Calendario
        </a>
        <?php if (puede_descargar()): ?>
            <a href="/exportar?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">
                <i class="fa-solid fa-file-export"></i> <?php echo s(t('Exportar')); ?>
            </a>
        <?php endif; ?>
        <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">
            <i class="fa-solid fa-arrow-left"></i> <?php echo s(t('Programa')); ?>
        </a>
    </div>
</div>

<?php
$alm = $almacenamiento['desglose'];
$usado = array_sum($alm);
$limite = \Classes\Almacenamiento::limite((int)$proyecto->id);
$pct = min(100, $usado / $limite * 100);
$fmt = fn($b) => \Classes\Almacenamiento::formato((int)$b);
?>
<div class="card" id="idioma" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-language"></i> <?php echo s(t('Idioma de la interfaz')); ?> · Language</h3>
    <form method="POST" action="/idioma" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="volver" value="<?php echo s($_SERVER['REQUEST_URI'] ?? '/'); ?>#idioma">
        <select name="idioma" aria-label="<?php echo s(t('Idioma')); ?>"><?php foreach (IDIOMAS as $cod => $nom): ?><option value="<?php echo $cod; ?>"<?php echo idioma_actual() === $cod ? ' selected' : ''; ?>><?php echo s($nom); ?></option><?php endforeach; ?></select>
        <button type="submit" class="btn btn-primary"><?php echo s(t('Guardar')); ?></button>
    </form>
    <p class="text-muted text-sm" style="margin-top:.8rem">Español / English · <?php echo idioma_actual() === 'en' ? 'Menus and the main screens are translated; the rest of the app keeps showing Spanish for now.' : 'Menús y pantallas principales traducidos; el resto de la aplicación sigue en español por ahora.'; ?></p>
</div>

<div class="card" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-database"></i> <?php echo s(t('Almacenamiento')); ?></h3>
    <p class="text-muted text-sm">Cada proyecto dispone de <?php echo $fmt($limite); ?> para sus archivos, fotos, planos, fichas y logos. No cuenta lo generado al exportar.</p>
    <div class="alm-barra" title="<?php echo number_format($pct, 1); ?> %"><span class="<?php echo $pct >= 90 ? 'alm-rojo' : ($pct >= 70 ? 'alm-amarillo' : ''); ?>" style="width: <?php echo number_format($pct, 2, '.', ''); ?>%"></span></div>
    <p><strong><?php echo $fmt($usado); ?></strong> usados de <?php echo $fmt($limite); ?> · <?php echo number_format($pct, 1); ?> % · libres <?php echo $fmt(max(0, $limite - $usado)); ?></p>
    <table class="tabla">
        <thead><tr><th><?php echo s(t('Concepto')); ?></th><th style="text-align:right"><?php echo s(t('Ocupado')); ?></th></tr></thead>
        <tbody>
        <?php foreach ($alm as $nombre => $bytes): ?>
            <tr><td><?php echo s($nombre); ?></td><td class="mono" style="text-align:right"><?php echo $fmt($bytes); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card" id="sin-conexion" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-plane"></i> Modo sin conexión (este equipo)</h3>
    <p class="text-muted text-sm">Guarda en este equipo las pantallas que abres y los cambios de estado de las órdenes de cambio. Sin red puedes seguir consultando y marcando avances; al volver la conexión se suben solos. Si algo cambió en el servidor mientras tanto, se te pregunta cuál versión queda. También funciona dentro de la aplicación de escritorio. Las fotos y los archivos grandes requieren conexión.</p>
    <label class="campo-check"><input type="checkbox" id="po-offline-activar"> Activar el modo sin conexión en este equipo</label>
    <div id="po-offline-acciones" hidden style="margin-top:1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
        <button type="button" class="btn btn-primary" id="po-offline-bajar"><i class="fa-solid fa-download"></i> Descargar este proyecto para usarlo sin conexión</button>
        <span id="po-offline-estado" class="text-muted text-sm"></span>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {   // el bundle (POOffline) se carga al final de la página
    const chk = document.getElementById('po-offline-activar'), acc = document.getElementById('po-offline-acciones'), est = document.getElementById('po-offline-estado');
    if (!window.POOffline) { chk.disabled = true; est.textContent = 'Este navegador no admite el modo sin conexión.'; return; }
    const pintar = async () => {
        chk.checked = POOffline.activo(); acc.hidden = !chk.checked;
        if (chk.checked) { const n = (await POOffline.listar().catch(() => [])).length; est.textContent = n ? n + ' cambio(s) en la cola' : ''; }
    };
    chk.addEventListener('change', async () => {
        if (chk.checked) { const ok = await POOffline.activar(); if (!ok) est.textContent = 'No se pudo registrar el servicio sin conexión (requiere https o localhost).'; }
        else await POOffline.desactivar();
        pintar();
    });
    document.getElementById('po-offline-bajar').addEventListener('click', async e => {
        e.target.disabled = true;
        const n = await POOffline.precargar(new URLSearchParams(location.search).get('proyecto'), (h, t) => { est.textContent = 'Descargando ' + h + ' de ' + t + '…'; });
        est.textContent = 'Listo: ' + n + ' pantalla(s) guardadas en este equipo.';
        e.target.disabled = false;
    });
    pintar();
});
</script>

<?php if (($_SESSION['rol'] ?? '') === 'cliente'): ?>
<div class="card" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-headset"></i> <?php echo s(t('Soporte')); ?></h3>
    <p class="text-muted text-sm"><?php echo s(t('¿Tienes una duda o necesitas ayuda? Escríbenos desde aquí y el administrador recibirá tu mensaje.')); ?></p>
    <a href="/soporte" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> <?php echo s(t('Abrir soporte')); ?></a>
</div>

<div class="card" id="cuenta" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-key"></i> <?php echo s(t('Cambiar contraseña')); ?></h3>
    <?php if (isset($_GET['pw'])): ?><div class="alerta alerta-exito"><?php echo s(t('Tu contraseña se actualizó correctamente.')); ?></div><?php endif; ?>
    <?php if (!empty($_GET['pw_error'])): ?><div class="alerta alerta-error"><?php echo s($_GET['pw_error']); ?></div><?php endif; ?>
    <form method="POST" action="/cuenta/password" class="formulario" autocomplete="off">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <div class="campo">
            <label for="password_actual"><?php echo s(t('Contraseña actual')); ?></label>
            <input type="password" id="password_actual" name="password_actual" autocomplete="current-password" required>
        </div>
        <div class="campo">
            <label for="password_nueva"><?php echo s(t('Nueva contraseña')); ?></label>
            <input type="password" id="password_nueva" name="password_nueva" minlength="6" autocomplete="new-password" required>
        </div>
        <div class="campo">
            <label for="password_nueva2"><?php echo s(t('Repetir nueva contraseña')); ?></label>
            <input type="password" id="password_nueva2" name="password_nueva2" minlength="6" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-lock"></i> <?php echo s(t('Actualizar contraseña')); ?></button>
    </form>
</div>
<?php endif; ?>

<div class="ajustes-grid">
    <!-- Configuración de jornada semanal -->
    <div class="card">
        <h3 class="card-titulo"><i class="fa-solid fa-clock"></i> <?php echo s(t('Jornada Semanal')); ?></h3>
        <p class="text-muted text-sm"><?php echo s(t('Factor de jornada por día. 0 = inhábil, 0.5 = medio turno, 1 = jornada completa.')); ?></p>
        <?php if (puede_gestionar_proyecto($proyecto)): ?>
            <form method="POST" action="/ajustes/jornada">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <div class="jornada-grid">
                    <?php
                    $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
                    $jornadaMap = [];
                    foreach ($jornada as $j) {
                        $jornadaMap[(int)$j->dia_semana] = $j->factor;
                    }
                    foreach ($dias as $num => $nombre):
                        $factor = $jornadaMap[$num] ?? ($num <= 5 ? 1 : ($num === 6 ? 0.5 : 0));
                    ?>
                        <div class="jornada-dia">
                            <label><?php echo s($nombre); ?></label>
                            <select name="factor_<?php echo $num; ?>">
                                <option value="0" <?php echo (float)$factor === 0.0 ? 'selected' : ''; ?>><?php echo s(t('0 — Inhábil')); ?></option>
                                <option value="0.5" <?php echo (float)$factor === 0.5 ? 'selected' : ''; ?>><?php echo s(t('0.5 — Medio turno')); ?></option>
                                <option value="1" <?php echo (float)$factor === 1.0 ? 'selected' : ''; ?>><?php echo s(t('1 — Completa')); ?></option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Guardar Jornada
                </button>
            </form>
        <?php else: ?>
            <div class="jornada-grid">
                <?php foreach ($dias ?? [] as $num => $nombre): ?>
                    <div class="jornada-dia">
                        <label><?php echo s($nombre); ?></label>
                        <span class="mono"><?php echo s($jornadaMap[$num] ?? 0); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-muted text-sm" style="margin-top: 1rem;">
                <i class="fa-solid fa-lock"></i> Solo el administrador puede modificar la jornada.
            </p>
        <?php endif; ?>
    </div>

    <!-- Días No Laborables -->
    <div class="card">
        <h3 class="card-titulo"><i class="fa-solid fa-calendar-xmark"></i> <?php echo s(t('Días No Laborables')); ?></h3>
        <p class="text-muted text-sm"><?php echo s(t('Festivos, paros técnicos y suspensiones por clima.')); ?></p>

        <?php if (puede_gestionar_proyecto($proyecto)): ?>
            <form method="POST" action="/ajustes/festivo/agregar" class="festivo-form">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <div class="form-grid form-grid-inline">
                    <div class="campo">
                        <label for="fecha"><?php echo s(t('Fecha')); ?></label>
                        <input type="date" id="fecha" name="fecha" required>
                    </div>
                    <div class="campo">
                        <label for="tipo"><?php echo s(t('Tipo')); ?></label>
                        <select id="tipo" name="tipo">
                            <option value="festivo"><?php echo s(t('Festivo')); ?></option>
                            <option value="paro_tecnico"><?php echo s(t('Paro Técnico')); ?></option>
                            <option value="clima"><?php echo s(t('Clima')); ?></option>
                            <option value="otro"><?php echo s(t('Otro')); ?></option>
                        </select>
                    </div>
                    <div class="campo campo-full">
                        <label for="motivo"><?php echo s(t('Motivo')); ?></label>
                        <input type="text" id="motivo" name="motivo" required placeholder="Ej: Día de la Independencia">
                    </div>
                    <div class="campo">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i> <?php echo s(t('Agregar')); ?>
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <table class="tabla">
            <thead>
                <tr>
                    <th><?php echo s(t('Fecha')); ?></th>
                    <th><?php echo s(t('Tipo')); ?></th>
                    <th><?php echo s(t('Motivo')); ?></th>
                    <?php if (puede_gestionar_proyecto($proyecto)): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($festivos)): ?>
                    <tr><td colspan="4" class="text-center text-muted"><?php echo s(t('No hay días no laborables registrados.')); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($festivos as $f): ?>
                        <tr>
                            <td class="mono"><?php echo formatear_fecha($f->fecha, 'd/m/Y (D)'); ?></td>
                            <td><span class="badge badge-<?php echo s($f->tipo); ?>"><?php echo s(ucfirst(str_replace('_', ' ', $f->tipo))); ?></span></td>
                            <td><?php echo s($f->motivo); ?></td>
                            <?php if (puede_gestionar_proyecto($proyecto)): ?>
                                <td>
                                    <form method="POST" action="/ajustes/festivo/eliminar" style="display:inline" data-confirm="¿Eliminar día no laborable?|El día volverá a contarse como hábil en el calendario.">
                                        <input type="hidden" name="id" value="<?php echo s($f->id); ?>">
                                        <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                                        <button type="submit" class="btn btn-xs btn-danger">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
