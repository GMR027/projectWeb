<?php
/** Lista de casillas para elegir varios elementos de un formato: [[valor, etiqueta, marcado], …] */
$casillas = function (string $name, string $titulo, array $items, string $ayuda = '') {
    ob_start(); ?>
    <div class="exp-checks" data-exp-checks>
        <div class="exp-checks-cab"><span class="text-sm text-muted"><?php echo s($titulo); ?></span>
            <label class="text-sm"><input type="checkbox" data-exp-todos> Todos</label></div>
        <div class="exp-checks-lista">
            <?php foreach ($items as [$v, $etq, $marcado]): ?>
                <label><input type="checkbox" name="<?php echo s($name); ?>" value="<?php echo s($v); ?>" data-slug="<?php echo s(slug_archivo($etq)); ?>"<?php echo $marcado ? ' checked' : ''; ?>> <span><?php echo s($etq); ?></span></label>
            <?php endforeach; ?>
        </div>
        <?php if ($ayuda !== ''): ?><p class="text-sm text-muted"><?php echo s($ayuda); ?></p><?php endif; ?>
    </div>
    <?php return ob_get_clean();
};
$partidasExport = isset($proyecto) && $proyecto instanceof \Model\Proyecto ? $proyecto->getPartidas() : [];
?>
<div class="page-header">
    <div>
        <h2>Centro de Exportación</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s($proyecto->codigo); ?></p>
    </div>
    <div>
        <a href="/ajustes?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">⚙ Ajustes</a>
        <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Programa</a>
    </div>
</div>

