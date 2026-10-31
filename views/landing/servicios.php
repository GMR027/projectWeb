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
    ['fa-sheet-plastic', 'Láminas visuales para juntas', 'Planos con el avance marcado, tablas del programa, recovery y restricciones, en láminas del tamaño que pidas, listas para proyectar o imprimir.', ['vPlan', 'Tabloide a A0', 'PDF por lámina']],
    ['fa-street-view', 'Recorridos y registro visual', 'Fotos 360° ligadas al plano y evidencias por actividad en una línea de tiempo, para que el cliente recorra la obra sin viajar.', ['View360', 'Línea de tiempo', 'Antes / después']],
    ['fa-gauge-high', 'Informes para dirección y cliente', 'Resumen ejecutivo periódico con avance, semáforo de la obra, desviaciones y los puntos que requieren una decisión.', ['Semáforo', 'SPI', 'Decisiones']],
    ['fa-file-export', 'Exportaciones y respaldo', 'Entregamos el programa en PDF tabloide, Excel, LibreOffice, MS Project o ProjectLibre, y un ZIP completo del proyecto cuando lo necesites.', ['MS Project', 'Excel', 'ZIP']],
];
$cifras = [
    ['12', 'frentes administrativos que podemos asumir'],
    ['1', 'sola plataforma para todo el proyecto'],
    ['24/7', 'consulta del cliente en solo lectura'],
    ['14', 'formatos de entrega, del PDF al XML de MS Project'],
];
$antesAhora = [
    ['fa-chart-gantt', 'Cada quien con su copia del programa y una junta para saber cuál es la buena.', 'Una sola versión en la nube: todos ven el mismo avance, del celular a la oficina.'],
    ['fa-clipboard-list', 'El reporte se arma en Word al final del mes y las fotos andan en el chat.', 'Reporte diario con personal, actividades y fotos, en PDF listo para el cliente.'],
    ['fa-binoculars', 'Las restricciones se descubren en la junta, cuando ya frenaron la obra.', 'Restricciones con responsable y fecha, visibles antes de que detengan el frente.'],
    ['fa-truck-ramp-box', 'Los materiales viven en una hoja que nadie cruza con el cronograma.', 'Cada material ligado a su actividad, con compras vencidas y submittals a la vista.'],
    ['fa-file-signature', 'Los cambios se explican de memoria y el atraso no tiene culpable.', 'Órdenes de cambio con impacto en costo y días, y línea base para comparar.'],
    ['fa-user-check', 'El cliente espera el PDF de la semana para saber cómo va la obra.', 'Consulta el avance en solo lectura cuando quiera, sin poder modificar nada.'],
];
$entregables = [
    ['fa-file-pdf', 'Informe ejecutivo', 'Avance, semáforo, desviaciones y decisiones pendientes.', 'Semanal o quincenal', 'base'],
    ['fa-clipboard-list', 'Reporte diario', 'Personal, actividades y fotografías de cada día, listos para compartir.', 'Diario o por corte', 'base'],
    ['fa-binoculars', 'Lookahead y Last Planner', 'Las próximas semanas con sus limitaciones y los post-its del día.', 'Semanal', 'base'],
    ['fa-handshake', 'Minutas con compromisos', 'Temas, responsables y fechas, en PDF.', 'Después de cada junta', 'base'],
    ['fa-folder-open', 'Archivo ordenado del proyecto', 'Planos, contratos y documentos por revisión, con acceso controlado.', 'Permanente', 'base'],
    ['fa-sheet-plastic', 'Láminas vPlan', 'Planos con el avance marcado por frente o por área.', 'Para cada junta', 'medida'],
    ['fa-truck-ramp-box', 'Tablero de materiales', 'Qué falta aprobar, comprar o entregar, y qué lo frena.', 'Semanal', 'medida'],
    ['fa-file-signature', 'Registro de cambios', 'Órdenes de cambio, líneas base y desviaciones justificadas.', 'Al ocurrir cada cambio', 'medida'],
    ['fa-clipboard-check', 'Cierre y punch list', 'Pendientes de calidad con foto antes y después, y acta de entrega.', 'Al cierre de etapa', 'medida'],
];
$formatos = [
    ['Documentos', ['PDF', 'Word (.docx)', 'ODT']],
    ['Hojas de cálculo', ['Excel (.xlsx)', 'ODS', 'CSV']],
    ['Programación', ['XML de MS Project', 'POD de ProjectLibre']],
    ['Planos y gráficos', ['PDF', 'PNG', 'Presentación (.odp)', 'ZIP']],
    ['Integración', ['JSON (API)', 'ICS (calendario)']],
];
$modalidades = [
    ['fa-bullseye', 'Seguimiento puntual', 'Para obras pequeñas, una etapa o un frente concreto.', ['Informe ejecutivo y reporte periódico', 'Control de materiales clave', 'Minutas y acuerdos de cada junta', 'Cierre de etapa con punch list'], false, 'Esencial'],
    ['fa-people-group', 'Acompañamiento continuo', 'Para obras en marcha que necesitan seguimiento semanal.', ['Todo lo del seguimiento puntual', 'Lookahead, Last Planner y restricciones', 'Tablero de materiales y cambios', 'Láminas vPlan para tus juntas', 'Acceso de solo lectura para tu cliente'], true, 'Completo'],
    ['fa-building-circle-check', 'Oficina de control de proyecto', 'Para varias obras o grupos constructores.', ['Todo lo anterior, para varias obras', 'Informes consolidados para dirección', 'Curva S y valor ganado por proyecto', 'Capacitación a tu equipo en la plataforma'], false, 'Consolidado'],
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
    ['¿Ustedes aportan la experiencia técnica de la especialidad?', 'No. Nosotros administramos y damos seguimiento a la información; la experiencia y el criterio de cada disciplina los aporta tu empresa, supervisor o residente. Trabajamos con los datos que ellos comparten y validan las decisiones.'],
    ['¿Puedo pedir más o menos información?', 'Sí. El alcance es a la medida: defines con nosotros qué entregables necesitas, con qué frecuencia y en qué formato, según tu presupuesto.'],
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
            <p class="lp-lead">Nos encargamos de la administración y el seguimiento de tu obra sobre una plataforma propia. La experiencia técnica de cada disciplina la aporta tu equipo —residente, supervisor o empresa—; nosotros mantenemos la información al día, ordenada y lista para decidir.</p>
            <div class="lp-cta">
                <a href="/#contacto" class="lp-btn lp-btn-primary lp-btn-lg"><i class="fa-solid fa-paper-plane"></i> Cuéntanos de tu obra</a>
                <a href="#que-hacemos" class="lp-btn lp-btn-ghost lp-btn-lg">Ver qué ofrecemos</a>
            </div>
            <ul class="lp-check">
                <li><i class="fa-solid fa-circle-check"></i> Informes semanales o por junta</li>
                <li><i class="fa-solid fa-circle-check"></i> Tu equipo conserva la responsabilidad técnica</li>
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
            <p>Doce frentes administrativos que podemos asumir total o parcialmente. Se ajustan al tamaño, la etapa y el presupuesto de cada proyecto.</p>
        </div>
        <div class="lp-rol lp-rev">
            <i class="fa-solid fa-people-arrows"></i>
            <div>
                <h3>Nosotros administramos; tu equipo aporta la experiencia técnica</h3>
                <p>Todo lo que llevamos va acompañado de la experiencia de tu empresa, supervisor o residente: ellos comparten la información de la disciplina y validan las decisiones. Nosotros actualizamos los datos, damos seguimiento, preparamos los reportes y mantenemos el control administrativo. No sustituimos la responsabilidad técnica de la obra.</p>
            </div>
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
            <h2>De apagar incendios con archivos sueltos<br>a dirigir la obra con un solo tablero</h2>
            <p>El problema no es la falta de datos: es que viven en el programa de una persona, en el chat de la cuadrilla y en el Word de la junta. Cuando el seguimiento se centraliza, cambia lo que puedes ver y decidir.</p>
        </div>
        <div class="lp-antes-grid">
            <?php foreach ($antesAhora as $i => [$ico, $a, $b]): ?>
                <article class="lp-antes-card lp-rev" style="--d:<?php echo ($i % 3) * 70; ?>ms">
                    <span class="lp-antes-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <div class="lp-antes-fila lp-antes-fila-no"><span class="lp-antes-tag">Antes</span><p><?php echo s($a); ?></p></div>
                    <div class="lp-antes-fila lp-antes-fila-si"><span class="lp-antes-tag">Ahora</span><p><?php echo s($b); ?></p></div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="lp-antes-cierre lp-rev"><i class="fa-solid fa-arrow-trend-up"></i> Una sola fuente de verdad, menos retrabajo y decisiones con los datos del día.</p>
    </div>
</section>

<section class="lp-sec" id="especializado">
    <div class="lp-wrap">
        <div class="lp-show lp-rev">
            <div class="lp-show-txt">
                <span class="lp-eyebrow">Análisis administrativo de la información</span>
                <h2>Detalles que se escapan cuando la obra<br>vive en archivos separados</h2>
                <p>Un proyecto genera cientos de datos que viven en archivos distintos. Revisamos la información en conjunto —fechas, materiales, documentos y cambios— y señalamos lo que no cuadra para que tu equipo técnico lo valide y decida.</p>
                <p>A modo indicativo, esto es lo que podemos detectar y poner sobre la mesa:</p>
                <ul class="lp-list">
                    <li><i class="fa-solid fa-check"></i> Cada hallazgo llega con su explicación y una propuesta</li>
                    <li><i class="fa-solid fa-check"></i> Tu equipo técnico valida y decide; nada cambia sin tu aprobación</li>
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

<section class="lp-sec lp-alt" id="entregables">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Lo que recibes</span>
            <h2>Entregables base,<br>más lo que tu obra necesite</h2>
            <p>No todos los proyectos necesitan lo mismo. Estos son los entregables base; con cada cliente acordamos cuáles se incluyen, con qué frecuencia y en qué formato, según el alcance, el tamaño de la obra y el presupuesto.</p>
        </div>
        <div class="lp-grid lp-grid-3">
            <?php foreach ($entregables as $i => [$ico, $t, $d, $f, $nivel]): ?>
                <article class="lp-card lp-rev" style="--d:<?php echo ($i % 3) * 70; ?>ms">
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <h3><?php echo s($t); ?></h3>
                    <p><?php echo s($d); ?></p>
                    <div class="lp-ent-meta">
                        <span class="lp-nivel lp-nivel-<?php echo $nivel === 'base' ? 'base' : 'medida'; ?>"><?php echo $nivel === 'base' ? 'Base' : 'A medida'; ?></span>
                        <span class="lp-freq"><i class="fa-regular fa-clock"></i> <?php echo s($f); ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="lp-niveles-nota lp-rev"><span class="lp-nivel lp-nivel-base">Base</span> incluido en cualquier modalidad. <span class="lp-nivel lp-nivel-medida">A medida</span> se cotiza según alcance, frecuencia y presupuesto.</p>

        <div class="lp-formatos lp-rev">
            <div class="lp-formatos-txt">
                <span class="lp-eyebrow">Formatos de entrega</span>
                <h3>En el formato que ya usa tu equipo</h3>
                <p>No tienes que adaptar tu forma de trabajar: entregamos el mismo dato en PDF, Excel, Word, XML de MS Project o en el formato que pida tu cliente. Si necesitas otro, lo revisamos contigo.</p>
            </div>
            <div class="lp-formatos-grupos">
                <?php foreach ($formatos as [$grupo, $items]): ?>
                    <div class="lp-formatos-grupo">
                        <h4><?php echo s($grupo); ?></h4>
                        <ul><?php foreach ($items as $it): ?><li><?php echo s($it); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="lp-sec" id="modalidades">
    <div class="lp-wrap">
        <div class="lp-head lp-rev">
            <span class="lp-eyebrow">Modalidades</span>
            <h2>Elige cuánto quieres delegar</h2>
            <p>No son paquetes cerrados: cada esquema se cotiza según el tamaño de la obra, el alcance y la frecuencia. Podemos empezar con una parte y ampliarla cuando la obra lo pida.</p>
        </div>
        <div class="lp-mods">
            <?php foreach ($modalidades as $i => $m): [$ico, $t, $lema, $items, $dest, $nivel] = $m; ?>
                <article class="lp-mod lp-rev<?php echo $dest ? ' lp-mod-top' : ''; ?>" style="--d:<?php echo $i * 80; ?>ms">
                    <?php if ($dest): ?><span class="lp-badge">Más elegida</span><?php endif; ?>
                    <span class="lp-ico"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                    <span class="lp-mod-nivel"><?php echo s($nivel); ?></span>
                    <h3><?php echo s($t); ?></h3>
                    <p class="lp-mod-lema"><?php echo s($lema); ?></p>
                    <ul class="lp-list"><?php foreach ($items as $it): ?><li><i class="fa-solid fa-check"></i> <?php echo s($it); ?></li><?php endforeach; ?></ul>
                    <a href="/?plan=<?php echo urlencode($t); ?>#contacto" class="lp-btn <?php echo $dest ? 'lp-btn-primary' : 'lp-btn-ghost'; ?>">Cotizar esta modalidad</a>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="lp-mods-nota lp-rev">
            <div>
                <h3>¿No encaja en ninguna?</h3>
                <p>Armamos un esquema mixto: tú llevas una parte y nosotros otra. El alcance, la frecuencia y los entregables se ajustan a tu presupuesto.</p>
            </div>
            <a href="/?plan=<?php echo urlencode('Plataforma + acompañamiento continuo'); ?>#contacto" class="lp-btn lp-btn-primary"><i class="fa-solid fa-paper-plane"></i> Armar mi esquema</a>
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
