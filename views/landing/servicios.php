<?php
$base = '/';
$activa = 'servicios';
$servicios = [
    ['fa-chart-gantt', 'Control del programa de obra', 'Mantenemos tu programa al día: avance real contra lo planeado, ruta crítica, holguras y proyección a cuatro semanas, para que siempre sepas qué sigue y qué se está atrasando.'],
    ['fa-truck-ramp-box', 'Seguimiento de materiales y entregables', 'Damos seguimiento a aprobaciones, compras y entregas, y las cruzamos con el programa para avisar con tiempo qué material puede frenar una actividad.'],
    ['fa-clipboard-list', 'Reportes, bitácora y minutas', 'Integramos el reporte diario con fotografías, la bitácora y las minutas de junta con sus compromisos, fechados y listos para compartir con el cliente.'],
    ['fa-file-signature', 'Control de cambios y alcance', 'Documentamos cada orden de cambio con su impacto en costo y tiempo, y comparamos el programa contra su línea base para justificar las desviaciones.'],
    ['fa-triangle-exclamation', 'Restricciones y pendientes', 'Registramos lo que frena a cada frente de trabajo, con responsable y fecha compromiso, y damos seguimiento hasta que se libera.'],
    ['fa-folder-open', 'Control documental', 'Ordenamos planos, especificaciones y contratos por revisión, y detectamos qué cambió entre una versión y otra para que nadie trabaje con un plano vencido.'],
    ['fa-file-lines', 'Documentos y tablas de obra', 'Redactamos y mantenemos actas, procedimientos, memorias y tablas de control con el formato de tu empresa o del cliente.'],
    ['fa-gauge-high', 'Informes para dirección y cliente', 'Resumen ejecutivo periódico con avance, semáforo de la obra, desviaciones y los puntos que requieren una decisión.'],
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
?>

<?php include __DIR__ . '/_nav.php'; ?>

<section class="lp-hero lp-hero-srv" style="--hero: url('/build/img/landing/campo.jpg')">
    <span class="lp-orb lp-orb1"></span><span class="lp-orb lp-orb2"></span>
    <div class="lp-wrap lp-srv-in">
        <div class="lp-rev">
            <span class="lp-pill"><i class="fa-solid fa-handshake-angle"></i> Servicios administrativos de obra</span>
            <h1>Tú construyes.<br><span class="lp-grad">Nosotros damos seguimiento.</span></h1>
            <p class="lp-lead">Nos encargamos del seguimiento administrativo de tu obra para que tu equipo se enfoque en lo que mejor sabe hacer: la parte física, construir. Tú decides con información clara y a tiempo.</p>
            <div class="lp-cta">
                <a href="/#contacto" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-paper-plane"></i> Cuéntanos de tu obra</a>
                <a href="#que-hacemos" class="lp-btn lp-btn-ghost lp-btn-lg">Ver qué ofrecemos</a>
            </div>
        </div>
    </div>
</section>

<section class="lp-sec" id="que-hacemos">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Qué podemos llevar por ti</span>
            <h2>El seguimiento de obra,<br>en manos de un equipo dedicado</h2>
            <p>Estos son algunos de los servicios que podemos asumir total o parcialmente. Se ajustan al tamaño y la etapa de cada proyecto.</p>
        </div>
        <div class="lp-grid">
            <?php foreach ($servicios as $i => [$ico, $t, $d]): ?>
                <article class="lp-card lp-rev" style="--d:<?php echo ($i % 4) * 70; ?>ms">
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
