<?php
// Curso del administrador (solo admin). $modulos viene de views/manual/_curso.php y ya trae las figuras resueltas.
$totalLecciones = 0;
$minutos = 0;
foreach ($modulos as $m) {
    $totalLecciones += count($m['lecciones']);
    $minutos += (int)$m['duracion'];
}
$horas = round($minutos / 60, 1);

require_once __DIR__ . '/../templates/herramientas-proyecto.php';
$ayudas = function_exists('ayudas_herramientas') ? ayudas_herramientas() : [];
$grupos = \Model\Usuario::gruposHerramientas();
$moduloDeGrupo = [
    'Planeación' => 2,
    'Obra y seguimiento' => 3,
    'Materiales y costos' => 4,
    'Documentos y planos' => 5,
    'Administración del proyecto' => 6,
    'IA y revisión' => 7,
    'Otras herramientas' => 6,
];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-graduation-cap"></i> Curso del administrador</h2>
        <p class="text-muted">De cero a capacitar a tus clientes · <?php echo count($modulos); ?> módulos · <?php echo (int)$totalLecciones; ?> lecciones · ~<?php echo $horas; ?> h</p>
    </div>
    <a href="/manual" class="btn btn-ghost"><i class="fa-solid fa-book-open"></i> Volver al manual</a>
</div>

<section class="card curso-hero">
    <div class="curso-hero-txt">
        <span class="lp-eyebrow">Ruta completa</span>
        <h3>Aprende haciendo, con una obra real</h3>
        <p>Este curso sigue el orden de una obra: preparar, planear, ejecutar, abastecer, documentar, administrar, usar la IA y capacitar al cliente. Cada lección trae pasos, un ejercicio y la forma de explicárselo a un cliente.</p>
    </div>
    <div class="curso-progreso-caja">
        <div class="curso-progreso"><div class="curso-progreso-barra"><span id="curso-progreso"></span></div><strong id="curso-progreso-txt">0 %</strong></div>
        <p class="text-muted text-sm">Tu avance se guarda en este navegador. No se comparte con nadie.</p>
        <button type="button" id="curso-reiniciar" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reiniciar progreso</button>
    </div>
</section>

