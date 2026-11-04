<?php
// Editor en línea de un hallazgo. Lo incluyen views/inconsistencias/index.php y views/analisis-materiales/index.php.
// Variables: $x (hallazgo enriquecido), $herramienta ('inconsistencias'|'analisis-materiales'), $pid,
//            $materiales (catálogo del proyecto, solo inconsistencias), $actividadesProyecto y $submittalsProyecto (solo materiales)
$esMat = $herramienta === 'analisis-materiales';
$ctx = $x['contexto'] ?? [];
// Se puede corregir cualquier actividad del proyecto desde cualquier aviso; cuando la actividad
// implicada viene en el contexto, sus datos precargan los campos del editor.
$actsTodas = [];
if (!$esMat) foreach ($ctx as $a) $actsTodas[(int)$a['id']] = $a;   // primero las actividades del hallazgo (se preseleccionan)
foreach (($actividadesProyecto ?? []) as $a) if (!isset($actsTodas[(int)$a['id']])) $actsTodas[(int)$a['id']] = $a;
$actsTodas = array_values($actsTodas ?: ($esMat ? [] : $ctx));
$sol = $x['solucion'];
$actual = (string)($sol['accion'] ?? 'nota');
$acciones = $esMat
    ? ['editar_material' => 'Editar los datos del material', 'renombrar_material' => 'Corregir el nombre (base + marca + modelo + extra)', 'marcar_comprado' => 'Marcar como comprado',
        'vincular_actividad' => 'Vincular una actividad que lo usa', 'desvincular_actividad' => 'Quitar una actividad vinculada', 'vincular_submittal' => 'Agregar a un submittal',
        'desvincular_submittal' => 'Quitar de un submittal', 'sincronizar_msr_material' => 'Copiar sus fechas al MSR', 'nota' => 'Solo marcarla como atendida']
    : ['ajustar_fechas' => 'Corregir fechas', 'ajustar_progreso' => 'Corregir avance', 'fijar_status' => 'Corregir estatus', 'sincronizar_status' => 'Sincronizar estatus con el avance',
        'ajustar_duracion' => 'Corregir duración', 'renombrar_actividad' => 'Renombrar la actividad', 'editar_descripcion' => 'Editar la descripción',
        'vincular_material' => 'Asignar un material', 'desvincular_material' => 'Quitar un material', 'marcar_sin_material' => 'Marcar si requiere material',
        'resolver_restriccion' => 'Resolver una restricción', 'recalcular_partida' => 'Recalcular las fechas de la partida', 'agregar_dia_no_habil' => 'Marcar un día no hábil',
        'quitar_dependencia' => 'Quitar una relación', 'nota' => 'Solo marcarla como atendida'];
if (!$esMat && !empty($sol['params']['material_id'])) $acciones['marcar_comprado'] = 'Marcar el material como comprado';
if (!isset($acciones[$actual])) $actual = 'nota';

$jm = fn($v) => htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

