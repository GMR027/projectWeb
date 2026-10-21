<?php
$base = '/';
$activa = 'servicios';
$servicios = [
    ['fa-chart-gantt', 'Control del programa de obra', 'Avance real contra lo planeado, ruta crítica, holguras y proyección a cuatro semanas, sobre el calendario real de tu obra.', ['Gantt y calendario', 'Ruta crítica', 'Curva S']],
    ['fa-binoculars', 'Lookahead y Last Planner', 'Preparamos el lookahead semanal con sus limitaciones y los post-its del día, listos para la junta de planeación y para imprimir.', ['4 semanas', 'PTA', 'Post-its']],
    ['fa-truck-ramp-box', 'Materiales, submittals y MSR', 'Aprobaciones, compras y entregas cruzadas con el programa, para avisar con tiempo qué material puede frenar una actividad.', ['MSR a tu medida', 'Submittals', 'Curvas']],
    ['fa-clipboard-list', 'Reportes, bitácora y minutas', 'Reporte diario con personal y fotografías, bitácora por departamento y minutas con compromisos, fechados y listos para el cliente.', ['PDF en un clic', 'Evidencias', 'Compromisos']],
    ['fa-file-signature', 'Control de cambios y alcance', 'Cada orden de cambio con su impacto en costo y tiempo, y el programa comparado contra su línea base para justificar las desviaciones.', ['Impacto en días', 'Línea base', 'Retiros']],
    ['fa-triangle-exclamation', 'Restricciones y pendientes', 'Lo que frena a cada frente, con responsable y fecha compromiso, hasta que se libera. Incluye el punch list de cierre con fotos antes y después.', ['Responsable', 'Fecha límite', 'Punch list']],
    ['fa-folder-open', 'Control documental', 'Planos, especificaciones y contratos por revisión; detectamos qué cambió entre versiones para que nadie trabaje con un plano vencido.', ['Revisiones', 'Comparador', 'Normativa']],
    ['fa-file-lines', 'Documentos y tablas de obra', 'Actas, procedimientos, memorias y tablas de control con fórmulas, con el formato de tu empresa o del cliente, en Word, Excel o PDF.', ['Actas', 'Tablas vivas', 'Traducción ES/EN']],
    ['fa-map-location-dot', 'Láminas visuales para juntas', 'Planos con el avance marcado, tablas del programa, recovery y restricciones, en láminas del tamaño que pidas, listas para proyectar o imprimir.', ['vPlan', 'Tabloide a A0', 'PDF por lámina']],
    ['fa-street-view', 'Recorridos y registro visual', 'Fotos 360° ligadas al plano y evidencias por actividad en una línea de tiempo, para que el cliente recorra la obra sin viajar.', ['View360', 'Línea de tiempo', 'Antes / después']],
    ['fa-gauge-high', 'Informes para dirección y cliente', 'Resumen ejecutivo periódico con avance, semáforo de la obra, desviaciones y los puntos que requieren una decisión.', ['Semáforo', 'SPI', 'Decisiones']],
    ['fa-file-export', 'Exportaciones y respaldo', 'Entregamos el programa en PDF tabloide, Excel, LibreOffice, MS Project o ProjectLibre, y un ZIP completo del proyecto cuando lo necesites.', ['MS Project', 'Excel', 'ZIP']],
];
$cifras = [
    ['12', 'frentes de seguimiento que podemos asumir'],
    ['1', 'sola plataforma para todo el proyecto'],
    ['24/7', 'consulta del cliente en solo lectura'],
    ['5', 'formatos de entrega: PDF, Excel, ODS, XML y POD'],
];
$antesAhora = [
    ['Programa en un archivo de escritorio que solo abre una persona', 'Programa en la nube: todos ven el mismo avance, en el celular o la computadora'],
    ['Reporte diario en Word y fotos por WhatsApp', 'Reporte con personal, actividades y fotos por día, en PDF listo para el cliente'],
    ['Lookahead y post-its armados a mano cada semana', 'Calculados del programa; al cambiar una fecha se actualizan solos'],
    ['Materiales en una hoja aparte que nadie cruza con el cronograma', 'Cada material ligado a sus actividades, con aviso de compras vencidas'],
    ['Tablas copiadas y pegadas en cada documento', 'Una tabla que se actualiza sola en las láminas, los gráficos y las actas'],
    ['Cambios y atrasos explicados de memoria en la junta', 'Órdenes de cambio y líneas base: cada desviación con su causa documentada'],
    ['Láminas de juntas hechas en un programa de dibujo', 'Láminas libres con capas, guías y datos vivos del programa'],
    ['El cliente pide un PDF y espera la siguiente semana', 'El cliente consulta el avance cuando quiera, sin poder modificar nada'],
];
$mejoras = [
    ['fa-up-right-and-down-left-from-center', 'Tablas que se adaptan', 'El texto crece o se reduce con el tamaño del bloque, de una lámina tabloide a una pantalla de celular. Nada queda diminuto.', 'Oct 2026'],
    ['fa-arrows-rotate', 'Todo en tiempo real', 'Cambias una tabla en un lugar y las láminas, los gráficos y las vistas se actualizan solos, sin recargar ni volver a capturar.', 'Oct 2026'],
    ['fa-wand-magic-sparkles', 'Tablas inteligentes', 'Columnas de lista, casilla y semáforo, con color de encabezado a tu gusto, también editables desde la lámina.', 'Oct 2026'],
    ['fa-clipboard-check', 'Punch list conectado', 'Los pendientes de cierre se insertan como tabla en láminas, actas y gráficos, con fotos antes y después.', 'Oct 2026'],
    ['fa-stamp', 'Estampas inteligentes', 'Cajetines y post-its cuyas etiquetas se llenan solas con avance, fechas, semáforos y actividades del día.', 'Oct 2026'],
    ['fa-pen-ruler', 'Editor de planos más preciso', 'Líneas con nodos editables, trazos a medida y símbolos que se actualizan solos al cambiar su origen.', 'Oct 2026'],
    ['fa-layer-group', 'vPlan de láminas libres', 'Bloques posicionables con capas, guías, rejilla, varios tamaños de hoja y PDF por lámina.', 'Oct 2026'],
    ['fa-binoculars', 'Lookahead configurable', 'Encabezados con logos, nombres de columnas y de 1 a 8 semanas visibles, a la medida de cada obra.', 'Oct 2026'],
    ['fa-puzzle-piece', 'Ensamblador de documentos', 'Un PDF que une actas y documentos del archivo y se actualiza solo cuando cambia alguno.', 'Oct 2026'],
    ['fa-language', 'Traductor ES ↔ EN', 'Textos y documentos de obra traducidos sin salir de la plataforma, para contratos y clientes internacionales.', 'Oct 2026'],
    ['fa-code-compare', 'Cambios y líneas base', 'Impacto de cada orden de cambio y comparación del programa contra su foto anterior, con barra «fantasma».', 'Sep 2026'],
    ['fa-diagram-project', 'Dependencias y ruta crítica', 'Relaciones FS, SS, FF y SF con retraso, holguras y conflictos, sin otro software.', 'Sep 2026'],
];
$entregables = [
    ['fa-file-pdf', 'Informe ejecutivo', 'Avance, semáforo, desviaciones y decisiones pendientes.', 'Semanal o quincenal'],
    ['fa-binoculars', 'Lookahead y Last Planner', 'Las próximas semanas con sus limitaciones y los post-its del día.', 'Semanal'],
    ['fa-map-location-dot', 'Láminas vPlan', 'Planos con el avance marcado por frente o por área.', 'Para cada junta'],
    ['fa-handshake', 'Minutas con compromisos', 'Temas, responsables y fechas, en PDF.', 'Después de cada junta'],
    ['fa-truck-ramp-box', 'Tablero de materiales', 'Qué falta aprobar, comprar o entregar, y qué lo frena.', 'Semanal'],
    ['fa-file-signature', 'Registro de cambios', 'Órdenes de cambio, líneas base y desviaciones justificadas.', 'Al ocurrir cada cambio'],
];
$modalidades = [
    ['fa-bullseye', 'Seguimiento puntual', 'Para obras pequeñas o una etapa concreta.', ['Programa y reporte periódico', 'Control de materiales clave', 'Informe al cierre de la etapa']],
    ['fa-people-group', 'Acompañamiento continuo', 'La opción más elegida para obras en marcha.', ['Todo lo anterior, con la frecuencia que necesites', 'Lookahead, juntas, minutas y cambios', 'Acceso de solo lectura para tu cliente'], true],
    ['fa-building-circle-check', 'Oficina de control de proyecto', 'Para varias obras o grupos constructores.', ['Un mismo estándar en todos tus proyectos', 'Informes consolidados para dirección', 'Capacitación a tu equipo en la plataforma']],
];
$deteccion = [
    ['fa-magnifying-glass-chart', 'Fechas que no cuadran', 'Compras, entregas y arranques que se contradicen entre sí o con el programa.'],
    ['fa-hourglass-half', 'Riesgos de atraso', 'Actividades que van a detenerse por un material, un permiso o una definición pendiente.'],
    ['fa-copy', 'Información duplicada o incompleta', 'Datos faltantes, repetidos o distintos entre documentos que deberían coincidir.'],
    ['fa-code-compare', 'Cambios entre versiones', 'Diferencias de alcance entre revisiones de planos, especificaciones y contratos.'],
    ['fa-bolt', 'Oportunidades de adelantar', 'Frentes que ya pueden arrancar y recuperar tiempo sin esperar la siguiente junta.'],
    ['fa-scale-balanced', 'Desviaciones sin justificar', 'Atrasos o movimientos del programa que no tienen una causa documentada.'],
];
$pasos = [
    ['Diagnóstico', 'Revisamos tu programa, tus documentos y cómo llevas hoy el seguimiento. Definimos contigo el alcance.'],
    ['Puesta en marcha', 'Cargamos tu proyecto, ordenamos la información y acordamos reportes, juntas y responsables.'],
    ['Seguimiento periódico', 'Actualizamos avance, materiales, restricciones y cambios con la frecuencia que necesites.'],
    ['Informes y decisiones', 'Te entregamos un panorama claro y alertas a tiempo para decidir mejor, sin dejar la obra.'],
];
$faq = [
    ['¿Tengo que dejar mis herramientas actuales?', 'No. Nos adaptamos a tu forma de trabajar: podemos importar tu programa (XML de MS Project, ProjectLibre) y entregarte todo en los formatos que ya usas.'],
    ['¿Quién ve la información de mi obra?', 'Solo las personas que tú autorices. Tu cliente puede tener un acceso de solo lectura y tú eliges qué herramientas ve. Los respaldos son periódicos y cada proyecto tiene su propio espacio.'],
    ['¿Puedo contratar solo una parte del servicio?', 'Sí. Puedes asignarnos un solo frente (por ejemplo materiales o reportes) y ampliar el alcance cuando lo necesites.'],
    ['¿Cada cuánto recibo informes?', 'Lo defines tú: semanal, quincenal o por junta. Además puedes consultar el avance en cualquier momento desde la plataforma.'],
    ['¿Y si ya uso ProjectWeb por mi cuenta?', 'Mejor aún: trabajamos dentro de tu mismo proyecto y tu equipo ve en vivo lo que actualizamos. También podemos capacitarlos.'],
    ['¿Cómo se cotiza?', 'Según el tamaño de la obra, el alcance y la frecuencia del seguimiento. Cuéntanos de tu proyecto y te proponemos un esquema.'],
];
?>

