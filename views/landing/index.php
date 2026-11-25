<?php
$planes = [];
$planesAhorro = [];
foreach (\Classes\Plan::comerciales() as $p) {
    $planes[] = [
        'id' => $p['clave'],
        'nombre' => 'Plan ' . $p['nombre'],
        'precio' => \Classes\Plan::precio($p['clave']),
        'precio_anual' => \Classes\Plan::precio($p['clave'], true),
        'destacado' => !empty($p['destacado']),
        'lema' => $p['lema'],
        'items' => [
            ($p['obras'] === null ? 'Obras <strong>ilimitadas</strong>' : 'Hasta <strong>' . (int)$p['obras'] . ' obras activas</strong>'),
            ($p['usuarios'] === null ? '<strong>Usuarios ilimitados</strong>' : '<strong>' . (int)$p['usuarios'] . ' usuarios</strong> de edición'),
            ($p['almacenamiento_gb'] === null ? 'Almacenamiento sin tope total' : '<strong>' . (int)$p['almacenamiento_gb'] . ' GB</strong> de almacenamiento'),
            'Todas las herramientas de obra',
            'Exportación a PDF, Excel y MS Project',
        ],
    ];
    $planesAhorro[] = [
        'clave' => $p['clave'],
        'nombre' => 'Plan ' . $p['nombre'],
        'usuarios' => $p['usuarios'],
        'obras' => $p['obras'],
        'precio_anual' => (int)$p['precio_anual'],
    ];
}

$filtroInicial = $_GET['frente'] ?? 'todas';
if (!is_string($filtroInicial) || (!isset($frentes[$filtroInicial]) && $filtroInicial !== 'todas')) {
    $filtroInicial = 'todas';
}

// Recorrido: solo las capturas que existen.
$tour = [
    ['programa', 'Programa y Gantt', 'fa-chart-gantt', 'Partidas por disciplina, actividades e hitos con avance, prioridad y restricciones. Las fechas respetan el calendario real de la obra.'],
    ['4wla', 'Lookahead 4 semanas', 'fa-binoculars', 'La semana actual y las 4 siguientes con PTA y limitaciones: qué puede arrancar y qué no.'],
    ['lastplanner', 'Last Planner', 'fa-note-sticky', 'Tarjetas por día y por partida listas para imprimir, calculadas desde tu programa.'],
    ['vplan', 'vPlan', 'fa-sheet-plastic', 'Láminas con plano, tabla del programa, recovery, restricciones y punch list para las juntas de obra.'],
    ['reportes', 'Reporte diario', 'fa-clipboard-list', 'Personal, actividades y fotografías de cada día, listos para compartir con el cliente.'],
    ['msr', 'MSR · materiales', 'fa-truck-ramp-box', 'Estatus de submittals, compra y entrega de materiales con fotos y proveedores.'],
    ['lineas-base', 'Líneas base', 'fa-timeline', 'Compara el programa actual contra una foto anterior y explica qué se movió.'],
    ['redactor', 'Redactor', 'fa-file-pen', 'Actas, memorias y procedimientos con versión y exportación a Word, ODT o PDF.'],
    ['comparador', 'Comparador', 'fa-code-compare', 'Dos revisiones de un plano con deslizador para ver qué cambió.'],
    ['view360', 'View360', 'fa-street-view', 'Un plano con zonas que abren fotos 360° de la obra: recorridos virtuales con notas para el cliente.'],
];
$tour = array_values(array_filter($tour, fn($t) => is_file(__DIR__ . '/../../public/build/img/landing/tour-' . $t[0] . '.jpg')));

// Comparativa por tipo de herramienta, sin nombrar marcas.
if (!function_exists('cmp_celda')) {
    function cmp_celda($v)
    {
        $ico = ['si' => ['fa-circle-check', 'ok'], 'parcial' => ['fa-circle-half-stroke', 'mid'], 'no' => ['fa-circle-xmark', 'no']];
        [$k, $txt] = $v;
        return '<i class="fa-solid ' . $ico[$k][0] . ' lp-cmp-' . $ico[$k][1] . '"></i> <span>' . s($txt) . '</span>';
    }
}
$comparativa = [
    ['Programa, Gantt y calendario real', ['si', 'Con festivos, paros y clima'], ['si', 'Sí, con configuración'], ['si', 'Sí, en el programa'], ['parcial', 'Gantt básico']],
    ['Ruta crítica y dependencias', ['si', 'FS, SS, FF y SF'], ['si', 'Sí'], ['si', 'Sí'], ['parcial', 'Limitado']],
    ['Lookahead y Last Planner', ['si', 'Calculados del programa'], ['parcial', 'Según el módulo'], ['no', 'A mano en Excel'], ['no', 'No incluido']],
    ['Materiales, submittals y MSR', ['si', 'Ligados a las actividades'], ['si', 'Con módulos'], ['no', 'Hojas aparte'], ['parcial', 'Campos personalizados']],
    ['Reporte diario, bitácora y minutas', ['si', 'Con fotos y PDF'], ['parcial', 'Módulos separados'], ['no', 'Word y WhatsApp'], ['parcial', 'Comentarios sueltos']],
    ['Evidencias, View360 y planos', ['si', 'Incluidos'], ['parcial', 'Según el módulo'], ['no', 'Carpetas aparte'], ['parcial', 'Adjuntos']],
    ['Una sola captura para todo', ['si', 'Todo se actualiza junto'], ['parcial', 'Integración costosa'], ['no', 'Se captura varias veces'], ['parcial', 'Depende de integraciones']],
    ['Control de cambios y líneas base', ['si', 'Con impacto y comparación'], ['si', 'Con módulo'], ['no', 'Excel y correos'], ['parcial', 'Solo seguimiento de tareas']],
    ['Documentos y tablas de obra', ['si', 'Redactor y SheetFlow integrados'], ['parcial', 'Fuera de la suite'], ['no', 'Word y Excel aparte'], ['parcial', 'Notas o adjuntos']],
    ['Comparar revisiones de planos', ['si', 'Comparador con deslizador'], ['parcial', 'Módulo aparte'], ['no', 'Programa de dibujo aparte'], ['no', 'No incluido']],
    ['Acceso del cliente solo lectura', ['si', 'Por herramienta'], ['parcial', 'Licencia por usuario'], ['no', 'Se envían PDF'], ['parcial', 'Con licencia por usuario']],
    ['Instalación y licencias', ['si', 'Solo navegador'], ['no', 'Implementación y licencias'], ['no', 'Licencias por equipo'], ['si', 'Navegador']],
    ['Exporta a MS Project y Excel', ['si', 'PDF, Excel, ODS, XML y POD'], ['si', 'Sí'], ['si', 'Nativo'], ['parcial', 'Solo CSV o Excel']],
];