/** Selector de actividad con su contexto en data-json (para precargar los campos) */
$selAct = function (string $name = 'actividad_id', array $solo = []) use ($ctx, $jm) {
    $o = '<select name="' . $name . '" class="ed-act" required>';
    foreach ($ctx as $a) {
        if ($solo && !in_array((int)$a['id'], $solo, true)) continue;
        $o .= '<option value="' . (int)$a['id'] . '" data-json="' . $jm($a) . '">' . s(trim($a['codigo'] . ' · ' . $a['nombre'])) . '</option>';
    }
    return $o . '</select>';
};
/** Selector con TODAS las actividades del proyecto (vincular materiales, marcar sin material) */
$selActTodas = function (string $name = 'actividad_id') use ($actsTodas, $jm) {
    $o = '<select name="' . $name . '" class="ed-act" required>';
    foreach ($actsTodas as $a) $o .= '<option value="' . (int)$a['id'] . '" data-json="' . $jm($a) . '">' . s(trim($a['codigo'] . ' · ' . $a['nombre'])) . '</option>';
    return $o . '</select>';
};
/** Selector de material con sus datos en data-json */
$selMat = function (string $name = 'material_id', array $lista = [], bool $req = true) use ($jm) {
    $o = '<select name="' . $name . '" class="ed-mat"' . ($req ? ' required' : '') . '>';
    foreach ($lista as $m) $o .= '<option value="' . (int)$m['id'] . '" data-json="' . $jm($m) . '">' . s($m['nombre'] ?? '') . '</option>';
    return $o . '</select>';
};
/** Lista de restricciones activas con botón para resolverlas en el acto */
$restricciones = [];
foreach ($ctx as $a) foreach (($a['restricciones'] ?? []) as $r) $restricciones[] = ['actividad' => $a, 'restriccion' => $r];
$materialesCtx = $esMat ? $ctx : ($materiales ?? []);
?>
<details class="inc-editor">
    <summary><i class="fa-solid fa-pen-to-square"></i> <?php echo $esMat ? 'Corregir el material aquí' : 'Corregir aquí'; ?></summary>
    <form class="inc-form" data-id="<?php echo (int)$x['id']; ?>" data-url="/<?php echo $herramienta; ?>/corregir">
        <label class="inc-campo inc-campo-accion">Qué quieres hacer
            <select name="accion" class="ed-accion">
                <?php foreach ($acciones as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $k === $actual ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?>
            </select>
        </label>

        <?php if (!$esMat): ?>
        <div class="ed-panel" data-panel="ajustar_fechas">
            <label class="inc-campo">Actividad <?php echo $selActTodas(); ?></label>
            <label class="inc-campo">Inicio <input type="date" name="inicio" class="ed-inicio" required></label>
            <label class="inc-campo">Fin <input type="date" name="fin" class="ed-fin" required></label>
            <p class="inc-nota">Si no sabes el fin, escribe solo el inicio y pulsa «Calcular fin por duración».</p>
            <button type="button" class="btn btn-xs btn-ghost ed-calcular"><i class="fa-solid fa-calculator"></i> Calcular fin por duración</button>
            <button type="button" class="btn btn-xs btn-ghost ed-impacto"><i class="fa-solid fa-diagram-project"></i> Ver impacto</button>
            <p class="inc-nota ed-impacto-msg" aria-live="polite"></p>
            <label class="inc-check"><input type="checkbox" name="confirmar_historico" value="1"> La actividad ya está avanzada o terminada: confirmo corregir su histórico</label>
            <label class="inc-check"><input type="checkbox" name="permitir_pasado" value="1"> Permitir una fecha pasada (corrección histórica)</label>
        </div>
        <div class="ed-panel" data-panel="ajustar_progreso">
            <label class="inc-campo">Actividad <?php echo $selActTodas(); ?></label>
            <label class="inc-campo">Avance (%) <input type="number" name="progreso" min="0" max="100" step="1" class="ed-progreso" required></label>
            <label class="inc-check"><input type="checkbox" name="confirmar_compuerta" value="1"> Omitir la compuerta de calidad (solo si el admin lo decide)</label>
            <div class="inc-bloqueo"></div>
        </div>
        <div class="ed-panel" data-panel="fijar_status">
            <label class="inc-campo">Actividad <?php echo $selActTodas(); ?></label>
            <label class="inc-campo">Estatus <select name="status" class="ed-status"><option value="no_iniciada">No iniciada</option><option value="en_proceso">En proceso</option><option value="completada">Completada</option></select></label>
            <div class="inc-bloqueo"></div>
        </div>
        <div class="ed-panel" data-panel="sincronizar_status">
            <label class="inc-campo">Actividad <?php echo $selActTodas(); ?></label>
            <p class="inc-nota">Se pondrá el estatus que corresponde al avance actual (0 % = no iniciada, 100 % = completada).</p>
        </div>
        <div class="ed-panel" data-panel="ajustar_duracion">
            <label class="inc-campo">Actividad <?php echo $selActTodas(); ?></label>
            <label class="inc-campo">Duración (días hábiles) <input type="number" name="dur" min="1" max="3650" class="ed-dur" required></label>
            <p class="inc-nota">La fecha de fin se recalcula con el calendario del proyecto.</p>
            <label class="inc-check"><input type="checkbox" name="confirmar_historico" value="1"> La actividad ya está avanzada o terminada: confirmo corregir su histórico</label>
        </div>
        <div class="ed-panel" data-panel="renombrar_actividad">
            <label class="inc-campo">Actividad <?php echo $selActTodas(); ?></label>
            <label class="inc-campo inc-ancho">Nuevo nombre <input type="text" name="nombre" maxlength="200" class="ed-nombre" required></label>
        </div>
        <div class="ed-panel" data-panel="editar_descripcion">
            <label class="inc-campo">Actividad <?php echo $selActTodas(); ?></label>
            <label class="inc-campo inc-ancho">Descripción <textarea name="descripcion" rows="3" maxlength="2000" class="ed-descripcion"></textarea></label>
        </div>
        <div class="ed-panel" data-panel="vincular_material">
            <label class="inc-campo">Actividad <?php echo $selActTodas(); ?></label>
            <label class="inc-campo">Material <?php echo $selMat('material_id', $materialesCtx); ?></label>
            <label class="inc-campo">Uso <select name="rol"><option value="instala">Instala</option><option value="consume">Consume</option></select></label>
        </div>
        <div class="ed-panel" data-panel="desvincular_material">
            <label class="inc-campo">Actividad <?php echo $selActTodas(); ?></label>
            <label class="inc-campo">Material vinculado <?php echo $selMat('material_id', $materialesCtx); ?></label>
            <p class="inc-nota">Solo se pueden quitar materiales que ya estén vinculados; si no aparece la lista, la actividad no tiene materiales.</p>
        </div>
        <div class="ed-panel" data-panel="marcar_sin_material">
            <label class="inc-campo">Actividad <?php echo $selActTodas(); ?></label>
            <label class="inc-campo">¿Requiere material? <select name="sin_material"><option value="0">Sí, requiere material</option><option value="1">No requiere (marcar «sin material»)</option></select></label>
        </div>
        <div class="ed-panel" data-panel="recalcular_partida">
            <?php $pa = $ctx[0]['partida'] ?? ''; ?>
            <p class="inc-nota">Se recalcularán las fechas de la partida «<?php echo s($pa); ?>» a partir de sus actividades.</p>
            <input type="hidden" name="partida_id" value="<?php echo (int)($ctx[0]['partida_id'] ?? 0); ?>">
        </div>
        <div class="ed-panel" data-panel="agregar_dia_no_habil">
            <label class="inc-campo">Fecha <input type="date" name="fecha" required></label>
            <label class="inc-campo inc-ancho">Motivo <input type="text" name="motivo" maxlength="190" value="Día no hábil"></label>
        </div>
        <div class="ed-panel" data-panel="quitar_dependencia">
            <label class="inc-campo">Predecesora <select name="pred" required><?php foreach ($ctx as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo s($a['codigo'] . ' · ' . $a['nombre']); ?></option><?php endforeach; ?></select></label>
            <label class="inc-campo">Sucesora <select name="suc" required><?php foreach ($ctx as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo s($a['codigo'] . ' · ' . $a['nombre']); ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="ed-panel" data-panel="marcar_comprado">
            <label class="inc-campo">Material <?php echo $selMat('material_id', $materialesCtx); ?></label>
            <p class="inc-nota">Se marcará el material como comprado (con la fecha de hoy si no tenía fecha real).</p>
        </div>
        <?php else: ?>
        <div class="ed-panel" data-panel="editar_material">
            <label class="inc-campo">Material <?php echo $selMat('material_id', $materialesCtx); ?></label>
            <label class="inc-campo">Nombre base <input type="text" name="nombre_base" maxlength="200" class="ed-nombre_base" required></label>
            <label class="inc-campo">Marca <input type="text" name="marca" maxlength="100" class="ed-marca"></label>
            <label class="inc-campo">Modelo <input type="text" name="modelo" maxlength="100" class="ed-modelo"></label>
            <label class="inc-campo">Extra <input type="text" name="valor_extra" maxlength="150" class="ed-valor_extra"></label>
            <label class="inc-campo">Especialidad <input type="text" name="especialidad" maxlength="100" class="ed-especialidad"></label>
            <label class="inc-campo">Cantidad <input type="number" name="cantidad" min="0" step="any" class="ed-cantidad"></label>
            <label class="inc-campo">Unidad <input type="text" name="unidad" maxlength="30" class="ed-unidad"></label>
            <label class="inc-campo">Referencia <input type="text" name="referencia" maxlength="100" class="ed-referencia"></label>
            <label class="inc-campo">Compra <select name="compra" class="ed-compra"><option value="pendiente">Pendiente</option><option value="comprado">Comprado</option></select></label>
            <label class="inc-campo">Compra programada <input type="date" name="fecha_compra_prog" class="ed-fecha_compra_prog"></label>
            <label class="inc-campo">Compra real <input type="date" name="fecha_compra_real" class="ed-fecha_compra_real"></label>
            <label class="inc-campo">Entrega programada <input type="date" name="fecha_entrega_prog" class="ed-fecha_entrega_prog"></label>
            <label class="inc-campo">Entrega real <input type="date" name="fecha_entrega_real" class="ed-fecha_entrega_real"></label>
            <label class="inc-campo inc-ancho">Link web <input type="url" name="link_web" maxlength="255" class="ed-link_web"></label>
        </div>
        <div class="ed-panel" data-panel="renombrar_material">
            <label class="inc-campo">Material <?php echo $selMat('material_id', $materialesCtx); ?></label>
            <label class="inc-campo">Nombre base <input type="text" name="nombre_base" maxlength="200" class="ed-nombre_base" required></label>
            <label class="inc-campo">Marca <input type="text" name="marca" maxlength="100" class="ed-marca"></label>
            <label class="inc-campo">Modelo <input type="text" name="modelo" maxlength="100" class="ed-modelo"></label>
            <label class="inc-campo">Extra <input type="text" name="valor_extra" maxlength="150" class="ed-valor_extra"></label>
        </div>
        <div class="ed-panel" data-panel="marcar_comprado">
            <label class="inc-campo">Material <?php echo $selMat('material_id', $materialesCtx); ?></label>
            <p class="inc-nota">Se pondrá como comprado con la fecha de hoy si no tenía fecha real.</p>
        </div>
        <div class="ed-panel" data-panel="vincular_actividad">
            <label class="inc-campo">Material <?php echo $selMat('material_id', $materialesCtx); ?></label>
            <label class="inc-campo">Actividad <select name="actividad_id" required><?php foreach (($actividadesProyecto ?? []) as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo s(trim($a['codigo'] . ' · ' . $a['nombre'])); ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="ed-panel" data-panel="desvincular_actividad">
            <label class="inc-campo">Material <?php echo $selMat('material_id', $materialesCtx); ?></label>
            <label class="inc-campo">Actividad vinculada <select name="actividad_id" required><?php foreach (($actividadesProyecto ?? []) as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo s(trim($a['codigo'] . ' · ' . $a['nombre'])); ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="ed-panel" data-panel="vincular_submittal">
            <label class="inc-campo">Material <?php echo $selMat('material_id', $materialesCtx); ?></label>
            <label class="inc-campo">Submittal <select name="submittal_id" required><?php foreach (($submittalsProyecto ?? []) as $su): ?><option value="<?php echo (int)$su['id']; ?>"><?php echo s($su['numero']); ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="ed-panel" data-panel="desvincular_submittal">
            <label class="inc-campo">Material <?php echo $selMat('material_id', $materialesCtx); ?></label>
            <label class="inc-campo">Submittal <select name="submittal_id" required><?php foreach (($submittalsProyecto ?? []) as $su): ?><option value="<?php echo (int)$su['id']; ?>"><?php echo s($su['numero']); ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="ed-panel" data-panel="sincronizar_msr_material">
            <p class="inc-nota">Copia las fechas de compra y entrega del material a todos sus renglones del MSR.</p>
            <input type="hidden" name="material_id" value="<?php echo (int)($ctx[0]['id'] ?? 0); ?>">
        </div>
        <?php endif; ?>

        <div class="ed-panel" data-panel="nota"><p class="inc-nota">No se cambia ningún dato: solo se marca el hallazgo como atendido.</p></div>

        <?php if ($restricciones): ?>
        <div class="ed-restricciones">
            <strong>Restricciones activas:</strong>
            <?php foreach ($restricciones as $r): ?>
                <button type="button" class="btn btn-xs btn-ghost" data-resolver="<?php echo (int)$r['restriccion']['id']; ?>" data-actividad="<?php echo (int)$r['actividad']['id']; ?>" title="Resolver esta restricción">
                    <i class="fa-solid fa-lock-open"></i> <?php echo s($r['actividad']['codigo'] . ': ' . mb_strimwidth((string)$r['restriccion']['descripcion'], 0, 70, '…')); ?>
                </button>
            <?php endforeach; ?>
            <span class="inc-msg ed-msg-restriccion" aria-live="polite"></span>
        </div>
        <?php endif; ?>

        <div class="inc-botones">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-check"></i> Aplicar corrección</button>
            <span class="inc-msg ed-msg" aria-live="polite"></span>
        </div>
    </form>
</details>