<?php include __DIR__ . '/_nav.php'; ?>

<section class="lp-hero lp-hero-srv" style="--hero: url('/build/img/landing/campo.jpg')">
    <span class="lp-orb lp-orb1"></span><span class="lp-orb lp-orb2"></span>
    <div class="lp-wrap lp-srv-hero">
        <div class="lp-rev">
            <span class="lp-pill"><i class="fa-solid fa-handshake-angle"></i> Servicios administrativos de obra</span>
            <h1>Tú construyes.<br><span class="lp-grad">Nosotros damos seguimiento.</span></h1>
            <p class="lp-lead">Nos encargamos del seguimiento administrativo de tu obra sobre una plataforma propia, conectada de punta a punta, para que tu equipo se enfoque en lo que mejor sabe hacer: construir. Tú decides con información clara y a tiempo.</p>
            <div class="lp-cta">
                <a href="/#contacto" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-paper-plane"></i> Cuéntanos de tu obra</a>
                <a href="#que-hacemos" class="lp-btn lp-btn-ghost lp-btn-lg">Ver qué ofrecemos</a>
            </div>
            <ul class="lp-check">
                <li><i class="fa-solid fa-circle-check"></i> Informes semanales o por junta</li>
                <li><i class="fa-solid fa-circle-check"></i> Tu cliente consulta en solo lectura</li>
                <li><i class="fa-solid fa-circle-check"></i> Alcance a tu medida</li>
            </ul>
        </div>
        <aside class="lp-srv-panel lp-rev" aria-label="Ejemplo ilustrativo de un tablero de seguimiento">
            <div class="lp-srv-panel-top"><i></i><i></i><i></i><span>Tablero de seguimiento · ejemplo</span></div>
            <div class="lp-srv-kpis">
                <div><b>68%</b><small>Avance real</small></div>
                <div><b>0.97</b><small>SPI</small></div>
                <div><b class="lp-warn">6</b><small>Restricciones</small></div>
            </div>
            <ul class="lp-srv-sem">
                <li><span class="lp-dot ok"></span> Estructura <em>En tiempo</em></li>
                <li><span class="lp-dot mid"></span> Instalación eléctrica <em>Vigilar</em></li>
                <li><span class="lp-dot bad"></span> Equipo de A/A <em>Compra vencida</em></li>
                <li><span class="lp-dot ok"></span> Acabados <em>En tiempo</em></li>
            </ul>
            <p class="lp-srv-panel-nota"><i class="fa-solid fa-circle-info"></i> Ilustración con datos de ejemplo.</p>
        </aside>
    </div>