$pilares = [
    ['1', 'plataforma', 'en lugar de hasta 8 tipos de software', 'Programación, ofimática, PDF, campo, materiales, tablero y almacenamiento en el mismo lugar.'],
    ['65–87', '% menos', 'en costo de software, según el tamaño del equipo', 'Con las licencias que realmente reemplazas, no con una suma de todo para cada persona.'],
    ['1', 'captura, 7 vistas', 'un dato alimenta toda la obra', 'Actualizas una actividad y cambian el Gantt, el lookahead, el vPlan, el reporte, el MSR y la Curva S.'],
];
$ventajas = [
    ['fa-layer-group', 'Una plataforma, no tres o cuatro programas', 'Lo que antes exigía varios programas y archivos sueltos ahora comparte los mismos datos y la misma forma de trabajar.'],
    ['fa-shield-halved', 'Información que cuadra a la primera', 'Capturas una vez y el programa, el lookahead, el reporte, el vPlan y las curvas hablan del mismo proyecto.'],
    ['fa-mobile-screen-button', 'En cualquier equipo, sin reinstalar', 'Cambia de computadora o entra desde el celular: no recompras licencias ni reinstalas nada.'],
    ['fa-people-roof', 'Hecha para el control de obra', 'Last Planner, lookahead, restricciones, MSR y submittals vienen pensados para el contratista, no adaptados desde un gestor genérico de tareas.'],
    ['fa-lock', 'Control de quién ve qué', 'Tres roles y accesos de visitante por herramienta: el cliente consulta avance y evidencias sin tocar el programa.'],
    ['fa-puzzle-piece', 'Todo en uno, de verdad', 'Del programa al acta, de la compra al cambio aprobado: los mismos datos se mueven entre las herramientas.'],
];
$pasos = [
    ['Crea tu proyecto', 'Define fechas, jornada semanal y festivos. El sistema prepara el calendario operativo.'],
    ['Arma el programa', 'Captura partidas, actividades y restricciones. Se calculan fechas, Gantt, 4WLA y Last Planner.'],
    ['Da seguimiento', 'Registra avance, reportes diarios, materiales y evidencias. Todo se refleja en el programa, el vPlan y las curvas.'],
    ['Controla, documenta y comparte', 'Lleva los cambios contra una línea base, redacta actas, da acceso de solo lectura al cliente y exporta.'],
];
$serviciosLanding = [
    [
        'icono' => 'fa-cloud',
        'nombre' => 'Solo plataforma',
        'precio' => 'Desde ' . \Classes\Plan::precio('obra') . ' MXN/mes',
        'para' => 'Tu equipo opera la plataforma y lleva su propio seguimiento.',
        'incluye' => ['Todas las herramientas de obra', 'Usuarios y obras según el plan', 'Manual y soporte de puesta en marcha'],
    ],
    [
        'icono' => 'fa-handshake-angle',
        'nombre' => 'Plataforma + acompañamiento',
        'precio' => 'Desde ' . \Classes\LandingContenido::mxn(\Classes\LandingContenido::precioServicio('quincenal')) . ' MXN/mes',
        'para' => 'Nosotros actualizamos y ordenamos; tú tomas las decisiones técnicas.',
        'incluye' => ['Todo lo del plan de plataforma', 'Programa y reportes al día', 'Juntas quincenales o semanales'],
        'destacado' => true,
    ],
    [
        'icono' => 'fa-people-group',
        'nombre' => 'Servicio completo',
        'precio' => 'Desde ' . \Classes\LandingContenido::mxn(\Classes\LandingContenido::precioServicio('semanal')) . ' MXN/mes',
        'para' => 'Un equipo administrativo dedicado a mantener el control de la obra.',
        'incluye' => ['Seguimiento semanal', 'Materiales, cambios y documentación', 'Informes para dirección y cliente'],
    ],
];
$ahorroDefault = \Classes\Ahorro::calcular(['direccion' => 1, 'residente' => 2, 'administracion' => 1, 'campo' => 2], 3);
$frentesResumen = $frentes;
?>

<?php include __DIR__ . '/_nav.php'; ?>

<main id="contenido">
<section class="lp-hero" style="--hero: url('/build/img/landing/hero.jpg')">
    <span class="lp-orb lp-orb1"></span><span class="lp-orb lp-orb2"></span>
    <div class="lp-wrap lp-hero-in">
        <div class="lp-hero-txt lp-rev">
            <span class="lp-pill"><i class="fa-solid fa-bolt"></i> Control de obra en tiempo real</span>
            <h1>Toda tu obra,<br><span class="lp-grad">conectada y bajo control.</span></h1>
            <p class="lp-lead">Programa, campo, materiales, documentos y control en una sola plataforma. Menos programas, menos capturas y decisiones con los datos del día.</p>
            <div class="lp-cta">
                <?php if ($demoDisponible): ?><a href="/demo" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-play"></i> Ver demo en vivo</a><?php endif; ?>
                <a href="#ahorro" class="lp-btn lp-btn-ghost lp-btn-lg"><i class="fa-solid fa-calculator"></i> Calcular mi ahorro</a>
                <?php if ($whatsappUrl): ?><a href="<?php echo s($whatsappUrl); ?>" class="lp-btn lp-btn-wa lp-btn-lg" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a><?php endif; ?>
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
            <div class="lp-mock-top"><i></i><i></i><i></i><span>Torre Aurora · Programa de obra</span></div>
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
        <div class="lp-rev"><b data-n="<?php echo (int)$totalHerramientas; ?>"><?php echo (int)$totalHerramientas; ?></b><span>herramientas conectadas</span></div>
        <div class="lp-rev" style="--d:80ms"><b data-n="5">5</b><span>frentes de obra</span></div>
        <div class="lp-rev" style="--d:160ms"><b data-n="5">5</b><span>formatos de exportación</span></div>
        <div class="lp-rev" style="--d:240ms"><b data-n="1" data-suf=" fuente">1 fuente</b><span>única de verdad</span></div>
    </div>