<?php if (!empty($_GET['error'])): ?><div class="alerta alerta-error"><?php echo s($_GET['error']); ?></div><?php endif; ?>
<div class="card">
    <p class="text-muted text-sm" style="margin-bottom: 1.6rem;">Genera entregables del programa de obra. El nombre del archivo es <strong>prefijo-nombre-CÓDIGO-EMPRESA-sufijo</strong>: eliges abajo el prefijo y el sufijo una sola vez y se aplican a todos los formatos; el nombre es el que escribas en cada recuadro. En las curvas de productividad, el nombre de la curva va antes del nombre (p. ej. Control-Curva).</p>

    <section class="exp-nomen" id="exp-nomen" aria-label="Nombre de los archivos" data-codigo="<?php echo s(slug_archivo((string)$proyecto->codigo)); ?>" data-empresa="<?php echo $proyecto->empresa ? s(slug_archivo((string)$proyecto->empresa)) : ''; ?>" data-proyecto="<?php echo (int)$proyecto->id; ?>">
        <h4><i class="fa-solid fa-signature"></i> Nombre de los archivos</h4>
        <div class="exp-nomen-fila">
            <?php foreach (['pre' => 'Prefijo', 'suf' => 'Sufijo'] as $k => $etq): ?>
            <div class="exp-nomen-pieza" data-pieza="<?php echo $k; ?>">
                <label class="text-sm text-muted" for="nom-<?php echo $k; ?>-tipo"><?php echo $etq; ?></label>
                <select id="nom-<?php echo $k; ?>-tipo" data-tipo>
                    <option value="ninguno">Ninguno</option>
                    <option value="fecha_ymd">Fecha (año-mes-día)</option>
                    <option value="fecha_dmy">Fecha (día-mes-año)</option>
                    <option value="numerador">Numerador (automático)</option>
                    <option value="texto">Texto</option>
                </select>
                <input type="text" data-valor-texto maxlength="60" placeholder="Texto" aria-label="Texto del <?php echo strtolower($etq); ?>" hidden>
                <label class="exp-nomen-num" data-valor-num hidden><span class="text-sm text-muted">Siguiente número</span> <input type="number" min="1" max="99999" step="1" value="1" aria-label="Siguiente número del <?php echo strtolower($etq); ?>"></label>
            </div>
            <?php endforeach; ?>
        </div>
        <label class="exp-nomen-cod text-sm"><input type="checkbox" id="nom-cod" checked> Incluir el código y la empresa del proyecto después del nombre</label>
        <p class="exp-nomen-vista text-sm">Así quedará: <strong class="mono" id="exp-nomen-ejemplo"></strong></p>
    </section>

    <form method="POST" action="/exportar/todo" class="exportar-todo">
        <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
        <input type="hidden" name="elegir" value="1">
        <div>
            <h4><i class="fa-solid fa-box-archive"></i> Exportar todo</h4>
            <p class="text-sm text-muted">Un solo ZIP con los formatos que marques. <strong>No incluye Last Planner.</strong> Lo que no tenga datos se omite y queda anotado en LEEME.txt.</p>
            <div class="exp-checks exp-todo-checks" data-exp-checks>
                <div class="exp-checks-cab"><span class="text-sm text-muted">Formatos a incluir</span><label class="text-sm"><input type="checkbox" data-exp-todos> Todos</label></div>
                <div class="exp-checks-lista">
                    <?php foreach ([
                        'prog_pdf' => 'Programa · PDF', 'prog_xml' => 'Programa · XML (MS Project)', 'prog_pod' => 'Programa · POD (ProjectLibre)',
                        'la_pdf' => 'Lookahead · PDF', 'la_xlsx' => 'Lookahead · Excel', 'msr_pdf' => 'MSR · PDF', 'msr_xlsx' => 'MSR · Excel',
                        'curvas' => 'Curvas de productividad · PDF', 'vplan' => 'vPlan · PDF',
                    ] as $k => $etq): ?>
                        <label><input type="checkbox" name="incluir[]" value="<?php echo $k; ?>" checked> <span><?php echo s($etq); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="exp-todo-accion">
            <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="Exportacion" placeholder="Nombre base" required>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-zipper"></i> Exportar todo</button>
        </div>
    </form>

    <div class="exportadores-grid">
        <div class="exportador-card">
            <div class="exportador-icono pdf">📄</div>
            <h4>Programa de obra</h4>
            <p class="text-sm text-muted">PDF ejecutivo (Gantt + tabla en tabloide 11×17" horizontal), XML MSPDI para MS Project (ábrelo y guárdalo como .mpp) o POD para ProjectLibre.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <label class="text-sm text-muted" for="prog_formato">Formato de archivo</label>
                <select id="prog_formato" name="formato">
                    <option value="pdf">PDF ejecutivo (.pdf)</option>
                    <option value="xml">Microsoft Project (.xml)</option>
                    <option value="pod">ProjectLibre (.pod)</option>
                </select>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="Programa" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-export"></i> Exportar programa</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">🔭</div>
            <h4>Lookahead</h4>
            <p class="text-sm text-muted">Semana actual + las semanas que tengas visibles, con X por día, PTA y limitaciones. Tabloide (11×17") horizontal. En .xlsx / .ods queda editable.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="lookahead">
                <label class="text-sm text-muted" for="salida">Formato de archivo</label>
                <select id="salida" name="salida">
                    <option value="pdf">PDF (.pdf)</option>
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="ods">LibreOffice Calc (.ods)</option>
                </select>
                <label class="text-sm text-muted" for="fecha_ref">Semana de referencia</label>
                <input type="date" id="fecha_ref" name="fecha_ref" value="<?php echo s($lunes->format('Y-m-d')); ?>" required>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="4WLA" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-export"></i> Exportar Lookahead</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">🗺️</div>
            <h4>vPlan · láminas</h4>
            <p class="text-sm text-muted">Plano con tablas del programa, recovery y tablas adicionales. Una lámina por página, con el tamaño de hoja de cada una. «Todas» lleva solo las láminas <strong>visibles</strong>.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="vplan">
                <label class="text-sm text-muted" for="vp_paquete">vPlan</label>
                <select id="vp_paquete" name="paquete_id">
                    <option value="0">Todos los vPlan</option>
                    <?php foreach ($paquetesVplan as $pv): ?><option value="<?php echo s($pv->id); ?>"<?php echo (int)$vplanElegido === (int)$pv->id ? ' selected' : ''; ?>><?php echo s($pv->nombre); ?> (<?php echo (int)$pv->visibles; ?> visible(s))</option><?php endforeach; ?>
                </select>
                <input type="hidden" name="lamina_id" value="todas">
                <?php if (count($laminasVplan) > 1): ?>
                    <?php echo $casillas('lamina_ids[]', 'Láminas', array_map(fn($lv) => [$lv->id, $lv->titulo . ((int)$lv->visible ? '' : ' (oculta)'), false], $laminasVplan), 'Sin marcar ninguna se exportan todas las visibles del vPlan elegido. Con varias marcadas, se entregan juntas en un solo PDF.'); ?>
                <?php elseif ($laminasVplan): ?>
                    <input type="hidden" name="lamina_ids[]" value="<?php echo s($laminasVplan[0]->id); ?>">
                <?php endif; ?>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="vPlan" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar vPlan</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">🗒️</div>
            <h4>Last Planner · post-its</h4>
            <p class="text-sm text-muted">Tarjetas de 7×7 cm por día y partida, en hojas carta con marcas de corte. Se calculan con el programa de obra.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="lastplanner">
                <label class="text-sm text-muted" for="lp_partida">Partida</label>
                <select id="lp_partida" name="partida_id">
                    <option value="juntas">★ Mostrar todas juntas (un post-it por día con todas las partidas)</option>
                    <option value="todas">Todas (cada partida en hoja nueva)</option>
                    <?php foreach ($partidasExport as $pt): ?><option value="<?php echo s($pt->id); ?>"><?php echo s($pt->codigo . ' · ' . $pt->nombre); ?></option><?php endforeach; ?>
                </select>
                <label class="text-sm text-muted" for="lp_filtro">Filtro</label>
                <select id="lp_filtro" name="restriccion">
                    <option value="">Todos los post-it</option>
                    <option value="1">Solo con restricción</option>
                </select>
                <label class="text-sm text-muted" for="lp_periodo">Período</label>
                <select id="lp_periodo" name="periodo">
                    <option value="">Todo el programa</option>
                    <option value="fechas">Entre fechas (usa Desde y Hasta)</option>
                    <option value="mes">Por mes (usa Mes)</option>
                </select>
                <div class="exportador-fechas">
                    <label class="text-sm text-muted">Desde <input type="date" name="desde"></label>
                    <label class="text-sm text-muted">Hasta <input type="date" name="hasta"></label>
                    <label class="text-sm text-muted">Mes <input type="month" name="mes"></label>
                </div>
                <label class="text-sm text-muted" for="lp_cols">Tarjetas por fila</label>
                <select id="lp_cols" name="columnas">
                    <option value="2">2 columnas × 3 filas (6 por hoja, margen cómodo)</option>
                    <option value="3">3 columnas × 3 filas (9 por hoja, margen mínimo)</option>
                </select>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="LastPlanner" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar Last Planner</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">📈</div>
            <h4>Curvas de productividad</h4>
            <p class="text-sm text-muted">Una curva con su tabla y gráfica, o la gráfica maestra con todas. PDF carta horizontal; .xlsx / .ods con tabla y gráfica.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="curva">
                <?php if ($curvas): ?>
                    <?php echo $casillas('curva_ids[]', 'Curvas', array_merge([['maestra', 'Gráfica maestra (todas)', true]], array_map(fn($cv) => [$cv->id, $cv->nombre, false], $curvas)), 'Con varias marcadas se entregan en un ZIP.'); ?>
                <?php else: ?>
                    <input type="hidden" name="curva_ids[]" value="maestra">
                    <p class="text-sm text-muted">Gráfica maestra (aún no hay curvas individuales).</p>
                <?php endif; ?>
                <label class="text-sm text-muted" for="salida-curva">Formato de archivo</label>
                <select id="salida-curva" name="salida">
                    <option value="pdf">PDF (.pdf)</option>
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="ods">LibreOffice Calc (.ods)</option>
                </select>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="Curva" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-export"></i> Exportar curva</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">🚚</div>
            <h4>MSR · Materiales</h4>
            <p class="text-sm text-muted">Reporte del status de submittals y entrega de materiales con fotos. PDF en A4 horizontal; .xlsx / .ods editables.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="msr">
                <?php $msrs = \Model\Msr::deProyecto((int)$proyecto->id); ?>
                <?php if (count($msrs) > 1): ?>
                    <?php $primero = true; echo $casillas('msr_ids[]', 'MSR', array_map(function ($mx) use (&$primero) { $m = [$mx->id, $mx->nombre, $primero]; $primero = false; return $m; }, $msrs), 'Con varios marcados se entregan en un ZIP.'); ?>
                <?php elseif ($msrs): ?>
                    <input type="hidden" name="msr_ids[]" value="<?php echo s($msrs[0]->id); ?>">
                    <p class="text-sm text-muted"><?php echo s($msrs[0]->nombre); ?></p>
                <?php else: ?>
                    <p class="text-sm text-muted">Aún no hay MSR.</p>
                <?php endif; ?>
                <label class="text-sm text-muted" for="salida-msr">Formato de archivo</label>
                <select id="salida-msr" name="salida">
                    <option value="pdf">PDF (.pdf)</option>
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="ods">LibreOffice Calc (.ods)</option>
                </select>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="MSR" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-export"></i> Exportar MSR</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">📆</div>
            <h4>Línea de tiempo activa</h4>
            <p class="text-sm text-muted">Gantt diario agrupado por partida con los filtros elegidos, igual que la vista de la Línea de tiempo. PDF en una sola hoja del tamaño que haga falta, o .ods editable.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="lineatiempo">
                <?php echo $casillas('estado[]', 'Actividades', [
                    ['no_iniciada', 'No iniciadas', true],
                    ['en_proceso', 'En proceso', true],
                    ['completada', 'Completadas', true],
                ], 'Se muestran las de cualquier estado marcado.'); ?>
                <label class="text-sm"><input type="checkbox" name="restriccion" value="1"> Solo con restricción</label>
                <label class="text-sm"><input type="checkbox" name="icono" value="1"> Icono de actividad (disco sobre cada barra)</label>
                <label class="text-sm text-muted" for="lt_partida">Partida</label>
                <select id="lt_partida" name="partida_id">
                    <option value="">Todas</option>
                    <?php foreach ($partidasExport as $pt): ?><option value="<?php echo s($pt->id); ?>"><?php echo s($pt->codigo . ' · ' . $pt->nombre); ?></option><?php endforeach; ?>
                </select>
                <?php if ($mesesLt): ?>
                    <?php echo $casillas('mes[]', 'Meses', array_map(fn($m, $t) => [$m, $t, false], array_keys($mesesLt), array_values($mesesLt)), 'Sin marcar ninguno se exporta el rango completo del proyecto; con uno o varios marcados, un archivo por mes (varios = ZIP).'); ?>
                <?php endif; ?>
                <label class="text-sm text-muted" for="lt_salida">Formato de archivo</label>
                <select id="lt_salida" name="salida">
                    <option value="pdf">PDF (.pdf)</option>
                    <option value="ods">LibreOffice Calc (.ods)</option>
                </select>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="Linea-de-tiempo" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-export"></i> Exportar línea de tiempo</button>
            </form>
        </div>

        <?php /* Reportes de obra en PDF */ ?>
        <div class="exportador-card">
            <div class="exportador-icono pdf">📋</div>
            <h4>Reporte diario</h4>
            <p class="text-sm text-muted">Un día (o todo un mes, un reporte por página) con actividades, personal, comentarios y fotos. Se aplica el primero que llenes: día o mes.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="reportes">
                <input type="hidden" name="tipo" value="reporte">
                <label class="text-sm text-muted" for="rd_fecha">Día</label>
                <input type="date" id="rd_fecha" name="fecha">
                <label class="text-sm text-muted" for="rd_mes">Mes</label>
                <input type="month" id="rd_mes" name="mes">
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="Reporte-diario" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar reporte diario</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">📓</div>
            <h4>Bitácora</h4>
            <p class="text-sm text-muted">Registros por día, mes, departamento y/o nivel de importancia. Los filtros se combinan; los vacíos no filtran.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="reportes">
                <input type="hidden" name="tipo" value="bitacora">
                <label class="text-sm text-muted" for="bi_fecha">Día</label>
                <input type="date" id="bi_fecha" name="fecha">
                <label class="text-sm text-muted" for="bi_mes">Mes</label>
                <input type="month" id="bi_mes" name="mes">
                <label class="text-sm text-muted" for="bi_dep">Departamento</label>
                <select id="bi_dep" name="departamento"><option value="">Todos</option><?php foreach ($departamentosBitacora as $dp): ?><option value="<?php echo s($dp); ?>"><?php echo s($dp); ?></option><?php endforeach; ?></select>
                <label class="text-sm text-muted" for="bi_imp">Importancia</label>
                <select id="bi_imp" name="importancia"><option value="">Todas</option><?php foreach (\Model\Bitacora::IMPORTANCIAS as $k => $v): ?><option value="<?php echo s($k); ?>"><?php echo s($v); ?></option><?php endforeach; ?></select>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="Bitacora" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar bitácora</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">🤝</div>
            <h4>Minutas</h4>
            <p class="text-sm text-muted">Minutas completas (asistentes, temas y compromisos), una por página, por importancia, día o mes.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="reportes">
                <input type="hidden" name="tipo" value="minutas">
                <label class="text-sm text-muted" for="mi_fecha">Día</label>
                <input type="date" id="mi_fecha" name="fecha">
                <label class="text-sm text-muted" for="mi_mes">Mes</label>
                <input type="month" id="mi_mes" name="mes">
                <label class="text-sm text-muted" for="mi_imp">Importancia</label>
                <select id="mi_imp" name="importancia"><option value="">Todas</option><?php foreach (\Model\Bitacora::IMPORTANCIAS as $k => $v): ?><option value="<?php echo s($k); ?>"><?php echo s($v); ?></option><?php endforeach; ?></select>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="Minutas" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar minutas</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">📦</div>
            <h4>Submittals</h4>
            <p class="text-sm text-muted">Uno o varios paquetes de submittal con sus materiales, uno por página. Si hay varios puedes marcar cuáles; sin marcar ninguno se exportan todos.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="reportes">
                <input type="hidden" name="tipo" value="submittals">
                <?php if (count($submittals) > 1): ?>
                    <?php echo $casillas('submittal_ids[]', 'Submittals', array_map(fn($sb) => [$sb->id, $sb->numero_submittal . ($sb->especialidad ? ' · ' . $sb->especialidad : ''), false], $submittals), 'Sin marcar ninguno se exportan todos.'); ?>
                <?php elseif (!$submittals): ?>
                    <p class="text-sm text-muted">Aún no hay submittals.</p>
                <?php endif; ?>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="Submittals" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar submittals</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">❓</div>
            <h4>RFI · Solicitudes de información</h4>
            <p class="text-sm text-muted">Uno o varios RFI con su pregunta, respuesta y las láminas del Editor adjuntas. Con varios marcados se entregan en un ZIP.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="rfi">
                <?php if ($rfis): ?>
                    <?php echo $casillas('rfi_ids[]', 'RFI', array_map(fn($rf) => [$rf['id'], $rf['numero'] . ' · ' . $rf['asunto'], false], $rfis), 'Marca al menos uno.'); ?>
                <?php else: ?>
                    <p class="text-sm text-muted">Aún no hay RFI.</p>
                <?php endif; ?>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="RFI" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"<?php echo $rfis ? '' : ' disabled'; ?>><i class="fa-solid fa-file-pdf"></i> Exportar RFI</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">🧱</div>
            <h4>Materiales</h4>
            <p class="text-sm text-muted">Listado de materiales con foto, por especialidad, estatus de compra y/o estado de entrega.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="reportes">
                <input type="hidden" name="tipo" value="materiales">
                <label class="text-sm text-muted" for="ma_esp">Especialidad</label>
                <select id="ma_esp" name="especialidad"><option value="">Todas</option><?php foreach ($especialidades as $e): ?><option value="<?php echo s($e); ?>"><?php echo s($e); ?></option><?php endforeach; ?></select>
                <label class="text-sm text-muted" for="ma_compra">Estatus de compra</label>
                <select id="ma_compra" name="compra"><option value="">Todos</option><?php foreach (\Model\Material::COMPRA as $k => $v): ?><option value="<?php echo s($k); ?>"><?php echo s($v); ?></option><?php endforeach; ?></select>
                <label class="text-sm text-muted" for="ma_entrega">Entrega</label>
                <select id="ma_entrega" name="entrega"><option value="">Todos</option><option value="entregado">Entregados</option><option value="por_entregar">Por entregar</option></select>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="Materiales" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar materiales</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">📷</div>
            <h4>Evidencias</h4>
            <p class="text-sm text-muted">Fotos de reportes diarios y evidencias subidas, agrupadas por fecha. Se aplica el primero que llenes: día, semana o mes.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="reportes">
                <input type="hidden" name="tipo" value="evidencias">
                <label class="text-sm text-muted" for="ev_fecha">Día</label>
                <input type="date" id="ev_fecha" name="fecha">
                <label class="text-sm text-muted" for="ev_semana">Semana (cualquier día de ella)</label>
                <input type="date" id="ev_semana" name="semana">
                <label class="text-sm text-muted" for="ev_mes">Mes</label>
                <input type="month" id="ev_mes" name="mes">
                <label class="text-sm text-muted" for="ev_act">Actividad</label>
                <select id="ev_act" name="actividad_id"><option value="">Todas</option><?php foreach ($actividadesEvidencia as [$aid, $anom]): ?><option value="<?php echo s($aid); ?>"><?php echo s($anom); ?></option><?php endforeach; ?></select>
                <input aria-label="Nombre del archivo a exportar" type="text" name="nombre_base" value="Evidencias" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar evidencias</button>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const panel = document.getElementById('exp-nomen');
    if (!panel) return;
    const clave = 'exp-nomen-' + panel.dataset.proyecto;
    const slug = (t) => (String(t).normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-+|-+$/g, '')) || 'sin-nombre';
    const piezas = {};
    panel.querySelectorAll('[data-pieza]').forEach((el) => {
        piezas[el.dataset.pieza] = { tipo: el.querySelector('[data-tipo]'), texto: el.querySelector('[data-valor-texto]'), numBox: el.querySelector('[data-valor-num]'), num: el.querySelector('[data-valor-num] input') };
    });
    const cod = document.getElementById('nom-cod');
    const memoria = { get() { try { return JSON.parse(localStorage.getItem(clave) || 'null'); } catch (_) { return null; } }, set(v) { try { localStorage.setItem(clave, JSON.stringify(v)); } catch (_) {} } };

    const leer = () => ({
        pre: { tipo: piezas.pre.tipo.value, texto: piezas.pre.texto.value, num: piezas.pre.num.value },
        suf: { tipo: piezas.suf.tipo.value, texto: piezas.suf.texto.value, num: piezas.suf.num.value },
        cod: cod.checked,
    });
    const aplicar = (c) => {
        if (!c) return;
        ['pre', 'suf'].forEach((k) => { if (!c[k]) return; piezas[k].tipo.value = c[k].tipo || 'ninguno'; piezas[k].texto.value = c[k].texto || ''; piezas[k].num.value = c[k].num || 1; });
        cod.checked = c.cod !== false;
    };
    const pad = (n) => String(Math.max(1, parseInt(n, 10) || 1)).padStart(3, '0');
    const fecha = (dmy) => { const d = new Date(), p = (n) => String(n).padStart(2, '0'); return dmy ? p(d.getDate()) + '-' + p(d.getMonth() + 1) + '-' + d.getFullYear() : d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()); };
    const pieza = (p) => ({ fecha_ymd: () => fecha(false), fecha_dmy: () => fecha(true), numerador: () => pad(p.num), texto: () => (p.texto.trim() ? slug(p.texto) : '') }[p.tipo] || (() => ''))();
    const nombre = (base, ext, curva) => {
        const c = leer();
        const partes = [pieza(c.pre), curva, base.trim() ? slug(base) : '', c.cod ? panel.dataset.codigo : '', c.cod ? panel.dataset.empresa : '', pieza(c.suf)].filter(Boolean);
        return partes.join('-') + '.' + ext;
    };

    const extension = (f) => {
        if (f.classList.contains('exportar-todo')) return 'zip';
        const marcados = [...f.querySelectorAll('[data-exp-checks] input[type=checkbox][name]:checked')];
        const sel = f.querySelector('select[name=salida]') || f.querySelector('select[name=formato]');
        // línea de tiempo: varios meses marcados = un archivo por mes en un ZIP
        if ([...f.querySelectorAll('input[name="mes[]"]:checked')].length > 1) return 'zip';
        if (marcados.length > 1 && /curva_ids|msr_ids|lamina_ids/.test(marcados[0].name)) return 'zip';
        return sel ? sel.value : 'pdf';
    };
    const prefijoCurva = (f) => {
        const m = f.querySelectorAll('input[name="curva_ids[]"]:checked');
        return m.length === 1 && m[0].value !== 'maestra' ? m[0].dataset.slug : '';
    };

    const formularios = [...document.querySelectorAll('form[action="/exportar/generar"], form[action="/exportar/todo"]')];
    const vistas = formularios.map((f) => {
        const campo = f.querySelector('input[name=nombre_base]');
        const v = document.createElement('small'); v.className = 'exp-vista text-muted mono';
        campo.insertAdjacentElement('afterend', v);
        return () => { v.textContent = nombre(campo.value, extension(f), prefijoCurva(f)); };
    });
    const ejemplo = document.getElementById('exp-nomen-ejemplo');
    const refrescar = () => {
        const c = leer();
        ['pre', 'suf'].forEach((k) => { piezas[k].texto.hidden = c[k].tipo !== 'texto'; piezas[k].numBox.hidden = c[k].tipo !== 'numerador'; });
        ejemplo.textContent = nombre('Programa', 'pdf', '');
        vistas.forEach((fn) => fn());
        memoria.set(c);
    };
    aplicar(memoria.get() || { pre: { tipo: 'fecha_ymd' }, suf: { tipo: 'ninguno' }, cod: true });
    panel.addEventListener('input', refrescar);
    panel.addEventListener('change', refrescar);
    formularios.forEach((f) => { f.addEventListener('input', refrescar); f.addEventListener('change', refrescar); });

    // casillas «Todos»
    document.querySelectorAll('[data-exp-checks]').forEach((g) => {
        const todos = g.querySelector('[data-exp-todos]');
        const items = () => [...g.querySelectorAll('.exp-checks-lista input[type=checkbox]')];
        const estado = () => { const it = items(); todos.checked = it.length > 0 && it.every((i) => i.checked); todos.indeterminate = !todos.checked && it.some((i) => i.checked); };
        todos.addEventListener('change', () => { items().forEach((i) => { i.checked = todos.checked; }); refrescar(); });
        g.addEventListener('change', (e) => { if (e.target !== todos) estado(); });
        estado();
    });

    // al enviar: la nomenclatura viaja con el formulario; el numerador sube un número después de exportar
    formularios.forEach((f) => f.addEventListener('submit', (e) => {
        // en «Exportar todo» debe haber al menos un formato marcado
        if (f.classList.contains('exportar-todo') && !f.querySelector('input[name="incluir[]"]:checked')) { e.preventDefault(); window.avisoModal('Exportar todo', 'Marca al menos un formato para exportar.'); return; }
        f.querySelectorAll('.nom-hid').forEach((x) => x.remove());
        const c = leer();
        const pone = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.className = 'nom-hid'; i.name = n; i.value = v; f.appendChild(i); };
        pone('nom_pre_tipo', c.pre.tipo); pone('nom_pre_valor', c.pre.tipo === 'numerador' ? c.pre.num : c.pre.texto);
        pone('nom_suf_tipo', c.suf.tipo); pone('nom_suf_valor', c.suf.tipo === 'numerador' ? c.suf.num : c.suf.texto);
        if (c.cod) pone('nom_cod', '1');
        setTimeout(() => {   // cada exportación usa el siguiente número
            ['pre', 'suf'].forEach((k) => { if (piezas[k].tipo.value === 'numerador') piezas[k].num.value = (parseInt(piezas[k].num.value, 10) || 1) + 1; });
            refrescar();
        }, 600);
    }));
    refrescar();
})();
</script>
