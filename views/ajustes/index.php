<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-gear"></i> <?php echo s(t('Ajustes del Proyecto')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s($proyecto->codigo); ?></p>
    </div>
    <div>
        <a href="/proyectos/ver?id=<?php echo s($proyecto->id); ?>" class="btn btn-secondary">
            <i class="fa-solid fa-heart-pulse"></i> Salud del proyecto
        </a>
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
$limiteMb = \Classes\Almacenamiento::limiteMb((int)$proyecto->id);
$limiteHeredado = \Classes\Almacenamiento::limiteHeredado((int)$proyecto->id);
$puedeAjustarAlmacenamiento = is_admin() && \Classes\Almacenamiento::hayColumnaProyecto();
?>
<?php
$pidResp = (int)$proyecto->id;
$respaldos = \Classes\RespaldoProyecto::listar($pidResp);
$respAnterior = \Classes\RespaldoProyecto::anterior($pidResp);
$etiquetaResp = ['auto' => 'Diario (automático)', 'semanal' => 'Semanal (automático)', 'mensual' => 'Mensual (automático)', 'manual' => 'Manual', 'previo' => 'Previo a restaurar'];
?>
<?php
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$jornadaMap = [];
foreach ($jornada as $j) { $jornadaMap[(int)$j->dia_semana] = $j->factor; }
$esCliente = ($_SESSION['rol'] ?? '') === 'cliente';
$esElectron = str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'Electron/');
$novedadesPlataforma = \Classes\Novedades::para($_SESSION['rol'] ?? 'visitante', (int)($_SESSION['id'] ?? 0));
$novedadesSinVer = \Classes\Novedades::sinVer($novedadesPlataforma);
$secNav = [
    ['general', 'fa-sliders', 'General', 'Jornada, festivos e idioma'],
    ['novedades', 'fa-bullhorn', 'Novedades', 'Lo último de la plataforma'],
    ['empezar', 'fa-flag-checkered', 'Por dónde empezar', 'Ruta de arranque'],
    ['datos', 'fa-database', 'Datos', 'Almacenamiento y respaldos'],
];
if (is_admin() || $esElectron) $secNav[] = ['equipo', 'fa-desktop', 'Equipo', 'App y modo sin conexión'];
if ($esCliente) $secNav[] = ['cuenta-sec', 'fa-user-shield', 'Cuenta', 'Soporte y contraseña'];
?>
<div class="ajustes-layout">
<nav class="ajustes-nav" role="tablist" aria-label="Secciones de ajustes">
<?php foreach ($secNav as [$sid, $ico, $tit, $sub]): ?>
    <a href="#<?php echo $sid; ?>" role="tab" data-sec="<?php echo $sid; ?>"><i class="fa-solid <?php echo $ico; ?>"></i><span><strong><?php echo s($tit); ?></strong><small><?php echo s($sub); ?></small></span></a>
<?php endforeach; ?>
</nav>
<div class="ajustes-contenido">

<section class="ajustes-seccion" id="general">
    <h3 class="seccion-titulo"><i class="fa-solid fa-sliders"></i> General</h3>
    <div class="ajustes-grid">
    <!-- Configuración de jornada semanal -->
    <div class="card" id="jornada">
        <h3 class="card-titulo"><i class="fa-solid fa-clock"></i> <?php echo s(t('Jornada Semanal')); ?></h3>
        <p class="text-muted text-sm"><?php echo s(t('Factor de jornada por día. 0 = inhábil, 0.5 = medio turno, 1 = jornada completa.')); ?></p>
        <?php if (puede_gestionar_proyecto($proyecto)): ?>
            <form method="POST" action="/ajustes/jornada">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <div class="jornada-grid">
                    <?php
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
<div class="card" id="registros">
    <h3 class="card-titulo"><i class="fa-solid fa-list-ol"></i> Registros por página</h3>
    <?php if (($_GET['registros'] ?? '') === 'ok'): ?><div class="alerta alerta-exito">Guardado.</div><?php endif; ?>
    <p class="text-muted text-sm">Cuántos registros se ven a la vez en las listas de Archivo, Reporte diario, Bitácora, Minutas, Evidencias, Punch list, Submittals, Catálogo de materiales, Editor, Redactor y SheetFlow; el resto se recorre con el paginador. Por defecto, 10.</p>
    <form method="POST" action="/ajustes/registros" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="proyecto_id" value="<?php echo (int)$proyecto->id; ?>">
        <label>Registros <input type="number" name="registros_por_pagina" min="1" max="500" value="<?php echo registros_por_pagina($proyecto); ?>" style="width:10rem"></label>
        <button type="submit" class="btn btn-primary"><?php echo s(t('Guardar')); ?></button>
    </form>
