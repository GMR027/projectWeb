<?php
// Pruebas rápidas de las piezas sin base de datos: php tests/run.php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/idioma.php';
require __DIR__ . '/../includes/funciones.php';

use Classes\SvgSeguro;
use Classes\Exportadores\OdpExporter;
use Model\Grafico;
use Model\Cajetin;

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

echo "Traductor\n";
$ok('solo español e inglés', Classes\Traductor::idiomaValido('es') && Classes\Traductor::idiomaValido('en') && !Classes\Traductor::idiomaValido('fr'));
$ok('textos sin letras no llaman al motor', Classes\Traductor::traducir(['123', ' ', '-'], 'es', 'en') === ['123', ' ', '-'] || !Classes\Traductor::configurado());

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

echo $fallos ? "\n$fallos prueba(s) fallaron\n" : "\nTodo bien\n";
exit($fallos ? 1 : 0);