</section>

<section class="lp-stats">
    <div class="lp-wrap lp-stats-in">
        <?php foreach ($cifras as $i => [$n, $t]): ?>
            <div class="lp-rev" style="--d:<?php echo $i * 80; ?>ms"><b><?php echo s($n); ?></b><span><?php echo s($t); ?></span></div>
        <?php endforeach; ?>
    </div>
</section>

<section class="lp-sec" id="que-hacemos">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Qué podemos llevar por ti</span>
            <h2>El seguimiento de obra,<br>en manos de un equipo dedicado</h2>
            <p>Doce frentes que podemos asumir total o parcialmente. Se ajustan al tamaño y la etapa de cada proyecto.</p>
        </div>
        <div class="lp-grid lp-grid-3">
            <?php foreach ($servicios as $i => [$ico, $t, $d, $tags]): ?>
                <article class="lp-card lp-rev" style="--d:<?php echo ($i % 3) * 70; ?>ms">
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <h3><?php echo s($t); ?></h3>
                    <p><?php echo s($d); ?></p>
                    <ul class="lp-tags"><?php foreach ($tags as $g): ?><li><?php echo s($g); ?></li><?php endforeach; ?></ul>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-sec lp-alt" id="antes-ahora">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Antes y ahora</span>
            <h2>De llevar la obra en archivos sueltos<br>a tenerla conectada</h2>
            <p>Nuestro servicio se apoya en una plataforma donde el programa, los materiales, los documentos y los cambios comparten los mismos datos.</p>
        </div>
        <div class="lp-cmp-wrap lp-rev">
            <table class="lp-cmp lp-antes">
                <thead><tr><th>Como suele hacerse</th><th class="lp-cmp-us">Con nuestro seguimiento</th></tr></thead>
                <tbody>
                    <?php foreach ($antesAhora as [$a, $b]): ?>
                        <tr><td><i class="fa-solid fa-circle-xmark lp-cmp-no"></i> <span><?php echo s($a); ?></span></td><td class="lp-cmp-us"><i class="fa-solid fa-circle-check lp-cmp-ok"></i> <span><?php echo s($b); ?></span></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="lp-sec" id="novedades">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Una plataforma que no se detiene</span>
            <h2>Lo que hemos mejorado<br>para darte un mejor servicio</h2>
            <p>Mejoramos la plataforma cada mes. Estas son algunas de las novedades recientes que ya trabajan para tu obra.</p>
        </div>
        <div class="lp-grid">
            <?php foreach ($mejoras as $i => [$ico, $t, $d, $f]): ?>
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