</div>
<div class="card" id="idioma">
    <h3 class="card-titulo"><i class="fa-solid fa-language"></i> <?php echo s(t('Idioma de la interfaz')); ?> · Language</h3>
    <form method="POST" action="/idioma" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="volver" value="<?php echo s($_SERVER['REQUEST_URI'] ?? '/'); ?>#idioma">
        <select name="idioma" aria-label="<?php echo s(t('Idioma')); ?>"><?php foreach (IDIOMAS as $cod => $nom): ?><option value="<?php echo $cod; ?>"<?php echo idioma_actual() === $cod ? ' selected' : ''; ?>><?php echo s($nom); ?></option><?php endforeach; ?></select>
        <button type="submit" class="btn btn-primary"><?php echo s(t('Guardar')); ?></button>
    </form>
    <p class="text-muted text-sm" style="margin-top:.8rem">Español / English · <?php echo idioma_actual() === 'en' ? 'Menus and the main screens are translated; the rest of the app keeps showing Spanish for now.' : 'Menús y pantallas principales traducidos; el resto de la aplicación sigue en español por ahora.'; ?></p>
</div>

<?php
$temaModo = \Classes\TemaProyecto::normModo($proyecto->tema_modo ?? 'usuario');
$temaPaleta = \Classes\TemaProyecto::normPaleta($proyecto->tema_paleta ?? 'naranja');
?>
<div class="card" id="tema">
    <h3 class="card-titulo"><i class="fa-solid fa-palette"></i> Apariencia del proyecto</h3>
    <?php if (($_GET['tema'] ?? '') === 'ok'): ?><div class="alerta alerta-exito">Guardado.</div><?php endif; ?>
    <p class="text-muted text-sm">Se guarda en el proyecto: se mantiene al cambiar de navegador o de equipo y lo ven todos los que abran el proyecto. El color se usa también en la carátula del portafolio. Las exportaciones (PDF, Excel, XML) no cambian.</p>
    <form method="POST" action="/ajustes/tema" class="tema-form">
        <input type="hidden" name="proyecto_id" value="<?php echo (int)$proyecto->id; ?>">

        <div class="tema-bloque">
            <span class="tema-etq">Modo</span>
            <div class="tema-modos">
                <?php foreach (\Classes\TemaProyecto::MODOS as $k => $etq): ?>
                    <label class="tema-modo">
                        <input type="radio" name="tema_modo" value="<?php echo s($k); ?>"<?php echo $temaModo === $k ? ' checked' : ''; ?>>
                        <span><?php echo s($etq); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tema-bloque">
            <span class="tema-etq">Color de acento</span>
            <div class="tema-paletas">
                <?php foreach (\Classes\TemaProyecto::PALETAS as $k => [$nombre, $claro, $oscuro]): ?>
                    <label class="tema-paleta" title="<?php echo s($nombre); ?>">
                        <input type="radio" name="tema_paleta" value="<?php echo s($k); ?>"<?php echo $temaPaleta === $k ? ' checked' : ''; ?> data-claro="<?php echo s($claro); ?>" data-oscuro="<?php echo s($oscuro); ?>">
                        <span class="tema-chip" style="--chip-claro:<?php echo s($claro); ?>;--chip-oscuro:<?php echo s($oscuro); ?>"></span>
                        <span class="tema-nom"><?php echo s($nombre); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <p id="tema-aviso" class="text-muted text-sm" hidden><i class="fa-solid fa-circle-info"></i> Estás viendo una vista previa: pulsa <strong>Guardar apariencia</strong> para dejarla en el proyecto.</p>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar apariencia</button>
    </form>
    <script>
        // Vista previa: al elegir modo o color se aplica al momento; «Guardar» lo deja en el proyecto.
        (function () {
            const card = document.getElementById('tema');
            if (!card) return;
            const raiz = document.documentElement;
            const TEMA = { usuario: null, claro: 'light', oscuro: 'dark' };
            const rgb = h => [1, 3, 5].map(i => parseInt(h.substr(i, 2), 16));
            const oscurecer = (h, p) => '#' + rgb(h).map(v => Math.round(v * (1 - p)).toString(16).padStart(2, '0')).join('');
            card.addEventListener('change', () => {
                const modo = card.querySelector('input[name="tema_modo"]:checked')?.value || 'usuario';
                raiz.setAttribute('data-theme', TEMA[modo] || localStorage.getItem('theme') || 'dark');
                const aviso = document.getElementById('tema-aviso');
                if (aviso) aviso.hidden = false;
                const p = card.querySelector('input[name="tema_paleta"]:checked');
                if (!p) return;
                const claro = raiz.getAttribute('data-theme') === 'light';
                const c = claro ? p.dataset.claro : p.dataset.oscuro;
                raiz.style.setProperty('--primary', c);
                raiz.style.setProperty('--primary-hover', oscurecer(c, 0.12));
                raiz.style.setProperty('--primary-glow', 'rgba(' + rgb(c).join(',') + ',' + (claro ? .15 : .25) + ')');
            });
        })();
    </script>
