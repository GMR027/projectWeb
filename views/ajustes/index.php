<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-gear"></i> Ajustes del Proyecto</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s($proyecto->codigo); ?></p>
    </div>
    <div>
        <a href="/calendario?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-secondary">
            <i class="fa-solid fa-calendar-days"></i> Ver Calendario
        </a>
        <?php if (puede_descargar()): ?>
            <a href="/exportar?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">
                <i class="fa-solid fa-file-export"></i> Exportar
            </a>
        <?php endif; ?>
        <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">
            <i class="fa-solid fa-arrow-left"></i> Programa
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
<div class="card" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-database"></i> Almacenamiento</h3>
    <p class="text-muted text-sm">Cada proyecto dispone de <?php echo $fmt($limite); ?> para sus archivos, fotos, planos, fichas y logos. No cuenta lo generado al exportar.</p>
    <div class="alm-barra" title="<?php echo number_format($pct, 1); ?> %"><span class="<?php echo $pct >= 90 ? 'alm-rojo' : ($pct >= 70 ? 'alm-amarillo' : ''); ?>" style="width: <?php echo number_format($pct, 2, '.', ''); ?>%"></span></div>
    <p><strong><?php echo $fmt($usado); ?></strong> usados de <?php echo $fmt($limite); ?> · <?php echo number_format($pct, 1); ?> % · libres <?php echo $fmt(max(0, $limite - $usado)); ?></p>
    <table class="tabla">
        <thead><tr><th>Concepto</th><th style="text-align:right">Ocupado</th></tr></thead>
        <tbody>
        <?php foreach ($alm as $nombre => $bytes): ?>
            <tr><td><?php echo s($nombre); ?></td><td class="mono" style="text-align:right"><?php echo $fmt($bytes); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (($_SESSION['rol'] ?? '') === 'cliente'): ?>
<div class="card" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-headset"></i> Soporte</h3>
    <p class="text-muted text-sm">¿Tienes una duda o necesitas ayuda? Escríbenos desde aquí y el administrador recibirá tu mensaje.</p>
    <a href="/soporte" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Abrir soporte</a>
</div>

<div class="card" id="cuenta" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-key"></i> Cambiar contraseña</h3>
    <?php if (isset($_GET['pw'])): ?><div class="alerta alerta-exito">Tu contraseña se actualizó correctamente.</div><?php endif; ?>
    <?php if (!empty($_GET['pw_error'])): ?><div class="alerta alerta-error"><?php echo s($_GET['pw_error']); ?></div><?php endif; ?>
    <form method="POST" action="/cuenta/password" class="formulario" autocomplete="off">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <div class="campo">
            <label for="password_actual">Contraseña actual</label>
            <input type="password" id="password_actual" name="password_actual" autocomplete="current-password" required>
        </div>
        <div class="campo">
            <label for="password_nueva">Nueva contraseña</label>
            <input type="password" id="password_nueva" name="password_nueva" minlength="6" autocomplete="new-password" required>
        </div>
        <div class="campo">
            <label for="password_nueva2">Repetir nueva contraseña</label>
            <input type="password" id="password_nueva2" name="password_nueva2" minlength="6" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-lock"></i> Actualizar contraseña</button>
    </form>
</div>
<?php endif; ?>

<div class="ajustes-grid">
    <!-- Configuración de jornada semanal -->
    <div class="card">
        <h3 class="card-titulo"><i class="fa-solid fa-clock"></i> Jornada Semanal</h3>
        <p class="text-muted text-sm">Factor de jornada por día. 0 = inhábil, 0.5 = medio turno, 1 = jornada completa.</p>
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
                                <option value="0" <?php echo (float)$factor === 0.0 ? 'selected' : ''; ?>>0 — Inhábil</option>
                                <option value="0.5" <?php echo (float)$factor === 0.5 ? 'selected' : ''; ?>>0.5 — Medio turno</option>
                                <option value="1" <?php echo (float)$factor === 1.0 ? 'selected' : ''; ?>>1 — Completa</option>
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
        <h3 class="card-titulo"><i class="fa-solid fa-calendar-xmark"></i> Días No Laborables</h3>
        <p class="text-muted text-sm">Festivos, paros técnicos y suspensiones por clima.</p>

        <?php if (puede_gestionar_proyecto($proyecto)): ?>
            <form method="POST" action="/ajustes/festivo/agregar" class="festivo-form">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <div class="form-grid form-grid-inline">
                    <div class="campo">
                        <label for="fecha">Fecha</label>
                        <input type="date" id="fecha" name="fecha" required>
                    </div>
                    <div class="campo">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo">
                            <option value="festivo">Festivo</option>
                            <option value="paro_tecnico">Paro Técnico</option>
                            <option value="clima">Clima</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="campo campo-full">
                        <label for="motivo">Motivo</label>
                        <input type="text" id="motivo" name="motivo" required placeholder="Ej: Día de la Independencia">
                    </div>
                    <div class="campo">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i> Agregar
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <table class="tabla">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Motivo</th>
                    <?php if (puede_gestionar_proyecto($proyecto)): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($festivos)): ?>
                    <tr><td colspan="4" class="text-center text-muted">No hay días no laborables registrados.</td></tr>
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