<section class="lp-sec lp-alt" id="especializado">
    <div class="lp-wrap">
        <div class="lp-show lp-rev">
            <div class="lp-show-txt">
                <span class="lp-eyebrow">Un grupo especializado detrás de tu proyecto</span>
                <h2>Detalles que se escapan a simple vista, para decidir mejor</h2>
                <p>Un proyecto de construcción genera cientos de datos que viven en archivos distintos. Nuestro grupo especializado, apoyado en análisis avanzado de la información, los revisa en conjunto y señala lo que merece atención antes de que se vuelva un problema.</p>
                <p>A modo indicativo, esto es lo que podemos detectar y ponerte sobre la mesa:</p>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> Cada hallazgo llega con su explicación y una propuesta</li>
                    <li><i class="fa-solid fa-check"></i> Tú revisas y decides; nada cambia sin tu aprobación</li>
                    <li><i class="fa-solid fa-check"></i> El alcance se define según tu proyecto</li>
                </ul>
            </div>
            <figure class="lp-fig"><img src="/build/img/landing/planos.jpg" alt="Revisión de planos y documentos de obra" loading="lazy"></figure>
        </div>
        <div class="lp-grid lp-grid-3 lp-srv-det">
            <?php foreach ($deteccion as $i => [$ico, $t, $d]): ?>
                <article class="lp-card lp-rev" style="--d:<?php echo ($i % 3) * 70; ?>ms">
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <h3><?php echo s($t); ?></h3>
                    <p><?php echo s($d); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-sec" id="entregables">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Lo que recibes</span>
            <h2>Entregables claros,<br>con la frecuencia que necesites</h2>
        </div>
        <div class="lp-grid lp-grid-3">
            <?php foreach ($entregables as $i => [$ico, $t, $d, $f]): ?>
                <article class="lp-card lp-rev" style="--d:<?php echo ($i % 3) * 70; ?>ms">
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <h3><?php echo s($t); ?></h3>
                    <p><?php echo s($d); ?></p>
                    <span class="lp-freq"><i class="fa-regular fa-clock"></i> <?php echo s($f); ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-sec lp-alt" id="modalidades">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Modalidades</span>
            <h2>Elige cuánto quieres delegar</h2>
            <p>Cada esquema se cotiza según el tamaño de la obra, el alcance y la frecuencia del seguimiento.</p>
        </div>
        <div class="lp-mods">
            <?php foreach ($modalidades as $i => $m): [$ico, $t, $lema, $items] = $m; $dest = !empty($m[4]); ?>
                <article class="lp-mod lp-rev<?php echo $dest ? ' lp-mod-top' : ''; ?>" style="--d:<?php echo $i * 80; ?>ms">
                    <?php if ($dest): ?><span class="lp-badge">Más elegida</span><?php endif; ?>
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <h3><?php echo s($t); ?></h3>
                    <p class="lp-mod-lema"><?php echo s($lema); ?></p>
                    <ul class="lp-list"><?php foreach ($items as $it): ?><li><i class="fa-solid fa-check"></i> <?php echo s($it); ?></li><?php endforeach; ?></ul>
                    <a href="/#contacto" class="lp-btn <?php echo $dest ? 'lp-btn-primary' : 'lp-btn-ghost'; ?>">Solicitar propuesta</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-steps">
    <div class="lp-wrap">
        <div class="lp-head lp-rev"><span class="lp-eyebrow">Cómo trabajamos</span><h2>De la primera llamada<br>al seguimiento continuo</h2></div>
        <ol class="lp-ol lp-ol-4">
            <?php foreach ($pasos as $i => [$t, $d]): ?>
                <li class="lp-rev" style="--d:<?php echo $i * 90; ?>ms"><b><?php echo $i + 1; ?></b><h3><?php echo s($t); ?></h3><p><?php echo s($d); ?></p></li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="lp-sec">
    <div class="lp-wrap">
        <div class="lp-show lp-rev lp-inv">
            <div class="lp-show-txt">
                <span class="lp-eyebrow">Lo que ganas</span>
                <h2>Tu equipo en la obra, la administración en nuestras manos</h2>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> Residentes y superintendentes dedicados a construir, no a llenar formatos</li>
                    <li><i class="fa-solid fa-check"></i> Información ordenada y al día, disponible para ti y para tu cliente</li>
                    <li><i class="fa-solid fa-check"></i> Alertas a tiempo para tomar decisiones antes del atraso</li>
                    <li><i class="fa-solid fa-check"></i> Menos retrabajo y menos discusiones por datos que no coinciden</li>
                    <li><i class="fa-solid fa-check"></i> Un equipo que crece o se ajusta contigo, obra por obra</li>
                </ul>
                <div class="lp-cta">
                    <a href="/#contacto" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-paper-plane"></i> Solicitar información</a>
                    <?php if (!empty($demoDisponible)): ?><a href="/demo" class="lp-btn lp-btn-ghost lp-btn-lg"><i class="fa-solid fa-play"></i> Ver un proyecto de ejemplo</a><?php endif; ?>
                </div>
            </div>
            <figure class="lp-fig"><img src="/build/img/landing/grua.jpg" alt="Obra en construcción" loading="lazy"></figure>
        </div>
    </div>