</div>

</section>

<section class="ajustes-seccion" id="novedades">
    <h3 class="seccion-titulo"><i class="fa-solid fa-bullhorn"></i> <?php echo s(t('Novedades de la plataforma')); ?></h3>
    <div class="card">
        <p class="text-muted text-sm">Lo que se publica aquí es lo mismo que avisa la campana de la barra superior.<?php if ($novedadesSinVer > 0): ?> Tienes <strong><?php echo (int)$novedadesSinVer; ?></strong> sin ver.<?php endif; ?></p>
        <?php if ($novedadesSinVer > 0): ?>
            <form method="POST" action="/novedades/vistas" style="margin:0 0 1.2rem">
                <input type="hidden" name="volver" value="/ajustes?proyecto=<?php echo (int)$proyecto->id; ?>#novedades">
                <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-check-double"></i> Marcar como vistas</button>
            </form>
        <?php endif; ?>
        <?php $novedades = $novedadesPlataforma; include __DIR__ . '/../templates/novedades-lista.php'; ?>
    </div>
</section>

<section class="ajustes-seccion" id="empezar">
<div class="card" id="por-donde-empezar">
    <h3 class="card-titulo"><i class="fa-solid fa-flag-checkered"></i> Por dónde empezar</h3>
    <p class="text-muted text-sm">Ruta recomendada para arrancar el proyecto. Lo que captures aquí se guarda en la herramienta correspondiente.</p>
    <?php $pdeProyecto = $proyecto; $pdeModo = 'lista'; $pdeEnModal = false; include __DIR__ . '/../templates/por-donde-empezar.php'; ?>
</div>

</section>

<section class="ajustes-seccion" id="datos">
    <h3 class="seccion-titulo"><i class="fa-solid fa-database"></i> Datos</h3>
<div class="card" id="almacenamiento">
    <h3 class="card-titulo"><i class="fa-solid fa-database"></i> <?php echo s(t('Almacenamiento')); ?></h3>
    <p class="text-muted text-sm">Cada proyecto dispone de <?php echo $fmt($limite); ?> para sus archivos, fotos, planos, fichas y logos. No cuenta lo generado al exportar.</p>
    <?php if ($puedeAjustarAlmacenamiento): ?>
        <form method="POST" action="/ajustes/almacenamiento">
            <input type="hidden" name="proyecto_id" value="<?php echo (int)$proyecto->id; ?>">
            <div class="form-grid form-grid-inline" style="margin-top:1rem">
                <div class="campo">
                    <label for="limite_almacenamiento_mb">Espacio del proyecto (MB)</label>
                    <input type="number" id="limite_almacenamiento_mb" name="limite_almacenamiento_mb" min="0" max="100000" step="1" value="<?php echo $limiteMb ?>">
                </div>
                <div class="campo" style="align-self:end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-arrows-up-down"></i> Ampliar o reducir
                    </button>
                </div>
            </div>
        </form>
        <p class="text-muted text-sm" style="margin-top:.6rem">
            <?php if ($limiteMb > 0): ?>
                Límite propio de este proyecto: <strong><?php echo number_format($limiteMb, 0, '.', ','); ?> MB</strong>.
                Escribe <strong>0</strong> para volver al límite heredado (<?php echo $fmt($limiteHeredado); ?>).
            <?php else: ?>
                Este proyecto usa el límite heredado: <strong><?php echo $fmt($limiteHeredado); ?></strong>. Escribe los MB que quieras asignarle.
            <?php endif; ?>
            <?php if (!empty($_GET['alm'])): ?>
                <br><span class="text-sm" style="color:var(--primary)">Espacio actualizado.</span>
            <?php endif; ?>
        </p>
    <?php elseif (is_admin()): ?>
        <p class="text-muted text-sm" style="margin-top:.8rem">
            <i class="fa-solid fa-lock"></i> Este proyecto usa el límite heredado: <strong><?php echo $fmt($limiteHeredado); ?></strong>.
        </p>
    <?php endif; ?>
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