</section>

<div class="lp-marquee" aria-hidden="true">
    <div class="lp-marquee-in">
        <?php for ($k = 0; $k < 2; $k++): foreach (['Obra civil', 'Cimentación', 'Estructura', 'Albañilería', 'Instalación eléctrica', 'Hidrosanitaria', 'Contra incendio', 'Aire acondicionado', 'Voz y datos', 'Seguridad electrónica', 'Acabados', 'Urbanización'] as $d): ?>
            <span><i class="fa-solid fa-circle"></i> <?php echo s($d); ?></span>
        <?php endforeach; endfor; ?>
    </div>
</div>

<section class="lp-sec lp-connected" id="conectado">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Conectado entre sí</span>
            <h2>Un dato,<br>toda la obra</h2>
            <p>Registras el avance una vez y el resto se actualiza solo. Sin copiar de un archivo a otro, sin versiones que alguien olvidó subir.</p>
        </div>
        <div class="lp-flow lp-rev">
            <article class="lp-flow-origen">
                <span class="lp-flow-kicker"><i class="fa-solid fa-pen"></i> Captura en campo</span>
                <h3>Colado de zapata Z-04</h3>
                <div class="lp-flow-dato"><b>45%</b><span>avance de la actividad</span></div>
                <p>Un solo movimiento: el residente actualiza el avance desde el celular.</p>
            </article>
            <div class="lp-flow-nodos">
                <?php foreach ([
                    ['fa-chart-gantt', 'Gantt', 'La barra y el avance de la partida cambian.'],
                    ['fa-binoculars', 'Lookahead 4S', 'La actividad entra o sale de la ventana semanal.'],
                    ['fa-note-sticky', 'Last Planner', 'El post-it del día refleja el avance real.'],
                    ['fa-sheet-plastic', 'vPlan', 'La lámina de la junta muestra el mismo dato.'],
                    ['fa-clipboard-list', 'Reporte diario', 'El reporte del día queda con la actividad actualizada.'],
                    ['fa-chart-line', 'Curva S', 'La curva planeada contra real se recalcula.'],
                ] as [$ico, $t, $d]): ?>
                    <article class="lp-flow-nodo">
                        <span><i class="fa-solid <?php echo $ico; ?>"></i></span>
                        <div><h4><?php echo s($t); ?></h4><p><?php echo s($d); ?></p></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <p class="lp-connected-nota lp-rev"><i class="fa-solid fa-circle-info"></i> Lo mismo pasa con una restricción, un material, una orden de cambio o una foto: se captura una vez y viaja a todas las vistas.</p>
    </div>
</section>

<section class="lp-sec lp-alt" id="todo-en-uno">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Todo en uno</span>
            <h2>Cinco frentes de obra.<br><?php echo (int)$totalHerramientas; ?> herramientas.<br>Una sola fuente de verdad.</h2>
            <p>Cada frente trae su caja de herramientas completa y comparte los mismos datos. No es un módulo suelto: es la misma obra vista desde donde estás.</p>
        </div>
        <div class="lp-fronts lp-rev">
            <?php foreach ($frentesResumen as $clave => $frente): ?>
                <article class="lp-front" data-frente="<?php echo s($clave); ?>">
                    <div class="lp-front-top">
                        <span class="lp-front-ico"><i class="fa-solid <?php echo s($frente['icono']); ?>"></i></span>
                        <span class="lp-front-count"><?php echo (int)$frente['total']; ?></span>
                    </div>
                    <h3><?php echo s($frente['titulo']); ?></h3>
                    <p class="lp-front-desc"><?php echo s($frente['desc']); ?></p>
                    <ul class="lp-front-chips">
                        <?php foreach (array_slice($frente['herramientas'], 0, 6) as [$nombre]): ?>
                            <li><?php echo s($nombre); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="#funciones" class="lp-front-link" data-filtro-frente="<?php echo s($clave); ?>">Ver las <?php echo (int)$frente['total']; ?> herramientas <i class="fa-solid fa-arrow-right"></i></a>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="lp-fronts-extra lp-rev">
            <div><i class="fa-solid fa-building-columns"></i> <strong>Y toda la administración:</strong> costos, conceptos, estimaciones, contratos, subcontratos, riesgos, cierre, recursos, equipo, directorio, historial y más.</div>
            <a href="#funciones" class="lp-front-link" data-filtro-frente="controlar">Ver las 16 de control <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <p class="lp-hub-nota lp-rev"><i class="fa-solid fa-circle-info"></i> La IA y la API están desde el Plan Pro; SSO y marca del cliente, desde Constructora; multiempresa, desde Corporativo.</p>
    </div>
</section>

