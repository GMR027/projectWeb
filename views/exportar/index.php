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
            <h4>PDF Ejecutivo</h4>
            <p class="text-sm text-muted">Gantt + tabla en formato tabloide (11×17") horizontal, ajustado a 1 hoja si es posible.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="pdf">
                <input type="text" name="nombre_base" value="Programa" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block">Exportar PDF</button>
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
                <button type="submit" class="btn btn-primary btn-block">Exportar 4WLA</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">🗺️</div>
            <h4>vPlan · láminas</h4>
            <p class="text-sm text-muted">Plano con tabla del programa, recovery y tablas adicionales. Tabloide (11×17") horizontal, una lámina por página.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="vplan">
                <label class="text-sm text-muted" for="vp_lamina">Lámina</label>
                <select id="vp_lamina" name="lamina_id">
                    <option value="todas">Todas las láminas</option>
                    <?php foreach ($laminasVplan as $lv): ?><option value="<?php echo s($lv->id); ?>"><?php echo s($lv->titulo); ?></option><?php endforeach; ?>
                </select>
                <input type="text" name="nombre_base" value="vPlan" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block">Exportar vPlan</button>
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
                <button type="submit" class="btn btn-primary btn-block">Exportar Last Planner</button>
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
                <button type="submit" class="btn btn-primary btn-block">Exportar curva</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pdf">🚚</div>
            <h4>MSR · Materiales</h4>
            <p class="text-sm text-muted">Reporte del status de submittals y entrega de materiales con fotos. PDF en A4 horizontal; .xlsx / .ods editables.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="msr">
                <label class="text-sm text-muted" for="salida-msr">Formato de archivo</label>
                <select id="salida-msr" name="salida">
                    <option value="pdf">PDF (.pdf)</option>
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="ods">LibreOffice Calc (.ods)</option>
                </select>
                <input type="text" name="nombre_base" value="MSR" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block">Exportar MSR</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono xml">📊</div>
            <h4>Microsoft Project (.xml)</h4>
            <p class="text-sm text-muted">XML MSPDI compatible con MS Project. Ábrelo en MSP y guárdalo como .mpp.</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="xml">
                <input type="text" name="nombre_base" value="Programa" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block">Exportar XML</button>
            </form>
        </div>

        <div class="exportador-card">
            <div class="exportador-icono pod">📁</div>
            <h4>ProjectLibre (.pod)</h4>
            <p class="text-sm text-muted">Compatible con ProjectLibre (alternativa open-source a MS Project).</p>
            <form method="POST" action="/exportar/generar">
                <input type="hidden" name="proyecto_id" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="formato" value="pod">
                <input type="text" name="nombre_base" value="Programa" placeholder="Nombre base" required>
                <button type="submit" class="btn btn-primary btn-block">Exportar POD</button>
            </form>
        </div>
    </div>
</div>