<div class="card" id="respaldo-proyecto">
    <h3 class="card-titulo"><i class="fa-solid fa-clock-rotate-left"></i> Respaldo de proyecto</h3>
    <p class="text-muted text-sm">Guarda una copia de los datos y archivos de este proyecto para poder recuperar los cambios del día anterior. Se hace a mano con el botón o automáticamente cada noche a las <?php echo \Classes\RespaldoProyecto::HORA_AUTO; ?>. Se conserva un respaldo diario, uno semanal y uno mensual, además de los manuales. Restaurar no borra archivos del servidor; solo devuelve los que falten.</p>
    <?php if (!empty($_GET['resp_msg'])): ?><div class="alerta <?php echo ($_GET['resp'] ?? '') === 'error' ? 'alerta-error' : 'alerta-exito'; ?>"><?php echo s($_GET['resp_msg']); ?></div><?php endif; ?>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem">
        <form method="POST" action="/ajustes/respaldo/crear">
            <input type="hidden" name="proyecto_id" value="<?php echo $pidResp; ?>">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Respaldar ahora</button>
        </form>
        <form method="POST" action="/ajustes/respaldo/restaurar" data-confirm="Restaurar el último respaldo|Se reemplazarán los datos actuales del proyecto por los del último respaldo. Antes se guardará una copia del estado actual. ¿Continuar?|Restaurar">
            <input type="hidden" name="proyecto_id" value="<?php echo $pidResp; ?>">
            <button type="submit" class="btn btn-secondary"<?php echo $respAnterior ? '' : ' disabled'; ?>><i class="fa-solid fa-rotate-left"></i> Restaurar desde el respaldo anterior<?php echo $respAnterior ? ' (' . date('d/m/Y H:i', $respAnterior['fecha']) . ')' : ''; ?></button>
        </form>
        <form method="POST" action="/ajustes/respaldo/automatico">
            <input type="hidden" name="proyecto_id" value="<?php echo $pidResp; ?>">
            <label class="campo-check"><input type="checkbox" name="auto" value="1" onchange="this.form.submit()"<?php echo \Classes\RespaldoProyecto::automatico($pidResp) ? ' checked' : ''; ?>> Respaldo automático diario (<?php echo \Classes\RespaldoProyecto::HORA_AUTO; ?>)</label>
        </form>
    </div>
    <?php if ($respaldos): ?>
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Tipo</th><th style="text-align:right">Tamaño</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($respaldos as $r): ?>
            <tr>
                <td class="mono"><?php echo date('d/m/Y H:i', $r['fecha']); ?></td>
                <td><?php echo s($etiquetaResp[$r['tipo']] ?? $r['tipo']); ?></td>
                <td class="mono" style="text-align:right"><?php echo $fmt($r['bytes']); ?></td>
                <td style="display:flex;gap:.6rem;justify-content:flex-end;flex-wrap:wrap">
                    <a class="btn btn-ghost" href="/ajustes/respaldo/descargar?proyecto=<?php echo $pidResp; ?>&archivo=<?php echo urlencode($r['archivo']); ?>"><i class="fa-solid fa-download"></i></a>
                    <form method="POST" action="/ajustes/respaldo/restaurar" data-confirm="Restaurar este respaldo|Se restaurará el proyecto a este respaldo. Antes se guardará una copia del estado actual.|Restaurar">
                        <input type="hidden" name="proyecto_id" value="<?php echo $pidResp; ?>"><input type="hidden" name="archivo" value="<?php echo s($r['archivo']); ?>">
                        <button type="submit" class="btn btn-ghost" title="Restaurar"><i class="fa-solid fa-rotate-left"></i></button>
                    </form>
                    <form method="POST" action="/ajustes/respaldo/eliminar" data-confirm="Eliminar respaldo|Se eliminará este respaldo. Esta acción no se puede deshacer.|Eliminar">
                        <input type="hidden" name="proyecto_id" value="<?php echo $pidResp; ?>"><input type="hidden" name="archivo" value="<?php echo s($r['archivo']); ?>">
                        <button type="submit" class="btn btn-ghost" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="text-muted">Aún no hay respaldos de este proyecto.</p>
    <?php endif; ?>