<section class="lp-sec" id="funciones">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Funciones</span>
            <h2>Todo lo que tu obra necesita,<br>conectado entre sí</h2>
            <p>Un solo programa alimenta el Gantt, el lookahead, los post-its, el vPlan y los reportes. Actualizas una vez y se refleja en todo.</p>
        </div>

        <div class="lp-explorer lp-rev" id="lp-explorer" data-filtro-inicial="<?php echo s($filtroInicial); ?>">
            <div class="lp-explorer-bar">
                <div class="lp-explorer-tabs" role="group" aria-label="Filtrar herramientas por frente">
                    <button type="button" class="lp-chip-btn on" data-filtro="todas" aria-pressed="true">Todas <b><?php echo (int)$totalHerramientas; ?></b></button>
                    <?php foreach ($frentes as $clave => $frente): ?>
                        <button type="button" class="lp-chip-btn" data-filtro="<?php echo s($clave); ?>" aria-pressed="false"><?php echo s($frente['titulo']); ?> <b><?php echo (int)$frente['total']; ?></b></button>
                    <?php endforeach; ?>
                </div>
                <label class="lp-explorer-busca">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <span class="sr-only">Buscar herramienta</span>
                    <input type="search" id="lp-busca" placeholder="Busca una herramienta: Last Planner, MSR, vPlan…" autocomplete="off">
                </label>
            </div>
            <p class="lp-explorer-count" id="lp-explorer-count" role="status" aria-live="polite">Mostrando <?php echo (int)$totalHerramientas; ?> herramientas</p>
            <div class="lp-tools" id="lp-tools">
                <?php foreach ($frentes as $clave => $frente): foreach ($frente['herramientas'] as [$nombre, $icono, $detalle]): ?>
                    <article class="lp-tool" data-frente="<?php echo s($clave); ?>" data-nombre="<?php echo s($nombre); ?>" data-detalle="<?php echo s($detalle); ?>">
                        <span class="lp-tool-ico"><i class="fa-solid <?php echo s($icono); ?>"></i></span>
                        <div class="lp-tool-txt">
                            <span class="lp-tool-frente"><?php echo s($frente['titulo']); ?></span>
                            <h3><?php echo s($nombre); ?></h3>
                            <p><?php echo s($detalle); ?></p>
                        </div>
                    </article>
                <?php endforeach; endforeach; ?>
            </div>
            <p class="lp-explorer-vacio" id="lp-explorer-vacio" hidden><i class="fa-solid fa-magnifying-glass"></i> No encontramos esa herramienta. Prueba con otro nombre o cambia de frente.</p>
        </div>
        <p class="lp-explorer-nota lp-rev"><i class="fa-solid fa-circle-info"></i> ¿Buscas la lista completa de la plataforma? Las herramientas de administración de la cuenta, IA y ajustes del sistema viven en el menú del administrador.</p>
    </div>
</section>

<section class="lp-sec lp-alt" id="ventajas">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">¿Por qué ProjectWeb?</span>
            <h2>Menos programas, menos costo,<br>menos estrés</h2>
            <p>Todo lo que antes se repartía entre varios programas y archivos sueltos, ahora vive junto y disponible donde lo necesites.</p>
        </div>
        <div class="lp-pillars lp-rev">
            <?php foreach ($pilares as $i => [$valor, $unidad, $etiqueta, $detalle]): ?>
                <article class="lp-pillar" style="--d:<?php echo $i * 80; ?>ms">
                    <span class="lp-pillar-valor"><?php echo s($valor); ?><small><?php echo s($unidad); ?></small></span>
                    <h3><?php echo s($etiqueta); ?></h3>
                    <p><?php echo s($detalle); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="lp-grid lp-grid-3 lp-ventajas lp-rev">
            <?php foreach ($ventajas as $i => [$ico, $t, $d]): ?>
                <article class="lp-card">
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <h3><?php echo s($t); ?></h3>
                    <p><?php echo s($d); ?></p>
                </article>
            <?php endforeach; ?>
        </div>

        <details class="lp-details lp-rev">
            <summary><span><i class="fa-solid fa-table-list"></i> Ver comparativa completa (13 criterios)</span><i class="fa-solid fa-chevron-down"></i></summary>
            <div class="lp-cmp-wrap">
                <table class="lp-cmp">
                    <thead>
                        <tr><th></th><th class="lp-cmp-us">ProjectWeb</th><th>Suite empresarial de gestión de obra</th><th>Programas de escritorio + Excel</th><th>Apps genéricas de tareas</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($comparativa as [$crit, $nosotros, $suite, $escritorio, $app]): ?>
                        <tr>
                            <th scope="row"><?php echo s($crit); ?></th>
                            <td class="lp-cmp-us" data-label="ProjectWeb"><?php echo cmp_celda($nosotros); ?></td>
                            <td data-label="Suite empresarial"><?php echo cmp_celda($suite); ?></td>
                            <td data-label="Escritorio + Excel"><?php echo cmp_celda($escritorio); ?></td>
                            <td data-label="Apps genéricas"><?php echo cmp_celda($app); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="lp-cmp-nota"><i class="fa-solid fa-circle-info"></i> Comparación general por tipo de herramienta, no de un producto en particular. Los programas de escritorio siguen siendo útiles: por eso exportamos a MS Project, Excel y ProjectLibre.</p>
        </details>
    </div>
</section>

