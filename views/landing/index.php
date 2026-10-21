<?php
$planes = [
    [
        'id' => 'pro', 'nombre' => 'Plan Pro', 'precio' => '$500', 'destacado' => false,
        'lema' => 'Para contratistas que arrancan su control digital.',
        'items' => ['Hasta <strong>10 proyectos</strong>', '<strong>1 GB</strong> de almacenamiento por proyecto', 'Todas las funciones de la plataforma', 'Exportación a PDF, Excel y MS Project'],
    ],
    [
        'id' => 'gold', 'nombre' => 'Plan Gold', 'precio' => '$1,500', 'destacado' => true,
        'lema' => 'Para empresas con varias obras en paralelo.',
        'items' => ['Hasta <strong>70 proyectos</strong>', '<strong>4 GB</strong> de almacenamiento por proyecto', 'Todas las funciones de la plataforma', 'Exportación a PDF, Excel y MS Project'],
    ],
    [
        'id' => 'corporativo', 'nombre' => 'Plan Corporativo', 'precio' => '$2,500', 'destacado' => false,
        'lema' => 'Para grupos constructores y grandes portafolios.',
        'items' => ['De <strong>50 a 500 proyectos</strong>', '<strong>10 GB</strong> de almacenamiento por proyecto', 'Todas las funciones de la plataforma', 'Exportación a PDF, Excel y MS Project'],
    ],
];
$tour = [
    ['programa', 'Programa y Gantt', 'fa-chart-gantt', 'Partidas por disciplina, actividades e hitos con avance, prioridad y restricciones. Las fechas respetan el calendario real de la obra.'],
    ['4wla', 'Lookahead 4 semanas', 'fa-binoculars', 'La semana actual y las 4 siguientes con PTA y limitaciones: qué puede arrancar y qué no.'],
    ['vplan', 'vPlan', 'fa-map-location-dot', 'Láminas con plano, tabla del programa, recovery, restricciones y punch list para las juntas de obra.'],
    ['reportes', 'Reporte diario', 'fa-clipboard-list', 'Personal, actividades del programa y fotografías de cada día, listos para compartir con el cliente.'],
    ['msr', 'MSR · materiales', 'fa-truck-ramp-box', 'Estatus de submittals, compra y entrega de materiales con fotos y proveedores.'],
    ['dependencias', 'Dependencias y ruta crítica', 'fa-diagram-project', 'Relaciones FS, SS, FF y SF con retraso: la ruta crítica en rojo, las holguras y los conflictos, sin mover tus fechas hasta que tú lo decidas.'],
    ['lastplanner', 'Last Planner', 'fa-note-sticky', 'Post-its por partida calculados desde el programa, con su estado y listos para imprimir.'],
    ['curva-s', 'Curva S', 'fa-chart-area', 'Avance acumulado programado contra real, con desvío e índice SPI, para saber si la obra va en tiempo.'],
    ['cambios', 'Órdenes de cambio', 'fa-file-signature', 'Cada cambio con su estado, su impacto en costo y días, y los retiros pendientes de lo que se instaló de forma temporal.'],
    ['lineas-base', 'Líneas base', 'fa-code-compare', 'Compara el programa actual contra una foto anterior: qué se movió, qué es nuevo y si el atraso tiene causa documentada.'],
    ['redactor', 'Redactor', 'fa-file-lines', 'Actas, memorias y procedimientos con tablas del programa, comentarios, control de cambios y exportación a Word, ODT o PDF.'],
    ['sheetflow', 'SheetFlow', 'fa-table-cells', 'Tablas con fórmulas para presupuestos y controles, que se importan y exportan a Excel, CSV o PDF.'],
    ['comparador', 'Comparador de documentos', 'fa-clone', 'Dos revisiones de un plano una sobre otra con un deslizador, para ver al instante qué cambió.'],
    ['normativa', 'Normativa y especificaciones', 'fa-scale-balanced', 'Normas y especificaciones en PDF o Word, leídas por cláusulas y ligadas a los materiales y actividades a las que aplican.'],
];
// Solo se muestran las pestañas cuya captura ya existe
$tour = array_values(array_filter($tour, fn($t) => is_file(__DIR__ . '/../../public/build/img/landing/tour-' . $t[0] . '.jpg')));
$novedades = [
    ['fa-up-right-and-down-left-from-center', 'Tablas que se adaptan a cualquier tamaño', 'El texto de las tablas crece o se reduce con el bloque de la lámina, el gráfico o la pantalla: nunca más letras diminutas.', 'Oct 2026'],
    ['fa-arrows-rotate', 'Datos en tiempo real', 'Cambias una tabla en un lugar y las láminas, los gráficos y las vistas se actualizan solos.', 'Oct 2026'],
    ['fa-wand-magic-sparkles', 'Tablas inteligentes', 'Columnas de lista, casilla y semáforo, editables también desde el vPlan.', 'Oct 2026'],
    ['fa-clipboard-check', 'Punch list conectado', 'Insértalo como tabla en vPlan, Redactor y Editor, con fotos antes y después.', 'Oct 2026'],
    ['fa-stamp', 'Estampas inteligentes', 'Post-its de Last Planner y cajetines que se llenan solos con los datos del proyecto.', 'Oct 2026'],
    ['fa-layer-group', 'vPlan de láminas libres', 'Bloques con capas, guías y rejilla, varios tamaños de hoja y PDF por lámina.', 'Oct 2026'],
    ['fa-binoculars', 'Lookahead configurable', 'Encabezado con logos, nombres de columnas y de 1 a 8 semanas visibles.', 'Oct 2026'],
    ['fa-pen-ruler', 'Editor más preciso', 'Nodos editables en líneas, trazos a medida y símbolos que se actualizan solos.', 'Oct 2026'],
];
$funciones = [
    ['fa-chart-gantt', 'Programa y Gantt', 'Partidas, actividades e hitos con fechas calculadas sobre el calendario real de la obra. Cambia una fecha y todo se recalcula.'],
    ['fa-calendar-check', 'Calendario operativo', 'Jornada semanal por proyecto, festivos, paros técnicos y días de clima. Los días hábiles se respetan en cada actividad.'],
    ['fa-binoculars', 'Lookahead 4WLA', 'Semana actual + 4 semanas con PTA y limitaciones. Detecta qué actividades pueden arrancar y cuáles no.'],
    ['fa-note-sticky', 'Last Planner', 'Tarjetas por día y por partida listas para imprimir, con marcas de corte, calculadas desde tu programa.'],
    ['fa-map-location-dot', 'vPlan visual', 'Láminas libres con bloques posicionables: planos, tablas del programa, recovery, restricciones y punch list. Modo diseño con capas y guías, y PDF por lámina.'],
    ['fa-chart-line', 'Curvas y Curva S', 'Planeado contra real por semana para cada material o frente de trabajo, y una Curva S que junta todas en el avance acumulado del programa.'],
    ['fa-truck-ramp-box', 'MSR · submittals · materiales', 'Catálogo con fotos y fichas, varios MSR por proyecto con las columnas que tú elijas (nombre, orden y color), control de submittals y estatus de compra y entrega.'],
    ['fa-clipboard-list', 'Reporte diario y bitácora', 'Personal, actividades y fotos del día en minutos. Bitácora por departamento con niveles de importancia.'],
    ['fa-handshake', 'Minutas de junta', 'Temas, responsables, compromisos y asistentes. Genera el PDF listo para enviar al cliente.'],
    ['fa-bolt', 'Restricciones y acciones rápidas', 'Registra lo que frena a cada actividad y consulta en un solo lugar lo del día y la semana: restricciones, bitácora crítica, minutas, submittals y materiales pendientes.'],
    ['fa-file-export', 'Exportación multi-formato', 'PDF tabloide, Excel, LibreOffice, XML de MS Project y ProjectLibre. Un ZIP con todo el proyecto.'],
    ['fa-user-group', 'Accesos para visitantes', 'Crea accesos de solo lectura para que tu cliente o tu equipo consulten el avance de tus proyectos, sin poder modificar nada. Tú eliges qué herramientas ve cada visitante.'],
    ['fa-diagram-project', 'Dependencias y ruta crítica', 'Relaciones FS, SS, FF y SF con retraso. Calcula la ruta crítica, las holguras y los conflictos; en modo automático recorre las sucesoras con vista previa y opción de deshacer.'],
    ['fa-diagram-next', 'Planificador por material', 'Cruza las actividades pendientes con los materiales comprados y te dice qué puedes adelantar y qué está bloqueado por compra o restricción.'],
    ['fa-camera-retro', 'Evidencias fotográficas', 'Línea de tiempo con las fotos del avance por actividad. Junta las de los reportes diarios y las que subas directo, agrupadas por día.'],
    ['fa-folder-open', 'Archivo del proyecto', 'Planos, especificaciones y contratos con categoría, fecha y número de revisión, hasta 100 MB por archivo y cuota por proyecto.'],
    ['fa-pen-ruler', 'Editor de gráficos', 'Lienzo para croquis, diagramas y láminas con varias hojas, y descarga en PNG, PDF o presentación .odp. Se complementa con el Redactor y SheetFlow.'],
    ['fa-shapes', 'Biblioteca de símbolos', 'Cámaras, racks, extintores, luminarias, tableros y más para marcar tus planos. Puedes sumar los tuyos.'],
    ['fa-file-signature', 'Órdenes de cambio', 'Estado, impacto en costo y tiempo, y renglones de instalación temporal con su retiro pendiente. Un cambio aprobado justifica la desviación en el programa.'],
    ['fa-code-compare', 'Líneas base y comparación', 'Guarda una foto del programa y compárala con el actual: actividades movidas, nuevas o eliminadas, ruta crítica y causa del atraso (cambio, material o sin justificar).'],
    ['fa-boxes-packing', 'Materiales contra cronograma', 'Cruza lo que necesita cada actividad con compra, entrega y submittal, y avisa qué material llega tarde o qué compra ya venció.'],
    ['fa-file-lines', 'Redactor de documentos', 'Actas, memorias y procedimientos con portada, tablas del programa, comentarios, versiones y exportación a DOCX, ODT o PDF.'],
    ['fa-table-cells', 'SheetFlow · tablas con fórmulas', 'Presupuestos, explosión de insumos y controles de obra con fórmulas; importa y exporta a XLSX, CSV o PDF.'],
    ['fa-clone', 'Comparador de documentos', 'Empareja dos revisiones de un plano o PDF y compáralas con un deslizador para ver qué cambió entre una versión y otra.'],
    ['fa-scale-balanced', 'Normativa y especificaciones', 'Carga normas y especificaciones, consúltalas por cláusula y vincúlalas a los materiales y actividades que les aplican. Tú confirmas cada vínculo.'],
    ['fa-folder-tree', 'Carpetas por departamento', 'Ordena minutas, documentos y tablas en carpetas por departamento: renómbralas y mueve cada documento en un clic.'],
    ['fa-stamp', 'Estampas inteligentes y cajetines', 'Bloques cuyas etiquetas se llenan solas con avance, fechas, semáforos y actividades del día: post-its de Last Planner, encabezados de MSR y cajetines con tu logo.'],
    ['fa-clipboard-check', 'Punch list de cierre', 'Pendientes de calidad con foto antes y después, inspección por ubicación y PDF por contratista. Se inserta como tabla en láminas, actas y gráficos.'],
    ['fa-puzzle-piece', 'Ensamblador de documentos', 'Un PDF que une actas del Redactor y documentos del Archivo y se actualiza solo cuando cambia alguno de ellos.'],
    ['fa-language', 'Traductor español ↔ inglés', 'Traduce textos y documentos de obra sin salir de la plataforma, para contratos y clientes internacionales.'],
    ['fa-street-view', 'View360', 'Un plano con zonas que abren fotos 360° de la obra: recorridos virtuales con notas para el cliente, sin viajar al sitio.'],
];
if (!function_exists('cmp_celda')) {
    function cmp_celda($v) {
        $ico = ['si' => ['fa-circle-check', 'ok'], 'parcial' => ['fa-circle-half-stroke', 'mid'], 'no' => ['fa-circle-xmark', 'no']];
        [$k, $txt] = $v;
        return '<i class="fa-solid ' . $ico[$k][0] . ' lp-cmp-' . $ico[$k][1] . '"></i> <span>' . s($txt) . '</span>';
    }
}
$comparativa = [
    ['Programa, Gantt y calendario real', ['si', 'Con festivos, paros y clima'], ['si', 'Sí, en el programa'], ['parcial', 'Gantt básico']],
    ['Ruta crítica y dependencias', ['si', 'FS, SS, FF, SF'], ['si', 'Sí'], ['parcial', 'Limitado']],
    ['Last Planner y lookahead 4S', ['si', 'Calculados del programa'], ['no', 'A mano en Excel'], ['no', 'No incluido']],
    ['Materiales, submittals y MSR', ['si', 'Ligados a las actividades'], ['no', 'Hojas aparte'], ['parcial', 'Campos personalizados']],
    ['Reporte diario, bitácora, minutas', ['si', 'Con fotos y PDF'], ['no', 'Word y WhatsApp'], ['parcial', 'Comentarios sueltos']],
    ['Evidencias, View360 y planos', ['si', 'Incluidos'], ['no', 'Carpetas aparte'], ['parcial', 'Adjuntos']],
    ['Una sola captura para todo', ['si', 'Todo se actualiza junto'], ['no', 'Se captura varias veces'], ['parcial', 'Depende de integraciones']],
    ['Control de cambios y líneas base', ['si', 'Con impacto y comparación'], ['no', 'Excel y correos'], ['parcial', 'Solo seguimiento de tareas']],
    ['Documentos y tablas de obra', ['si', 'Redactor y SheetFlow integrados'], ['no', 'Word y Excel aparte'], ['parcial', 'Notas o adjuntos']],
    ['Comparar revisiones de planos', ['si', 'Comparador con deslizador'], ['no', 'Programa de dibujo aparte'], ['no', 'No incluido']],
    ['Acceso del cliente solo lectura', ['si', 'Por herramienta'], ['no', 'Se envían PDF'], ['parcial', 'Con licencia por usuario']],
    ['Instalación y licencias', ['si', 'Solo navegador'], ['no', 'Licencias por equipo'], ['si', 'Navegador']],
    ['Exporta a MS Project y Excel', ['si', 'PDF, Excel, ODS, XML, POD'], ['si', 'Nativo'], ['parcial', 'Solo CSV o Excel']],
];
$licenciaEquipo = 20000;                       // licencias de los programas que ProjectWeb reemplaza, por equipo (pago inicial)
$planProAnual = 500 * 12;
$ahorro = [];
foreach ([1, 2, 3, 5] as $n) {
    $trad = $licenciaEquipo * $n;
    $ahorro[] = ['equipos' => $n, 'trad' => $trad, 'pw' => $planProAnual, 'ahorro' => $trad - $planProAnual, 'pct' => round(($trad - $planProAnual) / $trad * 100)];
}
$ventajas = [
    ['fa-layer-group', 'Una plataforma, no tres o cuatro programas', 'Gestionar un proyecto de forma tradicional exige varios programas distintos para llegar a lo que aquí ya viene integrado, con licencias que en promedio suman alrededor de <strong>$20,000 pesos</strong>.'],
    ['fa-shield-halved', 'Información que cuadra a la primera', 'Hecha a mano, la información se daña, se pierde o no coincide entre archivos. Aquí las herramientas están integradas: capturas una vez y todo cuadra.'],
    ['fa-mobile-screen-button', 'En cualquier equipo, sin reinstalar', 'Si cambias de computadora no recompras ni reinstalas licencias. Consulta y edita desde el celular, la tableta o la computadora.'],
    ['fa-route', 'Ruta crítica sin otro software', 'Las dependencias, holguras y la ruta crítica viven junto al Gantt, sin exportar a un programa de escritorio para saber qué actividad no se puede atrasar.'],
    ['fa-people-roof', 'Hecha para el control de obra', 'Last Planner, lookahead, restricciones, MSR y submittals ya vienen pensados para el contratista, no adaptados desde un gestor genérico de tareas.'],
    ['fa-lock', 'Control de quién ve qué', 'Tres roles y accesos de visitante por herramienta: el cliente consulta avance y evidencias sin tocar el programa ni ver lo que no debe.'],
    ['fa-cloud-arrow-up', 'Tu información respaldada', 'Respaldos programados y cuota de almacenamiento por proyecto: tus planos, fotos y reportes no dependen de la laptop de nadie.'],
    ['fa-puzzle-piece', 'Todo en uno, de verdad', 'Del programa al acta, de la compra al cambio aprobado: el programa, los materiales, los documentos y los cambios comparten los mismos datos y se hablan entre sí.'],
    ['fa-code-compare', 'Los cambios dejan huella', 'Órdenes de cambio, líneas base y comparación de planos explican por qué se movió una fecha y quién lo autorizó.'],
    ['fa-bolt', 'Acciones rápidas para no andar perdido', 'Las actividades del día y de la semana, las restricciones activas y tus pendientes de bitácora, minutas, submittals y materiales, juntos en un solo lugar.'],
];
?>

