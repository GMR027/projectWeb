<?php
/**
 * Inicio. Variables: $usuario, $proyectos, $totalProyectos, $proyectosActivos,
 * $proyectoInicio, $inicio [bloques, proyectos_n, propia], $activos, $datos,
 * $novedades, $personalizando.
 */
use Classes\InicioUsuario;

require_once __DIR__ . '/../templates/herramientas-proyecto.php';

$fechaCorta = fn($f) => $f ? date('d/m/y', strtotime($f)) : '—';
$proyectoInicio = $proyectoInicio ?? ($proyectos[0] ?? null);
$anchos = [];
foreach ($inicio['bloques'] as $b) $anchos[$b['k']] = (int)$b['ancho'];

$gruposHerramientas = $proyectoInicio ? herramientas_proyecto($proyectoInicio) : [];
$herramientasTotal = 0;
foreach ($gruposHerramientas as $items) $herramientasTotal += count($items);

$tieneProyectos = !empty($proyectos);
$tieneBloques = !empty($activos);
?>
<div class="inicio-pagina">
    <?php include __DIR__ . '/_hero.php'; ?>

    <?php if (!empty($mostrar_banner_android) && !empty($apk_android)): ?>
        <?php include __DIR__ . '/_android.php'; ?>
    <?php endif; ?>

    <?php if (!$tieneProyectos): ?>
        <?php include __DIR__ . '/_vacio.php'; ?>
    <?php else: ?>
        <div class="inicio-rejilla">
            <?php if (!$tieneBloques): ?>
                <div class="inicio-bloque inicio-bloque--12">
                    <section class="card inicio-sin-bloques">
                        <span class="inicio-sin-bloques-ico" aria-hidden="true"><i class="fa-regular fa-square-check"></i></span>
                        <div>
                            <h3><?php echo s(t('Tu inicio está vacío')); ?></h3>
                            <p class="text-muted"><?php echo s(t('No tienes ningún bloque activo. Abre «Personalizar inicio» para elegir qué ver.')); ?></p>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" data-inicio-personalizar><i class="fa-solid fa-sliders" aria-hidden="true"></i> <?php echo s(t('Personalizar inicio')); ?></button>
                    </section>
                </div>
            <?php endif; ?>

            <?php foreach ($activos as $bloque): $ancho = $anchos[$bloque] ?? 12; ?>
                <div class="inicio-bloque inicio-bloque--<?php echo (int)$ancho; ?>" data-inicio-bloque="<?php echo s($bloque); ?>">
                    <?php switch ($bloque):
                        case 'resumen': include __DIR__ . '/_resumen.php'; break;
                        case 'atencion': include __DIR__ . '/_atencion.php'; break;
                        case 'proyectos': include __DIR__ . '/_proyectos.php'; break;
                        case 'herramientas': include __DIR__ . '/_herramientas.php'; break;
                        case 'novedades': include __DIR__ . '/_novedades.php'; break;
                        case 'atajos': include __DIR__ . '/_atajos.php'; break;
                    endswitch; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php include __DIR__ . '/_personalizar.php'; ?>
    <?php include __DIR__ . '/_herramientas-dialogo.php'; ?>
</div>

<script src="/vendor/inicio.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/inicio.js'); ?>"></script>
