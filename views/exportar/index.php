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
    <p class="text-muted text-sm" style="margin-bottom: 2rem;">Genera entregables del programa de obra. El archivo se nombra automáticamente <strong>año-mes-día-nombre-CÓDIGO-EMPRESA</strong> (p. ej. <?php echo s(date('Y-m-d') . '-Programa-' . slug_archivo($proyecto->codigo) . ($proyecto->empresa ? '-' . slug_archivo($proyecto->empresa) : '')); ?>); el nombre es el que escribas en cada recuadro. En las curvas de productividad, el nombre de la curva va antes (p. ej. Control-Curva).</p>

    <form method="POST" action="/exportar/todo" class="exportar-todo">
        <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
        <div>
            <h4><i class="fa-solid fa-box-archive"></i> Exportar todo</h4>
            <p class="text-sm text-muted">Un solo ZIP con el programa (PDF, XML y POD), 4WLA, MSR, curvas de productividad y vPlan. <strong>No incluye Last Planner.</strong> Lo que no tenga datos se omite y queda anotado en LEEME.txt.</p>
        </div>
        <input type="text" name="nombre_base" value="Exportacion" placeholder="Nombre base" required>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-zipper"></i> Exportar todo</button>
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
                <input type="text" name="nombre_base" value="Programa" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-export"></i> Exportar programa</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">🔭</div>
            <h4>4WLA · Lookahead 4 semanas</h4>
            <p class="text-sm text-muted">Semana actual + 4 semanas con X por día, PTA y limitaciones. Tabloide (11×17") horizontal. En .xlsx / .ods queda editable.</p>
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
                <input type="text" name="nombre_base" value="4WLA" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-export"></i> Exportar 4WLA</button>
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
                <label class="text-sm text-muted" for="vp_lamina">Lámina</label>
                <select id="vp_lamina" name="lamina_id">
                    <option value="todas">Todas las visibles del vPlan elegido</option>
                    <?php foreach ($laminasVplan as $lv): ?><option value="<?php echo s($lv->id); ?>"><?php echo s($lv->titulo . ((int)$lv->visible ? '' : ' (oculta)')); ?></option><?php endforeach; ?>
                </select>
                <input type="text" name="nombre_base" value="vPlan" placeholder="Nombre base" required>
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
                    <?php foreach ($proyecto->getPartidas() as $pt): ?><option value="<?php echo s($pt->id); ?>"><?php echo s($pt->codigo . ' · ' . $pt->nombre); ?></option><?php endforeach; ?>
                </select>
                <label class="text-sm text-muted" for="lp_cols">Tarjetas por fila</label>
                <select id="lp_cols" name="columnas">
                    <option value="2">2 columnas × 3 filas (6 por hoja, margen cómodo)</option>
                    <option value="3">3 columnas × 3 filas (9 por hoja, margen mínimo)</option>
                </select>
                <input type="text" name="nombre_base" value="LastPlanner" placeholder="Nombre base" required>
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
                <label class="text-sm text-muted" for="curva_id">Curva</label>
                <select id="curva_id" name="curva_id">
                    <option value="maestra">Gráfica maestra (todas)</option>
                    <?php foreach ($curvas as $cv): ?><option value="<?php echo s($cv->id); ?>"><?php echo s($cv->nombre); ?></option><?php endforeach; ?>
                </select>
                <label class="text-sm text-muted" for="salida-curva">Formato de archivo</label>
                <select id="salida-curva" name="salida">
                    <option value="pdf">PDF (.pdf)</option>
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="ods">LibreOffice Calc (.ods)</option>
                </select>
                <input type="text" name="nombre_base" value="Curva" placeholder="Nombre base" required>
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
                <label class="text-sm text-muted" for="msr-id">MSR</label>
                <select id="msr-id" name="msr_id" required>
                    <?php foreach (\Model\Msr::deProyecto((int)$proyecto->id) as $mx): ?>
                        <option value="<?php echo s($mx->id); ?>"><?php echo s($mx->nombre); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="text-sm text-muted" for="salida-msr">Formato de archivo</label>
                <select id="salida-msr" name="salida">
                    <option value="pdf">PDF (.pdf)</option>
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="ods">LibreOffice Calc (.ods)</option>
                </select>
                <input type="text" name="nombre_base" value="MSR" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-export"></i> Exportar MSR</button>
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
                <input type="text" name="nombre_base" value="Reporte-diario" placeholder="Nombre base" required>
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
                <input type="text" name="nombre_base" value="Bitacora" placeholder="Nombre base" required>
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
                <input type="text" name="nombre_base" value="Minutas" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar minutas</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">📦</div>
            <h4>Submittals</h4>
            <p class="text-sm text-muted">Uno o varios paquetes de submittal con sus materiales, uno por página. Sin elegir ninguno se exportan todos (Ctrl/⌘ para elegir varios).</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="reportes">
                <input type="hidden" name="tipo" value="submittals">
                <label class="text-sm text-muted" for="su_ids">Submittals</label>
                <select id="su_ids" name="submittal_ids[]" multiple size="6"><?php foreach ($submittals as $sb): ?><option value="<?php echo s($sb->id); ?>"><?php echo s($sb->numero_submittal . ($sb->especialidad ? ' · ' . $sb->especialidad : '')); ?></option><?php endforeach; ?></select>
                <input type="text" name="nombre_base" value="Submittals" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar submittals</button>
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
                <input type="text" name="nombre_base" value="Materiales" placeholder="Nombre base" required>
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
                <input type="text" name="nombre_base" value="Evidencias" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-file-pdf"></i> Exportar evidencias</button>
            </form>
        </div>
    </div>
</div>