<?php include __DIR__ . '/_nav.php'; ?>

<section class="lp-hero" style="--hero: url('/build/img/landing/hero.jpg')">
    <span class="lp-orb lp-orb1"></span><span class="lp-orb lp-orb2"></span>
    <div class="lp-wrap lp-hero-in">
        <div class="lp-hero-txt lp-rev">
            <span class="lp-pill"><i class="fa-solid fa-bolt"></i> Control de obra en tiempo real</span>
            <h1>Tu programa de obra,<br><span class="lp-grad">siempre bajo control.</span></h1>
            <p class="lp-lead">Gantt, ruta crítica, lookahead, Last Planner, vPlan, materiales, órdenes de cambio, documentos, evidencias y reportes diarios en una sola plataforma. Menos programas, menos licencias y menos hojas de cálculo; más obra entregada a tiempo.</p>
            <div class="lp-cta">
                <?php if ($demoDisponible): ?><a href="/demo" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-play"></i> Ver demo en vivo</a><?php endif; ?>
                <a href="#planes" class="lp-btn lp-btn-ghost lp-btn-lg">Ver planes</a>
            </div>
            <ul class="lp-check">
                <li><i class="fa-solid fa-circle-check"></i> Sin instalar nada ni comprar licencias</li>
                <li><i class="fa-solid fa-circle-check"></i> Celular, tableta o computadora</li>
                <li><i class="fa-solid fa-circle-check"></i> Exporta a PDF, Excel y MS Project</li>
                <li><i class="fa-solid fa-circle-check"></i> Demo sin registro</li>
            </ul>
        </div>

        <div class="lp-mock-wrap lp-rev" aria-hidden="true">
        <span class="lp-float lp-f1"><i class="fa-solid fa-circle-check"></i> Avance sincronizado</span>
        <span class="lp-float lp-f2"><i class="fa-solid fa-triangle-exclamation"></i> 6 restricciones activas</span>
        <span class="lp-float lp-f3"><i class="fa-solid fa-file-pdf"></i> PDF tabloide listo</span>
        <div class="lp-mock">
            <div class="lp-mock-top"><i></i><i></i><i></i><span>Planta Meridian · Programa de obra</span></div>
            <div class="lp-mock-kpis">
                <div><b>68%</b><small>Avance real</small></div>
                <div><b>33</b><small>Actividades</small></div>
                <div><b class="lp-warn">6</b><small>Restricciones</small></div>
            </div>
            <div class="lp-gantt">
                <div class="lp-g-head"><span></span><em>Ago</em><em>Sep</em><em>Oct</em><em>Nov</em><em>Dic</em></div>
                <?php foreach ([
                    ['Arranque', 0, 14, 100, '#FF5A1F'], ['Ingeniería', 6, 38, 78, '#00B4D8'], ['Canalización', 14, 30, 100, '#8B5CF6'],
                    ['Videovigilancia', 34, 36, 55, '#10B981'], ['Control de acceso', 44, 30, 35, '#F59E0B'], ['Comunicaciones', 40, 34, 40, '#EF4444'], ['Puesta en marcha', 72, 24, 0, '#6366F1'],
                ] as $i => [$n, $ini, $w, $p, $c]): ?>
                    <div class="lp-g-row"><span><?php echo s($n); ?></span>
                        <div class="lp-g-track"><div class="lp-g-bar" style="--i:<?php echo $i; ?>;left:<?php echo $ini; ?>%;width:<?php echo $w; ?>%;--c:<?php echo $c; ?>"><u style="width:<?php echo $p; ?>%"></u></div></div>
                    </div>
                <?php endforeach; ?>
                <div class="lp-g-hoy"><b>Hoy</b></div>
            </div>
        </div>
        </div>
    </div>
