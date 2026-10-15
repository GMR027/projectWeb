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
    ['curvas', 'Curvas de productividad', 'fa-chart-line', 'Planeado contra real por semana, con gráfica y tabla acumulada.'],
];
$funciones = [
    ['fa-chart-gantt', 'Programa y Gantt', 'Partidas, actividades e hitos con fechas calculadas sobre el calendario real de la obra. Cambia una fecha y todo se recalcula.'],
    ['fa-calendar-check', 'Calendario operativo', 'Jornada semanal por proyecto, festivos, paros técnicos y días de clima. Los días hábiles se respetan en cada actividad.'],
    ['fa-binoculars', 'Lookahead 4WLA', 'Semana actual + 4 semanas con PTA y limitaciones. Detecta qué actividades pueden arrancar y cuáles no.'],
    ['fa-note-sticky', 'Last Planner', 'Tarjetas por día y por partida listas para imprimir, con marcas de corte, calculadas desde tu programa.'],
    ['fa-map-location-dot', 'vPlan visual', 'Láminas con plano, tabla del programa, recovery y restricciones. El estado de la obra de un solo vistazo.'],
    ['fa-chart-line', 'Curvas de productividad', 'Planeado contra real por semana para cada material o frente de trabajo, con gráfica maestra.'],
    ['fa-truck-ramp-box', 'MSR · submittals · materiales', 'Catálogo con fotos y fichas, varios MSR por proyecto, control de submittals y estatus de compra y entrega de materiales. Las fechas se comparten entre catálogo, submittals y MSR.'],
    ['fa-clipboard-list', 'Reporte diario y bitácora', 'Personal, actividades y fotos del día en minutos. Bitácora por departamento con niveles de importancia.'],
    ['fa-handshake', 'Minutas de junta', 'Temas, responsables, compromisos y asistentes. Genera el PDF listo para enviar al cliente.'],
    ['fa-bolt', 'Restricciones y acciones rápidas', 'Registra lo que frena a cada actividad y consulta en un solo lugar lo del día y la semana: restricciones, bitácora crítica, minutas, submittals y materiales pendientes.'],
    ['fa-file-export', 'Exportación multi-formato', 'PDF tabloide, Excel, LibreOffice, XML de MS Project y ProjectLibre. Un ZIP con todo el proyecto.'],
    ['fa-user-group', 'Accesos para visitantes', 'Crea accesos de solo lectura para que tu cliente o tu equipo consulten el avance de tus proyectos, sin poder modificar nada.'],
];
$ventajas = [
    ['fa-layer-group', 'Una plataforma, no tres o cuatro programas', 'Gestionar un proyecto de forma tradicional exige varios programas distintos para llegar a lo que aquí ya viene integrado, con licencias que en promedio suman alrededor de <strong>$20,000 pesos</strong>.'],
    ['fa-shield-halved', 'Información que cuadra a la primera', 'Hecha a mano, la información se daña, se pierde o no coincide entre archivos. Aquí las herramientas están integradas: capturas una vez y todo cuadra.'],
    ['fa-mobile-screen-button', 'En cualquier equipo, sin reinstalar', 'Si cambias de computadora no recompras ni reinstalas licencias. Consulta y edita desde el celular, la tableta o la computadora.'],
    ['fa-bolt', 'Acciones rápidas para no andar perdido', 'Las actividades del día y de la semana, las restricciones activas y tus pendientes de bitácora, minutas, submittals y materiales, juntos en un solo lugar.'],
];
?>

<header class="lp-nav" id="top">
    <div class="lp-wrap lp-nav-in">
        <a href="/" class="lp-logo"><span class="lp-logo-i"><i class="fa-solid fa-helmet-safety"></i></span> ProjectWeb</a>
        <input type="checkbox" id="lp-menu" class="lp-menu-chk" aria-label="Menú">
        <label for="lp-menu" class="lp-burger" aria-hidden="true"><i class="fa-solid fa-bars"></i></label>
        <nav class="lp-links">
            <a href="#funciones">Funciones</a>
            <a href="#ventajas">Ventajas</a>
            <a href="#recorrido">Recorrido</a>
            <a href="#planes">Planes</a>
            <a href="#contacto">Contacto</a>
            <?php if ($demoDisponible): ?><a href="/demo" class="lp-btn lp-btn-ghost lp-btn-sm"><i class="fa-solid fa-play"></i> Demo</a><?php endif; ?>
            <a href="/login" class="lp-btn lp-btn-primary lp-btn-sm">Iniciar sesión</a>
        </nav>
    </div>
</header>

<section class="lp-hero" style="--hero: url('/build/img/landing/hero.jpg')">
    <span class="lp-orb lp-orb1"></span><span class="lp-orb lp-orb2"></span>
    <div class="lp-wrap lp-hero-in">
        <div class="lp-hero-txt lp-rev">
            <span class="lp-pill"><i class="fa-solid fa-bolt"></i> Control de obra en tiempo real</span>
            <h1>Tu programa de obra,<br><span class="lp-grad">siempre bajo control.</span></h1>
            <p class="lp-lead">Gantt, lookahead, Last Planner, vPlan, materiales y reportes diarios en una sola plataforma. Menos programas, menos licencias y menos hojas de cálculo; más obra entregada a tiempo.</p>
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
        <div class="lp-rev"><b data-n="11">11</b><span>módulos conectados entre sí</span></div>
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
    </div>
</section>

<section class="lp-steps">
    <div class="lp-wrap">
        <div class="lp-head lp-rev"><span class="lp-eyebrow">Cómo funciona</span><h2>Listo para usarse en tres pasos</h2></div>
        <ol class="lp-ol">
            <li class="lp-rev"><b>1</b><h3>Crea tu proyecto</h3><p>Define fechas, jornada semanal y festivos. El sistema prepara el calendario operativo.</p></li>
            <li class="lp-rev" style="--d:90ms"><b>2</b><h3>Arma el programa</h3><p>Captura partidas, actividades y restricciones. Se calculan fechas, Gantt, 4WLA y Last Planner.</p></li>
            <li class="lp-rev" style="--d:180ms"><b>3</b><h3>Da seguimiento y exporta</h3><p>Registra avance y reportes diarios, comparte con el cliente y descarga en el formato que pida.</p></li>
        </ol>
    </div>
</section>

<?php if ($demoDisponible): ?>
<section class="lp-band" style="--bg: url('/build/img/landing/grua.jpg')">
    <div class="lp-wrap lp-rev">
        <h2>Míralo funcionando con un proyecto de ejemplo</h2>
        <p>Entra al demo sin registrarte: recorre el programa, el vPlan, los reportes diarios, el MSR y más. Es de solo lectura, no puedes romper nada.</p>
        <a href="/demo" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-play"></i> Entrar al demo</a>
    </div>
</section>
<?php endif; ?>

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
        <span class="lp-credit">Fotografías: <a href="https://unsplash.com" target="_blank" rel="noopener">Unsplash</a></span>
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