</section>

<section class="lp-sec lp-alt" id="faq">
    <div class="lp-wrap lp-narrow">
        <div class="lp-head lp-rev"><span class="lp-eyebrow">Preguntas frecuentes</span><h2>Sobre el servicio</h2></div>
        <div class="lp-faq lp-rev">
            <?php foreach ($faq as [$q, $a]): ?><details><summary><?php echo s($q); ?></summary><p><?php echo s($a); ?></p></details><?php endforeach; ?>
        </div>
    </div>
</section>

<section class="lp-band" style="--bg: url('/build/img/landing/campo.jpg')">
    <div class="lp-wrap lp-rev">
        <h2>Hablemos de tu próxima obra</h2>
        <p>Cuéntanos qué necesitas dar seguimiento y te proponemos un esquema a tu medida.</p>
        <a href="/#contacto" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-paper-plane"></i> Contactar</a>
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
(function () {
    const els = document.querySelectorAll('.lp-rev');
    if (!('IntersectionObserver' in window)) { els.forEach(e => e.classList.add('in')); return; }
    const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .12 });
    els.forEach(e => io.observe(e));
    document.querySelectorAll('.lp-links a').forEach(a => a.addEventListener('click', () => { document.getElementById('lp-menu').checked = false; }));
})();
</script>