</section>

<section class="lp-strip">
    <div class="lp-wrap">
        <p>Compatible con las herramientas que ya usas</p>
        <div class="lp-chips">
            <span><i class="fa-solid fa-file-pdf"></i> PDF tabloide 11×17"</span>
            <span><i class="fa-solid fa-file-excel"></i> Excel (.xlsx)</span>
            <span><i class="fa-solid fa-table"></i> LibreOffice (.ods)</span>
            <span><i class="fa-solid fa-diagram-project"></i> MS Project (XML)</span>
            <span><i class="fa-solid fa-folder-tree"></i> ProjectLibre (.pod)</span>
        </div>
    </div>
</section>

<section class="lp-stats">
    <div class="lp-wrap lp-stats-in">
        <div class="lp-rev"><b data-n="<?php echo count($funciones); ?>"><?php echo count($funciones); ?></b><span>herramientas conectadas entre sí</span></div>
        <div class="lp-rev" style="--d:80ms"><b data-n="5">5</b><span>formatos de exportación</span></div>
        <div class="lp-rev" style="--d:160ms"><b data-n="10">10</b><span>disciplinas en el proyecto demo</span></div>
        <div class="lp-rev" style="--d:240ms"><b data-n="1" data-suf=" fuente">1 fuente</b><span>única de verdad para toda la obra</span></div>
    </div>
