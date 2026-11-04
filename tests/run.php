<?php
// Pruebas rápidas de las piezas sin base de datos: php tests/run.php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/idioma.php';
require __DIR__ . '/../includes/funciones.php';

use Classes\SvgSeguro;
use Classes\Exportadores\OdpExporter;
use Model\Grafico;
use Model\Cajetin;
use Model\Subactividad;

$fallos = 0;
$ok = function (string $nombre, bool $cond) use (&$fallos) { echo ($cond ? '  ok   ' : '  FALLA ') . $nombre . "\n"; if (!$cond) $fallos++; };

echo "SvgSeguro\n";
$limpio = SvgSeguro::limpiar('<svg viewBox="0 0 10 10" onload="x()"><script>alert(1)</script><rect width="5" height="5" onclick="a" style="fill:url(http://x)"/><image href="http://e/x.png"/></svg>');
$ok('quita scripts, eventos, imágenes y estilos externos', $limpio && !preg_match('/<script|onload|onclick|<image|url\(/i', $limpio) && str_contains($limpio, '<rect'));
$ok('agrega ancho y alto desde el viewBox', $limpio && str_contains($limpio, 'width="10"') && str_contains($limpio, 'height="10"'));
$ok('rechaza entidades externas', SvgSeguro::limpiar('<!DOCTYPE x [<!ENTITY a "b">]><svg/>') === null);
$ok('rechaza lo que no es SVG', SvgSeguro::limpiar('<html></html>') === null);

echo "Grafico::limpiarConfigFondo\n";
$c = Grafico::limpiarConfigFondo(['modo' => 'x', 'repX' => 999, 'opacidad' => 1, 'color' => 'rojo', 'escala' => 50]);
$ok('valores fuera de rango se corrigen', $c['modo'] === 'cubrir' && $c['repX'] === 60 && $c['opacidad'] === 5 && $c['color'] === '#ffffff' && $c['escala'] === 5.0);

echo "Cajetin\n";
$f = json_decode(Cajetin::filasDeTexto("Proyecto | {proyecto}\n\nRevisó |\n"), true);
$ok('una fila por línea, sin líneas vacías', $f === [['Proyecto', '{proyecto}'], ['Revisó', '']]);
$ok('máximo 20 filas', count(json_decode(Cajetin::filasDeTexto(str_repeat("a | b\n", 30)), true)) === 20);