</div>

</section>

<?php if (is_admin() || $esElectron): ?>
<section class="ajustes-seccion" id="equipo">
    <h3 class="seccion-titulo"><i class="fa-solid fa-desktop"></i> Equipo</h3>
<?php if (is_admin()): $instaladores = \Classes\Instaladores::listar(); $verInst = \Classes\Instaladores::version(); ?>
<div class="card" id="app-escritorio">
    <h3 class="card-titulo"><i class="fa-solid fa-desktop"></i> Aplicación de escritorio<?php echo $verInst ? ' · v' . s($verInst) : ''; ?></h3>
    <p class="text-muted text-sm">Instala la aplicación en tu equipo para usar las herramientas de IA y el modo sin conexión. Solo la ve el administrador.</p>
    <?php if ($instaladores): ?>
        <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <?php foreach ($instaladores as $a): if (!preg_match('/\.(deb|AppImage|exe|dmg|rpm|zip)$/i', $a['nombre'])) continue; ?>
            <a class="btn btn-primary" href="/sistema/instalador?archivo=<?php echo urlencode($a['nombre']); ?>"><i class="fa-solid fa-download"></i> <?php echo s($a['nombre']); ?> <span class="text-sm">(<?php echo number_format($a['tam'] / 1048576, 1); ?> MB)</span></a>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">Aún no hay instaladores publicados. Súbelos desde <a href="/sistema#escritorio">Sistema</a>.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'Electron/')): // el modo sin conexión es solo de la app de escritorio ?>
<div class="card" id="sin-conexion">
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
<?php endif; ?>

</section>
<?php endif; ?>

<?php if ($esCliente): ?>
<section class="ajustes-seccion" id="cuenta-sec">
    <h3 class="seccion-titulo"><i class="fa-solid fa-user-shield"></i> Cuenta</h3>
<div class="card">
    <h3 class="card-titulo"><i class="fa-solid fa-headset"></i> <?php echo s(t('Soporte')); ?></h3>
    <p class="text-muted text-sm"><?php echo s(t('¿Tienes una duda o necesitas ayuda? Escríbenos desde aquí y el administrador recibirá tu mensaje.')); ?></p>
    <a href="/soporte" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> <?php echo s(t('Abrir soporte')); ?></a>
</div>

<div class="card" id="cuenta">
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
</section>
<?php endif; ?>

</div>
</div>
<script>
(() => {
    const links = [...document.querySelectorAll('.ajustes-nav a')];
    const secs = [...document.querySelectorAll('.ajustes-seccion[id]')];
    const ids = links.map(a => a.dataset.sec);
    const mostrar = id => {
        if (!ids.includes(id)) id = ids[0];
        secs.forEach(x => { x.hidden = x.id !== id; });
        links.forEach(a => { const on = a.dataset.sec === id; a.classList.toggle('activo', on); a.setAttribute('aria-selected', on); });
        try { sessionStorage.setItem('ajustes-tab', id); } catch (e) {}
    };
    const desdeHash = () => {
        const el = location.hash.length > 1 ? document.getElementById(location.hash.slice(1)) : null;
        const sec = el && el.closest('.ajustes-seccion[id]');
        if (sec) { mostrar(sec.id); if (el !== sec) el.scrollIntoView(); return true; }
        return false;
    };
    links.forEach(a => a.addEventListener('click', e => { e.preventDefault(); mostrar(a.dataset.sec); history.replaceState(null, '', '#' + a.dataset.sec); }));
    window.addEventListener('hashchange', desdeHash);
    if (!desdeHash()) { let g = null; try { g = sessionStorage.getItem('ajustes-tab'); } catch (e) {} mostrar(g); }
})();
</script>