</section>

<div class="lp-marquee" aria-hidden="true">
    <div class="lp-marquee-in">
        <?php for ($k = 0; $k < 2; $k++): foreach (['Obra civil', 'Cimentación', 'Estructura', 'Albañilería', 'Instalación eléctrica', 'Hidrosanitaria', 'Contra incendio', 'Aire acondicionado', 'Voz y datos', 'Seguridad electrónica', 'Acabados', 'Urbanización'] as $d): ?>
            <span><i class="fa-solid fa-circle"></i> <?php echo s($d); ?></span>
        <?php endforeach; endfor; ?>
    </div>
</div>

<section class="lp-sec lp-tour" id="recorrido">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Recorrido por el producto</span>
            <h2>Así se ve una obra<br>multidisciplinaria en ProjectWeb</h2>
            <p>Pantallas reales del proyecto demo: un edificio corporativo con obra civil, estructura, instalaciones, acabados y más.</p>
        </div>
        <div class="lp-tabs lp-rev" role="tablist">
            <?php foreach ($tour as $i => [$id, $nom, $ico, $txt]): ?>
                <button type="button" role="tab" class="lp-tab <?php echo $i === 0 ? 'on' : ''; ?>" data-i="<?php echo $i; ?>"><i class="fa-solid <?php echo $ico; ?>"></i> <?php echo s($nom); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="lp-browser lp-rev">
            <div class="lp-browser-bar"><i></i><i></i><i></i><span class="lp-url" id="lp-url">projectweb/programa</span></div>
            <div class="lp-shots">
                <?php foreach ($tour as $i => [$id, $nom, $ico, $txt]): ?>
                    <img src="/build/img/landing/tour-<?php echo $id; ?>.jpg" alt="<?php echo s($nom); ?> del proyecto demo" class="<?php echo $i === 0 ? 'on' : ''; ?>" <?php echo $i ? 'loading="lazy"' : ''; ?> data-url="projectweb/<?php echo $id; ?>">
                <?php endforeach; ?>
            </div>
        </div>
        <p class="lp-tour-txt lp-rev" id="lp-tour-txt"><?php echo s($tour[0][3]); ?></p>
    </div>
</section>

<section class="lp-sec" id="funciones">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Funciones</span>
            <h2>Todo lo que tu obra necesita,<br>conectado entre sí</h2>
            <p>Un solo programa alimenta el Gantt, el lookahead, los post-its, el vPlan y los reportes. Actualizas una vez y se refleja en todo.</p>
        </div>
        <div class="lp-grid">
            <?php foreach ($funciones as $i => [$ico, $t, $d]): ?>
                <article class="lp-card lp-rev" style="--d:<?php echo ($i % 4) * 70; ?>ms">
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <h3><?php echo s($t); ?></h3>
                    <p><?php echo s($d); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-sec" id="novedades">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Lo nuevo</span>
            <h2>Una plataforma que mejora<br>cada mes</h2>
            <p>Las últimas mejoras de ProjectWeb, pensadas para que la obra se vea clara en cualquier pantalla y la información nunca esté desactualizada.</p>
        </div>
        <div class="lp-grid">
            <?php foreach ($novedades as $i => [$ico, $t, $d, $f]): ?>
                <article class="lp-card lp-nuevo lp-rev" style="--d:<?php echo ($i % 4) * 70; ?>ms">
                    <span class="lp-badge"><?php echo s($f); ?></span>
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <h3><?php echo s($t); ?></h3>
                    <p><?php echo s($d); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-sec lp-alt" id="ventajas">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">¿Por qué ProjectWeb?</span>
            <h2>Menos programas, menos costo,<br>menos estrés</h2>
            <p>Todo lo que antes se repartía entre varios programas y archivos sueltos, ahora vive junto y disponible donde lo necesites.</p>
        </div>
        <div class="lp-grid">
            <?php foreach ($ventajas as $i => [$ico, $t, $d]): ?>
                <article class="lp-card lp-rev" style="--d:<?php echo $i * 70; ?>ms">
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <h3><?php echo s($t); ?></h3>
                    <p><?php echo $d; ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-sec" id="comparativa">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Comparativa</span>
            <h2>Lo que cambia frente a<br>las otras formas de llevar la obra</h2>
            <p>Comparamos ProjectWeb con las dos maneras más comunes de controlar una obra hoy: combinar programas de escritorio y hojas de cálculo, o contratar un gestor de proyectos genérico.</p>
        </div>
        <div class="lp-cmp-wrap lp-rev">
            <table class="lp-cmp">
                <thead>
                    <tr><th></th><th class="lp-cmp-us">ProjectWeb</th><th>Programas de escritorio + Excel</th><th>Gestor genérico de tareas</th></tr>
                </thead>
                <tbody>
                <?php foreach ($comparativa as [$crit, $nosotros, $escritorio, $generico]): ?>
                    <tr>
                        <th scope="row"><?php echo s($crit); ?></th>
                        <td class="lp-cmp-us"><?php echo cmp_celda($nosotros); ?></td>
                        <td><?php echo cmp_celda($escritorio); ?></td>
                        <td><?php echo cmp_celda($generico); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="lp-cmp-nota lp-rev"><i class="fa-solid fa-circle-info"></i> Comparación general por tipo de herramienta, no de un producto en particular. Los programas de escritorio siguen siendo útiles: por eso exportamos a MS Project, Excel y ProjectLibre.</p>
    </div>