<section class="lp-sec" id="ahorro">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Ahorro</span>
            <h2>Cuánto te ahorras al cambiar<br>varios programas por una plataforma</h2>
            <p>Calculado con las licencias que ProjectWeb reemplaza y el plan que cubre a tu equipo. Sin sumar una licencia de todo para cada persona.</p>
        </div>

        <div class="lp-calc lp-rev" id="lp-calc"
             data-costos="<?php echo s(json_encode(array_map(fn($r) => $r['costo'], $ahorroRoles))); ?>"
             data-planes="<?php echo s(json_encode($planesAhorro)); ?>">
            <div class="lp-calc-form">
                <h3><i class="fa-solid fa-users-gear"></i> Arma tu equipo</h3>
                <p class="lp-calc-ayuda">Cuenta a las personas que necesitan licencia. La calculadora recomienda el plan más pequeño que las cubre.</p>
                <?php foreach ($ahorroRoles as $clave => $rol): ?>
                    <div class="lp-stepper" data-rol="<?php echo s($clave); ?>">
                        <div class="lp-stepper-txt">
                            <strong><?php echo s($rol['nombre']); ?></strong>
                            <small><?php echo s($rol['detalle']); ?></small>
                        </div>
                        <div class="lp-stepper-ctl">
                            <button type="button" data-paso="-1" aria-label="Quitar <?php echo s($rol['nombre']); ?>">−</button>
                            <output data-valor="<?php echo s($clave); ?>"><?php echo (int)$rol['inicial']; ?></output>
                            <button type="button" data-paso="1" aria-label="Agregar <?php echo s($rol['nombre']); ?>">+</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <label class="lp-calc-obras">Obras activas
                    <input type="number" id="lp-obras" min="1" max="500" value="3" inputmode="numeric">
                </label>
                <button type="button" class="lp-btn lp-btn-primary lp-btn-block" id="lp-calcular"><i class="fa-solid fa-calculator"></i> Calcular mi ahorro</button>
                <p class="lp-calc-nota">El plan se comparte entre todo el equipo; no se paga por usuario.</p>
            </div>

            <div class="lp-calc-result" id="lp-resultado" aria-live="polite">
                <div class="lp-result-top">
                    <span class="lp-result-eyebrow">Plan recomendado</span>
                    <span class="lp-result-plan" id="lp-plan-calc"><?php echo s($ahorroDefault['plan']['etiqueta']); ?></span>
                </div>
                <div class="lp-result-grid">
                    <div><span>Licencias tradicionales</span><b id="lp-trad"><?php echo s(\Classes\Ahorro::mxn($ahorroDefault['tradicional_anio'])); ?></b><small>MXN / año</small></div>
                    <div><span>ProjectWeb</span><b id="lp-pw"><?php echo s(\Classes\Ahorro::mxn($ahorroDefault['pw_anio'])); ?></b><small>MXN / año</small></div>
                </div>
                <div class="lp-result-ahorro">
                    <span>Ahorras</span>
                    <b id="lp-ahorro"><?php echo s(\Classes\Ahorro::mxn($ahorroDefault['ahorro_anio'])); ?></b>
                    <em id="lp-pct"><?php echo (int)$ahorroDefault['ahorro_pct']; ?>% menos</em>
                    <small>al año, en software</small>
                </div>
                <ul class="lp-result-desglose" id="lp-desglose">
                    <?php foreach ($ahorroDefault['desglose'] as $d): ?>
                        <li><span><?php echo s($d['nombre']); ?> · <?php echo (int)$d['cantidad']; ?></span><b><?php echo s(\Classes\Ahorro::mxn($d['costo_mes'])); ?>/mes</b></li>
                    <?php endforeach; ?>
                </ul>
                <p class="lp-result-nota"><i class="fa-solid fa-circle-info"></i> Estimación de referencia. Revisa los supuestos abajo.</p>
            </div>
        </div>

        <div class="lp-scenarios lp-rev">
            <table class="lp-cmp lp-ahorro">
                <thead>
                    <tr><th>Escenario</th><th>Equipo</th><th>Licencias tradicionales / año</th><th class="lp-cmp-us">ProjectWeb / año</th><th>Ahorro / año</th></tr>
                </thead>
                <tbody>
                <?php foreach ($ahorroEscenarios as $e): $c = $e['calculo']; ?>
                    <tr>
                        <th scope="row"><?php echo s($e['nombre']); ?><small><?php echo s($e['detalle']); ?></small></th>
                        <td data-label="Equipo"><?php echo (int)$c['usuarios']; ?> personas · <?php echo (int)$c['obras']; ?> obra<?php echo $c['obras'] === 1 ? '' : 's'; ?></td>
                        <td data-label="Licencias tradicionales / año"><?php echo s(\Classes\Ahorro::mxn($c['tradicional_anio'])); ?></td>
                        <td class="lp-cmp-us" data-label="ProjectWeb / año"><?php echo s(\Classes\Ahorro::mxn($c['pw_anio'])); ?> <small><?php echo s($c['plan']['etiqueta']); ?></small></td>
                        <td class="lp-ahorro-n" data-label="Ahorro / año"><strong><?php echo s(\Classes\Ahorro::mxn($c['ahorro_anio'])); ?></strong> <span><?php echo (int)$c['ahorro_pct']; ?> % menos</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <details class="lp-details lp-details-ahorro lp-rev">
            <summary><span><i class="fa-solid fa-list-check"></i> Ver qué reemplaza y cómo lo calculamos</span><i class="fa-solid fa-chevron-down"></i></summary>
            <div class="lp-reemplazos">
                <?php foreach ($ahorroReemplazos as $r): ?>
                    <article class="lp-reemplazo">
                        <span class="lp-reemplazo-ico"><i class="fa-solid <?php echo s($r['icono']); ?>"></i></span>
                        <div>
                            <div class="lp-reemplazo-top"><h4><?php echo s($r['nombre']); ?></h4><b><?php echo s(\Classes\Ahorro::mxn($r['costo'])); ?>/mes</b></div>
                            <p><strong>Tradicional:</strong> <?php echo s($r['tradicional']); ?></p>
                            <p><strong>ProjectWeb:</strong> <?php echo s($r['pw']); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <ul class="lp-supuestos">
                <?php foreach ($ahorroSupuestos as $sup): ?><li><i class="fa-solid fa-circle-info"></i> <?php echo s($sup); ?></li><?php endforeach; ?>
            </ul>
        </details>

        <ul class="lp-ahorro-extra lp-rev">
            <li><i class="fa-solid fa-clock"></i><span><strong>Menos horas de captura.</strong> Capturas una vez y el programa, el lookahead, los reportes y el vPlan se actualizan juntos.</span></li>
            <li><i class="fa-solid fa-file-word"></i><span><strong>Menos licencias de ofimática y de dibujo.</strong> Actas, tablas con fórmulas y comparación de planos viven dentro de la misma plataforma.</span></li>
            <li><i class="fa-solid fa-people-arrows"></i><span><strong>Menos retrabajo.</strong> Todos ven la misma información, así no se corrigen datos distintos entre archivos.</span></li>
            <li><i class="fa-solid fa-user-plus"></i><span><strong>Crece sin volver a cotizar.</strong> Sumar usuarios, obras o disciplinas no obliga a comprar otro paquete ni a migrar de sistema.</span></li>
        </ul>
        <p class="lp-cmp-nota lp-rev"><i class="fa-solid fa-circle-info"></i> Cifras en pesos mexicanos, planes anuales de 10 meses. Los costos de licencias son promedios de referencia por rol; no incluyen hardware, internet, implementación ni impuestos. Si se comparten licencias, el ahorro real será menor.</p>
    </div>
</section>