<div class="manual-area curso-area">
    <aside class="card manual-indice curso-indice" id="curso-contenido" aria-label="Índice del curso">
        <h3 class="card-titulo">Módulos</h3>
        <nav id="curso-toc">
            <?php foreach ($modulos as $i => $m): ?>
                <a href="#<?php echo s($m['id']); ?>" data-nav-modulo="<?php echo s($m['id']); ?>"><i class="fa-solid <?php echo s($m['icono']); ?>"></i> <span><?php echo s($m['titulo']); ?></span><small><?php echo s($m['duracion']); ?> · <?php echo count($m['lecciones']); ?> lecciones</small></a>
                <div class="curso-subnav">
                    <?php foreach ($m['lecciones'] as $l): ?>
                        <a href="#<?php echo s($l['id']); ?>" data-nav-leccion="<?php echo s($l['id']); ?>"><?php echo s($l['titulo']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="manual-contenido">
        <section class="card curso-mapa" id="mapa">
            <h3><i class="fa-solid fa-map"></i> Mapa de todas las herramientas</h3>
            <p class="text-muted">Cada herramienta de la plataforma, agrupada por sección, con lo que hace y el módulo donde se enseña. El <strong>Ensamblador</strong> y algunas herramientas de IA viven en la app de escritorio; se explican en los módulos 5 y 7.</p>
            <?php foreach ($grupos as $grupo => $items): ?>
                <h4 class="curso-mapa-grupo"><?php echo s($grupo); ?></h4>
                <div class="curso-mapa-grid">
                    <?php foreach ($items as $clave => $etiqueta):
                        $rutas = \Model\Usuario::HERRAMIENTAS[$clave][1] ?? [];
                        $ruta = $rutas[0] ?? '';
                        $desc = $ayudas[$ruta][0] ?? '';
                        $sec = $ayudas[$ruta][1] ?? '';
                        $mod = $moduloDeGrupo[$grupo] ?? 6;
                    ?>
                        <article class="curso-mapa-item">
                            <strong><?php echo s($etiqueta); ?></strong>
                            <p><?php echo s($desc); ?></p>
                            <span class="curso-mapa-meta">Módulo <?php echo (int)$mod; ?><?php if ($sec): ?> · <a href="/manual#<?php echo s($sec); ?>">Ver en el manual</a><?php endif; ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </section>

        <?php foreach ($modulos as $m): ?>
            <section class="card curso-modulo" id="<?php echo s($m['id']); ?>">
                <header class="curso-modulo-cab">
                    <h3><i class="fa-solid <?php echo s($m['icono']); ?>"></i> <?php echo s($m['titulo']); ?></h3>
                    <p><?php echo s($m['objetivo']); ?></p>
                    <span class="curso-meta"><i class="fa-regular fa-clock"></i> <?php echo s($m['duracion']); ?> · <?php echo count($m['lecciones']); ?> lecciones</span>
                </header>
                <?php foreach ($m['lecciones'] as $l): ?>
                    <article class="curso-leccion" id="<?php echo s($l['id']); ?>" data-leccion="<?php echo s($l['id']); ?>">
                        <label class="curso-check"><input type="checkbox" data-curso-check="<?php echo s($l['id']); ?>"> <span>Lección completada</span></label>
                        <h4><?php echo s($l['titulo']); ?></h4>
                        <div class="curso-cuerpo"><?php echo $l['cuerpo']; // HTML fijo del curso ?></div>
                        <?php if (!empty($l['ejercicio'])): ?>
                            <div class="curso-callout curso-ejercicio"><i class="fa-solid fa-pen-to-square"></i><div><strong>Ejercicio</strong><p><?php echo s($l['ejercicio']); ?></p></div></div>
                        <?php endif; ?>
                        <?php if (!empty($l['ensenar'])): ?>
                            <div class="curso-callout curso-ensenar"><i class="fa-solid fa-chalkboard-user"></i><div><strong>Cómo enseñarlo al cliente</strong><p><?php echo s($l['ensenar']); ?></p></div></div>
                        <?php endif; ?>
                        <?php if (!empty($l['video'])): ?>
                            <details class="curso-video">
                                <summary><i class="fa-solid fa-video"></i> Video sugerido</summary>
                                <p><?php echo s($l['video']); ?></p>
                                <p class="text-muted text-sm">Cuando grabes el video, guárdalo en <code>public/vendor/curso/</code> y reemplaza este bloque por un <code>&lt;video controls&gt;</code>. Así el curso queda completo y sin depender de plataformas externas.</p>
                            </details>
                        <?php endif; ?>
                        <a href="#curso-contenido" class="curso-subir">↑ Volver al índice</a>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>

        <section class="card curso-cierre" id="cierre">
            <h3><i class="fa-solid fa-certificate"></i> Cierre del curso</h3>
            <p>Cuando termines los nueve módulos, haz el examen práctico del Módulo 9 y agenda una demo real con un cliente. El objetivo no es saber más: es poder <strong>enseñar sin depender de nadie</strong>.</p>
            <div class="curso-cta-final">
                <a href="/manual" class="btn btn-ghost"><i class="fa-solid fa-book-open"></i> Repasar el manual</a>
                <a href="/proyectos" class="btn btn-primary"><i class="fa-solid fa-play"></i> Ir a una obra real</a>
            </div>
        </section>
    </div>
</div>

<script src="/vendor/curso-admin.js?v=<?php echo file_exists(__DIR__ . '/../../public/vendor/curso-admin.js') ? filemtime(__DIR__ . '/../../public/vendor/curso-admin.js') : '1'; ?>"></script>