</section>

<section class="lp-sec lp-alt" id="ahorro">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Ahorro</span>
            <h2>Cuánto te ahorras<br>al reunir todo en un solo lugar</h2>
            <p>Armar lo que ProjectWeb ya incluye con programas de escritorio cuesta alrededor de <strong>$<?php echo number_format($licenciaEquipo); ?> MXN por equipo</strong> en licencias. Con el Plan Pro pagas <strong>$<?php echo number_format($planProAnual); ?> MXN al año</strong> por todas las funciones.</p>
        </div>
        <div class="lp-cmp-wrap lp-rev">
            <table class="lp-cmp lp-ahorro">
                <thead>
                    <tr><th>Equipos de tu empresa</th><th>Licencias tradicionales</th><th class="lp-cmp-us">ProjectWeb Plan Pro (1.er año)</th><th>Ahorras en el primer año</th></tr>
                </thead>
                <tbody>
                <?php foreach ($ahorro as $f): ?>
                    <tr>
                        <th scope="row"><?php echo (int)$f['equipos']; ?> <?php echo $f['equipos'] === 1 ? 'equipo' : 'equipos'; ?></th>
                        <td>$<?php echo number_format($f['trad']); ?></td>
                        <td class="lp-cmp-us">$<?php echo number_format($f['pw']); ?></td>
                        <td class="lp-ahorro-n"><strong>$<?php echo number_format($f['ahorro']); ?></strong> <span><?php echo (int)$f['pct']; ?> % menos</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="lp-ahorro-extra lp-rev">
            <li><i class="fa-solid fa-clock"></i><span><strong>Menos horas de captura.</strong> Capturas una vez y el programa, el lookahead, los reportes y el vPlan se actualizan juntos.</span></li>
            <li><i class="fa-solid fa-laptop"></i><span><strong>Sin comprar de nuevo.</strong> Si cambias de computadora, no recompras ni reinstalas licencias: entras desde el navegador.</span></li>
            <li><i class="fa-solid fa-file-word"></i><span><strong>Menos licencias de ofimática y de dibujo.</strong> Actas, tablas con fórmulas y comparación de planos viven dentro de la misma plataforma, sin abrir otros programas.</span></li>
            <li><i class="fa-solid fa-people-arrows"></i><span><strong>Menos retrabajo.</strong> Todos ven la misma información, así no se corrigen datos distintos entre archivos.</span></li>
        </ul>
        <p class="lp-cmp-nota lp-rev"><i class="fa-solid fa-circle-info"></i> Cifras en pesos mexicanos. El costo de licencias es un promedio de referencia por equipo; sin contar renovaciones ni actualizaciones, que harían mayor el ahorro. El Plan Pro es de $500 MXN al mes (1 año = $<?php echo number_format($planProAnual); ?>).</p>
    </div>
</section>