<section class="lp-sec lp-alt" id="planeacion">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Planeación</span>
            <h2>Del plano a la junta,<br>sin saltos</h2>
            <p>Programa con calendario real, revisa las próximas semanas, lleva la junta con vPlan y Last Planner y compara contra tu línea base. Todo sale del mismo programa.</p>
        </div>
        <div class="lp-plan-flow lp-rev">
            <?php foreach ([
                ['01', 'Programa', 'Partidas, actividades, dependencias y ruta crítica.', 'fa-list-check'],
                ['02', 'Mira la obra', 'Lookahead 4S y planificador por material.', 'fa-binoculars'],
                ['03', 'Lleva la junta', 'Last Planner, vPlan y minutas con compromisos.', 'fa-handshake'],
                ['04', 'Compara y ajusta', 'Líneas base, cambios y curvas de avance.', 'fa-code-compare'],
            ] as [$n, $t, $d, $ico]): ?>
                <article class="lp-plan-paso">
                    <b><?php echo s($n); ?></b>
                    <span><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <h3><?php echo s($t); ?></h3>
                    <p><?php echo s($d); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="lp-plan-mosaico lp-rev">
            <?php foreach ([
                ['programa', 'Programa y Gantt', 'Fechas, avance, prioridad y restricciones en una tabla WBS.'],
                ['4wla', 'Lookahead 4S', 'La semana actual y las cuatro siguientes con sus limitaciones.'],
                ['lastplanner', 'Last Planner', 'Tarjetas del día listas para imprimir y llevar a la junta.'],
                ['vplan', 'vPlan', 'Planos, tablas, recovery y punch list en la misma lámina.'],
            ] as [$id, $t, $d]): ?>
                <figure class="lp-plan-shot">
                    <img src="/build/img/landing/tour-<?php echo s($id); ?>.jpg" alt="<?php echo s($t); ?> del proyecto demo" loading="lazy" decoding="async">
                    <figcaption><strong><?php echo s($t); ?></strong><span><?php echo s($d); ?></span></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <div class="lp-plan-cta lp-rev">
            <a href="#funciones" class="lp-btn lp-btn-ghost" data-filtro-frente="planear"><i class="fa-solid fa-list-check"></i> Ver las 11 herramientas de planeación</a>
            <?php if ($demoDisponible): ?><a href="/demo" class="lp-btn lp-btn-primary"><i class="fa-solid fa-play"></i> Recorrer el demo</a><?php endif; ?>
        </div>
    </div>
</section>

<?php if ($tour): ?>
<section class="lp-sec" id="recorrido">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Recorrido por el producto</span>
            <h2>Así se ve una obra<br>multidisciplinaria en ProjectWeb</h2>
            <p>Pantallas reales del proyecto demo: programa, lookahead, Last Planner, vPlan, reportes, materiales y más.</p>
        </div>
        <div class="lp-tabs lp-rev" role="tablist">
            <?php foreach ($tour as $i => [$id, $nom, $ico, $txt]): ?>
                <button type="button" role="tab" class="lp-tab <?php echo $i === 0 ? 'on' : ''; ?>" data-i="<?php echo $i; ?>" data-texto="<?php echo s($txt); ?>" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"><i class="fa-solid <?php echo $ico; ?>"></i> <?php echo s($nom); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="lp-browser lp-rev">
            <div class="lp-browser-bar"><i></i><i></i><i></i><span class="lp-url" id="lp-url">projectweb/programa</span></div>
            <div class="lp-shots">
                <?php foreach ($tour as $i => [$id, $nom, $ico, $txt]): ?>
                    <img src="/build/img/landing/tour-<?php echo $id; ?>.jpg" alt="<?php echo s($nom); ?> del proyecto demo" class="<?php echo $i === 0 ? 'on' : ''; ?>" <?php echo $i ? 'loading="lazy"' : 'fetchpriority="high"'; ?> decoding="async" data-url="projectweb/<?php echo $id; ?>">
                <?php endforeach; ?>
            </div>
        </div>
        <p class="lp-tour-txt lp-rev" id="lp-tour-txt"><?php echo s($tour[0][3]); ?></p>
    </div>
</section>
<?php endif; ?>

<section class="lp-steps">
    <div class="lp-wrap">
        <div class="lp-head lp-rev"><span class="lp-eyebrow">Cómo funciona</span><h2>Listo para usarse en cuatro pasos</h2></div>
        <ol class="lp-ol lp-ol-4">
            <?php foreach ($pasos as $i => [$t, $d]): ?>
                <li class="lp-rev" style="--d:<?php echo $i * 90; ?>ms"><b><?php echo $i + 1; ?></b><h3><?php echo s($t); ?></h3><p><?php echo s($d); ?></p></li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<?php if ($demoDisponible): ?>
<section class="lp-band" style="--bg: url('/build/img/landing/grua.jpg')">
    <div class="lp-wrap lp-rev">
        <h2>Míralo funcionando con un proyecto de ejemplo</h2>
        <p>Entra al demo sin registrarte: recorre el programa, el vPlan, los reportes diarios, el MSR, las órdenes de cambio y los documentos. Es de solo lectura.</p>
        <a href="/demo" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-play"></i> Entrar al demo</a>
    </div>
</section>
<?php endif; ?>

<section class="lp-sec lp-alt" id="servicios">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Servicios</span>
            <h2>¿Prefieres que lo llevemos<br>por ti?</h2>
            <p>Puedes usar la plataforma con tu equipo o sumar un servicio administrativo de seguimiento. La experiencia técnica de cada disciplina sigue siendo tuya.</p>
        </div>
        <div class="lp-grid lp-grid-3 lp-servicios-cards">
            <?php foreach ($serviciosLanding as $i => $s): ?>
                <article class="lp-card lp-servicio-card lp-rev <?php echo !empty($s['destacado']) ? 'lp-servicio-top' : ''; ?>" style="--d:<?php echo $i * 80; ?>ms">
                    <span class="lp-ico"><i class="fa-solid <?php echo $s['icono']; ?>"></i></span>
                    <h3><?php echo s($s['nombre']); ?></h3>
                    <p class="lp-servicio-precio"><?php echo s($s['precio']); ?></p>
                    <p class="lp-servicio-para"><?php echo s($s['para']); ?></p>
                    <ul class="lp-list"><?php foreach ($s['incluye'] as $it): ?><li><i class="fa-solid fa-check"></i> <?php echo s($it); ?></li><?php endforeach; ?></ul>
                    <a href="/servicios" class="lp-btn <?php echo !empty($s['destacado']) ? 'lp-btn-primary' : 'lp-btn-ghost'; ?> lp-btn-block">Conocer los servicios</a>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="lp-hub-nota lp-rev"><i class="fa-solid fa-people-arrows"></i> Nosotros administramos y damos seguimiento; tu equipo aporta la experiencia técnica y valida las decisiones.</p>
    </div>
</section>