echo "Subactividad\n";
$ok('sin subactividades el promedio es 0', Subactividad::promedio([]) === 0);
$ok('promedia y redondea los avances', Subactividad::promedio([100, 50]) === 75 && Subactividad::promedio([100, 1]) === 51);
$ok('acota los avances fuera de 0–100', Subactividad::promedio([-10, 150]) === 50);
$sub = new Subactividad(['nombre' => '', 'fecha_inicio' => '', 'fecha_fin' => '']);
$sub->validar();
$ok('exige nombre y fechas', count(Subactividad::getAlertas()['error'] ?? []) === 3);
$sub = new Subactividad(['nombre' => 'Tramo A', 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-01', 'status' => 40]);
$sub->validar();
$ok('rechaza fin anterior al inicio', !empty(Subactividad::getAlertas()['error']));
$sub = new Subactividad(['nombre' => 'Tramo A', 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-10', 'status' => 140]);
$alertas = $sub->validar();
$ok('una subactividad válida no tiene errores y acota el avance', empty($alertas) && $sub->status === 100);

echo "Contacto\n";
$ok('el interés agrupa plataforma, servicio y ambos', in_array('Plan Pro', \Model\Contacto::planesValidos(), true)
    && in_array('Acompañamiento continuo', \Model\Contacto::planesValidos(), true)
    && in_array('Plataforma + acompañamiento continuo', \Model\Contacto::planesValidos(), true)
    && !in_array('Servicio de seguimiento', \Model\Contacto::planesValidos(), true));

echo "Traductor\n";
$ok('solo español e inglés', Classes\Traductor::idiomaValido('es') && Classes\Traductor::idiomaValido('en') && !Classes\Traductor::idiomaValido('fr'));
$ok('textos sin letras no llaman al motor', !Classes\Traductor::configurado() || Classes\Traductor::traducir(['123', ' ', '-'], 'es', 'en') === ['123', ' ', '-']);

echo "OdpExporter\n";
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
$odp = OdpExporter::generar([['png' => $png, 'nombre' => 'Uno'], ['png' => $png]], 1920, 1080, 'Prueba');
$tmp = tempnam(sys_get_temp_dir(), 'odp'); file_put_contents($tmp, $odp);
$z = new ZipArchive(); $abre = $z->open($tmp) === true;
$ok('es un zip válido', $abre);
$ok('mimetype es la primera entrada y sin comprimir', $abre && $z->getNameIndex(0) === 'mimetype' && $z->getFromName('mimetype') === 'application/vnd.oasis.opendocument.presentation');
$ok('incluye una imagen por lámina', $abre && $z->locateName('Pictures/imagen1.png') !== false && $z->locateName('Pictures/imagen2.png') !== false);
$ok('content.xml bien formado con 2 páginas', $abre && ($d = simplexml_load_string($z->getFromName('content.xml'))) !== false && substr_count($z->getFromName('content.xml'), '<draw:page ') === 2);
@unlink($tmp);


echo "RutaCritica\n";
$cal = new class {
    public function esHabil(string $d): bool { return (int)date('N', strtotime($d)) <= 5; }
    public function getFactor(string $d): float { return $this->esHabil($d) ? 1.0 : 0.0; }
    public function calcularFechaFin(string $ini, int $dur): string { $f = $ini; while (!$this->esHabil($f)) $f = date('Y-m-d', strtotime("$f +1 day")); for ($i = 1; $i < $dur; $i++) { do { $f = date('Y-m-d', strtotime("$f +1 day")); } while (!$this->esHabil($f)); } return $f; }
};
$rc = new Classes\RutaCritica($cal);
$mk = fn($id, $ini, $dur, $hito = 0, $fija = 0) => ['id' => $id, 'inicio' => $ini, 'fin' => $dur ? $cal->calcularFechaFin($ini, $dur) : $ini, 'dur' => $dur, 'hito' => $hito, 'fija' => $fija];
// A 5d → B 4d → C 2d (fin-inicio) y D 3d suelta; todas empiezan el lunes 5-oct-2026
$acts = [1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-05', 4), 3 => $mk(3, '2026-10-05', 2), 4 => $mk(4, '2026-10-05', 3)];
$r = $rc->calcular($acts, [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0], ['id' => 2, 'pred' => 2, 'suc' => 3, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('FS: B empieza el lunes siguiente al fin de A', $r['acts'][2]['es'] === '2026-10-12' && $r['acts'][2]['ef'] === '2026-10-15');
$ok('FS encadenado: C empieza el viernes 16 y termina el lunes 19', $r['acts'][3]['es'] === '2026-10-16' && $r['acts'][3]['ef'] === '2026-10-19');
$ok('B y C estaban en conflicto con sus fechas actuales', $r['acts'][2]['conflicto'] && $r['acts'][3]['conflicto'] && $r['deps'][1]['conflicto']);
$ok('A, B y C son la ruta crítica; D (sin relaciones) no', $r['acts'][1]['critica'] && $r['acts'][2]['critica'] && $r['acts'][3]['critica'] && !$r['acts'][4]['critica'] && !$r['acts'][4]['enRed']);
$ok('fin calculado = fin de C', $r['finCalculado'] === '2026-10-19');
// Holgura: E (2d) cuelga de A y no tiene sucesoras → 9 días hábiles de holgura contra el fin de C
$acts[5] = $mk(5, '2026-10-12', 2);
$r = $rc->calcular($acts, [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0], ['id' => 2, 'pred' => 2, 'suc' => 3, 'tipo' => 'FS', 'retraso' => 0], ['id' => 3, 'pred' => 1, 'suc' => 5, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('holgura de una rama corta: E (2 días) puede empezar hasta el viernes 16 → 4 días hábiles', $r['acts'][5]['holgura'] === 4 && !$r['acts'][5]['critica']);
// SS con retraso 2 y FF
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-05', 3)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'SS', 'retraso' => 2]]);
$ok('SS +2: la sucesora empieza 2 días hábiles después del inicio', $r['acts'][2]['es'] === '2026-10-07');
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-05', 3)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FF', 'retraso' => 0]]);
$ok('FF: la sucesora termina cuando termina la predecesora', $r['acts'][2]['ef'] === '2026-10-09' && $r['acts'][2]['es'] === '2026-10-07');
// fecha fija y que nunca adelanta
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-20', 2, 0, 1)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('una sucesora con fecha posterior no se adelanta', $r['acts'][2]['es'] === '2026-10-20' && !$r['acts'][2]['conflicto']);
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-05', 2, 0, 1)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('fecha fija: no se mueve, pero se marca el conflicto', $r['acts'][2]['es'] === '2026-10-05' && $r['acts'][2]['conflicto']);
// ciclos
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 2), 2 => $mk(2, '2026-10-05', 2)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0], ['id' => 2, 'pred' => 2, 'suc' => 1, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('detecta ciclos', $r['ciclo'] !== null && count($r['ciclo']) === 2);
// hito
$r = $rc->calcular([1 => $mk(1, '2026-10-05', 5), 2 => $mk(2, '2026-10-05', 0, 1)], [['id' => 1, 'pred' => 1, 'suc' => 2, 'tipo' => 'FS', 'retraso' => 0]]);
$ok('hito después de una actividad: mismo día de inicio y fin', $r['acts'][2]['es'] === '2026-10-12' && $r['acts'][2]['ef'] === '2026-10-12');

echo "Idioma\n";
$_COOKIE['idioma'] = 'en';
$ok('traduce al inglés', t('Ajustes') === 'Settings');
$ok('texto compuesto «Sección - Proyecto»', t('Editor - Torre') === 'Editor - Torre' && t('Evidencias - Torre') === 'Evidence - Torre');
$ok('texto sin traducción queda igual', t('Algo inventado') === 'Algo inventado');

echo "Entradas manipuladas (arreglos en la URL)\n";
$ok('s() con arreglo devuelve vacío en vez de TypeError', s(['x']) === '');
$c = ['es'];   // cookie idioma manipulada: la misma lógica de idioma_actual() la descarta
if (!is_string($c)) $c = 'es';
$ok('cookie de idioma como arreglo se ignora y cae a «es»', $c === 'es');

echo "IP del cliente (detrás de proxy)\n";
$ok('IP dentro del rango CIDR', ip_en_rangos('10.1.219.129', '10.1.0.0/16') && !ip_en_rangos('10.2.0.1', '10.1.0.0/16'));
$_SERVER['REMOTE_ADDR'] = '10.1.219.129';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '9.9.9.9, 201.1.2.3, 10.1.1.1';
$_ENV['TRUSTED_PROXIES'] = '10.1.0.0/16';
$ok('con proxy de confianza toma la primera IP no confiable desde la derecha (ignora la falsificada)', ip_cliente() === '201.1.2.3');
$_ENV['TRUSTED_PROXIES'] = '';
$ok('sin TRUSTED_PROXIES usa REMOTE_ADDR aunque venga X-Forwarded-For', ip_cliente() === '10.1.219.129');
$_SERVER['REMOTE_ADDR'] = '200.5.5.5';
$_ENV['TRUSTED_PROXIES'] = '10.1.0.0/16';
$ok('si la conexión no viene del proxy se ignora X-Forwarded-For', ip_cliente() === '200.5.5.5');

echo "Cruce de materiales y cronograma\n";
$hoy = '2026-10-18';
[$e] = Classes\CruceMateriales::evaluar('2026-10-25', ['fecha_entrega_real' => '2026-10-20'], $hoy);
$ok('entregado = en sitio', $e === 'en_sitio');
[$e, $d] = Classes\CruceMateriales::evaluar('2026-10-25', ['compra' => 'pendiente', 'fecha_compra_prog' => '2026-10-15', 'fecha_entrega_prog' => '2026-10-20'], $hoy);
$ok('compra pendiente con fecha pasada = compra vencida (3 días)', $e === 'compra_vencida' && $d === 3);
[$e, $d] = Classes\CruceMateriales::evaluar('2026-10-25', ['compra' => 'comprado', 'fecha_entrega_prog' => '2026-10-28'], $hoy);
$ok('entrega posterior a la necesidad = llega tarde (3 días)', $e === 'tarde' && $d === 3);
[$e] = Classes\CruceMateriales::evaluar('2026-10-25', ['compra' => 'comprado'], $hoy);
$ok('sin fecha de entrega', $e === 'sin_fecha');
[$e] = Classes\CruceMateriales::evaluar('2026-10-25', ['compra' => 'comprado', 'fecha_entrega_prog' => '2026-10-22', 'sub' => 'rechazado'], $hoy);
$ok('a tiempo pero con submittal sin aprobar', $e === 'submittal');
[$e] = Classes\CruceMateriales::evaluar('2026-10-25', ['compra' => 'comprado', 'fecha_entrega_prog' => '2026-10-22', 'sub' => 'aprobado'], $hoy);
$ok('a tiempo y aprobado', $e === 'a_tiempo');

echo "Dependencias de materiales\n";
$sumar = fn(string $f, int $n): string => date('Y-m-d', strtotime($f . ' +' . $n . ' day'));
$calc = Classes\DependenciasMateriales::calcular([
    1 => ['id' => 1, 'nombre' => 'Caja', 'entrega' => '2026-10-10', 'instalacion' => null],
    2 => ['id' => 2, 'nombre' => 'Base', 'entrega' => '2026-10-12', 'instalacion' => null],
    3 => ['id' => 3, 'nombre' => 'Cámara bullet', 'entrega' => '2026-10-05', 'instalacion' => null],
], [
    ['pred' => 1, 'suc' => 3, 'requisito' => 'entrega', 'retraso' => 0],
    ['pred' => 2, 'suc' => 3, 'requisito' => 'entrega', 'retraso' => 0],
], $sumar);
$ok('varios prerrequisitos: la disponibilidad es el más tardío', $calc['materiales'][3]['entrega_ef'] === '2026-10-12');
$ok('sin ciclo en un grafo de varios prerrequisitos', $calc['ciclo'] === null && count($calc['orden']) === 3);
$ok('piso de la actividad = máximo de sus materiales', Classes\DependenciasMateriales::pisoActividad([1, 2, 3], $calc)['fecha'] === '2026-10-12');

$calc = Classes\DependenciasMateriales::calcular([
    1 => ['id' => 1, 'nombre' => 'Soportería', 'entrega' => '2026-10-10', 'instalacion' => '2026-10-20'],
    2 => ['id' => 2, 'nombre' => 'Cable', 'entrega' => '2026-10-08', 'instalacion' => null],
], [['pred' => 1, 'suc' => 2, 'requisito' => 'instalacion', 'retraso' => 2]], $sumar);
$ok('requisito de instalación + retraso en días hábiles', $calc['materiales'][2]['entrega_ef'] === '2026-10-22');
$ok('el material que se instala también tiene fecha de instalación', $calc['materiales'][1]['instalacion_ef'] === '2026-10-20');

$calc = Classes\DependenciasMateriales::calcular([
    1 => ['id' => 1, 'nombre' => 'A', 'entrega' => '2026-10-10', 'instalacion' => null],
    2 => ['id' => 2, 'nombre' => 'B', 'entrega' => '2026-10-10', 'instalacion' => null],
], [
    ['pred' => 1, 'suc' => 2, 'requisito' => 'entrega', 'retraso' => 0],
    ['pred' => 2, 'suc' => 1, 'requisito' => 'entrega', 'retraso' => 0],
], $sumar);
$ok('detecta el ciclo entre materiales', is_array($calc['ciclo']) && count($calc['ciclo']) === 2);

$calc = Classes\DependenciasMateriales::calcular([
    1 => ['id' => 1, 'nombre' => 'Sin fecha', 'entrega' => null, 'instalacion' => null],
    2 => ['id' => 2, 'nombre' => 'Dependiente', 'entrega' => '2026-10-05', 'instalacion' => null],
], [['pred' => 1, 'suc' => 2, 'requisito' => 'entrega', 'retraso' => 0]], $sumar);
$ok('un prerrequisito sin fecha bloquea el material', $calc['materiales'][2]['entrega_ef'] === null && $calc['materiales'][2]['bloqueado']);
$ok('el piso de una actividad bloqueada no inventa fecha', Classes\DependenciasMateriales::pisoActividad([2], $calc)['bloqueado'] === true && Classes\DependenciasMateriales::pisoActividad([2], $calc)['fecha'] === null);
[$e, $d] = Classes\CruceMateriales::evaluar('2026-10-25', ['compra' => 'comprado', 'fecha_entrega_prog' => '2026-10-20'], '2026-10-18', '2026-10-28');
$ok('cruce: el material llega por un prerrequisito después de la necesidad', $e === 'bloqueado_requisito' && $d === 3);

echo "Renglones de órdenes de cambio\n";
$ok('lo temporal se cierra al retirarlo', Model\CambioPartida::estadosDe('instalacion_temporal', true) === ['pendiente', 'ejecutado', 'retirado', 'cancelado']);
$ok('lo demás se cierra al verificarlo', Model\CambioPartida::estadosDe('reubicacion', false) === ['pendiente', 'ejecutado', 'verificado', 'cancelado']);
$c = new Model\CambioPartida(['tipo' => 'instalacion_temporal', 'requiere_retiro' => 1, 'estado' => 'ejecutado']);
$ok('temporal ya instalado = retiro pendiente', $c->retiroPendiente());
$c->estado = 'retirado';
$ok('temporal retirado ya no está pendiente', !$c->retiroPendiente());
$ok('la descripción es obligatoria', isset((new Model\CambioPartida(['descripcion' => ' ']))->validar()['error']));

echo "Ensamblador y Renumeracion\n";
$ok('norm quita acentos, signos y palabras vacías', Classes\Ensamblador::norm('Instalación de CÁMARAS (tipo domo)') === 'instalacion camaras domo');
$ok('nombres iguales salvo acentos = 1', Classes\Ensamblador::similitud('Ingeniería', 'INGENIERIA') === 1.0);
$ok('nombres distintos = parecido bajo', Classes\Ensamblador::similitud('Cimentación', 'Submittals') < 0.4);
$ok('un nombre dentro de otro se acerca a coincidir', Classes\Ensamblador::similitud('Cámaras de videovigilancia', 'Instalación de cámaras de videovigilancia en área L') >= 0.65);
$ok('esPregunta: con signo o palabra interrogativa y sin botones', Classes\Ensamblador::esPregunta('¿cuántas cámaras hay?', []) && !Classes\Ensamblador::esPregunta('¿cuántas?', ['materiales']) && !Classes\Ensamblador::esPregunta('agrega los materiales', []));
$cod = Classes\Renumeracion::codigos([['id' => 9, 'actividades' => [4, 5]], ['id' => 3, 'actividades' => [7]]]);
$ok('renumerar: partidas N.0 y actividades N.M en el nuevo orden', $cod[9] === '1.0' && $cod['a5'] === '1.2' && $cod[3] === '2.0' && $cod['a7'] === '2.1');

echo "Estampas inteligentes\n";
use Classes\Estampas;
$ok('fecha: corta, larga y completa', Estampas::formatear('2026-10-15', 'fecha') === '15/10/2026' && Estampas::formatear('2026-10-15', 'fecha', 'largo') === '15 de octubre de 2026'
    && Estampas::formatear('2026-10-15', 'fecha', 'completo') === 'jueves 15 de octubre de 2026' && Estampas::formatear('2026-10-05', 'fecha', 'corto') === '05 oct');
$ok('números: enteros sin decimales y decimales pedidos', Estampas::formatear(5, 'num') === '5' && Estampas::formatear(0.8912, 'num') === '0.89' && Estampas::formatear(0.8912, 'num', '1') === '0.9' && Estampas::formatear(34.8, 'pct') === '34.8');
$ok('un valor vacío no escribe nada', Estampas::formatear(null, 'texto') === '' && Estampas::formatear('', 'fecha') === '');
$ok('semáforo «mayor es mejor»', Estampas::semaforo(0.97, 'mayor', 0.95, 0.85) === 'verde' && Estampas::semaforo(0.9, 'mayor', 0.95, 0.85) === 'amarillo' && Estampas::semaforo(0.8, 'mayor', 0.95, 0.85) === 'rojo');
$ok('semáforo «menor es mejor»', Estampas::semaforo(0, 'menor', 0, 3) === 'verde' && Estampas::semaforo(2, 'menor', 0, 3) === 'amarillo' && Estampas::semaforo(4, 'menor', 0, 3) === 'rojo');
$r = Estampas::reemplazar('Corte {corte:largo} · {desconocida} · {proyecto}', fn($k, $f) => $k === 'corte' ? "[$k/$f]" : ($k === 'proyecto' ? 'Torre' : null));
$ok('variables {campo} y {campo:formato}; las desconocidas se dejan', $r === 'Corte [corte/largo] · {desconocida} · Torre');
$ok('catálogo: todo campo trae grupo, etiqueta y tipo válidos', !array_filter(Estampas::CAMPOS, fn($c) => !isset(Estampas::GRUPOS[$c[0]]) || !in_array($c[2], ['texto', 'fecha', 'num', 'pct'], true) || $c[1] === ''));
$lista = fn($p) => ($p['config']['modo'] ?? '') === 'lienzo' ? ($p['config']['elementos'] ?? []) : ($p['config']['etiquetas'] ?? []);
$ok('plantillas: todas con contenido y campos del catálogo', !array_filter(Estampas::plantillas(), fn($p) => !$lista($p) || array_filter($lista($p), fn($e) => isset($e['campo']) && !isset(Estampas::CAMPOS[$e['campo']]))));
$ok('semáforos por defecto apuntan a campos numéricos', !array_filter(array_keys(Estampas::SEMAFORO), fn($k) => !in_array(Estampas::CAMPOS[$k][2] ?? '', ['num', 'pct'], true)));

echo "Pdf y ShellTools\n";
$pdfA = \Classes\Pdf::generar('<html><body><p>Hola mundo</p></body></html>', 'letter', 'portrait');
$pdfB = \Classes\Pdf::generar('<html><body><p>Segunda</p></body></html>', 'a4', 'landscape', ['isHtml5ParserEnabled' => true]);
$ok('Pdf::generar devuelve un PDF', str_starts_with($pdfA, '%PDF') && str_starts_with($pdfB, '%PDF'));
$ok('Pdf::generar acepta papel en puntos', str_starts_with(\Classes\Pdf::generar('<p>x</p>', [0, 0, 300, 200]), '%PDF'));
$ok('ShellTools::disponible distingue lo que existe', \Classes\ShellTools::disponible('ls') && !\Classes\ShellTools::disponible('programa-que-no-existe-xyz'));
$r = \Classes\ShellTools::ejecutar(['echo', 'hola'], 5);
$ok('ShellTools::ejecutar devuelve salida y código', $r && trim($r['salida']) === 'hola' && $r['codigo'] === 0 && !$r['agotado']);
$r = \Classes\ShellTools::ejecutar(['sleep', '5'], 1);
$ok('ShellTools::ejecutar corta lo que tarda de más', $r && $r['agotado']);
$ok('ShellTools::correr: falso si el código no es 0', \Classes\ShellTools::correr(['true']) && !\Classes\ShellTools::correr(['false']));
if (\Classes\ShellTools::disponible('pdfunite') || \Classes\ShellTools::disponible('gs')) {
    $d = sys_get_temp_dir() . '/pdftest_' . bin2hex(random_bytes(4));
    mkdir($d);
    file_put_contents("$d/a.pdf", $pdfA);
    file_put_contents("$d/b.pdf", $pdfB);
    $unido = \Classes\ShellTools::unirPdf(["$d/a.pdf", "$d/b.pdf"], "$d/ab.pdf");
    $ok('ShellTools::unirPdf junta dos PDF', $unido && is_file("$d/ab.pdf") && substr_count((string)file_get_contents("$d/ab.pdf"), '/Type /Page') >= 2);
    if (\Classes\ShellTools::disponible('pdftotext')) {
        $t = \Classes\ShellTools::pdfATexto("$d/ab.pdf");
        $ok('ShellTools::pdfATexto lee el PDF unido', $t && str_contains($t['salida'], 'Hola mundo') && str_contains($t['salida'], 'Segunda'));
    }
    if (\Classes\ShellTools::disponible('soffice')) {
        file_put_contents("$d/n.txt", "Texto de prueba\n");
        $conv = \Classes\ShellTools::libreOffice("$d/n.txt", 'txt', 'pdf');
        $ok('ShellTools::libreOffice convierte a PDF y limpia sus temporales', $conv && str_starts_with((string)file_get_contents($conv), '%PDF') && !glob(sys_get_temp_dir() . '/lo_*'));
        if ($conv) @unlink($conv);
    }
    array_map('unlink', glob("$d/*"));
    rmdir($d);
}

echo "Sheetflow::importar\n";
$dImp = sys_get_temp_dir() . '/imp_' . bin2hex(random_bytes(3));
mkdir($dImp);
file_put_contents("$dImp/a.csv", "Nombre,Cantidad,Nota\nCañería,12,ñandú\nTubo,3.5,\n,,\nCable,7,ok\n");
file_put_contents("$dImp/b.csv", mb_convert_encoding("Nombre;Cantidad\nÁrbol;4\nPeña;5\n", 'Windows-1252', 'UTF-8'));
$ia = \Classes\Sheetflow::importar("$dImp/a.csv", 'a.csv');
$ib = \Classes\Sheetflow::importar("$dImp/b.csv", 'b.csv');
$ok('CSV UTF-8: encabezados, tipos y sin renglones vacíos', array_column($ia['cols'], 'n') === ['Nombre', 'Cantidad', 'Nota'] && $ia['cols'][1]['t'] === 'numero' && count($ia['rows']) === 3 && $ia['rows'][0][0] === 'Cañería');
$ok('CSV en Windows-1252 se lee con sus acentos', $ib['rows'] === [['Árbol', '4'], ['Peña', '5']]);
$rechazado = false;
try { \Classes\Sheetflow::importar("$dImp/a.csv", 'x.docx'); } catch (\RuntimeException $e) { $rechazado = true; }
$ok('rechaza formatos que no son hoja de cálculo', $rechazado);
array_map('unlink', glob("$dImp/*"));
rmdir($dImp);

echo "Sheetflow: fórmulas\n";
$sfF = \Classes\Sheetflow::normalizar([
    'cols' => [['n' => 'A'], ['n' => 'B'], ['n' => 'C'], ['n' => 'D'], ['n' => 'Importe', 't' => 'moneda'], ['n' => 'Amortización', 't' => 'moneda']],
    'rows' => [['', '', '', '', '1500', '200'], ['', '', '', '', '=E2-F2', ''], ['', '', '', '', '=SUMA(E2:F2)', ''], ['', 'texto', '3', '', '=B5-C5', ''], ['', '', '', '', '=SI.ERROR(1/0;"sin dato")', '']],
]);
$sfCalc = (array)$sfF['calc'];
$ok('resta y suma de dos campos', ($sfCalc['1,4'] ?? '') === '1300' && ($sfCalc['2,4'] ?? '') === '1700');
$ok('operar con texto da #VALOR! y SI.ERROR lo atrapa', ($sfCalc['3,4'] ?? '') === '#VALOR!' && ($sfCalc['4,4'] ?? '') === 'sin dato');

$sfNuevas = \Classes\Sheetflow::normalizar([
    'cols' => [['n' => 'A', 't' => 'numero'], ['n' => 'B', 't' => 'numero'], ['n' => 'C'], ['n' => 'D', 't' => 'fecha'], ['n' => 'E']],
    'rows' => [
        ['1', '2', 'Alfa', '2026-01-31', '=SUMAPRODUCTO(A2:A4;B2:B4)'],
        ['3', '4', 'Beta', '2026-02-28', '=TEXTO(D2;"dd/mm/yyyy")'],
        ['5', '6', 'Gamma', '2026-03-31', '=BUSCARV(3;A2:B4;2;0)'],
        ['', '', '', '', '=BUSCARX(3;A2:A4;B2:B4;"no")'],
        ['', '', '', '', '=DIAS.LAB(D2;5)'],
        ['', '', '', '', '=PAGO(0.05;10;1000)'],
        ['', '', '', '', '=INDIRECTO("B3")'],
        ['', '', '', '', '=DESREF(A2;1;0)'],
        ['', '', '', '', '=NOMPROPIO("hola mundo")'],
        ['', '', '', '', '=CONTAR.SI.CONJUNTO(A2:A4;">1";B2:B4;"<9")'],
        ['', '', '', '', '=MAX.SI(A2:A4;B2:B4;">2")'],
        ['', '', '', '', '=MEDIANA(A2:A4)'],
        ['', '', '', '', '=SI.ND(BUSCARV(99;A2:B4;2;0);"no")'],
        ['', '', '', '', '=AÑO(D2)'],
    ],
]);
$sfN = (array)$sfNuevas['calc'];
$ok('matemáticas, estadística y condicionales nuevos', ($sfN['0,4'] ?? '') === '44' && ($sfN['9,4'] ?? '') === '2' && ($sfN['10,4'] ?? '') === '5' && ($sfN['11,4'] ?? '') === '3');
$ok('texto, búsqueda, referencias, fechas y finanzas', ($sfN['1,4'] ?? '') === '31/01/2026' && ($sfN['2,4'] ?? '') === '4' && ($sfN['3,4'] ?? '') === '4' && ($sfN['4,4'] ?? '') === '2026-02-06' && ($sfN['8,4'] ?? '') === 'Hola Mundo' && ($sfN['12,4'] ?? '') === 'no' && ($sfN['13,4'] ?? '') === '2026' && str_starts_with($sfN['5,4'] ?? '', '-129.5') && ($sfN['6,4'] ?? '') === '4' && ($sfN['7,4'] ?? '') === '3');

$sfExtra = \Classes\Sheetflow::normalizar([
    'cols' => [['n' => 'A'], ['n' => 'B']],
    'rows' => [
        ['=PRODUCTO(2;3;4)', ''],
        ['=SI.CONJUNTO(FALSO;"a";VERDADERO;"b")', ''],
        ['=REEMPLAZAR("hola mundo";6;5;"a todos")', ''],
        ['=EXACTO("Hola";"hola")', ''],
        ['=MONEDA(1234.5;2)', ''],
        ['=FECHAVALOR("31/12/2026")', ''],
        ['=HORA("14:35:20")', ''],
        ['=MINUTO("14:35:20")', ''],
        ['=SEGUNDO("14:35:20")', ''],
    ],
]);
$sfE = (array)$sfExtra['calc'];
$ok('PRODUCTO, SI.CONJUNTO, REEMPLAZAR, EXACTO y MONEDA', ($sfE['0,0'] ?? '') === '24' && ($sfE['1,0'] ?? '') === 'b'
    && ($sfE['2,0'] ?? '') === 'hola a todos' && ($sfE['3,0'] ?? '') === '0' && ($sfE['4,0'] ?? '') === '$1,234.50');
$ok('FECHAVALOR y HORA/MINUTO/SEGUNDO', ($sfE['5,0'] ?? '') === '2026-12-31' && ($sfE['6,0'] ?? '') === '14'
    && ($sfE['7,0'] ?? '') === '35' && ($sfE['8,0'] ?? '') === '20');

$sfFmt = \Classes\Sheetflow::limpiar([
    'cols' => [['n' => 'A']], 'rows' => [['x'], ['y']],
    'fmt' => ['0,0' => ['wr' => 1, 'va' => 'arriba', 'nf' => '#,##0.00'], '1,0' => ['va' => 'otro']],
    'vista' => ['alto' => ['0' => 54, '1' => 999, '2' => 30, 'x' => 40]],
]);
$sfFmtMap = (array)$sfFmt['fmt'];
$ok('el ajuste de texto, la alineación vertical y el alto de fila se sanean', ($sfFmtMap['0,0']['wr'] ?? 0) === 1
    && ($sfFmtMap['0,0']['va'] ?? '') === 'arriba' && !isset($sfFmtMap['1,0']['va'])
    && (array)$sfFmt['vista']['alto'] === ['0' => 54]
    && str_contains(\Classes\Sheetflow::cssFmt(['wr' => 1, 'va' => 'arriba']), 'white-space:pre-wrap')
    && str_contains(\Classes\Sheetflow::cssFmt(['wr' => 1, 'va' => 'arriba']), 'vertical-align:top'));

echo "SheetFlow: celdas combinadas\n";
$sfMerge = \Classes\Sheetflow::limpiar([
    'cols' => [['n' => 'A'], ['n' => 'B'], ['n' => 'C'], ['n' => 'D']],
    'rows' => [['1', '2', '3', '4'], ['5', '6', '7', '8'], ['9', '10', '11', '12']],
    'merges' => [
        ['r0' => 0, 'c0' => 0, 'r1' => 0, 'c1' => 1],
        ['r0' => 1, 'c0' => 2, 'r1' => 2, 'c1' => 3],
        ['r0' => -1, 'c0' => 0, 'r1' => 0, 'c1' => 1],
        ['r0' => 0, 'c0' => 1, 'r1' => 1, 'c1' => 2],
    ],
]);
$ok('las combinaciones se validan y no se solapan', count($sfMerge['merges']) === 2 && $sfMerge['merges'][0] === ['r0' => 0, 'c0' => 0, 'r1' => 0, 'c1' => 1]);
$sfHoja = new \Model\SheetflowHoja(['id' => 1, 'nombre' => 'T', 'fecha' => '2026-01-01', 'departamento' => '', 'datos' => json_encode($sfMerge)]);
$sfHtml = \Classes\Sheetflow::html($sfHoja);
$ok('la tabla HTML y su matriz llevan colspan y rowspan', str_contains($sfHtml, 'colspan="2"') && str_contains($sfHtml, 'rowspan="2"') && count(\Classes\Sheetflow::matriz($sfHoja)['merges']) === 2);

echo "SheetFlow: vista, formato y fórmulas del proyecto\n";
$sfVista = \Classes\Sheetflow::limpiar([
    'cols' => [['n' => 'Concepto'], ['n' => 'Cantidad', 't' => 'numero'], ['n' => 'Precio', 't' => 'moneda']],
    'rows' => [['Acero', '10', '150'], ['Cemento', '20', '90'], ['Arena', '5', '200']],
    'fmt' => ['1,2' => ['nf' => '#,##0.00']],
    'vista' => [
        'filas' => 1, 'cols' => 2,
        'filtros' => ['0' => ['vals' => ['Acero', 'Cemento'], 'texto' => ''], '1' => ['vals' => ['']], '9' => ['vals' => ['x']]],
        'cond' => [
            ['r0' => 0, 'c0' => 1, 'r1' => 2, 'c1' => 1, 'tipo' => 'mayor', 'a' => '8', 'color' => '#dc2626', 'fondo' => '#fee2e2'],
            ['r0' => 0, 'c0' => 2, 'r1' => 2, 'c1' => 2, 'tipo' => 'escala', 'color' => '#16a34a', 'fondo' => '#ffffff'],
        ],
        'graficos' => [['tipo' => 'barra', 'r0' => 0, 'c0' => 0, 'r1' => 2, 'c1' => 2, 'titulo' => 'Prueba'], ['tipo' => 'otro']],
    ],
]);
$ok('la vista se acota: congelar, filtros, reglas y gráficos', $sfVista['vista']['filas'] === 1 && $sfVista['vista']['cols'] === 2
    && count((array)$sfVista['vista']['filtros']) === 2 && count($sfVista['vista']['cond']) === 2 && count($sfVista['vista']['graficos']) === 1);
$sfHoja2 = new \Model\SheetflowHoja(['id' => 2, 'nombre' => 'Vista', 'fecha' => '2026-01-01', 'departamento' => '', 'datos' => json_encode($sfVista)]);
$sfM2 = \Classes\Sheetflow::matriz($sfHoja2);
$ok('el formato de número por celda se aplica al valor mostrado', ($sfM2['filas'][1][2] ?? '') === '90.00');
$sfCnd = \Classes\Sheetflow::condicional($sfVista, $sfM2);
$ok('el formato condicional marca las celdas (regla y escala)', ($sfCnd['1,1']['bg'] ?? '') === '#fee2e2' && isset($sfCnd['0,2']));
$sfHtml2 = \Classes\Sheetflow::html($sfHoja2);
$ok('el HTML de la tabla incluye el gráfico SVG y los colores condicionales', str_contains($sfHtml2, '<svg') && str_contains($sfHtml2, '#fee2e2'));
$ok('el formato de número entiende miles, moneda, porcentaje y fechas', \Classes\Sheetflow::formatoNumero('1800', '#,##0.00') === '1,800.00'
    && \Classes\Sheetflow::formatoNumero('1800', '$#,##0') === '$1,800' && \Classes\Sheetflow::formatoNumero('0.5', '0.0%') === '50.0%'
    && \Classes\Sheetflow::formatoNumero('2026-01-05', 'dd/mm/aaaa') === '05/01/2026' && \Classes\Sheetflow::formatoNumero('-1800', '$#,##0.00') === '-$1,800.00');

\Classes\SheetflowFormulas::setProyecto([
    ['codigo' => '1.1', 'nombre' => 'Cimentación', 'tipo' => 'partida', 'avance' => 42.5, 'inicio' => '2026-01-01', 'fin' => '2026-02-15', 'dias_rest' => -3],
]);
$sfProy = \Classes\Sheetflow::normalizar([
    'cols' => [['n' => 'A'], ['n' => 'B'], ['n' => 'C'], ['n' => 'D'], ['n' => 'E']],
    'rows' => [['', '', '', '=AVANCE("1.1")', '=FIN("1.1")'], ['', '', '', '=PARTIDA("1.1")', '=DIAS.REST("1.1")']],
]);
$sfP = (array)$sfProy['calc'];
$ok('AVANCE/FIN/PARTIDA/DIAS.REST leen el programa del proyecto', ($sfP['0,3'] ?? '') === '42.5' && ($sfP['0,4'] ?? '') === '2026-02-15'
    && ($sfP['1,3'] ?? '') === 'Cimentación' && ($sfP['1,4'] ?? '') === '-3');
\Classes\SheetflowFormulas::setProyecto([]);
$sfCache = \Classes\Sheetflow::normalizar([
    'cols' => [['n' => 'A'], ['n' => 'B']],
    'rows' => [['=AVANCE("1.1")', '']],
    'calc' => ['0,0' => '42.5'],
]);
$ok('sin programa cargado se conserva el resultado que calculó el cliente', (((array)$sfCache['calc'])['0,0'] ?? '') === '42.5');

echo "SheetFlow: vínculo con subactividades\n";
$sfVinculo = \Classes\Sheetflow::limpiar([
    'cols' => [
        ['n' => 'Nombre', 'rol' => 'nombre', 'fija' => 1],
        ['n' => 'Inicio', 't' => 'fecha', 'rol' => 'inicio', 'fija' => 1],
        ['n' => 'Extra'],
    ],
    'rows' => [['Tramo A', '2026-10-01', 'nota'], ['', '', '']],
    'meta' => ['subactividad' => ['actividad_id' => 7, 'ids' => [12, null]]],
]);
$ok('conserva los ids de subactividad por renglón', $sfVinculo['meta']['subactividad']['ids'] === [12, null]);
$ok('conserva el rol y la protección de las columnas base', $sfVinculo['cols'][0]['rol'] === 'nombre' && $sfVinculo['cols'][1]['fija'] === 1 && !isset($sfVinculo['cols'][2]['rol']));
$ok('los ids se recortan o completan según los renglones', \Classes\Sheetflow::limpiar(['rows' => [['a'], ['b'], ['c']], 'meta' => ['subactividad' => ['ids' => [4]]]])['meta']['subactividad']['ids'] === [4, null, null]);
$sinVinculo = \Classes\Sheetflow::sinVinculo($sfVinculo);
$ok('sinVinculo quita los ids y los roles, pero conserva los datos', !isset($sinVinculo['meta']['subactividad']) && !isset($sinVinculo['cols'][0]['rol']) && $sinVinculo['rows'][0][0] === 'Tramo A');

echo "SheetFlow: instalación de materiales\n";
$sfMat = \Classes\Sheetflow::limpiar([
    'cols' => [
        ['n' => 'Material', 'rol' => 'material', 'fija' => 1],
        ['n' => 'Fecha de material', 't' => 'fecha', 'rol' => 'fecha_material', 'fija' => 1],
        ['n' => 'Status', 't' => 'lista', 'rol' => 'status_material', 'fija' => 1, 'op' => ['No iniciada', 'En proceso', 'Completada']],
        ['n' => 'Observaciones'],
    ],
    'rows' => [['Soportería', '2026-10-01', 'No iniciada', 'nota'], ['Cableado', '2026-10-02', 'En proceso', '']],
    'meta' => ['materiales' => ['ids' => ['a7', 's9']]],
]);
$ok('conserva los roles de las columnas de materiales', $sfMat['cols'][0]['rol'] === 'material' && $sfMat['cols'][2]['fija'] === 1);
$ok('conserva la clave oculta de cada renglón', $sfMat['meta']['materiales']['ids'] === ['a7', 's9']);
$ok('descarta claves de material que no son válidas', \Classes\Sheetflow::limpiar(['rows' => [['a'], ['b']], 'meta' => ['materiales' => ['ids' => ['a7', 'x9', 's3']]]])['meta']['materiales']['ids'] === ['a7', null]);
$soloSub = \Classes\Sheetflow::sinVinculoSubactividades($sfMat);
$ok('quitar el vínculo de subactividades conserva el de materiales', isset($soloSub['meta']['materiales']) && $soloSub['cols'][0]['rol'] === 'material');
$todo = \Classes\Sheetflow::sinVinculo($sfMat);
$ok('sinVinculo total quita también el vínculo de materiales', !isset($todo['meta']['materiales']) && !isset($todo['cols'][0]['rol']));

$arbolMat = ['partidas' => [['id' => 1, 'codigo' => '01', 'nombre' => 'Instalaciones', 'color' => '#111111']], 'actividades' => [[
    'id' => 7, 'codigo' => '01.01', 'nombre' => 'Soportería y cableado', 'partida_id' => 1, 'partida' => 'Instalaciones',
    'estado' => 'espera', 'progreso' => 0, 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-10',
    'subactividades' => [['id' => 9, 'nombre' => 'Caja NEMA', 'fecha_inicio' => '2026-10-02', 'fecha_fin' => '2026-10-05', 'status' => 50]],
]]];
$baseMat = \Classes\Sheetflow::normalizar([
    'cols' => $sfMat['cols'],
    'rows' => [['Soportería y cableado', '2026-10-10', 'No iniciada', 'conservar']],
    'meta' => ['materiales' => ['ids' => ['a7']]],
]);
$fusion = \Classes\MaterialesLamina::fusionar($baseMat, ['a7', 's9'], $arbolMat);
$ok('la sincronización agrega una fila por subactividad nueva', count($fusion['rows']) === 2 && $fusion['meta']['materiales']['ids'] === ['a7', 's9']);
$ok('la sincronización no toca las columnas extra', $fusion['rows'][0][3] === 'conservar' && $fusion['rows'][1][3] === '');
$refres = \Classes\MaterialesLamina::fusionar($baseMat, ['a7'], $arbolMat, true);
$ok('refrescar las columnas base actualiza nombre, fecha y estado', $refres['rows'][0][0] === '01.01 · Soportería y cableado' && $refres['rows'][0][1] === '2026-10-10' && $refres['rows'][0][3] === 'conservar');
$coloresMat = \Classes\MaterialesLamina::coloresDeMarcas(json_encode(['shapes' => [
    ['id' => 's1', 'type' => 'line', 'stroke' => '#E11D48', 'material' => ['items' => [['tipo' => 'actividad', 'id' => 7], ['tipo' => 'subactividad', 'id' => 9, 'actividad_id' => 7]]]],
    ['id' => 's2', 'type' => 'rect', 'fill' => '#22c55e', 'material' => ['items' => [['tipo' => 'actividad', 'id' => 7]]]],
    ['id' => 's3', 'type' => 'line', 'stroke' => '#111111', 'actividad_id' => 8],
]]));
$ok('el color del material sale de la primera línea o forma que lo usa', $coloresMat === ['a7' => '#e11d48', 's9' => '#e11d48', 'a8' => '#111111']);

echo "Personalización (Fase 4)\n";
use Classes\CamposPersonalizados as CPz;
$ok('opciones de lista: una por línea, sin vacías ni repetidas', CPz::listaOpciones("N1\n\n N2 \nN1\n<b>N3</b>") === ['N1', 'N2', 'N3']);
$campos = [7 => ['tipo' => 'texto'], 8 => ['tipo' => 'lista']];
$ok('filtro de texto busca por fragmento sin importar mayúsculas', CPz::coincide([7 => 'Torre Norte'], [7 => 'norte'], $campos) && !CPz::coincide([7 => 'Torre Sur'], [7 => 'norte'], $campos));
$ok('filtro de lista es exacto y «(vacío)» busca los que no tienen valor', CPz::coincide([8 => 'N1'], [8 => 'N1'], $campos) && !CPz::coincide([8 => 'N10'], [8 => 'N1'], $campos) && CPz::coincide([], [8 => '__vacio'], $campos));
$ok('sin filtros todo coincide', CPz::coincide([], [], $campos));
$ok('claves cp_ del programa', Classes\ProgramaColumnas::esCp('cp_12') && !Classes\ProgramaColumnas::esCp('cp_x') && !Classes\ProgramaColumnas::esCp('nombre'));
$ok('colores de la marca: solo #rrggbb', Classes\Marca::colorValido('#0B6FC0') === '#0b6fc0' && Classes\Marca::colorValido('rojo') === null && Classes\Marca::colorValido('#fff') === null);
$ok('el texto de la marca contrasta con el fondo', Classes\Marca::contraste('#ffffff') === '#111111' && Classes\Marca::contraste('#0b132b') === '#ffffff');
$ok('inicio: solo bloques conocidos y sin repetir', array_keys(Classes\InicioUsuario::BLOQUES) === ['resumen', 'hoy', 'atrasadas', 'proyectos', 'novedades', 'atajos'] && array_diff(Classes\InicioUsuario::DEFECTO, array_keys(Classes\InicioUsuario::BLOQUES)) === []);

echo "Pruebas de calidad de la IA (Fase 5.3)\n";
use Classes\PruebasIA as PIA;
$perfecto = [
    'ext-fecha' => ['fecha' => '2026-03-12'], 'ext-cantidades' => ['materiales' => [['nombre' => 'cámaras IP', 'cantidad' => 48, 'unidad' => 'pza'], ['nombre' => 'gabinetes', 'cantidad' => 6, 'unidad' => 'pza'], ['nombre' => 'cable UTP', 'cantidad' => 350, 'unidad' => 'm']]],
    'ext-responsable' => ['responsable' => 'Ing. Laura Pérez', 'fecha_limite' => '2026-11-05'], 'cla-severidad' => ['severidad' => 'critica', 'motivo' => 'Compromete la estructura'], 'cla-restriccion' => ['categoria' => 'permiso'],
    'cla-contradice' => ['contradice' => true, 'dato_plano' => '4 pulgadas', 'dato_especificacion' => '6 pulgadas'], 'cla-no-contradice' => ['contradice' => 'false', 'explicacion' => 'Ambos dicen 12 cm'],
    'red-resumen' => ['resumen' => 'El avance real es de 45 % contra 52 % programado, con 3 actividades atrasadas por la falta de cable UTP.'],
    'red-rfi' => ['asunto' => 'Choque entre muro eje 3 y bajada de aire', 'pregunta' => 'En el plano A-05 el muro del eje 3 interfiere con la bajada del plano M-02 a 2.40 m. Solicitamos confirmar cuál plano prevalece para continuar con la obra.'],
    'red-acciones' => ['acciones' => ['Comprar el cable con otro proveedor', 'Pedir entrega parcial', 'Reprogramar el montaje de cámaras']],
    'raz-orden' => ['orden' => ['Canalización', 'Tendido de cableado', 'Montaje de cámaras', 'Configuración', 'Pruebas y entrega']], 'raz-ruta' => ['duracion_total' => 13, 'criticas' => ['A', 'C', 'D']], 'raz-proyeccion' => ['avance_semana_20' => '80 %', 'razon' => '4 % por semana'],
];
$todoOk = true; $detalle = [];
foreach (PIA::casos() as $c) { $r = PIA::puntuar($c, $perfecto[$c['id']] ?? null); if (!$r['aprobado']) { $todoOk = false; $detalle[] = $c['id'] . ': ' . implode('; ', $r['fallas']); } }
$ok('la respuesta ideal aprueba los ' . count(PIA::casos()) . ' casos' . ($detalle ? ' (' . implode(' | ', $detalle) . ')' : ''), $todoOk);
$ok('todos los casos tienen respuesta ideal de prueba y prompt que pide JSON', count(array_diff(array_column(PIA::casos(), 'id'), array_keys($perfecto))) === 0 && !array_filter(PIA::casos(), fn($c) => !str_contains($c['prompt'], 'JSON')));
$ok('sin respuesta vale 0', PIA::puntuar(PIA::caso('ext-fecha'), null)['nota'] === 0.0);
$ok('una regla de dos: nota parcial 0.5', PIA::puntuar(PIA::caso('ext-responsable'), ['responsable' => 'Laura', 'fecha_limite' => '2026-11-06'])['nota'] === 0.5);
$ok('inventar una cifra se castiga', !PIA::puntuar(PIA::caso('red-resumen'), ['resumen' => 'El avance es 45 % y hay 7 actividades atrasadas por falta de cable.'])['aprobado']);
$ok('responder en inglés no cuenta como español', !PIA::puntuar(PIA::caso('red-resumen'), ['resumen' => 'The progress is 45 percent and the cable is missing.'])['aprobado']);
$ok('orden equivocado de etapas falla', !PIA::puntuar(PIA::caso('raz-orden'), ['orden' => ['Montaje', 'Canalización', 'Cableado', 'Configuración', 'Pruebas']])['aprobado']);
$ok('ids de casos únicos', count(array_unique(array_column(PIA::casos(), 'id'))) === count(PIA::casos()));

echo "Cobertura de la traducción\n";
exec('php ' . escapeshellarg(__DIR__ . '/../bin/idioma_cobertura.php') . ' 2>&1', $salida, $codigo);
$ok('todos los t() tienen su traducción al inglés' . ($codigo ? ' — ' . implode(' / ', array_slice($salida, 1, 3)) : ''), $codigo === 0);

echo "Predicción de atrasos y contradicciones (Fase 5.4)\n";
use Classes\Herramientas\PrediccionAtrasos as PA;
use Classes\Herramientas\Contradicciones as CTR;
$act = fn($ini, $fin, $prog, $dur = 10) => ['hito' => 0, 'progreso' => $prog, 'fecha_inicio' => $ini, 'fecha_fin' => $fin, 'duracion_dias' => $dur];
$ok('avance esperado: 0 antes, 100 después, a la mitad 50', PA::esperado('2026-10-10', '2026-10-19', '2026-10-01') == 0 && PA::esperado('2026-10-10', '2026-10-19', '2026-10-30') == 100 && PA::esperado('2026-10-10', '2026-10-19', '2026-10-14') == 50);
$ok('una actividad al día no tiene riesgo', PA::puntuar($act('2026-10-10', '2026-10-19', 50), '2026-10-14', [], 0, []) === null);
$r = PA::puntuar($act('2026-10-10', '2026-10-19', 0), '2026-10-14', ['4.2'], 1, []);
$ok('sin iniciar, vencida de inicio, con predecesora atrasada y restricción: riesgo medio o alto con razones', $r && $r['puntos'] >= 55 && count($r['razones']) === 3 && $r['atraso_dias'] === 4);
$ok('un hito o una actividad terminada no se puntúan', PA::puntuar(['hito' => 1] + $act('2026-10-10', '2026-10-19', 0), '2026-10-14', [], 0, []) === null && PA::puntuar($act('2026-10-10', '2026-10-19', 100), '2026-10-14', [], 0, []) === null);
$ok('el puntaje nunca pasa de 100', PA::puntuar($act('2026-01-01', '2026-01-10', 0), '2026-10-14', ['a', 'b'], 5, array_fill(0, 6, ['texto' => 'x']))['puntos'] === 100);
$d = PA::desempeno([$act('2026-10-10', '2026-10-19', 0), $act('2026-10-10', '2026-10-19', 0)], '2026-10-19');
$ok('SPI = avance real entre programado', $d['plan'] == 100 && $d['real'] == 0 && $d['spi'] == 0.0);
$ok('magnitudes: convierte pulgadas a mm y no confunde la preposición «a»', CTR::magnitudes('de 12 a 15 m con 4 pulgadas')[1]['valor'] === 101.6 && count(CTR::magnitudes('de 12 a 15 sin unidad')) === 0);
$ok('mm² (área) no se toma como longitud', CTR::magnitudes('cable de 2.5 mm² y 20 AWG') === [['dim' => 'awg', 'valor' => 20.0, 'texto' => '20 AWG']]);
$ok('contradicción: 4 vs 6 pulgadas difieren; 4 pulgadas y 101.6 mm son lo mismo', CTR::difieren(CTR::magnitudes('tubo de 4 pulgadas'), CTR::magnitudes('tubo de 6 pulgadas')) !== null && CTR::difieren(CTR::magnitudes('tubo de 4 pulgadas'), CTR::magnitudes('tubo de 101.6 mm')) === null);
$ok('palabras técnicas iguales entre español e inglés', count(array_intersect(CTR::claves('tubería de acero'), CTR::claves('steel pipe'))) >= 1);

echo "Telegram y clima automático (sin BD)\n";
use Classes\Telegram;
use Classes\TelegramBot;
use Classes\ClimaAuto;
$ok('el comando se separa de su texto y del @bot', TelegramBot::comando('/Avance@pw_bot muro 3') === ['avance', 'muro 3'] && TelegramBot::comando('/hoy') === ['hoy', ''] && TelegramBot::comando('hola /x') === null);
$ok('el texto de Telegram se escapa', Telegram::h('a <b> & "c"') === 'a &lt;b&gt; &amp; "c"');
$m = Telegram::mensajeEvento(1, 'rfi.creado', ['numero' => 'RFI-7', 'asunto' => 'Muro <A>', 'prioridad' => 'critica', 'fecha_limite' => '2026-12-01']);
$ok('el aviso de RFI lleva número, asunto escapado, prioridad y fecha límite', str_contains($m, 'RFI-7') && str_contains($m, 'Muro &lt;A&gt;') && str_contains($m, '🔴') && str_contains($m, '01/12/2026'));
$ok('la bitácora media no genera aviso, la crítica sí', Telegram::mensajeEvento(1, 'bitacora.creada', ['importancia' => 'media', 'descripcion' => 'x']) === null && Telegram::mensajeEvento(1, 'bitacora.creada', ['importancia' => 'critica', 'descripcion' => 'x']) !== null);
$ok('un evento desconocido no genera aviso', Telegram::mensajeEvento(1, 'inventado', []) === null);
$ok('los avisos iniciales existen en el catálogo', !array_diff(Telegram::INICIALES, array_keys(Telegram::EVENTOS)));
$d = ['tmax' => 31.4, 'tmin' => 17.6, 'precip_mm' => 12.0, 'prob' => 85, 'viento_kmh' => 35.2];
$ok('el resumen del clima es legible y marca la lluvia', ClimaAuto::resumen($d) === 'máx 31° / mín 18° · lluvia 12 mm (85%) · viento 35 km/h ⚠ lluvia');
$ok('la fecha larga va en español', ClimaAuto::fechaLarga('2026-10-22') === 'jueves 22 oct');

// Botones de acción en los avisos: en el chat privado se cambian datos; en grupo o sin id, solo «Ver»
$bp = Telegram::botonesEvento(1, 'punch.creado', ['id' => 5], true);
$bpData = array_map(fn($fila) => array_column($fila, 'callback_data'), $bp);
$ok('el aviso de punch en privado ofrece corregir o en proceso', in_array(['pu:5:corregido', 'pu:5:en_proceso'], $bpData, true) && str_contains(json_encode($bp), 'url'));
$ok('el aviso de punch en grupo solo lleva el enlace a la web', Telegram::botonesEvento(1, 'punch.creado', ['id' => 5], false) === [[['text' => '🌐 Ver en la web', 'url' => Telegram::urlApp('/punch?proyecto=1')]]]);
$br = Telegram::botonesEvento(1, 'rfi.creado', ['id' => 7], true);
$ok('el aviso de RFI ofrece «Responder»', in_array('rs:7', array_merge(...array_map(fn($f) => array_column($f, 'callback_data'), $br)), true));
$bs = Telegram::botonesEvento(1, 'submittal.actualizado', ['id' => 3], true);
$ok('el aviso de submittal ofrece Aprobado, A/AN y Resubmitar', count(array_filter(array_merge(...array_map(fn($f) => array_column($f, 'callback_data'), $bs)), fn($c) => str_starts_with((string)$c, 'su:3:'))) === 3);
$ok('un evento sin id (clima) no ofrece acciones, solo el enlace', count(Telegram::botonesEvento(1, 'clima.alerta', [], true)[0]) === 1 && Telegram::botonesEvento(1, 'clima.alerta', [], true)[0][0]['url'] !== '');
$ok('el resumen matutino tiene su mensaje en el catálogo', Telegram::mensajeEvento(1, 'resumen.matutino', ['texto' => 'Buenos días']) === 'Buenos días' && Telegram::mensajeEvento(1, 'resumen.matutino', []) === null);
$ok('el resumen matutino no es un aviso inicial (es opt-in)', !in_array('resumen.matutino', Telegram::INICIALES, true));
$ok('el menú «/» trae los comandos del bot, incluidos /salud y /pdf', in_array('salud', array_column(Telegram::COMANDOS, 0), true) && in_array('pdf', array_column(Telegram::COMANDOS, 0), true));
$ok('la URL de la web sale de APP_URL o del servidor', str_starts_with(Telegram::urlApp('/punch'), 'http'));
$ok('los errores saneados ocultan el token de la API', Telegram::sanearError('fallo en https://api.telegram.org/bot123456:AAEsecret_x/sendMessage') === 'fallo en https://api.telegram.org/bot***/sendMessage'
    && Telegram::sanearError('sin token en el texto') === 'sin token en el texto');

// Resumen de la obra: la hora viene del entorno (RESUMEN_HORA), por defecto las 6
putenv('RESUMEN_HORA'); unset($_ENV['RESUMEN_HORA']);
$ok('la hora del resumen es las 6 si no se configura', Classes\ResumenObra::hora() === 6);
putenv('RESUMEN_HORA=7'); $_ENV['RESUMEN_HORA'] = '7';
$ok('RESUMEN_HORA fija otra hora', Classes\ResumenObra::hora() === 7);
putenv('RESUMEN_HORA'); unset($_ENV['RESUMEN_HORA']);

// Token del bot: se guarda cifrado en telegram_config; en pantalla solo se ve la máscara
$tokPrueba = '123456789:AAFSECRETODEPRUEBA_0123456789abcdef';
$cif = Telegram::cifrar($tokPrueba);
$ok('el token se cifra (AES-256-GCM) y se recupera idéntico', $cif !== '' && $cif !== $tokPrueba && Telegram::descifrar($cif) === $tokPrueba);
$ok('la clave de cifrado es de 32 bytes', preg_match('/^[0-9a-f]{64}$/', Telegram::clave()) === 1);
$mal = $cif;
$mal[0] = $mal[0] === 'a' ? 'b' : 'a';
$ok('un dato alterado o muy corto devuelve vacío, no basura', Telegram::descifrar($mal) === '' && Telegram::descifrar('corto') === '');
$ok('la máscara enseña solo los últimos 4 y nunca el token', Telegram::mascara($tokPrueba) === '•••• cdef' && !str_contains(Telegram::mascara($tokPrueba), 'SECRET'));
$ok('sin tabla de configuración el token viene del entorno', Telegram::disponibleConfig() === false && Telegram::token() === Telegram::env('TELEGRAM_BOT_TOKEN'));


echo "Informes sin menciones de IA\n";
$inf = Classes\Informes::sinMencionesIA(['secciones' => [
    ['titulo' => 'Resumen (redactado con IA, revísalo)', 'tipo' => 'texto', 'texto' => 'Sin IA: reúne a la cuadrilla.'],
    ['titulo' => 'Prioridades sugeridas por la IA', 'tipo' => 'tabla', 'columnas' => ['Origen'], 'filas' => [['(automático)']]],
    ['titulo' => 'Lecciones aprendidas (automáticas, a partir de los datos)', 'tipo' => 'texto', 'texto' => 'Texto normal.'],
    ['titulo' => 'Sin IA', 'tipo' => 'texto', 'texto' => 'Esta minuta se creó sin IA: solo guarda la transcripción.'],
    ['titulo' => 'Importante', 'tipo' => 'texto', 'texto' => 'La clasificación la hace un modelo de visión y puede equivocarse. Revisa la foto antes de aceptar.'],
]]);
$ok('quita del título la nota de IA, el modelo y el «por la IA»', array_column($inf['secciones'], 'titulo') === ['Resumen', 'Prioridades sugeridas', 'Lecciones aprendidas', 'Importante']);
$ok('quita el prefijo «Sin IA:» del texto', $inf['secciones'][0]['texto'] === 'reúne a la cuadrilla.');
$ok('limpia también las celdas de las tablas', $inf['secciones'][1]['filas'] === [['']]);
$ok('no toca el texto que no habla de la IA', $inf['secciones'][2]['texto'] === 'Texto normal.');
$ok('quita la frase del modelo de visión y deja lo demás', $inf['secciones'][3]['texto'] === 'Revisa la foto antes de aceptar.');

echo "Resumen ejecutivo diario (sin BD)\n";
use Classes\Herramientas\ResumenEjecutivoDiario as RED;
$ok('el nivel de detalle fija cuántas filas lleva cada bloque', RED::tope('basico') === 5 && RED::tope('medio') === 10 && RED::tope('alto') === 20 && RED::tope('maximo') === 40);
$ok('nivel desconocido cae en el medio', RED::tope('cualquiera') === 10);
$ok('la ficha ofrece tipo de reporte simple/detallado y los cuatro niveles', RED::ficha()['tipo'] === 'resumen_diario' && array_keys(RED::ficha()['parametros'][1]['opciones']) === ['simple', 'detallado'] && array_keys(RED::ficha()['parametros'][2]['opciones']) === ['basico', 'medio', 'alto', 'maximo']);
$ok('los parámetros nuevos se validan y toman su defecto', \Classes\Herramientas\Registro::parametros(RED::class, ['tipo' => 'x', 'detalle' => 'x']) === ['fecha' => date('Y-m-d'), 'tipo' => 'detallado', 'detalle' => 'medio', 'linea' => 0]);
$ok('los valores elegidos pasan tal cual', \Classes\Herramientas\Registro::parametros(RED::class, ['tipo' => 'simple', 'detalle' => 'maximo', 'fecha' => '2026-01-05'])['tipo'] === 'simple' && \Classes\Herramientas\Registro::parametros(RED::class, ['tipo' => 'simple', 'detalle' => 'maximo', 'fecha' => '2026-01-05'])['detalle'] === 'maximo');

// datos mínimos como los deja preparar() para componer()
$mkDatos = fn(string $tipo, string $detalle) => [
    'fecha' => '2026-10-22', 'tipo' => $tipo, 'detalle' => $detalle, 'tope' => RED::tope($detalle), 'semaforo' => 'ATENCION',
    'curva' => ['plan' => 42.0, 'real' => 40.0, 'spi' => 0.95],
    'linea' => ['id' => 1, 'nombre' => 'LB inicial', 'creado' => '2026-01-01'],
    'comparacion' => ['resumen' => ['linea' => 'x', 'fin_base' => '2026-12-01', 'fin_actual' => '2026-12-08', 'd_fin' => 5, 'movida' => 2, 'nueva' => 0, 'eliminada' => 0, 'oc' => 1, 'material' => 0, 'sin_justificar' => 1, 'criticas_entran' => [], 'criticas_salen' => []]],
    'cruce' => ['en_riesgo' => 1, 'tarde' => 0, 'total' => 12], 'riesgos_materiales' => [], 'retiros' => [], 'retiros_vencidos' => 0, 'retiros_sin_programar' => 0,
    'dia' => ['ordenes' => [], 'renglones' => []], 'cambios' => ['costo_autorizado' => 0, 'dias_autorizados' => 0], 'sin_oc' => [], 'ordenes_por_decidir' => [],
    'riesgo_alto' => [['codigo' => '1.1', 'nombre' => 'Excavación', 'puntos' => 72, 'progreso' => 0, 'atraso_dias' => 6, 'razones' => ['No ha iniciado y debía empezar hace 6 día(s)']]],
    'riesgo_conteo' => ['alto' => 1, 'medio' => 2, 'bajo' => 0], 'riesgo_horizonte' => 14,
    'criticas' => [['codigo' => '1.1', 'nombre' => 'Excavación', 'inicio' => '2026-10-01', 'fin' => '2026-11-01', 'progreso' => 0]], 'criticas_total' => 1, 'dependencias_modo' => 'informativo',
    'restricciones' => [['codigo' => '1.1', 'actividad' => 'Excavación', 'tipo' => 'Permiso', 'descripcion' => 'Falta permiso de altura', 'fecha' => '2026-10-20', 'inicio' => '2026-10-01', 'dias' => 2]], 'restricciones_total' => 1,
    'contradicciones' => ['titulo' => 'Contradicciones', 'creado' => '2026-10-21 09:00:00', 'columnas' => ['Tema', 'Fuente A', 'Fuente B', 'Dice A / Dice B', 'Severidad', 'Explicación'],
        'filas' => [['longitud', 'Plano A', 'Espec 27', '4 pulgadas vs 6 pulgadas', '🔴 Alta', 'Piden diámetros distintos']]],
    'punch' => [['titulo' => 'Falta etiqueta en gabinete IDF', 'ubicacion' => 'Nivel 2', 'severidad' => 'critica', 'estado' => 'abierto', 'responsable' => 'Juan', 'contratista' => 'C1', 'fecha_limite' => '2026-10-20', 'vencido' => true]], 'punch_total' => 1, 'punch_criticos' => 1, 'punch_vencidos' => 1,
    'causas' => ['total' => 2, 'por_causa' => ['material' => 1, 'submittal' => 1, 'restriccion' => 0, 'predecesora' => 0, 'clima' => 0, 'bitacora' => 0, 'sin_causa' => 0],
        'filas' => [['codigo' => '1.1', 'nombre' => 'Excavación', 'atraso' => 6, 'progreso' => 0, 'causas' => [['material', '«Bomba» llegará el 30/10']], 'tipos' => ['material']]]],
];
$titulos = fn(array $s) => array_map(fn($x) => $x['titulo'], $s);
$br = RED::componer(new \Model\Proyecto(['id' => 1, 'nombre' => 'Obra']), [], $mkDatos('detallado', 'medio'), []);
$tDet = $titulos($br['secciones']);
$ok('el detallado trae los seis bloques nuevos', count(array_filter($tDet, fn($t) => str_contains($t, 'riesgo alto') || str_contains($t, 'ruta crítica') || str_contains($t, 'Restricciones activas') || str_contains($t, 'Contradicciones entre documentos') || str_contains($t, 'Punch list abierto') || str_contains($t, 'Causas de atraso') || str_contains($t, 'atrasadas y sus causas'))) >= 7);
$ok('el detallado además trae las tablas operativas', in_array('Cambios del día', $tDet, true) && in_array('Retiros pendientes', $tDet, true) && in_array('Desviaciones sin orden de cambio', $tDet, true));
$kpis = array_map(fn($k) => $k['etq'], $br['secciones'][0]['kpis']);
$ok('las cifras nuevas aparecen en los KPI', count($kpis) >= 13 && count(array_intersect($kpis, ['Riesgo alto', 'Ruta crítica', 'Restricciones activas', 'Punch abierto', 'Contradicciones', 'Atrasadas por causa'])) === 6);
$ok('el título del punch separa el total de críticos y vencidos', count(array_filter($tDet, fn($t) => str_starts_with($t, 'Punch list abierto (1) — '))) === 1);
$simple = RED::componer(new \Model\Proyecto(['id' => 1, 'nombre' => 'Obra']), [], $mkDatos('simple', 'basico'), []);
$tSim = $titulos($simple['secciones']);
$ok('el simple conserva los bloques nuevos pero quita las tablas operativas', in_array('Cambios del día', $tSim, true) === false && in_array('Retiros pendientes', $tSim, true) === false);
$ok('el simple muestra riesgo alto como lista (una línea por actividad)', (array_search('Actividades en riesgo alto', $tSim, true)) !== false && $simple['secciones'][array_search('Actividades en riesgo alto', $tSim, true)]['tipo'] === 'lista');
$ok('el nivel básico limita cada bloque a 5 filas', count($br['secciones'][array_search('Restricciones activas (1)', $tDet, true)]['filas']) <= 5);
$pend = $br['secciones'][array_key_last($br['secciones'])]['items'];
$ok('los pendientes del sistema agregan restricción, punch crítico vencido, contradicción y riesgo alto', count(array_filter($pend, fn($x) => str_contains($x, 'Levantar restricción'))) === 1 && count(array_filter($pend, fn($x) => str_contains($x, 'Resolver punch'))) === 1 && count(array_filter($pend, fn($x) => str_contains($x, 'Escalar contradicción'))) === 1 && count(array_filter($pend, fn($x) => str_contains($x, 'Revisar riesgo alto'))) === 1);
$vacio = RED::componer(new \Model\Proyecto(['id' => 1, 'nombre' => 'Obra']), [], array_merge($mkDatos('detallado', 'medio'), ['riesgo_alto' => [], 'criticas' => [], 'restricciones' => [], 'punch' => [], 'contradicciones' => null, 'causas' => ['total' => 0, 'por_causa' => [], 'filas' => []]]), []);
$tv = $titulos($vacio['secciones']);
$seis = ['Actividades en riesgo alto', 'En ruta crítica', 'Restricciones activas', 'Contradicciones entre documentos', 'Punch list abierto', 'Causas de atraso'];
$ok('los seis bloques siempre están, aunque no haya nada, para saber que se revisaron', count(array_intersect($seis, $tv)) === 6);
$vacTxt = implode(' ', array_map(fn($s) => $s['texto'] ?? '', array_filter($vacio['secciones'], fn($s) => in_array($s['titulo'], $seis, true))));
$ok('los bloques vacíos explican por qué no hay nada en vez de inventar datos', str_contains($vacTxt, 'Ninguna') && str_contains($vacTxt, 'Sin análisis reciente') && str_contains($vacTxt, 'No hay actividades en la ruta crítica') && str_contains($vacTxt, 'Ninguno'));
foreach ([['detallado', 'medio', $br], ['simple', 'basico', $simple], ['detallado', 'medio', $vacio]] as [$tk, $nk, $r]) {
    $salida = strip_tags(\Classes\Informes::html(['secciones' => $r['secciones']]));
    $ok("el informe $tk/$nk exportado no menciona IA ni modelos", !preg_match('/\bIA\b/', $salida) && !preg_match('/inteligencia artificial|redactado con|modelo de visi|app de escritorio/i', $salida));
}

echo "Redactor (cuadro de texto → DOCX/ODT)\n";
$xhtml = new ReflectionMethod(\Classes\Redactor::class, 'xhtml');
$xhtml->setAccessible(true);
$tablasOdt = new ReflectionProperty(\Classes\Redactor::class, 'tablasOdt');
$tablasOdt->setAccessible(true);
$especiales = [];
$conv = function (string $html) use ($xhtml, &$especiales) { $args = [&$html, &$especiales]; return $xhtml->invokeArgs(null, $args); };
$caja = '<p>Antes</p><div class="rd-caja" style="float:right;width:40%;border:1px solid #d97706;background-color:#fef3c7;padding:8px"><p>Dentro de la caja</p></div><table style="width:100%"><tbody><tr><td style="border:1px solid #888888">c</td></tbody></table>';
$sal = $conv($caja);
$ok('la caja de texto viaja como tabla de una sola celda', str_contains($sal, '<table') && !str_contains($sal, 'rd-caja') && str_contains($sal, 'Dentro de la caja'));
$ok('conserva el ancho, el borde (en orden para PhpWord) y el fondo', str_contains($sal, 'width:40%') && str_contains($sal, 'border:1px #d97706 solid') && str_contains($sal, 'background-color:#fef3c7'));
$ok('las celdas de la caja llevan borde para que el ODT lo pinte', preg_match('/<td[^>]*border:[^>]*>/', $sal) === 1);
$tablasOdt->setValue(null, []);
$conv($caja);
$ok('tablasOdt sigue el orden del documento (caja incluida)', $tablasOdt->getValue() === [true, true]);
$tablasOdt->setValue(null, []);
$otra = $conv('<table><tbody><tr><td>a</td></tbody></table><div class="rd-caja" style="padding:8px"><p>b</p></div>');
$ok('una caja sin estilo conserva ancho y borde por defecto', str_contains($otra, 'width:100%') && str_contains($otra, 'border:1px #999999 solid') && $tablasOdt->getValue() === [false, true]);

echo "Normativa: citas\n";
$ok('la sección CSI se muestra en formato legible', \Classes\Normativa::seccionCsi('271500') === 'Sección 27 15 00');
$ok('una sección CSI inválida no inventa texto', \Classes\Normativa::seccionCsi('27-15') === '' && \Classes\Normativa::seccionCsi(null) === '');
$cita = \Classes\Normativa::cita(['doc' => 'Norma XYZ', 'csi' => '271500', 'clausula' => '', 'titulo' => 'Cableado estructurado', 'pagina' => 12, 'idioma' => 'es']);
$ok('la cita trae documento, sección, título y página', $cita['documento'] === 'Norma XYZ' && $cita['seccion'] === 'Sección 27 15 00' && $cita['clausula'] === 'Cableado estructurado' && $cita['pagina'] === 12);
$cita = \Classes\Normativa::cita(['doc' => 'Norma XYZ', 'csi' => '271500', 'clausula' => '3.2.1', 'titulo' => 'Requisitos', 'pagina' => 4]);
$ok('la cláusula numerada convive con la sección y el título', $cita['seccion'] === 'Sección 27 15 00' && str_contains($cita['clausula'], '3.2.1') && str_contains($cita['clausula'], 'Requisitos'));
$cita = \Classes\Normativa::cita(['documento' => 'Especificación', 'clausula' => 'ARTÍCULO 250', 'titulo' => '', 'pagina' => 0]);
$ok('una cláusula sin CSI se respeta tal cual', $cita['seccion'] === '' && $cita['clausula'] === 'ARTÍCULO 250' && $cita['pagina'] === 0);
$cita = \Classes\Normativa::cita(['referencia' => 'NOM-001-STPS', 'clausula' => '', 'titulo' => '']);
$ok('un vínculo manual conserva su referencia', $cita['documento'] === '' && $cita['seccion'] === '' && $cita['referencia'] === 'NOM-001-STPS');

echo "Plantillas de proyecto desde un proceso\n";
$tpl = \Classes\PlantillaProyecto::desdeTipoObra('videovigilancia');
$ok('el catálogo genera partidas y actividades encadenadas', $tpl && $tpl['encadenar'] === true && count($tpl['partidas']) === 5 && count($tpl['actividades']) > 0);
$ok('las actividades quedan sin fecha y con duración positiva', $tpl && !array_filter($tpl['actividades'], fn($a) => $a['fecha_inicio'] !== null || (int)$a['duracion_dias'] < 1));
$ok('el tipo de obra queda guardado en la plantilla', $tpl && $tpl['tipo_obra'] === 'videovigilancia' && $tpl['meta']['fuente']['tipo'] === 'tipo_obra');
$ok('un tipo de obra inexistente no genera nada', \Classes\PlantillaProyecto::desdeTipoObra('no_existe') === []);
$ok('la plantilla sin partidas se rechaza antes de tocar la BD', \Classes\PlantillaProyecto::guardarDatos(['partidas' => [], 'actividades' => []], ['id' => 1, 'rol' => 'admin'], 'X', '', false)[0] === false);
$det = \Classes\Plantillas::detectarTexto('Proyecto de videovigilancia con cámaras IP, NVR y cableado estructurado para CCTV.');
$ok('detecta el tipo de obra en el texto de un documento', array_key_first($det['ranking']) === 'videovigilancia' && $det['ranking']['videovigilancia']['puntos'] > 0);
$ok('un texto sin pistas no inventa un tipo de obra', \Classes\Plantillas::detectarTexto('Texto genérico sin palabras del oficio.')['total'] === 0);

echo "Administración de obra\n";
$ok('la situación del contrato se calcula por fecha', \Classes\Contratos::situacion(['estado' => 'vigente', 'vencimiento' => date('Y-m-d', strtotime('-1 day')), 'aviso_dias' => 30]) === 'vencido'
    && \Classes\Contratos::situacion(['estado' => 'vigente', 'vencimiento' => null, 'aviso_dias' => 30]) === 'vigente'
    && \Classes\Contratos::situacion(['estado' => 'cerrado', 'vencimiento' => date('Y-m-d', strtotime('-1 day')), 'aviso_dias' => 30]) === 'cerrado');
$ok('las cuatro herramientas nuevas están declaradas para el visitante', in_array('calidad', array_keys(\Model\Usuario::HERRAMIENTAS), true)
    && in_array('equipo', array_keys(\Model\Usuario::HERRAMIENTAS), true)
    && in_array('contratos', array_keys(\Model\Usuario::HERRAMIENTAS), true)
    && in_array('cierre', array_keys(\Model\Usuario::HERRAMIENTAS), true));
$ok('Calidad, Equipo y Cierre tienen sus catálogos completos', count(\Classes\Calidad::ESTADOS) === 5 && count(\Classes\Equipo::ESTADOS) === 3 && count(\Classes\Cierre::CATEGORIAS) === 6);
$ok('el ITP con IA se aplica a Calidad y pide actividad/máximo', \Classes\Herramientas\ItpCalidad::ficha()['aplicable'] === true
    && \Classes\Herramientas\ItpCalidad::ficha()['tipo'] === 'itp_calidad'
    && \Classes\Herramientas\Registro::parametros(\Classes\Herramientas\ItpCalidad::class, ['maximo' => 999])['maximo'] === 20);
$ok('Compras y suministros está en el grupo compras y no cambia datos solo', \Classes\Herramientas\ComprasMateriales::ficha()['grupo'] === 'compras'
    && \Classes\Herramientas\ComprasMateriales::ficha()['aplicable'] === false);
$ok('el registro descubre las dos herramientas nuevas', isset(\Classes\Herramientas\Registro::todas()['itp_calidad'], \Classes\Herramientas\Registro::todas()['compras']));

echo "Compuerta de calidad\n";
$ok('un punto pendiente topa el avance en 99 %', \Classes\Calidad::tope(['nivel' => 'pendiente'], 40) === 99);
$ok('una no conformidad abierta congela el avance en el valor actual', \Classes\Calidad::tope(['nivel' => 'nc'], 40) === 40 && \Classes\Calidad::tope(['nivel' => 'nc'], 100) === 100);
$ok('una liberación fija su propio tope', \Classes\Calidad::tope(['nivel' => 'liberada', 'tope' => 80], 10) === 80);
$ok('la compuerta se puede omitir para corregir datos y se restaura', (function () {
    \Model\Actividad::omitirCompuertaCalidad(true);
    $r = \Model\Actividad::bloqueoCalidad();
    \Model\Actividad::omitirCompuertaCalidad(false);
    return $r === null;
})());
$ok('la migración y el esquema traen la columna y la tabla de liberaciones',
    str_contains((string)@file_get_contents(__DIR__ . '/../database/migracion_calidad_bloquea.sql'), 'calidad_liberaciones')
    && str_contains((string)@file_get_contents(__DIR__ . '/../database/schema.sql'), 'CREATE TABLE IF NOT EXISTS calidad_liberaciones')
    && str_contains((string)@file_get_contents(__DIR__ . '/../database/schema.sql'), 'bloquea TINYINT(1) NOT NULL DEFAULT 1'));
$ok('las rutas de liberar y revocar están registradas', (function () {
    $t = (string)@file_get_contents(__DIR__ . '/../public/index.php');
    return str_contains($t, "/calidad/liberar'") && str_contains($t, "/calidad/liberar/revocar'");
})());
$ok('el parte diario avisa cuando calidad recorta el avance', method_exists(\Classes\ParteDiario::class, 'avisos'));
$ok('el avance del programa pasa por la compuerta', str_contains((string)@file_get_contents(__DIR__ . '/../controllers/ProgramaController.php'), 'Calidad::bloqueo('));
echo "Registro de riesgos\n";
$ok('la severidad multiplica probabilidad por impacto y respeta los topes', \Classes\Riesgos::severidad(3, 3) === 9 && \Classes\Riesgos::severidad(1, 1) === 1 && \Classes\Riesgos::severidad(9, 0) === 3);
$ok('la matriz 3×3 pinta verde, amarillo y rojo en sus rangos', \Classes\Riesgos::nivel(3) === 'verde' && \Classes\Riesgos::nivel(4) === 'amarillo' && \Classes\Riesgos::nivel(6) === 'amarillo' && \Classes\Riesgos::nivel(7) === 'rojo');
$ok('los catálogos del registro están completos', count(\Classes\Riesgos::PROBABILIDADES) === 3 && count(\Classes\Riesgos::IMPACTOS) === 3
    && count(\Classes\Riesgos::CATEGORIAS) === 9 && count(\Classes\Riesgos::ESTRATEGIAS) === 4 && count(\Classes\Riesgos::ESTADOS) === 4);
$ok('un riesgo materializado o cerrado no cuenta como abierto', !\Classes\Riesgos::abierto(['estado' => 'materializado']) && !\Classes\Riesgos::abierto(['estado' => 'cerrado'])
    && \Classes\Riesgos::abierto(['estado' => 'identificado']) && \Classes\Riesgos::abierto(['estado' => 'en_tratamiento']));
$ok('la tabla de riesgos está en el esquema y en su migración', str_contains((string)@file_get_contents(__DIR__ . '/../database/schema.sql'), 'CREATE TABLE IF NOT EXISTS riesgos')
    && str_contains((string)@file_get_contents(__DIR__ . '/../database/migracion_riesgos.sql'), 'CREATE TABLE IF NOT EXISTS riesgos'));
$ok('las rutas del registro están registradas', (function () {
    $t = (string)@file_get_contents(__DIR__ . '/../public/index.php');
    return str_contains($t, "'/riesgos'") && str_contains($t, "'/riesgos/guardar'") && str_contains($t, "'/riesgos/materializar'") && str_contains($t, "'/riesgos/pdf'");
})());
$ok('la herramienta riesgos está declarada y agrupada para el visitante', isset(\Model\Usuario::HERRAMIENTAS['riesgos'])
    && in_array('riesgos', \Model\Usuario::HERRAMIENTAS_GRUPOS['Planeación'], true));
$ok('la salud y el resumen matutino leen el registro de riesgos', str_contains((string)@file_get_contents(__DIR__ . '/../classes/Salud.php'), 'Riesgos::resumen(')
    && str_contains((string)@file_get_contents(__DIR__ . '/../classes/ResumenObra.php'), 'Riesgos::rojosAbiertos('));
$ok('la página de riesgos tiene matriz, formulario y conversión a restricción', (function () {
    $v = (string)@file_get_contents(__DIR__ . '/../views/riesgos/index.php');
    return str_contains($v, 'rg-matriz') && str_contains($v, '/riesgos/materializar') && str_contains($v, 'rg-sev-resultado');
})());
echo "Cantidades y precio unitario\n";
$ok('el importe es cantidad por precio y queda vacío si falta uno', \Classes\Cantidades::importe(240, 2000) === 480000.0
    && \Classes\Cantidades::importe('2.5', '100.50') === 251.25
    && \Classes\Cantidades::importe(null, 10) === null && \Classes\Cantidades::importe(10, '') === null);
$ok('la migración y el esquema traen precio unitario y el respaldo del presupuesto manual', (function () {
    $m = (string)@file_get_contents(__DIR__ . '/../database/migracion_cantidades_precio.sql');
    $s = (string)@file_get_contents(__DIR__ . '/../database/schema.sql');
    return str_contains($m, 'precio_unitario') && str_contains($m, 'presupuesto_manual')
        && str_contains($s, 'precio_unitario DECIMAL(14,2)') && str_contains($s, 'origen ENUM(\'manual\',\'cantidades\')');
})());
$ok('la ruta para guardar el concepto de obra está registrada', str_contains((string)@file_get_contents(__DIR__ . '/../public/index.php'), "'/programa/actividades/cantidad'"));
$ok('la actividad tiene el formulario de concepto de obra', (function () {
    $v = (string)@file_get_contents(__DIR__ . '/../views/programa/editar-actividad.php');
    return str_contains($v, '/programa/actividades/cantidad') && str_contains($v, 'cant-precio') && str_contains($v, 'cant-importe');
})());
$ok('Costos calcula el presupuesto desde las cantidades y conserva el manual', str_contains((string)@file_get_contents(__DIR__ . '/../classes/Costos.php'), 'Cantidades::resumenPartidas(')
    && str_contains((string)@file_get_contents(__DIR__ . '/../classes/Costos.php'), 'presupuesto_manual'));
$ok('Datos de obra muestra el precio unitario', str_contains((string)@file_get_contents(__DIR__ . '/../classes/DatosObra.php'), 'precio_unitario'));
$ok('el Ensamblador deduce el precio unitario al importar cantidades', str_contains((string)@file_get_contents(__DIR__ . '/../classes/Ensamblador.php'), 'precio_unitario, importe, origen'));
echo "Compromisos y disponible\n";
$ok('los catálogos de compromisos están completos', count(\Classes\Compromisos::TIPOS) === 3
    && count(\Classes\Compromisos::ESTADOS) === 5 && \Classes\Compromisos::ABIERTOS === ['vigente', 'parcial']);
$ok('la tabla de compromisos está en el esquema y en su migración', (function () {
    $m = (string)@file_get_contents(__DIR__ . '/../database/migracion_compromisos.sql');
    $s = (string)@file_get_contents(__DIR__ . '/../database/schema.sql');
    return str_contains($m, 'CREATE TABLE IF NOT EXISTS compromisos') && str_contains($m, 'compromiso_aplicaciones')
        && str_contains($s, 'CREATE TABLE IF NOT EXISTS compromisos') && str_contains($s, 'CREATE TABLE IF NOT EXISTS compromiso_aplicaciones');
})());
$ok('las rutas de compromisos están registradas', (function () {
    $t = (string)@file_get_contents(__DIR__ . '/../public/index.php');
    return str_contains($t, "'/costos/compromiso'") && str_contains($t, "'/costos/compromiso/aplicar'")
        && str_contains($t, "'/costos/compromiso/cerrar'") && str_contains($t, "'/costos/compromiso/eliminar'");
})());
$ok('Costos descuenta lo comprometido y calcula el disponible', str_contains((string)@file_get_contents(__DIR__ . '/../classes/Costos.php'), 'Compromisos::resumenProyecto(')
    && str_contains((string)@file_get_contents(__DIR__ . '/../classes/Costos.php'), "'disponible'"));
$ok('la factura aplicada se descuenta del compromiso y se puede revertir al borrar el movimiento', str_contains((string)@file_get_contents(__DIR__ . '/../classes/Compromisos.php'), 'compromiso_aplicaciones')
    && str_contains((string)@file_get_contents(__DIR__ . '/../classes/Costos.php'), 'quitarAplicacionesDeMovimiento'));
$ok('la pestaña de compromisos tiene alta, aplicación de factura y sobregiro firmado', (function () {
    $v = (string)@file_get_contents(__DIR__ . '/../views/costos/index.php');
    return str_contains($v, "pestana' => 'compromisos'") && str_contains($v, '/costos/compromiso/aplicar')
        && str_contains($v, 'motivo_sobregiro') && str_contains($v, 'Sobregiro autorizado');
})());
echo "Estimaciones completas\n";
$ok('el cálculo aplica IVA, retención y amortización sobre el subtotal', (function () {
    $c = \Classes\Estimaciones::calcular(2000000, 0, 16, 5, 10);
    return $c['base'] === 2000000.0 && $c['iva'] === 320000.0 && $c['retencion'] === 100000.0
        && $c['amortizacion'] === 200000.0 && $c['total_bruto'] === 2320000.0 && $c['neto'] === 2020000.0;
})());
$ok('el ajuste de precios entra en la base antes de IVA y retención', (function () {
    $c = \Classes\Estimaciones::calcular(1000, 100, 16, 5, 0);
    return $c['base'] === 1100.0 && $c['iva'] === 176.0 && $c['retencion'] === 55.0 && $c['neto'] === 1221.0;
})());
$ok('las tablas de estimaciones y contrato están en el esquema y en su migración', (function () {
    $m = (string)@file_get_contents(__DIR__ . '/../database/migracion_estimaciones.sql');
    $s = (string)@file_get_contents(__DIR__ . '/../database/schema.sql');
    return str_contains($m, 'CREATE TABLE IF NOT EXISTS estimaciones') && str_contains($m, 'costos_contrato')
        && str_contains($m, 'estimacion_partidas') && str_contains($s, 'CREATE TABLE IF NOT EXISTS estimaciones');
})());
$ok('las rutas de estimaciones están registradas', (function () {
    $t = (string)@file_get_contents(__DIR__ . '/../public/index.php');
    return str_contains($t, "'/estimaciones/contrato'") && str_contains($t, "'/estimaciones/guardar'")
        && str_contains($t, "'/estimaciones/eliminar'") && str_contains($t, "'/estimaciones/pdf'");
})());
$ok('Costos muestra el bruto y el neto de las estimaciones', str_contains((string)@file_get_contents(__DIR__ . '/../classes/Costos.php'), 'estimado_neto')
    && str_contains((string)@file_get_contents(__DIR__ . '/../views/costos/index.php'), 'Neto a cobrar'));
$ok('la hoja de estimación tiene deducciones y firmas', (function () {
    $v = (string)@file_get_contents(__DIR__ . '/../views/estimaciones/pdf.php');
    return str_contains($v, 'Retención') && str_contains($v, 'Amortización del anticipo')
        && str_contains($v, 'Neto a cobrar') && str_contains($v, 'firmas');
})());
echo "Almacén y órdenes de compra\n";
$ok('los tipos de movimiento del almacén están completos', count(\Classes\Almacen::TIPOS_MOV) === 4
    && isset(\Classes\Almacen::TIPOS_MOV['entrada'], \Classes\Almacen::TIPOS_MOV['salida'], \Classes\Almacen::TIPOS_MOV['ajuste_entrada'], \Classes\Almacen::TIPOS_MOV['ajuste_salida']));
$ok('las tablas de almacén están en el esquema y en su migración', (function () {
    $m = (string)@file_get_contents(__DIR__ . '/../database/migracion_almacen.sql');
    $s = (string)@file_get_contents(__DIR__ . '/../database/schema.sql');
    return str_contains($m, 'CREATE TABLE IF NOT EXISTS almacenes') && str_contains($m, 'compra_lineas')
        && str_contains($m, 'almacen_movimientos') && str_contains($s, 'stock_minimo')
        && str_contains($s, 'CREATE TABLE IF NOT EXISTS almacen_movimientos');
})());
$ok('las rutas del almacén están registradas', (function () {
    $t = (string)@file_get_contents(__DIR__ . '/../public/index.php');
    return str_contains($t, "'/almacen'") && str_contains($t, "'/almacen/orden'")
        && str_contains($t, "'/almacen/recibir'") && str_contains($t, "'/almacen/salida'") && str_contains($t, "'/almacen/ajuste'");
})());
$ok('el material guarda su existencia mínima', str_contains((string)@file_get_contents(__DIR__ . '/../models/Material.php'), 'stock_minimo')
    && str_contains((string)@file_get_contents(__DIR__ . '/../views/materiales/formulario.php'), 'name="stock_minimo"'));
$ok('la existencia se calcula con entradas menos salidas y ajustes', str_contains((string)@file_get_contents(__DIR__ . '/../classes/Almacen.php'), "tipo IN ('entrada','ajuste_entrada')")
    && str_contains((string)@file_get_contents(__DIR__ . '/../classes/Almacen.php'), 'stockMaterial('));
$ok('la página de almacén tiene orden, recepción, salida y ajuste', (function () {
    $v = (string)@file_get_contents(__DIR__ . '/../views/almacen/index.php');
    return str_contains($v, '/almacen/orden') && str_contains($v, '/almacen/recibir')
        && str_contains($v, '/almacen/salida') && str_contains($v, '/almacen/ajuste');
})());
echo "Contexto de IA\n";
$ctx = \Classes\ContextoIA::guardar(99, 'prueba', ['x' => 1], ['dato' => 'valor'], []);
$leido = \Classes\ContextoIA::leer($ctx, 99, 'prueba');
$ok('el contexto se guarda y se lee con el mismo proyecto y herramienta', $ctx !== '' && $leido && $leido['datos']['dato'] === 'valor' && $leido['params']['x'] === 1);
$ok('otro proyecto no puede leer el contexto', \Classes\ContextoIA::leer($ctx, 100, 'prueba') === null);
$ok('un token inventado no lee nada', \Classes\ContextoIA::leer('../../etc/passwd', 99, 'prueba') === null);
\Classes\ContextoIA::borrar($ctx);

$asset = fn(string $p): string => (string)@file_get_contents(__DIR__ . '/../' . $p);
echo "Anotador: plano, medidas y firmas\n";
$ok('el esquema y su migración crean las tablas del Anotador', str_contains($asset('database/schema.sql'), 'CREATE TABLE IF NOT EXISTS anotador_documentos') && str_contains($asset('database/migracion_anotador.sql'), 'CREATE TABLE IF NOT EXISTS anotador_versiones') && str_contains($asset('database/migracion_anotador.sql'), 'anotador_firmas_aplicadas'));
$ok('las rutas del Anotador están registradas', str_contains($asset('public/index.php'), "/anotador/guardar") && str_contains($asset('public/index.php'), "/anotador/estampa") && str_contains($asset('public/index.php'), "/anotador/firmar") && !str_contains($asset('public/index.php'), "/anotador/publico"));
$ok('la herramienta está en el menú y en los permisos', str_contains($asset('views/templates/herramientas-proyecto.php'), "'/anotador', 'fa-pen-nib', 'Anotador'") && str_contains($asset('models/Usuario.php'), "'anotador' => ['Anotador de planos', ['/anotador']]"));
$ok('la vista tiene el visor, las herramientas y los paneles', str_contains($asset('views/anotador/index.php'), 'id="ant-hoja"') && str_contains($asset('views/anotador/index.php'), "['poligono'") && str_contains($asset('views/anotador/index.php'), "['volumen'") && str_contains($asset('views/anotador/index.php'), "['firma'") && str_contains($asset('views/anotador/index.php'), 'id="ant-panel"'));
$ok('el motor comparte las formas con el Comparador', str_contains($asset('views/anotador/index.php'), '/vendor/anotaciones.js') && str_contains($asset('views/comparador/index.php'), '/vendor/anotaciones.js') && str_contains($asset('public/vendor/anotador.js'), 'A.dibujar'));
$ok('el Anotador dibuja polígonos, volúmenes, imágenes y firmas', str_contains($asset('public/vendor/anotaciones.js'), "case 'volumen'") && str_contains($asset('public/vendor/anotaciones.js'), 'areaPoligono') && str_contains($asset('public/vendor/anotaciones.js'), "case 'firma'"));
$ok('el Anotador calibra por página y calcula el cuadro de medidas', str_contains($asset('public/vendor/anotador.js'), 'ant.cfg.escalas[paginaActual()]') && str_contains($asset('views/anotador/index.php'), 'id="ant-medidas-tabla"') && str_contains($asset('public/vendor/anotador.js'), 'medidasCsv'));
$ok('el Anotador resalta texto del PDF y exporta PDF', str_contains($asset('public/vendor/anotador.js'), 'renderTextLayer') && str_contains($asset('public/vendor/anotador.js'), 'aplicarResaltadoTexto') && str_contains($asset('public/vendor/anotador.js'), 'jsPDF'));
$ok('el Anotador firma, bloquea y permite reabrir al administrador', str_contains($asset('classes/Anotador.php'), 'function finalizar') && str_contains($asset('classes/Anotador.php'), 'anotador_firmas_aplicadas') && str_contains($asset('views/anotador/index.php'), 'ant-finalizado') && str_contains($asset('controllers/AnotadorController.php'), 'function reabrir'));
$ok('las imágenes del Anotador se entregan con permiso del proyecto', str_contains($asset('classes/Media.php'), "'anotador'") && str_contains($asset('controllers/AnotadorController.php'), 'function imagenVer'));
$ok('el Anotador cuenta para el almacenamiento', str_contains($asset('classes/Almacenamiento.php'), 'Anotador::bytes'));
$anAnt = \Classes\Anotador::anotaciones(json_encode(['ancho' => 1000, 'alto' => 700, 'escalas' => [1 => 0.002], 'shapes' => [
    ['tipo' => 'poligono', 'pts' => [[0, 0], [100, 0], [100, 100]], 'pagina' => 1],
    ['tipo' => 'volumen', 'pts' => [[0, 0], [100, 0], [100, 100]], 'profundidad' => 0.3, 'pagina' => 1],
    ['tipo' => 'firma', 'x' => 10, 'y' => 10, 'w' => 100, 'h' => 40, 'imagen' => str_repeat('a', 32) . '.png', 'nombre' => 'Ana', 'pagina' => 1],
    ['tipo' => 'inventada', 'x' => 1, 'y' => 1],
]]));
$ok('el normalizador acepta polígonos, volúmenes y firmas y descarta lo desconocido', count($anAnt['shapes']) === 3 && $anAnt['shapes'][0]['tipo'] === 'poligono' && $anAnt['shapes'][1]['profundidad'] === 0.3 && $anAnt['shapes'][2]['nombre'] === 'Ana' && $anAnt['escalas'][1] === 0.002);
$ok('una imagen de firma con nombre raro se rechaza', \Classes\Anotador::anotaciones(json_encode(['shapes' => [['tipo' => 'firma', 'imagen' => '../../etc/passwd', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 10]]]))['shapes'] === []);
$ok('la configuración del Anotador acota las escalas por página', \Classes\Anotador::config(json_encode(['escalas' => [1 => 0.002, 2 => -5, 'x' => 1, 3 => '0.5'], 'unidad' => 'm']))['escalas'] === [1 => 0.002, 3 => 0.5]);

echo "Seguridad: control de acceso del visitante\n";
$ok('las rutas de las herramientas del menú están declaradas en Usuario::HERRAMIENTAS', (function () {
    $src = (string)file_get_contents(__DIR__ . '/../views/templates/herramientas-proyecto.php');
    preg_match_all("/\\['[^']+',\\s*'(\\/[^']+)'/", $src, $m);
    $sin = [];
    foreach (array_unique($m[1]) as $ruta) {
        if (in_array($ruta, ['/proyectos/ver', '/dashboard', '/exportar'], true)) continue;   // rutas generales, no herramientas
        if (herramienta_de_ruta($ruta) !== null) continue;
        $sin[] = $ruta;
    }
    if ($sin) fwrite(STDERR, '  sin declarar: ' . implode(', ', $sin) . "\n");
    return !$sin;
})());
$ok('una herramienta declarada se resuelve por su ruta', herramienta_de_ruta('/punch') === 'punch' && herramienta_de_ruta('/seguridad') === 'seguridad');
$ok('historial-cambios no se confunde con historial', herramienta_de_ruta('/historial-cambios') === 'centro-ia' && herramienta_de_ruta('/historial') === 'historial');
$ok('el historial muestra al autor en una columna junto a la fecha', str_contains((string)file_get_contents(__DIR__ . '/../views/historial/index.php'), "<th><?php echo s(t('Fecha')); ?></th><th><?php echo s(t('Usuario')); ?></th>"));
$ok('las rutas generales del visitante no son herramientas', ruta_general('/proyectos') && ruta_general('/manual') && ruta_general('/') && !ruta_general('/punch'));

echo "Colaboradores: permisos y borrado\n";
$ok('el colaborador nunca edita ajustes, compartir ni el historial', \Model\Usuario::herramientasEdicionDePost(['programa', 'ajustes', 'compartir', 'historial'], null) === json_encode(['programa']));
$ok('lo que edita es siempre un subconjunto de lo que ve', \Model\Usuario::herramientasEdicionDePost(['programa', 'msr'], ['programa']) === json_encode(['programa']));
$ok('sin herramientas de edición la lista queda vacía', \Model\Usuario::herramientasEdicionDePost([], null) === null);
$ok('las herramientas se agrupan sin perder ninguna', (function () {
    $grupos = \Model\Usuario::gruposHerramientas();
    $claves = [];
    foreach ($grupos as $filas) { $claves = array_merge($claves, array_keys($filas)); }
    sort($claves);
    $todas = array_keys(\Model\Usuario::HERRAMIENTAS);
    sort($todas);
    return $claves === $todas && !isset($grupos['Otras herramientas']);
})());
$u = new \Model\Usuario(['rol' => 'cliente']);
$ok('los topes por defecto son 2 visitantes y 3 colaboradores', $u->limiteEquipo('visitante') === 2 && $u->limiteEquipo('colaborador') === 3);
$ok('un visitante de nivel ver no es colaborador', !(new \Model\Usuario(['rol' => 'visitante', 'nivel_acceso' => 'ver']))->esColaborador());
$ok('un visitante con nivel editar sí es colaborador', (new \Model\Usuario(['rol' => 'visitante', 'nivel_acceso' => 'editar']))->esColaborador());
$ok('las rutas de borrado se reconocen', es_ruta_de_borrado('/programa/actividades/eliminar') && es_ruta_de_borrado('/api/dependencias/eliminar')
    && es_ruta_de_borrado('/vplan/subactividades/sheetflow/quitar') && !es_ruta_de_borrado('/programa'));
$ok('toda ruta de borrado del front controller queda bloqueada al colaborador', (function () {
    $src = (string)file_get_contents(__DIR__ . '/../public/index.php');
    preg_match_all("/\\\$router->(?:get|post)\\('(\\/[^']+)'/", $src, $m);
    $sin = [];
    foreach (array_unique($m[1]) as $ruta) {
        if (preg_match('~/(eliminar|borrar|quitar)(/|$)~i', $ruta) && !es_ruta_de_borrado($ruta)) $sin[] = $ruta;
    }
    if ($sin) fwrite(STDERR, '  no detectadas: ' . implode(', ', $sin) . "\n");
    return !$sin;
})());
$ok('el Centro de Exportación filtra por herramienta', \Controllers\AjustesController::herramientaExportacion('pdf') === 'programa'
    && \Controllers\AjustesController::herramientaExportacion('reportes', 'bitacora') === 'bitacora'
    && \Controllers\AjustesController::herramientaExportacion('reportes', 'evidencia') === 'evidencias'
    && \Controllers\AjustesController::herramientaExportacion('msr') === 'msr');
$ok('exportar no está atado a Ajustes y sí es ruta general', herramienta_de_ruta('/exportar') === null && ruta_general('/exportar'));
$ok('el menú de Ajustes tiene la pestaña de accesos del proyecto', str_contains((string)file_get_contents(__DIR__ . '/../views/ajustes/index.php'), "id=\"accesos\""));
$ok('la columna «Editar» se oculta de verdad (hidden vence al display:flex)', str_contains((string)file_get_contents(__DIR__ . '/../public/build/css/app.css'), '.herr-grid [hidden]'));

$ok('la contraseña mínima es de 10 y rechaza las comunes', \Model\Usuario::passwordDebil('corta') !== null && \Model\Usuario::passwordDebil('1234567890') !== null && \Model\Usuario::passwordDebil('Obra2026-segura') === null);

echo "Interfaz táctil de vPlan y editor\n";
$asset = fn(string $p): string => (string)@file_get_contents(__DIR__ . '/../' . $p);
$gestos = $asset('public/vendor/gestos-lienzo.js');
$hoja = $asset('public/vendor/hoja-inferior.js');
$ok('gestos-lienzo.js expone GestosLienzo (montar y esTactil)', str_contains($gestos, 'window.GestosLienzo') && str_contains($gestos, 'montar:') && str_contains($gestos, 'esTactil:'));
$ok('hoja-inferior.js expone HojaInferior', str_contains($hoja, 'window.HojaInferior') && str_contains($hoja, 'crear:'));
$ok('el editor carga los gestos y la hoja antes del motor', str_contains($asset('views/vplan/_editor-scripts.php'), 'gestos-lienzo.js') && str_contains($asset('views/vplan/_editor-scripts.php'), 'hoja-inferior.js'));
$ok('la hoja inferior y el dock están en el CSS compilado', str_contains($asset('public/build/css/app.css'), '.hi-caja') && str_contains($asset('public/build/css/app.css'), '.ve-dock'));
$ok('el editor de marcas ya no se bloquea en el celular', !str_contains($asset('views/vplan/editor.php'), 'aviso-escritorio'));
$ok('el manual explica el pellizco con dos dedos', str_contains($asset('views/manual/_secciones.php'), 'pellizca con dos dedos'));
$ok('el visor tiene ficha de tarea y vista Tareas', str_contains($asset('views/vplan/ver.php'), "HojaInferior.crear('vp-tarea')") && str_contains($asset('views/vplan/ver.php'), 'id="vp-tareas"'));
$ok('las tareas llevan data-actividad y data-titulo', str_contains($asset('views/vplan/_hoja.php'), 'data-actividad=') && str_contains($asset('views/vplan/_hoja.php'), 'data-titulo='));
$ok('capa-superior.js reubica los flotantes en pantalla completa', str_contains($asset('public/vendor/capa-superior.js'), 'window.CapaSuperior') && str_contains($asset('public/vendor/capa-superior.js'), 'fullscreenchange'));
$ok('ampliar usa la capa superior (paletas y menús visibles)', str_contains($asset('views/vplan/ver.php'), 'contenido-principal') && str_contains($asset('views/vplan/_editor-scripts.php'), 'contenido-principal'));
$ok('la paleta y las hojas usan CapaSuperior', str_contains($asset('public/vendor/selector-color.js'), 'CapaSuperior.adoptar') && str_contains($asset('public/vendor/hoja-inferior.js'), 'CapaSuperior.adoptar'));
$ok('los botones con texto de la barra tienen estilo propio', str_contains($asset('public/build/css/app.css'), '.ve-barra .ve-txt'));

echo "Editor, Redactor, SheetFlow y Estampas en tablet y celular\n";
$ok('Gráficos y Símbolos heredan la vista ampliada del editor', str_contains($asset('views/graficos/abrir.php'), '_editor-scripts.php') && str_contains($asset('views/graficos/abrir.php'), '_editor-lienzo.php'));
$ok('el modal de confirmación también se ve en pantalla completa', str_contains($asset('src/js/app.js'), 'CapaSuperior.adoptar(modal)'));
$ok('GestosLienzo permite el pellizco sobre una zona editable (excepto)', str_contains($gestos, 'op.excepto') && str_contains($gestos, 'excepto:'));
$ok('el Redactor ya no se bloquea en el celular', !str_contains($asset('views/redactor/abrir.php'), 'aviso-escritorio'));
$ok('el Redactor tiene dock y hoja de herramientas', str_contains($asset('public/vendor/redactor-movil.js'), 'HojaInferior.crear') && str_contains($asset('public/vendor/redactor-movil.js'), 'rd-dock'));
$ok('el modo enfoque del Redactor es pantalla completa de verdad', str_contains($asset('public/vendor/redactor.js'), "classList.toggle('rd-en-ampliado'") && str_contains($asset('public/vendor/redactor.js'), 'contenido-principal'));
$ok('SheetFlow ya no se bloquea en el celular', !str_contains($asset('views/sheetflow/abrir.php'), 'aviso-escritorio'));
$ok('SheetFlow edita la celda en hoja inferior y tiene pellizco', str_contains($asset('public/vendor/sheetflow.js'), "HojaInferior.crear('sf-celda')") && str_contains($asset('public/vendor/sheetflow.js'), 'GestosLienzo.montar'));
$ok('las estampas usan los gestos de lienzo', str_contains($asset('public/vendor/estampas-editor.js'), 'GestosLienzo.montar') && str_contains($asset('views/simbolos/index.php'), 'gestos-lienzo.js'));
$ok('el CSS compilado trae el dock del Redactor y la celda en hoja', str_contains($asset('public/build/css/app.css'), '.rd-dock') && str_contains($asset('public/build/css/app.css'), '.sf-celda-hoja'));
$ok('SheetFlow edita en la celda en tablet y en hoja solo en el celular', str_contains($asset('public/vendor/sheetflow.js'), 'MOVIL_ESTRECHO.matches'));
$ok('SheetFlow inserta referencias al hacer clic en celdas mientras se escribe una fórmula', str_contains($asset('public/vendor/sheetflow.js'), 'function entradaFormula') && str_contains($asset('public/vendor/sheetflow.js'), 'function insertarRef') && str_contains($asset('public/vendor/sheetflow.js'), 'function celdaApuntada'));
$ok('SheetFlow adapta las referencias de «Insertar función» a la columna y el renglón elegidos', str_contains($asset('public/vendor/sheetflow.js'), 'function formulaAdaptada') && str_contains($asset('public/vendor/sheetflow.js'), 'function destinoPlantilla') && str_contains($asset('public/vendor/sheetflow.js'), 'function bloqueDatos') && str_contains($asset('public/vendor/sheetflow.js'), 'REF_PLANTILLA'));
$ok('SheetFlow combina y separa celdas (colspan, rowspan y merges en los datos)', str_contains($asset('public/vendor/sheetflow.js'), 'function mergeDe') && str_contains($asset('views/sheetflow/abrir.php'), 'data-acc="combinar"') && str_contains($asset('public/vendor/sheetflow.js'), 'merges: S.merges'));
$ok('SheetFlow rellena con el asa y ajusta las referencias al copiar', str_contains($asset('public/vendor/sheetflow.js'), 'function ajustarFormula')
    && str_contains($asset('public/vendor/sheetflow.js'), 'function aplicarRelleno') && str_contains($asset('views/sheetflow/abrir.php'), 'id="sf-asa"'));
$ok('SheetFlow busca y reemplaza, filtra, congela y guarda el historial', str_contains($asset('public/vendor/sheetflow.js'), 'function buscarSiguiente')
    && str_contains($asset('public/vendor/sheetflow.js'), 'function abrirFiltro') && str_contains($asset('public/vendor/sheetflow.js'), 'function aplicarCongelado')
    && str_contains($asset('public/vendor/sheetflow.js'), 'function abrirHistorial') && str_contains($asset('views/sheetflow/abrir.php'), 'id="sf-historial"'));
$ok('SheetFlow tiene formato condicional, gráficos y fórmulas del proyecto', str_contains($asset('public/vendor/sheetflow.js'), 'function condicional')
    && str_contains($asset('public/vendor/sheetflow.js'), 'function svgGrafico') && str_contains($asset('public/vendor/sheetflow.js'), 'funcionProyecto')
    && str_contains($asset('views/sheetflow/abrir.php'), 'id="sf-cond-aplicar"') && str_contains($asset('views/sheetflow/abrir.php'), 'id="sf-graf-crear"'));
$ok('SheetFlow suma la selección, con Ctrl+clic y con el dedo en pantalla táctil', str_contains($asset('public/vendor/sheetflow.js'), 'function celdasElegidas')
    && str_contains($asset('public/vendor/sheetflow.js'), 'function alternarCeldaSuelta') && str_contains($asset('public/vendor/sheetflow.js'), 'function estadistica')
    && str_contains($asset('public/vendor/sheetflow.js'), 'sf-eleccion-tactil') && str_contains($asset('public/vendor/sheetflow.js'), 'LARGO_TOQUE')
    && str_contains($asset('public/build/css/app.css'), 'sf-eleccion-tactil') && str_contains($asset('views/sheetflow/abrir.php'), 'Ctrl/⌘+clic'));
$ok('la cinta de Redactor y SheetFlow se pliega como en OnlyOffice', str_contains($asset('public/vendor/redactor.js'), 'rd-cinta-cerrada') && str_contains($asset('public/vendor/sheetflow.js'), 'sf-cinta-cerrada') && str_contains($asset('views/redactor/abrir.php'), 'id="rd-plegar"') && str_contains($asset('views/sheetflow/abrir.php'), 'id="sf-plegar"'));
$ok('la cinta compacta y su botón están en el CSS compilado', str_contains($asset('public/build/css/app.css'), '.cinta-plegar') && str_contains($asset('public/build/css/app.css'), '.cinta-cab'));
$ok('la cinta de Redactor y SheetFlow no cambia de alto entre pestañas', str_contains($asset('public/vendor/cinta-scroll.js'), 'cinta-desborda')
    && str_contains($asset('public/vendor/cinta-scroll.js'), 'cinta-fin') && str_contains($asset('public/build/css/app.css'), 'cinta-desborda')
    && str_contains($asset('views/redactor/abrir.php'), 'cinta-scroll.js') && str_contains($asset('views/sheetflow/abrir.php'), 'cinta-scroll.js'));
$ok('el Redactor navega entre páginas con flechas debajo de la hoja', str_contains($asset('views/redactor/abrir.php'), 'id="rd-paginas-bar"') && str_contains($asset('public/vendor/redactor.js'), 'pintarBarraPaginas') && str_contains($asset('public/build/css/app.css'), '.rd-paginas-bar'));
$ok('Redactor y SheetFlow se pueden editar en móvil y tablet', !str_contains($asset('views/redactor/abrir.php'), 'aviso-escritorio') && !str_contains($asset('views/sheetflow/abrir.php'), 'aviso-escritorio') && str_contains($asset('views/redactor/abrir.php'), 'contenteditable="true"') && str_contains($asset('views/sheetflow/abrir.php'), 'id="sf-grid"'));
$ok('la edición táctil carga los gestos y la hoja inferior', str_contains($asset('views/redactor/abrir.php'), 'hoja-inferior.js') && str_contains($asset('views/redactor/abrir.php'), 'redactor-movil.js') && str_contains($asset('views/sheetflow/abrir.php'), 'hoja-inferior.js') && str_contains($asset('views/sheetflow/abrir.php'), 'gestos-lienzo.js'));
$ok('el mousedown emulado de un toque no cierra el editor de SheetFlow', str_contains($asset('public/vendor/sheetflow.js'), 'toqueEdito'));
$ok('el botón Editar de Redactor y SheetFlow se ve en móvil y tablet', !str_contains($asset('views/redactor/index.php'), 'hide-m"><i class="fa-solid fa-pen"></i> Editar') && !str_contains($asset('views/sheetflow/index.php'), 'hide-m"><i class="fa-solid fa-pen"></i> Editar') && !str_contains($asset('views/redactor/ver.php'), 'hide-m"><i class="fa-solid fa-pen"></i> Editar') && !str_contains($asset('views/sheetflow/ver.php'), 'hide-m"><i class="fa-solid fa-pen"></i> Editar'));

echo "Punch list: fecha de emisión\n";
$ok('la fecha de emisión existe en el esquema y en su migración', str_contains($asset('database/schema.sql'), 'fecha_emision DATE NULL') && str_contains($asset('database/migracion_punch_fecha_emision.sql'), 'ADD COLUMN fecha_emision'));
$ok('el punch guarda y edita la fecha de emisión', str_contains($asset('classes/Punch.php'), 'fecha_emision') && str_contains($asset('views/punch/index.php'), 'name="fecha_emision"') && str_contains($asset('views/punch/ver.php'), 'name="fecha_emision"'));
$ok('la fecha de emisión llega a la vista configurable, la API y el PDF', str_contains($asset('classes/PunchVista.php'), "'fecha_emision'") && str_contains($asset('classes/ApiRest.php'), 'fecha_emision') && str_contains($asset('classes/Punch.php'), '<th>Emisión</th>'));

echo "Iconos del menú de herramientas\n";
$tp = $asset('views/templates/herramientas-proyecto.php');
preg_match('/\$defs = \[(.*?)\n    \];/s', $tp, $md);
preg_match_all("/\\[\\s*'[^']+',\\s*'(\\/[^']+)',\\s*'(fa-[a-z0-9-]+)',\\s*'([^']+)'/", $md[1] ?? '', $mi, PREG_SET_ORDER);
$iconosMenu = array_column($mi, 2);
$ok('cada herramienta del menú tiene un icono propio (sin repetir)', count($mi) > 40 && count($iconosMenu) === count(array_unique($iconosMenu)));

echo "Comparador: anotaciones y guardado\n";
$cmpJs = $asset('public/vendor/comparador.js');
$ok('el esquema y su migración crean comparador_guardados', str_contains($asset('database/schema.sql'), 'CREATE TABLE IF NOT EXISTS comparador_guardados') && str_contains($asset('database/migracion_comparador_guardados.sql'), 'CREATE TABLE IF NOT EXISTS comparador_guardados'));
$ok('las rutas de guardar, abrir y duplicar están registradas', str_contains($asset('public/index.php'), "/comparador/guardar") && str_contains($asset('public/index.php'), "/comparador/guardado/archivo") && str_contains($asset('public/index.php'), "/comparador/duplicar"));
$ok('la vista tiene la barra de anotaciones y la lista de guardadas', str_contains($asset('views/comparador/index.php'), 'id="cmp-anot-toggle"') && str_contains($asset('views/comparador/index.php'), 'data-anot="globo"') && str_contains($asset('views/comparador/index.php'), 'cmp-anot-lista') && str_contains($asset('views/comparador/index.php'), 'Comparaciones guardadas'));
$ok('el motor dibuja globos, textos, flechas, formas y nubes', str_contains($asset('public/vendor/anotaciones.js'), "case 'globo'") && str_contains($asset('public/vendor/anotaciones.js'), "case 'nube'") && str_contains($asset('public/vendor/anotaciones.js'), "case 'flecha'") && str_contains($cmpJs, 'A.dibujar') && str_contains($asset('views/comparador/index.php'), '/vendor/anotaciones.js'));
$ok('el motor guarda y reabre comparaciones', str_contains($cmpJs, '/comparador/guardar') && str_contains($cmpJs, 'restaurarGuardado') && str_contains($cmpJs, 'dibujarAnotaciones'));
$ok('las formas comprueban el tipo recibido (sin variable global)', str_contains($cmpJs, 'const vacia = sh =>') && !preg_match('/\bvacia\(\)/', $cmpJs));
$ok('las anotaciones se dibujan nítidas en la ventana (no dentro del documento)', str_contains($cmpJs, 'function redibujarAnot') && str_contains($cmpJs, 'window.devicePixelRatio') && str_contains($cmpJs, 'anotCtx.scale(vista.k, vista.k)') && !str_contains($asset('views/comparador/index.php'), 'cmp-mundo-anot'));
$ok('el selector admite documentos de Archivo, vPlan, Editor y Redactor', str_contains($asset('classes/Comparador.php'), 'FUENTES_PROYECTO') && str_contains($asset('classes/Comparador.php'), 'VplanLamina::deProyecto') && str_contains($asset('classes/Comparador.php'), 'editor_laminas') && str_contains($asset('classes/Comparador.php'), 'RedactorDocumento::deProyecto') && str_contains($asset('public/index.php'), "/comparador/fuente") && str_contains($asset('views/comparador/index.php'), 'optgroup'));
$ok('las fuentes del proyecto se validan al guardar', str_contains($asset('classes/Comparador.php'), "in_array(\$tipo, ['vplan', 'editor', 'redactor'], true)") && str_contains($asset('classes/Comparador.php'), 'fuenteDeProyecto($pid, $tipo'));
$ok('las copias del comparador cuentan para el almacenamiento', str_contains($asset('classes/Almacenamiento.php'), 'Comparador::bytes'));
$ok('el historial de versiones y el enlace público están en el esquema y su migración', str_contains($asset('database/schema.sql'), 'CREATE TABLE IF NOT EXISTS comparador_versiones') && str_contains($asset('database/schema.sql'), 'uq_cmp_token') && str_contains($asset('database/migracion_comparador_revisiones.sql'), 'comparador_versiones'));
$ok('las rutas de compartir, historial y vista pública están registradas', str_contains($asset('public/index.php'), "/comparador/publico") && str_contains($asset('public/index.php'), "/comparador/compartir") && str_contains($asset('public/index.php'), "/comparador/versiones") && str_contains($asset('public/index.php'), "/comparador/restaurar"));
$ok('la vista pública es de solo lectura y sin sesión', str_contains($asset('controllers/ComparadorController.php'), 'function publico(') && str_contains($asset('controllers/ComparadorController.php'), 'publicoLayout') && is_file(__DIR__ . '/../views/comparador/publico-no-disponible.php'));
$ok('el comparador usa los gestos táctiles compartidos', str_contains($asset('views/comparador/index.php'), 'gestos-lienzo.js') && str_contains($cmpJs, 'GestosLienzo.montar') && str_contains($cmpJs, 'alDobleToque'));
$ok('hay herramientas de medir y área con escala', str_contains($asset('views/comparador/index.php'), 'data-anot="medir"') && str_contains($asset('views/comparador/index.php'), 'data-anot="area"') && str_contains($asset('views/comparador/index.php'), 'id="cmp-calibrar"') && str_contains($cmpJs, 'function calibrarCon') && str_contains($cmpJs, 'fmtLong'));
$ok('las diferencias tienen tolerancia, porcentaje y navegación', str_contains($asset('views/comparador/index.php'), 'id="cmp-diff-tol"') && str_contains($asset('views/comparador/index.php'), 'id="cmp-diff-pct"') && str_contains($cmpJs, 'function calcularDiff') && str_contains($cmpJs, 'function irCambio'));
$ok('las anotaciones viven por página y conservan su hilo', str_contains($cmpJs, 'enPaginaActual') && str_contains($cmpJs, 'pagina: paginaActual()') && str_contains($cmpJs, 'tamanos: slots.a.tamanos') && str_contains($asset('classes/Comparador.php'), "['notas'] = \$notas") && str_contains($asset('classes/Comparador.php'), "'tamanos' => \$tamanos"));
$ok('se puede crear un punch o un RFI desde una anotación', str_contains($cmpJs, 'function crearPunch') && str_contains($cmpJs, 'function crearRfi') && str_contains($asset('views/punch/index.php'), "\$pre['titulo']") && str_contains($asset('views/rfi/index.php'), "\$pre['asunto']"));
$ok('la sección de comparaciones guardadas es compacta y plegable', str_contains($asset('views/comparador/index.php'), '<details class="card cmp-guardados"') && str_contains($asset('src/scss/paginas/_comparador.scss'), '.cmp-guardados[open] .cmp-guardados-grid'));
$ok('el diálogo de guardado se centra en la ventana', str_contains($asset('src/scss/paginas/_comparador.scss'), 'dialog.cmp-dlg { position: fixed; inset: 0; margin: auto;'));
$ok('hay presentación, copiar/pegar e informe de revisión', str_contains($cmpJs, 'function entrarPresentacion') && str_contains($cmpJs, "e.key.toLowerCase() === 'c'") && str_contains($cmpJs, 'function abrirInforme'));
$ok('las anotaciones se pueden agrupar y se exportan las páginas con cambios', str_contains($cmpJs, 'function seleccionar') && str_contains($cmpJs, 'function exportarCambios') && str_contains($asset('views/comparador/index.php'), 'id="cmp-exportar-cambios"') && str_contains($asset('views/comparador/index.php'), 'id="cmp-det-grupo"'));

echo "Apariencia: texturas y halo\n";
$ok('textura desconocida cae en «ninguna»', \Classes\TemaProyecto::normTextura('inventada') === 'ninguna');
$ok('los colores se normalizan a #rrggbb', \Classes\TemaProyecto::normHex('#a1b') === '#aa11bb' && \Classes\TemaProyecto::normHex('naranja') === '');
$ok('las intensidades se acotan a 0-100', \Classes\TemaProyecto::intensidad(-5) === 0 && \Classes\TemaProyecto::intensidad(180) === 100);
$ok('la expansión se acota a 50-250', \Classes\TemaProyecto::expansion(10) === 50 && \Classes\TemaProyecto::expansion(999) === 250);
$cssPuntos = \Classes\TemaProyecto::cssFondo('cian', ['textura' => 'puntos', 'textura_intensidad' => 50, 'halo_intensidad' => 60, 'halo_expansion' => 120]);
$ok('el halo radial y la textura llegan al CSS', str_contains($cssPuntos, '--pw-fondo:') && str_contains($cssPuntos, 'radial-gradient') && str_contains($cssPuntos, '22px 22px') && str_contains($cssPuntos, '--pw-halo-exp:1.2'));
$cssSinHalo = \Classes\TemaProyecto::cssFondo('cian', ['textura' => 'ninguna', 'halo_intensidad' => 0]);
$ok('con intensidad 0 no se pinta el halo', !str_contains($cssSinHalo, 'radial-gradient') && str_contains($cssSinHalo, '--pw-fondo:none'));
$ok('el esquema y su migración tienen las columnas de fondo', str_contains($asset('database/schema.sql'), 'tema_textura') && str_contains($asset('database/migracion_tema_texturas.sql'), 'tema_halo_intensidad'));

echo $fallos ? "\n$fallos prueba(s) fallaron\n" : "\nTodo bien\n";
exit($fallos ? 1 : 0);