<section class="lp-sec lp-alt" id="todo-en-uno">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Todo en uno</span>
            <h2>Cinco áreas de la obra,<br>una sola plataforma</h2>
            <p>Lo que hoy vive en el Excel del programa, el Word de las actas, la carpeta de planos y el chat de la cuadrilla, aquí comparte la misma información.</p>
        </div>
        <div class="lp-hub lp-rev">
            <?php foreach ([
                ['Planear', 'fa-chart-gantt', ['Programa y Gantt', 'Calendario operativo', 'Dependencias y ruta crítica', 'Lookahead', 'Last Planner']],
                ['Ejecutar', 'fa-helmet-safety', ['Reporte diario', 'Bitácora', 'Evidencias fotográficas', 'Restricciones', 'Acciones rápidas']],
                ['Abastecer', 'fa-truck-ramp-box', ['Catálogo de materiales', 'Submittals', 'MSR configurable', 'Planificador por material', 'Materiales vs. cronograma']],
                ['Documentar', 'fa-file-lines', ['Redactor', 'SheetFlow', 'Minutas', 'Archivo de planos', 'Comparador de documentos']],
                ['Controlar', 'fa-scale-balanced', ['Órdenes de cambio', 'Líneas base', 'Curvas y Curva S', 'vPlan y View360', 'Accesos de cliente']],
            ] as [$tit, $ico, $items]): ?>
                <div class="lp-hub-col">
                    <h3><i class="fa-solid <?php echo $ico; ?>"></i> <?php echo s($tit); ?></h3>
                    <ul><?php foreach ($items as $it): ?><li><i class="fa-solid fa-circle-check"></i> <?php echo s($it); ?></li><?php endforeach; ?></ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-sec" id="producto">
    <div class="lp-wrap">
        <div class="lp-show lp-rev">
            <div class="lp-show-txt">
                <span class="lp-eyebrow">Planeación</span>
                <h2>Del plano a la programación, sin saltos</h2>
                <p>Construye el programa con partidas y actividades, calcula fechas con la jornada real de la obra y deja listo el lookahead semanal. Las restricciones quedan ligadas a cada actividad para que nada se pierda en una junta.</p>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> Fechas con días hábiles, festivos y paros</li>
                    <li><i class="fa-solid fa-check"></i> Avance por actividad y por partida</li>
                    <li><i class="fa-solid fa-check"></i> Restricciones visibles en 4WLA y vPlan</li>
                </ul>
            </div>
            <figure class="lp-fig"><img src="/build/img/landing/planos.jpg" alt="Revisión de planos de obra" loading="lazy"></figure>
        </div>

        <div class="lp-show lp-rev lp-inv">
            <div class="lp-show-txt">
                <span class="lp-eyebrow">Campo</span>
                <h2>Evidencia de lo que pasa en obra</h2>
                <p>Reporte diario con personal, actividades y fotografías; bitácora por departamento y minutas con compromisos. Todo queda fechado, buscable y listo para compartir con el cliente.</p>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> Fotos y personal en cada reporte</li>
                    <li><i class="fa-solid fa-check"></i> Bitácora con niveles de importancia</li>
                    <li><i class="fa-solid fa-check"></i> Minutas en PDF con un clic</li>
                </ul>
            </div>
            <figure class="lp-fig"><img src="/build/img/landing/campo.jpg" alt="Cuadrilla en obra vista desde arriba" loading="lazy"></figure>
        </div>

        <div class="lp-show lp-rev">
            <div class="lp-show-txt">
                <span class="lp-eyebrow">Materiales y entregables</span>
                <h2>Controla lo que llega, y cuándo llega</h2>
                <p>Catálogo de materiales con foto y ficha técnica, submittals y MSR para saber qué falta aprobar, comprar o entregar. Curvas de productividad para comparar lo planeado contra lo ejecutado.</p>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> Estatus de aprobación y entrega por material</li>
                    <li><i class="fa-solid fa-check"></i> Curvas planeado vs. real por frente</li>
                    <li><i class="fa-solid fa-check"></i> Exporta el MSR en PDF o Excel</li>
                </ul>
            </div>
            <figure class="lp-fig lp-fig-pair">
                <img src="/build/img/landing/redes.jpg" alt="Gabinete de comunicaciones" loading="lazy">
                <img src="/build/img/landing/cctv.jpg" alt="Cámaras de videovigilancia" loading="lazy">
            </figure>
        </div>

        <div class="lp-show lp-rev lp-inv">
            <div class="lp-show-txt">
                <span class="lp-eyebrow">Cambios y líneas base</span>
                <h2>Cada desviación tiene una explicación</h2>
                <p>Registra las órdenes de cambio con su impacto en costo y días, captura una línea base del programa y compárala con el avance actual. Sabrás qué se movió, qué entró a la ruta crítica y si el atraso viene de un cambio, de un material o de nadie.</p>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> Instalaciones temporales con su retiro pendiente</li>
                    <li><i class="fa-solid fa-check"></i> Comparación con barra «fantasma» de la línea base</li>
                    <li><i class="fa-solid fa-check"></i> Materiales cruzados contra el cronograma</li>
                </ul>
            </div>
            <figure class="lp-fig"><img src="/build/img/landing/<?php echo is_file(__DIR__ . '/../../public/build/img/landing/tour-lineas-base.jpg') ? 'tour-lineas-base' : 'planos'; ?>.jpg" alt="Comparación de línea base del programa" loading="lazy"></figure>
        </div>

        <div class="lp-show lp-rev">
            <div class="lp-show-txt">
                <span class="lp-eyebrow">Documentación</span>
                <h2>Actas, tablas y planos sin salir de la obra</h2>
                <p>Escribe actas y memorias en el Redactor, lleva presupuestos y controles en tablas con fórmulas y compara dos revisiones de un plano con un deslizador. Todo ordenado en carpetas por departamento y exportable a Word, Excel o PDF.</p>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> Tablas del programa dentro de tus documentos</li>
                    <li><i class="fa-solid fa-check"></i> Fórmulas y exportación a XLSX, CSV y PDF</li>
                    <li><i class="fa-solid fa-check"></i> Comparador de planos y vPlan para las juntas</li>
                </ul>
            </div>
            <figure class="lp-fig"><img src="/build/img/landing/<?php echo is_file(__DIR__ . '/../../public/build/img/landing/tour-redactor.jpg') ? 'tour-redactor' : 'planos'; ?>.jpg" alt="Documento en el Redactor" loading="lazy"></figure>
        </div>
    </div>
</section>

<section class="lp-steps">
    <div class="lp-wrap">
        <div class="lp-head lp-rev"><span class="lp-eyebrow">Cómo funciona</span><h2>Listo para usarse en cuatro pasos</h2></div>
        <ol class="lp-ol lp-ol-4">
            <li class="lp-rev"><b>1</b><h3>Crea tu proyecto</h3><p>Define fechas, jornada semanal y festivos. El sistema prepara el calendario operativo.</p></li>
            <li class="lp-rev" style="--d:90ms"><b>2</b><h3>Arma el programa</h3><p>Captura partidas, actividades y restricciones. Se calculan fechas, Gantt, 4WLA y Last Planner.</p></li>
            <li class="lp-rev" style="--d:180ms"><b>3</b><h3>Da seguimiento</h3><p>Registra avance, reportes diarios, materiales y evidencias. Todo se refleja en el programa, el vPlan y las curvas.</p></li>
            <li class="lp-rev" style="--d:270ms"><b>4</b><h3>Controla, documenta y comparte</h3><p>Lleva los cambios contra una línea base, redacta actas, da acceso de solo lectura al cliente y descarga en el formato que pida.</p></li>
        </ol>
    </div>
</section>

<?php if ($demoDisponible): ?>
<section class="lp-band" style="--bg: url('/build/img/landing/grua.jpg')">
    <div class="lp-wrap lp-rev">
        <h2>Míralo funcionando con un proyecto de ejemplo</h2>
        <p>Entra al demo sin registrarte: recorre el programa, el vPlan, los reportes diarios, el MSR, las órdenes de cambio, los documentos y más. Es de solo lectura, no puedes romper nada.</p>
        <a href="/demo" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-play"></i> Entrar al demo</a>
    </div>
</section>
<?php endif; ?>

<section class="lp-sec" id="servicios">
    <div class="lp-wrap">
        <div class="lp-srv-teaser lp-rev">
            <div>
                <span class="lp-eyebrow">Servicios</span>
                <h2>¿Prefieres que nos encarguemos del seguimiento?</h2>
                <p>Llevamos el seguimiento administrativo de tu obra para que tu equipo se dedique a construir.</p>
                <div class="lp-cta"><a href="/servicios" class="lp-btn lp-btn-primary lp-btn-lg">Conocer los servicios <i class="fa-solid fa-arrow-right"></i></a></div>
            </div>
            <ul>
                <li><i class="fa-solid fa-circle-check"></i> Programa, materiales y reportes al día</li>
                <li><i class="fa-solid fa-circle-check"></i> Control de cambios y documentación</li>
                <li><i class="fa-solid fa-circle-check"></i> Un grupo especializado que detecta detalles para decidir mejor</li>
            </ul>
        </div>
    </div>