<section class="lp-sec" id="piloto">
    <div class="lp-wrap">
        <div class="lp-srv-teaser lp-rev">
            <div>
                <span class="lp-eyebrow">Piloto de 30 días</span>
                <h2>Pruébalo con tu obra,<br>no con un demo</h2>
                <p>Elige una obra real, sube a tu equipo y usa ProjectWeb durante 30 días con acompañamiento. Al final decides con números sobre la mesa: si te sirve, sigues con el plan anual; si no, te quedas con tu información y tu informe.</p>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> 1 obra real y 3 usuarios de edición</li>
                    <li><i class="fa-solid fa-check"></i> Implementación, carga del programa y capacitación</li>
                    <li><i class="fa-solid fa-check"></i> 4 juntas semanales de 30 minutos</li>
                    <li><i class="fa-solid fa-check"></i> Reporte semanal y informe final con números</li>
                    <li><i class="fa-solid fa-check"></i> Si continúas, el piloto se descuenta del plan anual</li>
                </ul>
                <div class="lp-cta">
                    <a href="#contacto" data-plan="Piloto de 30 días" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-paper-plane"></i> Agendar mi piloto</a>
                    <a href="/servicios" class="lp-btn lp-btn-ghost lp-btn-lg">Ver los servicios</a>
                </div>
            </div>
            <div class="lp-plan lp-plan-top" style="transform:none">
                <span class="lp-badge">Acreditable</span>
                <h3>Piloto de 30 días</h3>
                <p class="lp-plan-lema">Una obra real, con acompañamiento.</p>
                <p class="lp-precio"><b><?php echo s(\Classes\LandingContenido::mxn(\Classes\LandingContenido::precioServicio('piloto'))); ?></b><span> MXN / mes</span></p>
                <p class="text-muted text-sm">Se descuenta del primer pago si firmas el plan anual antes del día 30.</p>
                <ul>
                    <li><i class="fa-solid fa-circle-check"></i> <span>Plan Pro durante el piloto</span></li>
                    <li><i class="fa-solid fa-circle-check"></i> <span>Implementación incluida</span></li>
                    <li><i class="fa-solid fa-circle-check"></i> <span>Junta semanal y reporte</span></li>
                    <li><i class="fa-solid fa-circle-check"></i> <span>Garantía de 30 días</span></li>
                </ul>
                <a href="#contacto" data-plan="Piloto de 30 días" class="lp-btn lp-btn-primary lp-btn-block">Quiero mi piloto</a>
            </div>
        </div>
    </div>
</section>

<section class="lp-sec lp-alt" id="planes">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Planes</span>
            <h2>Elige el plan que crece contigo</h2>
            <p>Precios mensuales en pesos mexicanos (MXN). Todas las herramientas de obra están en todos los planes; los planes crecen en capacidad, usuarios y gobierno. El anual equivale a 10 meses.</p>
        </div>
        <div class="lp-plans">
            <?php foreach ($planes as $i => $pl): ?>
                <article class="lp-plan lp-rev <?php echo $pl['destacado'] ? 'lp-plan-top' : ''; ?>" style="--d:<?php echo $i * 90; ?>ms">
                    <?php if ($pl['destacado']): ?><span class="lp-badge">Más popular</span><?php endif; ?>
                    <h3><?php echo s($pl['nombre']); ?></h3>
                    <p class="lp-plan-lema"><?php echo s($pl['lema']); ?></p>
                    <p class="lp-precio"><b><?php echo s($pl['precio']); ?></b><span> MXN / mes</span></p>
                    <p class="text-muted text-sm">o <strong><?php echo s($pl['precio_anual']); ?> MXN al año</strong> (2 meses gratis)</p>
                    <a href="#contacto" data-plan="<?php echo s($pl['nombre']); ?>" class="lp-btn <?php echo $pl['destacado'] ? 'lp-btn-primary' : 'lp-btn-ghost'; ?> lp-btn-block">Quiero el <?php echo s($pl['nombre']); ?></a>
                    <ul>
                        <?php foreach ($pl['items'] as $it): ?><li><i class="fa-solid fa-circle-check"></i> <span><?php echo $it; ?></span></li><?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="lp-hub-nota lp-rev"><i class="fa-solid fa-circle-info"></i> IA y API desde el Plan Pro; SSO y marca desde Constructora; multiempresa desde Corporativo. Los visitantes de solo lectura no consumen licencias de edición.</p>
    </div>
</section>

