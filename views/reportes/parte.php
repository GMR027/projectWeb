<?php
/** Parte diario único de campo: todo lo del día en una sola pantalla, móvil primero. */
$b = $vista['base'];
$cat = $vista['catalogos'];
$pid = s($proyecto->id);
$esNuevo = empty($b['id']);
$cerrado = ($b['estado'] ?? 'borrador') === 'cerrado';
$selActs = array_values(array_filter($vista['actividades'], fn($a) => $a['sel']));
$opcionesAct = function (int $sel = 0) use ($vista) {
    $h = '<option value="0">— Sin actividad —</option>';
    foreach ($vista['actividades'] as $a) {
        $h .= '<option value="' . (int)$a['id'] . '"' . ((int)$sel === (int)$a['id'] ? ' selected' : '') . '>' . s($a['codigo'] . ' · ' . $a['nombre']) . '</option>';
    }
    return $h;
};
$opcionesPersonal = function (int $sel = 0) use ($cat) {
    $h = '<option value="0">— Elegir persona —</option>';
    foreach ($cat['personal'] as $p) $h .= '<option value="' . (int)$p['id'] . '" data-tarifa="' . (float)$p['tarifa_hora'] . '"' . ((int)$sel === (int)$p['id'] ? ' selected' : '') . '>' . s($p['nombre'] . ($p['departamento'] ? ' · ' . $p['departamento'] : '')) . '</option>';
    return $h;
};
$opcionesEquipo = function (int $sel = 0) use ($cat) {
    $h = '<option value="0">— Elegir equipo —</option>';
    foreach ($cat['equipo'] as $e) $h .= '<option value="' . (int)$e['id'] . '" data-tarifa="' . (float)$e['tarifa_hora'] . '"' . ((int)$sel === (int)$e['id'] ? ' selected' : '') . '>' . s($e['nombre'] . ($e['tipo'] ? ' · ' . $e['tipo'] : '')) . '</option>';
    return $h;
};
$opcionesMaterial = function (int $sel = 0) use ($cat) {
    $h = '<option value="0">— Elegir material —</option>';
    foreach ($cat['materiales'] as $m) $h .= '<option value="' . (int)$m['id'] . '" data-costo="' . (float)$m['costo_unitario'] . '" data-unidad="' . s($m['unidad']) . '"' . ((int)$sel === (int)$m['id'] ? ' selected' : '') . '>' . s($m['nombre']) . '</option>';
    return $h;
};
?>
<div class="page-header pd-cabecera">
    <div>
        <h2><i class="fa-solid fa-clipboard-list"></i> <?php echo $esNuevo ? 'Nuevo parte diario' : 'Parte diario · ' . s(fecha_larga_es($b['fecha'])); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->numero_trabajo ? ' · Trabajo ' . s($proyecto->numero_trabajo) : ''; ?>
            <?php if ($cerrado): ?><span class="pd-chip pd-chip-cerrado"><i class="fa-solid fa-lock"></i> Cerrado</span><?php else: ?><span class="pd-chip"><i class="fa-solid fa-pen"></i> Borrador</span><?php endif; ?>
        </p>
    </div>
    <div class="la-controles">
        <a href="/reportes?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Reportes</a>
        <?php if (!$esNuevo): ?><a href="/reportes/parte/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$b['id']; ?>" class="btn btn-ghost" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i> PDF</a><?php endif; ?>
    </div>
</div>

<?php if (!$cat['personal'] && !$cat['equipo']): ?>
    <div class="alerta alerta-info">Aún no hay personal ni equipo en el proyecto. Puedes capturar el parte con las actividades, materiales y fotos; agrega personas en <a href="/reportes/personal?proyecto=<?php echo $pid; ?>">Personal</a> y máquinas en <a href="/equipo?proyecto=<?php echo $pid; ?>">Equipo</a>.</div>
<?php endif; ?>