</section>

<section class="lp-sec" id="planes">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Planes</span>
            <h2>Elige el plan que crece contigo</h2>
            <p>Precios mensuales en pesos mexicanos (MXN). Todos los planes incluyen todas las funciones.</p>
        </div>
        <div class="lp-plans">
            <?php foreach ($planes as $i => $pl): ?>
                <article class="lp-plan lp-rev <?php echo $pl['destacado'] ? 'lp-plan-top' : ''; ?>" style="--d:<?php echo $i * 90; ?>ms">
                    <?php if ($pl['destacado']): ?><span class="lp-badge">Más popular</span><?php endif; ?>
                    <h3><?php echo s($pl['nombre']); ?></h3>
                    <p class="lp-plan-lema"><?php echo s($pl['lema']); ?></p>
                    <p class="lp-precio"><b><?php echo s($pl['precio']); ?></b><span> MXN / mes</span></p>
                    <a href="#contacto" data-plan="<?php echo s($pl['nombre']); ?>" class="lp-btn <?php echo $pl['destacado'] ? 'lp-btn-primary' : 'lp-btn-ghost'; ?> lp-btn-block">Quiero el <?php echo s($pl['nombre']); ?></a>
                    <ul>
                        <?php foreach ($pl['items'] as $it): ?><li><i class="fa-solid fa-circle-check"></i> <span><?php echo $it; ?></span></li><?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-sec lp-alt" id="faq">
    <div class="lp-wrap lp-narrow">
        <div class="lp-head lp-rev"><span class="lp-eyebrow">Preguntas frecuentes</span><h2>Lo que suelen preguntarnos</h2></div>
        <div class="lp-faq lp-rev">
            <details><summary>¿Puedo exportar mi programa a MS Project?</summary><p>Sí. Se exporta en XML (MSPDI) y lo abres en MS Project para guardarlo como .mpp. También en ProjectLibre, Excel, LibreOffice y PDF tabloide.</p></details>
            <details><summary>¿Necesito instalar algo o comprar licencias de otros programas?</summary><p>No. ProjectWeb funciona desde el navegador y reúne lo que tradicionalmente exige tres o cuatro programas distintos, con sus licencias. Si quieres, sigues exportando a PDF, Excel o MS Project.</p></details>
            <details><summary>¿Puedo trabajar desde el celular o si cambio de equipo?</summary><p>Sí. Entras con tu usuario desde el celular, la tableta o cualquier computadora y ves y editas la misma información, sin reinstalar ni volver a comprar nada.</p></details>
            <details><summary>¿Qué pasa si llego al límite de almacenamiento?</summary><p>Cada proyecto tiene su cuota según el plan. Al llegar al límite no se pueden subir más archivos hasta liberar espacio; lo ya guardado no se pierde.</p></details>
            <details><summary>¿En qué se diferencian los planes?</summary><p>En la cantidad de proyectos que puedes crear y el almacenamiento por proyecto. Todas las funciones están incluidas en cada plan.</p></details>
            <details><summary>¿Pueden encargarse del seguimiento de mi obra?</summary><p>Sí. Además de la plataforma ofrecemos servicios administrativos de seguimiento de obra para que tu equipo se concentre en construir. Conoce el detalle en <a href="/servicios" class="lp-foot-link">Servicios</a>.</p></details>
            <details><summary>¿Puedo redactar actas o llevar tablas dentro de la plataforma?</summary><p>Sí. El Redactor exporta a Word, ODT y PDF, y SheetFlow maneja tablas con fórmulas que se importan y exportan a Excel, CSV y PDF.</p></details>
            <details><summary>¿Las tablas se ven bien en cualquier tamaño de pantalla o de hoja?</summary><p>Sí. El texto de las tablas se adapta solo: crece o se reduce con el bloque del vPlan, con las asas del Editor y con el ancho de la pantalla. Y si cambias los datos en un lugar, el resto de las vistas se actualiza en tiempo real.</p></details>
            <details><summary>¿El demo guarda mis cambios?</summary><p>No. El demo es de solo lectura y usa datos de ejemplo: no necesitas registrarte ni se guarda nada de lo que hagas.</p></details>
        </div>
    </div>
</section>

<section class="lp-sec lp-contact" id="contacto">
    <div class="lp-wrap lp-contact-in">
        <div class="lp-contact-txt lp-rev">
            <span class="lp-eyebrow">Contacto</span>
            <h2>Hablemos de tu próxima obra</h2>
            <p>Cuéntanos qué necesitas y te ayudamos a elegir el plan ideal. También puedes ver el demo sin registrarte.</p>
            <ul class="lp-list">
                <li><i class="fa-solid fa-check"></i> Te contactamos por el medio que prefieras</li>
                <li><i class="fa-solid fa-check"></i> Sin compromiso: resolvemos tus dudas primero</li>
                <li><i class="fa-solid fa-check"></i> Planes de 10 a 500 proyectos</li>
            </ul>
            <?php if ($contacto): ?><p class="lp-contact-mail"><i class="fa-solid fa-envelope"></i> <a href="mailto:<?php echo s($contacto); ?>"><?php echo s($contacto); ?></a></p><?php endif; ?>
            <?php if ($demoDisponible): ?><a href="/demo" class="lp-btn lp-btn-ghost lp-btn-lg" style="margin-top:2rem"><i class="fa-solid fa-play"></i> Probar el demo</a><?php endif; ?>
        </div>

        <div class="lp-form-wrap lp-rev" style="--d:120ms">
            <form id="lp-form" class="lp-form" method="POST" action="/contacto" novalidate>
                <input type="hidden" name="t" id="lp-t" value="0">
                <div class="lp-hp" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
                <div class="lp-row2">
                    <label>Nombre *<input type="text" name="nombre" maxlength="150" autocomplete="name" required></label>
                    <label>Empresa *<input type="text" name="empresa" maxlength="150" autocomplete="organization" required></label>
                </div>
                <div class="lp-row2">
                    <label>Correo *<input type="email" name="correo" maxlength="150" autocomplete="email" required></label>
                    <label>Teléfono *<input type="tel" name="telefono" maxlength="25" autocomplete="tel" placeholder="Con lada" required></label>
                </div>
                <div class="lp-row2">
                    <label>Puesto<input type="text" name="puesto" maxlength="100" autocomplete="organization-title" placeholder="Ej. Gerente de obra"></label>
                    <label>Plan de interés
                        <select name="plan" id="lp-plan"><option value="">Selecciona…</option><?php foreach (\Model\Contacto::PLANES as $o): ?><option><?php echo s($o); ?></option><?php endforeach; ?></select>
                    </label>
                </div>
                <div class="lp-row2">
                    <label>Proyectos simultáneos
                        <select name="proyectos_aprox"><option value="">Selecciona…</option><?php foreach (\Model\Contacto::PROYECTOS as $o): ?><option><?php echo s($o); ?></option><?php endforeach; ?></select>
                    </label>
                    <label>¿Cómo prefieres que te contactemos?
                        <select name="preferencia"><?php foreach (\Model\Contacto::PREFERENCIAS as $k => $o): ?><option value="<?php echo s($k); ?>"><?php echo s($o); ?></option><?php endforeach; ?></select>
                    </label>
                </div>
                <label>¿Cómo nos conociste?
                    <select name="origen"><option value="">Selecciona…</option><?php foreach (\Model\Contacto::ORIGENES as $o): ?><option><?php echo s($o); ?></option><?php endforeach; ?></select>
                </label>
                <label>Mensaje *<textarea name="mensaje" rows="4" maxlength="4000" placeholder="Cuéntanos sobre tu obra o lo que te gustaría resolver" required></textarea></label>
                <label class="lp-check-l"><input type="checkbox" name="privacidad" value="1" required> <span>Acepto que usen mis datos para contactarme sobre ProjectWeb.</span></label>
                <div class="lp-form-err" id="lp-err" role="alert" hidden></div>
                <button type="submit" class="lp-btn lp-btn-primary lp-btn-lg lp-btn-block" id="lp-send"><i class="fa-solid fa-paper-plane"></i> Enviar mensaje</button>
            </form>
            <div class="lp-form-ok" id="lp-ok" hidden>
                <span><i class="fa-solid fa-circle-check"></i></span>
                <h3>¡Mensaje enviado!</h3>
                <p>Gracias por escribirnos. Te contactaremos lo antes posible.</p>
            </div>
        </div>
    </div>