<section class="lp-sec" id="faq">
    <div class="lp-wrap lp-narrow">
        <div class="lp-head lp-rev"><span class="lp-eyebrow">Preguntas frecuentes</span><h2>Lo que suelen preguntarnos</h2></div>
        <div class="lp-faq lp-rev">
            <details><summary>¿Qué es el piloto de 30 días?</summary><p>Es una prueba con una obra real: cargamos tu programa, capacitamos a 3 usuarios y te acompañamos 4 semanas. Cuesta <?php echo s(\Classes\LandingContenido::mxn(\Classes\LandingContenido::precioServicio('piloto'))); ?> y se descuenta del plan anual si decides continuar. Si no, no tienes obligación de seguir.</p></details>
            <details><summary>¿Puedo exportar mi programa a MS Project?</summary><p>Sí. Se exporta en XML (MSPDI) y lo abres en MS Project para guardarlo como .mpp. También en ProjectLibre, Excel, LibreOffice y PDF tabloide.</p></details>
            <details><summary>¿Qué formatos de entrega tienen?</summary><p>PDF, Word (DOCX), ODT, Excel (XLSX), ODS, CSV, XML de MS Project, POD de ProjectLibre, PNG, ZIP, JSON para integraciones y calendario ICS. Si tu cliente pide otro, lo revisamos contigo.</p></details>
            <details><summary>¿Puedo importar el programa que ya tengo?</summary><p>Sí. Importamos XML de MS Project o ProjectLibre y conservamos partidas, actividades y fechas. Después ajustamos el calendario y las dependencias para que el Gantt y la ruta crítica queden completos.</p></details>
            <details><summary>¿Necesito instalar algo o comprar licencias de otros programas?</summary><p>No. ProjectWeb funciona desde el navegador y reúne lo que tradicionalmente exige varios programas distintos, con sus licencias. Si quieres, sigues exportando a PDF, Excel o MS Project.</p></details>
            <details><summary>¿Cómo se calcula el ahorro?</summary><p>Comparamos el costo anual de referencia de las licencias que ProjectWeb reemplaza (programación, ofimática, PDF, campo, tablero y almacenamiento) contra el plan anual que cubre a tu equipo. No sumamos una licencia de todo para cada persona y no incluimos hardware, internet, implementación ni impuestos. Los supuestos están a la vista en la sección de Ahorro.</p></details>
            <details><summary>¿Puedo trabajar desde el celular o si cambio de equipo?</summary><p>Sí. Entras con tu usuario desde el celular, la tableta o cualquier computadora y ves y editas la misma información, sin reinstalar ni volver a comprar nada.</p></details>
            <details><summary>¿Qué pasa si no hay internet en la obra?</summary><p>Puedes seguir trabajando en campo y capturar cuando recuperes señal: la plataforma no exige conexión permanente. Para el demo y las juntas conviene revisar antes las zonas con cobertura.</p></details>
            <details><summary>¿Quién es dueño de la información y qué pasa si me voy?</summary><p>La información es tuya. Puedes descargarla en cualquier momento en PDF, Excel, XML o los formatos disponibles, y llevarte los archivos originales. No queda retenida en la plataforma.</p></details>
            <details><summary>¿Incluye capacitación y soporte?</summary><p>Sí. La puesta en marcha incluye la configuración inicial, el manual de usuario dentro de la plataforma y soporte para dudas. En el servicio de seguimiento también capacitamos a tu equipo.</p></details>
            <details><summary>¿Cuánto tarda la puesta en marcha?</summary><p>Un proyecto nuevo puede quedar listo el mismo día: se crea, se define la jornada y se capturan o importan las partidas. Si vienes de otro sistema, coordinamos la migración y la validación de fechas.</p></details>
            <details><summary>¿Qué pasa si llego al límite de almacenamiento?</summary><p>Cada organización tiene un tope total según el plan y cada obra un máximo de espacio. Al llegar al límite no se pueden subir más archivos hasta liberar espacio o ampliar el plan; lo ya guardado no se pierde.</p></details>
            <details><summary>¿En qué se diferencian los planes?</summary><p>En obras activas, usuarios de edición, almacenamiento total y funciones de gobierno (IA, API, SSO y marca). Todas las herramientas de obra están incluidas en todos los planes.</p></details>
            <details><summary>¿Pueden encargarse del seguimiento de mi obra?</summary><p>Sí. Además de la plataforma ofrecemos servicios administrativos de seguimiento de obra para que tu equipo se concentre en construir. La experiencia técnica de cada disciplina sigue siendo de tu equipo, supervisor o residente. Conoce el detalle en <a href="/servicios" class="lp-foot-link">Servicios</a>.</p></details>
            <details><summary>¿El demo guarda mis cambios?</summary><p>No. El demo es de solo lectura y usa datos de ejemplo: no necesitas registrarte ni se guarda nada de lo que hagas.</p></details>
        </div>
    </div>
</section>

<section class="lp-sec lp-contact" id="contacto">
    <div class="lp-wrap lp-contact-in">
        <div class="lp-contact-txt lp-rev">
            <span class="lp-eyebrow">Contacto</span>
            <h2>Hablemos de tu próxima obra</h2>
            <p>Cuéntanos si buscas la plataforma, el servicio de seguimiento o ambos. Te ayudamos a elegir el esquema que mejor se ajuste a tu alcance y presupuesto. También puedes ver el demo sin registrarte.</p>
            <ul class="lp-list">
                <li><i class="fa-solid fa-check"></i> Plataforma, servicio o una combinación de ambos</li>
                <li><i class="fa-solid fa-check"></i> Te contactamos por el medio que prefieras</li>
                <li><i class="fa-solid fa-check"></i> Sin compromiso: resolvemos tus dudas primero</li>
                <li><i class="fa-solid fa-check"></i> Desde una obra hasta un portafolio de 500 proyectos</li>
            </ul>
            <?php if ($whatsappUrl): ?>
                <a class="lp-contact-wa" href="<?php echo s($whatsappUrl); ?>" target="_blank" rel="noopener">
                    <span><i class="fa-brands fa-whatsapp"></i></span>
                    <div><strong>WhatsApp directo</strong><small><?php echo s($whatsapp); ?></small></div>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            <?php endif; ?>
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
                    <label>¿Qué te interesa?
                        <select name="plan" id="lp-plan"><option value="">Selecciona…</option><?php foreach (\Model\Contacto::PLANES as $grupo => $opciones): ?><optgroup label="<?php echo s($grupo); ?>"><?php foreach ($opciones as $o): ?><option><?php echo s($o); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select>
                    </label>
                </div>
                <div class="lp-row2">
                    <label>Proyectos simultáneos
                        <select name="proyectos_aprox"><option value="">Selecciona…</option><?php foreach (\Model\Contacto::PROYECTOS as $o): ?><option><?php echo s($o); ?></option><?php endforeach; ?></select>
                    </label>
                    <label>¿Cómo prefieres que te contactemos?
                        <select name="preferencia"><?php foreach (\Model\Contacto::PREFERENCIAS as $k => $o): ?><option value="<?php echo s($k); ?>"<?php echo $k === 'whatsapp' ? ' selected' : ''; ?>><?php echo s($o); ?></option><?php endforeach; ?></select>
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
</main>

<footer class="lp-foot">
    <div class="lp-wrap">
        <span class="lp-logo"><span class="lp-logo-i"><i class="fa-solid fa-helmet-safety"></i></span> ProjectWeb</span>
        <span>© <?php echo date('Y'); ?> ProjectWeb · Sistema de programas de obra · <a href="/terminos" class="lp-foot-link">Términos y condiciones de uso</a></span>
        <?php if ($whatsappUrl): ?><a href="<?php echo s($whatsappUrl); ?>" class="lp-foot-link" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> <?php echo s($whatsapp); ?></a><?php endif; ?>
        <span class="lp-credit">Imágenes: <a href="https://unsplash.com" target="_blank" rel="noopener">Unsplash</a>, <a href="https://polyhaven.com" target="_blank" rel="noopener">Poly Haven</a> y <a href="https://commons.wikimedia.org" target="_blank" rel="noopener">Wikimedia Commons</a></span>
    </div>
</footer>