<form method="POST" action="/reportes/parte" enctype="multipart/form-data" class="pd-form formulario" id="pd-form" autocomplete="off">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
    <input type="hidden" name="offline_uuid" value="<?php echo s($b['offline_uuid'] ?? ''); ?>">
    <input type="hidden" name="personal_present" value="1">
    <input type="hidden" name="equipo_present" value="1">
    <input type="hidden" name="material_present" value="1">
    <input type="hidden" name="tiempo_present" value="1">
    <input type="hidden" name="seguridad_present" value="1">

    <!-- 1. Resumen -->
    <details class="pd-seccion" open>
        <summary><span class="pd-num">1</span> Resumen del día</summary>
        <div class="form-grid">
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($b['fecha']); ?>" required></div>
            <div class="campo"><label for="turno">Turno</label><select id="turno" name="turno"><?php foreach (\Model\ReporteDiario::TURNOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $b['turno'] === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></div>
            <div class="campo"><label for="area">Área / frente</label><input type="text" id="area" name="area" maxlength="150" value="<?php echo s($b['area']); ?>" placeholder="Ej: Torre A, nivel 3"></div>
            <div class="campo"><label for="supervisor">Supervisor / residente</label><input type="text" id="supervisor" name="supervisor" maxlength="150" value="<?php echo s($b['supervisor']); ?>" placeholder="Quién firma el parte"></div>
        </div>
        <div class="pd-clima">
            <span class="pd-etiqueta"><i class="fa-solid fa-cloud-sun"></i> Clima del día</span>
            <label>Lluvia (mm) <input type="number" step="0.1" min="0" name="clima_precip_mm" value="<?php echo $b['clima_precip_mm'] !== null && $b['clima_precip_mm'] !== '' ? s($b['clima_precip_mm']) : ''; ?>"></label>
            <label>Viento (km/h) <input type="number" step="0.1" min="0" name="clima_viento_kmh" value="<?php echo $b['clima_viento_kmh'] !== null && $b['clima_viento_kmh'] !== '' ? s($b['clima_viento_kmh']) : ''; ?>"></label>
            <label>T. máx (°C) <input type="number" step="0.1" name="clima_tmax" value="<?php echo $b['clima_tmax'] !== null && $b['clima_tmax'] !== '' ? s($b['clima_tmax']) : ''; ?>"></label>
            <label>T. mín (°C) <input type="number" step="0.1" name="clima_tmin" value="<?php echo $b['clima_tmin'] !== null && $b['clima_tmin'] !== '' ? s($b['clima_tmin']) : ''; ?>"></label>
            <small class="text-muted">Se precarga del clima automático del proyecto; corrígelo si hace falta.</small>
        </div>
        <div class="campo"><label for="visitas">Visitas</label><input type="text" id="visitas" name="visitas" maxlength="500" value="<?php echo s($b['visitas']); ?>" placeholder="Cliente, supervisión externa, proveedores…"></div>
        <div class="campo"><label for="comentarios">Comentarios del día</label><textarea id="comentarios" name="comentarios" rows="4" placeholder="Qué se hizo, qué quedó pendiente, acuerdos…"><?php echo s($b['comentarios']); ?></textarea></div>
    </details>

    <!-- 2. Personal -->
    <details class="pd-seccion" open>
        <summary><span class="pd-num">2</span> Personal y horas <span class="pd-contador" id="pd-c-personal"><?php echo count($vista['personal']); ?></span></summary>
        <div class="pd-filas" id="pd-personal">
            <?php foreach ($vista['personal'] as $x): ?>
                <div class="pd-fila pd-fila-personal">
                    <label class="pd-campo-g"><span>Persona</span><select name="personal_id[]"><?php echo $opcionesPersonal((int)$x['personal_id']); ?></select></label>
                    <label class="pd-campo-s"><span>Actividad</span><select name="personal_actividad[]"><?php echo $opcionesAct((int)$x['actividad_id']); ?></select></label>
                    <label class="pd-campo-xs"><span>Horas</span><input type="number" step="0.25" min="0" max="24" name="personal_horas[]" value="<?php echo s($x['horas_normal']); ?>"></label>
                    <label class="pd-campo-xs"><span>Extra</span><input type="number" step="0.25" min="0" max="24" name="personal_extra[]" value="<?php echo s($x['horas_extra']); ?>"></label>
                    <label class="pd-campo-s"><span>Cuadrilla</span><input type="text" name="personal_cuadrilla[]" maxlength="100" value="<?php echo s($x['cuadrilla']); ?>"></label>
                    <label class="pd-campo-s"><span>Observación</span><input type="text" name="personal_observacion[]" maxlength="500" value="<?php echo s($x['observacion']); ?>"></label>
                    <button type="button" class="pd-quitar" title="Quitar" aria-label="Quitar persona"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-pd-add="personal"><i class="fa-solid fa-plus"></i> Agregar persona</button>
        <template id="tpl-personal">
            <div class="pd-fila pd-fila-personal">
                <label class="pd-campo-g"><span>Persona</span><select name="personal_id[]"><?php echo $opcionesPersonal(); ?></select></label>
                <label class="pd-campo-s"><span>Actividad</span><select name="personal_actividad[]"><?php echo $opcionesAct(); ?></select></label>
                <label class="pd-campo-xs"><span>Horas</span><input type="number" step="0.25" min="0" max="24" name="personal_horas[]" value="8"></label>
                <label class="pd-campo-xs"><span>Extra</span><input type="number" step="0.25" min="0" max="24" name="personal_extra[]" value="0"></label>
                <label class="pd-campo-s"><span>Cuadrilla</span><input type="text" name="personal_cuadrilla[]" maxlength="100"></label>
                <label class="pd-campo-s"><span>Observación</span><input type="text" name="personal_observacion[]" maxlength="500"></label>
                <button type="button" class="pd-quitar" title="Quitar" aria-label="Quitar persona"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </template>
    </details>

    <!-- 3. Equipo -->
    <details class="pd-seccion">
        <summary><span class="pd-num">3</span> Equipo y maquinaria <span class="pd-contador" id="pd-c-equipo"><?php echo count($vista['equipo']); ?></span></summary>
        <div class="pd-filas" id="pd-equipo">
            <?php foreach ($vista['equipo'] as $x): ?>
                <div class="pd-fila pd-fila-equipo">
                    <label class="pd-campo-g"><span>Equipo</span><select name="equipo_id[]"><?php echo $opcionesEquipo((int)$x['equipo_id']); ?></select></label>
                    <label class="pd-campo-s"><span>Actividad</span><select name="equipo_actividad[]"><?php echo $opcionesAct((int)$x['actividad_id']); ?></select></label>
                    <label class="pd-campo-xs"><span>Horas</span><input type="number" step="0.25" min="0" max="24" name="equipo_horas[]" value="<?php echo s($x['horas']); ?>"></label>
                    <label class="pd-campo-s"><span>Operador</span><input type="text" name="equipo_operador[]" maxlength="150" value="<?php echo s($x['operador']); ?>"></label>
                    <button type="button" class="pd-quitar" title="Quitar" aria-label="Quitar equipo"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-pd-add="equipo"><i class="fa-solid fa-plus"></i> Agregar equipo</button>
        <template id="tpl-equipo">
            <div class="pd-fila pd-fila-equipo">
                <label class="pd-campo-g"><span>Equipo</span><select name="equipo_id[]"><?php echo $opcionesEquipo(); ?></select></label>
                <label class="pd-campo-s"><span>Actividad</span><select name="equipo_actividad[]"><?php echo $opcionesAct(); ?></select></label>
                <label class="pd-campo-xs"><span>Horas</span><input type="number" step="0.25" min="0" max="24" name="equipo_horas[]" value="8"></label>
                <label class="pd-campo-s"><span>Operador</span><input type="text" name="equipo_operador[]" maxlength="150"></label>
                <button type="button" class="pd-quitar" title="Quitar" aria-label="Quitar equipo"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </template>
    </details>

    <!-- 4. Materiales -->
    <details class="pd-seccion">
        <summary><span class="pd-num">4</span> Materiales <span class="pd-contador" id="pd-c-material"><?php echo count($vista['materiales']); ?></span></summary>
        <div class="pd-filas" id="pd-materiales">
            <?php foreach ($vista['materiales'] as $x): ?>
                <div class="pd-fila pd-fila-material">
                    <label class="pd-campo-g"><span>Material</span><select name="material_id[]"><?php echo $opcionesMaterial((int)$x['material_id']); ?></select></label>
                    <label class="pd-campo-xs"><span>Cantidad</span><input type="number" step="0.001" min="0" name="material_cantidad[]" value="<?php echo s($x['cantidad']); ?>"></label>
                    <label class="pd-campo-xs"><span>Unidad</span><input type="text" name="material_unidad[]" maxlength="30" value="<?php echo s($x['unidad']); ?>" placeholder="m³, kg…"></label>
                    <label class="pd-campo-s"><span>Movimiento</span><select name="material_tipo[]"><?php foreach (\Model\ReporteMaterial::TIPOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $x['tipo'] === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></label>
                    <label class="pd-campo-s"><span>Actividad</span><select name="material_actividad[]"><?php echo $opcionesAct((int)$x['actividad_id']); ?></select></label>
                    <label class="pd-campo-s"><span>Proveedor</span><input type="text" name="material_proveedor[]" maxlength="150" value="<?php echo s($x['proveedor']); ?>"></label>
                    <label class="pd-campo-xs"><span>Remisión</span><input type="text" name="material_remision[]" maxlength="80" value="<?php echo s($x['remision']); ?>"></label>
                    <button type="button" class="pd-quitar" title="Quitar" aria-label="Quitar material"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-pd-add="material"><i class="fa-solid fa-plus"></i> Agregar material</button>
        <template id="tpl-material">
            <div class="pd-fila pd-fila-material">
                <label class="pd-campo-g"><span>Material</span><select name="material_id[]"><?php echo $opcionesMaterial(); ?></select></label>
                <label class="pd-campo-xs"><span>Cantidad</span><input type="number" step="0.001" min="0" name="material_cantidad[]" value="0"></label>
                <label class="pd-campo-xs"><span>Unidad</span><input type="text" name="material_unidad[]" maxlength="30"></label>
                <label class="pd-campo-s"><span>Movimiento</span><select name="material_tipo[]"><?php foreach (\Model\ReporteMaterial::TIPOS as $k => $t): ?><option value="<?php echo $k; ?>"><?php echo s($t); ?></option><?php endforeach; ?></select></label>
                <label class="pd-campo-s"><span>Actividad</span><select name="material_actividad[]"><?php echo $opcionesAct(); ?></select></label>
                <label class="pd-campo-s"><span>Proveedor</span><input type="text" name="material_proveedor[]" maxlength="150"></label>
                <label class="pd-campo-xs"><span>Remisión</span><input type="text" name="material_remision[]" maxlength="80"></label>
                <button type="button" class="pd-quitar" title="Quitar" aria-label="Quitar material"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </template>
    </details>

    <!-- 5. Actividades y avance -->
    <details class="pd-seccion" open>
        <summary><span class="pd-num">5</span> Actividades trabajadas y avance <span class="pd-contador" id="pd-c-acts"><?php echo count($selActs); ?></span></summary>
        <div class="pd-busca"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="pd-busca-act" placeholder="Buscar actividad o partida…" autocomplete="off"></div>
        <div class="pd-acts" id="pd-acts">
            <?php foreach ($vista['actividades'] as $a): ?>
                <?php
                $bq = $a['bloqueo'] ?? null;
                $topeCal = $bq ? (int)$bq['tope'] : 100;
                $maxCal = max($topeCal, (int)$a['progreso']);
                $congelado = $bq && $bq['nivel'] === 'nc' && (int)$a['progreso'] < 100;
                if ($bq && $topeCal >= 100) $bq = null;
                $editable = $a['sel'] && !$a['hito'] && !$a['subactividades'] && !$congelado;
                ?>
                <label class="pd-act<?php echo $a['sel'] ? ' pd-act-sel' : ''; ?><?php echo $bq ? ' pd-act-bloq' : ''; ?>" data-texto="<?php echo s(mb_strtolower($a['codigo'] . ' ' . $a['nombre'] . ' ' . $a['partida'])); ?>">
                    <input type="checkbox" name="actividades[]" value="<?php echo (int)$a['id']; ?>"<?php echo $a['sel'] ? ' checked' : ''; ?>>
                    <span class="pd-act-color" style="--c:<?php echo s($a['color'] ?: '#64748b'); ?>"></span>
                    <span class="pd-act-txt"><span class="mono"><?php echo s($a['codigo']); ?></span> <?php echo s($a['nombre']); ?><small><?php echo s($a['partida']); ?></small></span>
                    <?php if ($a['hito']): ?><span class="pd-chip pd-chip-min">Hito</span><?php elseif ($a['subactividades']): ?><span class="pd-chip pd-chip-min" title="El avance se calcula de sus subactividades"><i class="fa-solid fa-lock"></i> Subact.</span><?php endif; ?>
                    <?php if ($bq): ?><span class="pd-chip pd-chip-cal" title="<?php echo s($bq['motivo']); ?>"><i class="fa-solid <?php echo $bq['nivel'] === 'liberada' ? 'fa-lock-open' : 'fa-lock'; ?>"></i> Calidad</span><?php endif; ?>
                    <span class="pd-avance"><input type="number" min="0" max="100" step="1" name="avance[<?php echo (int)$a['id']; ?>]" value="<?php echo (int)$a['progreso']; ?>"
                        data-tope="<?php echo $topeCal; ?>"<?php echo $editable ? '' : ' disabled'; ?><?php echo $a['hito'] || $a['subactividades'] ? ' data-fijo="1"' : ''; ?><?php echo $congelado ? ' data-congelado="1"' : ''; ?>
                        title="<?php echo s($bq ? $bq['motivo'] : 'Avance de ' . $a['codigo']); ?>" aria-label="Avance de <?php echo s($a['codigo']); ?>">%</span>
                    <?php if ($bq && !$congelado && $topeCal < 100): ?><small class="pd-tope-aviso" hidden>Se ajusta al tope de calidad (<?php echo $topeCal; ?> %)</small><?php endif; ?>
                </label>
            <?php endforeach; ?>
            <?php if (!$vista['actividades']): ?><p class="text-muted">El proyecto aún no tiene actividades en el programa.</p><?php endif; ?>
        </div>
        <small class="text-muted">El avance se anota con la fecha del parte (Curva S real). Los hitos y las actividades con subactividades se calculan solos.</small>
    </details>

    <!-- 6. Tiempos muertos -->
    <details class="pd-seccion">
        <summary><span class="pd-num">6</span> Tiempos muertos <span class="pd-contador" id="pd-c-tiempos"><?php echo count($vista['tiempos']); ?></span></summary>
        <div class="pd-filas" id="pd-tiempos">
            <?php foreach ($vista['tiempos'] as $x): ?>
                <div class="pd-fila pd-fila-tiempo">
                    <label class="pd-campo-s"><span>Actividad</span><select name="tiempo_actividad[]"><?php echo $opcionesAct((int)$x['actividad_id']); ?></select></label>
                    <label class="pd-campo-xs"><span>Horas</span><input type="number" step="0.25" min="0" max="24" name="tiempo_horas[]" value="<?php echo s($x['horas']); ?>"></label>
                    <label class="pd-campo-s"><span>Causa</span><select name="tiempo_causa[]"><?php foreach (\Model\ReporteTiempo::CAUSAS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $x['causa'] === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></label>
                    <label class="pd-campo-g"><span>Nota</span><input type="text" name="tiempo_nota[]" maxlength="500" value="<?php echo s($x['nota']); ?>"></label>
                    <button type="button" class="pd-quitar" title="Quitar" aria-label="Quitar tiempo"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-pd-add="tiempo"><i class="fa-solid fa-plus"></i> Agregar tiempo muerto</button>
        <template id="tpl-tiempo">
            <div class="pd-fila pd-fila-tiempo">
                <label class="pd-campo-s"><span>Actividad</span><select name="tiempo_actividad[]"><?php echo $opcionesAct(); ?></select></label>
                <label class="pd-campo-xs"><span>Horas</span><input type="number" step="0.25" min="0" max="24" name="tiempo_horas[]" value="1"></label>
                <label class="pd-campo-s"><span>Causa</span><select name="tiempo_causa[]"><?php foreach (\Model\ReporteTiempo::CAUSAS as $k => $t): ?><option value="<?php echo $k; ?>"><?php echo s($t); ?></option><?php endforeach; ?></select></label>
                <label class="pd-campo-g"><span>Nota</span><input type="text" name="tiempo_nota[]" maxlength="500"></label>
                <button type="button" class="pd-quitar" title="Quitar" aria-label="Quitar tiempo"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </template>
    </details>

    <!-- 7. Seguridad -->
    <details class="pd-seccion">
        <summary><span class="pd-num">7</span> Seguridad</summary>
        <div class="form-grid">
            <div class="campo"><label for="charla_tema">Charla de 5 minutos</label><input type="text" id="charla_tema" name="charla_tema" maxlength="255" value="<?php echo s($vista['seguridad']['charla_tema']); ?>" placeholder="Tema de la plática"></div>
            <div class="campo"><label for="charla_asistentes">Asistentes</label><input type="number" id="charla_asistentes" name="charla_asistentes" min="0" max="9999" value="<?php echo (int)$vista['seguridad']['asistentes']; ?>"></div>
            <div class="campo"><label class="pd-check"><input type="checkbox" name="epp_ok" value="1"<?php echo (int)$vista['seguridad']['epp_ok'] ? ' checked' : ''; ?>> EPP completo y condiciones seguras</label></div>
        </div>
        <details class="pd-sub">
            <summary><i class="fa-solid fa-triangle-exclamation"></i> Registrar incidente o casi accidente</summary>
            <div class="form-grid">
                <div class="campo"><label>Tipo</label><select name="incidente_tipo"><?php foreach (\Classes\Seguridad::INCIDENTES as $k => $t): ?><option value="<?php echo $k; ?>"><?php echo s($t); ?></option><?php endforeach; ?></select></div>
                <div class="campo"><label>Severidad</label><select name="incidente_severidad"><?php foreach (['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'] as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $k === 'media' ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></div>
                <div class="campo"><label>Actividad</label><select name="incidente_actividad"><?php echo $opcionesAct(); ?></select></div>
                <div class="campo pd-ancho"><label>Descripción</label><textarea name="incidente_descripcion" rows="2" maxlength="3000" placeholder="Qué pasó y qué se hizo"></textarea></div>
            </div>
        </details>
    </details>

    <!-- 8. Fotos -->
    <details class="pd-seccion">
        <summary><span class="pd-num">8</span> Fotos <span class="pd-contador" id="pd-c-fotos"><?php echo count($vista['fotos']); ?></span></summary>
        <?php if ($vista['fotos']): ?>
            <p class="text-sm text-muted">Fotos del parte: marca las que quieras quitar.</p>
            <div class="pd-fotos">
                <?php foreach ($vista['fotos'] as $f): ?>
                    <label class="pd-foto">
                        <img src="/uploads/reportes/<?php echo s($f['archivo']); ?>" alt="<?php echo s($f['leyenda']); ?>" loading="lazy">
                        <?php if ($f['leyenda']): ?><span class="pd-foto-leyenda"><?php echo s($f['leyenda']); ?></span><?php endif; ?>
                        <span class="pd-foto-quitar"><input type="checkbox" name="quitar_fotos[]" value="<?php echo (int)$f['id']; ?>"> Quitar</span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="campo">
            <label for="fotos">Agregar fotos</label>
            <input type="file" id="fotos" name="fotos[]" accept="image/*" multiple>
            <small class="text-muted">Hasta 30 fotos por guardado (jpg, png, webp o gif; máx. 15 MB cada una). Sin conexión se guardan para después.</small>
        </div>
        <div class="pd-previa" id="pd-previa"></div>
    </details>

    <!-- 9. Firma -->
    <details class="pd-seccion">
        <summary><span class="pd-num">9</span> Firma del residente</summary>
        <div class="form-grid">
            <div class="campo"><label for="firma_nombre">Nombre de quien firma</label><input type="text" id="firma_nombre" name="firma_nombre" maxlength="150" value="<?php echo s($b['firma_nombre']); ?>"></div>
        </div>
        <?php if (!empty($b['firma_imagen'])): ?><p class="text-sm text-muted">Firma guardada:</p><img class="pd-firma-img" src="/uploads/reportes/<?php echo s($b['firma_imagen']); ?>" alt="Firma guardada"><?php endif; ?>
        <div class="pd-firma-caja">
            <canvas id="pd-firma" width="700" height="220"></canvas>
            <button type="button" class="btn btn-ghost btn-sm" id="pd-firma-limpiar">Limpiar firma</button>
        </div>
        <input type="hidden" name="firma_data" id="firma_data" value="">
    </details>

    <!-- Totales y acciones -->
    <div class="pd-pie">
        <div class="pd-costos" aria-live="polite">
            <div><span>Personal</span><b id="pd-costo-personal">$<?php echo number_format($vista['costos']['personal'], 2); ?></b></div>
            <div><span>Equipo</span><b id="pd-costo-equipo">$<?php echo number_format($vista['costos']['equipo'], 2); ?></b></div>
            <div><span>Material</span><b id="pd-costo-material">$<?php echo number_format($vista['costos']['material'], 2); ?></b></div>
            <div class="pd-costo-total"><span>Costo del día</span><b id="pd-costo-total">$<?php echo number_format($vista['costos']['total'], 2); ?></b></div>
            <small class="text-muted">Horas: <span id="pd-horas">0</span> · Tiempos muertos: <span id="pd-horas-muertas">0</span> h</small>
        </div>
        <div class="pd-acciones">
            <button type="submit" class="btn btn-primary" name="accion" value="guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar borrador</button>
            <?php if ($puede_cerrar): ?>
                <button type="submit" class="btn btn-primary" name="accion" value="cerrar" data-pd-cerrar><i class="fa-solid fa-lock"></i> Guardar y cerrar</button>
            <?php endif; ?>
        </div>
    </div>
</form>

<script type="application/json" id="pd-datos"><?php echo json_encode([
    'personal' => array_column($cat['personal'], 'tarifa_hora', 'id'),
    'equipo' => array_column($cat['equipo'], 'tarifa_hora', 'id'),
    'materiales' => array_column($cat['materiales'], 'costo_unitario', 'id'),
], JSON_UNESCAPED_UNICODE); ?></script>