</section>

<footer class="lp-foot">
    <div class="lp-wrap">
        <span class="lp-logo"><span class="lp-logo-i"><i class="fa-solid fa-helmet-safety"></i></span> ProjectWeb</span>
        <span>© <?php echo date('Y'); ?> ProjectWeb · Sistema de programas de obra · <a href="/terminos" class="lp-foot-link">Términos y condiciones de uso</a></span>
        <span class="lp-credit">Imágenes: <a href="https://unsplash.com" target="_blank" rel="noopener">Unsplash</a>, <a href="https://polyhaven.com" target="_blank" rel="noopener">Poly Haven</a> y <a href="https://commons.wikimedia.org" target="_blank" rel="noopener">Wikimedia Commons</a></span>
    </div>
</footer>

<script>
// Aparición suave al hacer scroll
(function () {
    const els = document.querySelectorAll('.lp-rev');
    if (!('IntersectionObserver' in window)) { els.forEach(e => e.classList.add('in')); return; }
    const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .12 });
    els.forEach(e => io.observe(e));
    document.querySelectorAll('.lp-links a').forEach(a => a.addEventListener('click', () => { document.getElementById('lp-menu').checked = false; }));
})();

// Contadores animados
(function () {
    const nums = document.querySelectorAll('.lp-stats b[data-n]');
    if (!nums.length || !('IntersectionObserver' in window)) return;   // sin JS/observer queda el valor final del HTML
    const io = new IntersectionObserver(es => es.forEach(e => {
        if (!e.isIntersecting) return;
        io.unobserve(e.target);
        const el = e.target, fin = +el.dataset.n, t0 = performance.now(); if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        (function paso(t) { const p = Math.min(1, (t - t0) / 1100); el.textContent = Math.round(fin * (1 - Math.pow(1 - p, 3))) + (p === 1 ? (el.dataset.suf || '') : ''); if (p < 1) requestAnimationFrame(paso); })(t0);
    }), { threshold: .6 });
    nums.forEach(n => io.observe(n));
})();

// Recorrido del producto: pestañas con rotación automática
(function () {
    const tabs = [...document.querySelectorAll('.lp-tab')], imgs = [...document.querySelectorAll('.lp-shots img')];
    const txt = document.getElementById('lp-tour-txt'), url = document.getElementById('lp-url');
    const textos = <?php echo json_encode(array_map(fn($t) => $t[3], $tour), JSON_UNESCAPED_UNICODE); ?>;
    let actual = 0, timer = null;
    function ir(i, manual) {
        actual = i;
        tabs.forEach((t, k) => t.classList.toggle('on', k === i));
        imgs.forEach((m, k) => m.classList.toggle('on', k === i));
        txt.textContent = textos[i]; url.textContent = imgs[i].dataset.url;
        if (manual) { clearInterval(timer); timer = null; }
    }
    tabs.forEach((t, i) => t.addEventListener('click', () => ir(i, true)));
    const sec = document.getElementById('recorrido');
    if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        new IntersectionObserver(es => es.forEach(e => {
            if (e.isIntersecting && !timer && !sec.dataset.manual) timer = setInterval(() => ir((actual + 1) % tabs.length), 5200);
            else if (!e.isIntersecting) { clearInterval(timer); timer = null; }
        }), { threshold: .35 }).observe(sec);
        tabs.forEach(t => t.addEventListener('click', () => { sec.dataset.manual = '1'; }));
    }
})();

// Formulario de contacto
(function () {
    const f = document.getElementById('lp-form'); if (!f) return;
    const t0 = Date.now(), err = document.getElementById('lp-err'), btn = document.getElementById('lp-send');
    // Los botones de plan preseleccionan el plan en el formulario
    document.querySelectorAll('[data-plan]').forEach(a => a.addEventListener('click', () => { document.getElementById('lp-plan').value = a.dataset.plan; }));
    f.addEventListener('submit', async e => {
        e.preventDefault();
        err.hidden = true;
        document.getElementById('lp-t').value = t0;
        if (!f.checkValidity()) { f.reportValidity(); return; }
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando…';
        try {
            const r = await fetch('/contacto', { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json' } });
            const d = await r.json();
            if (d.success) { f.hidden = true; document.getElementById('lp-ok').hidden = false; return; }
            err.innerHTML = (d.errores || ['No se pudo enviar. Intenta de nuevo.']).map(x => '<div>' + x.replace(/[<>&]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c])) + '</div>').join(''); err.hidden = false;
        } catch (_) { err.textContent = 'No se pudo enviar. Revisa tu conexión e intenta de nuevo.'; err.hidden = false; }
        btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar mensaje';
    });
    if (new URLSearchParams(location.search).get('contacto') === 'ok') { f.hidden = true; document.getElementById('lp-ok').hidden = false; }
})();
</script>
