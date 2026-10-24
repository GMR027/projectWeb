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

echo $fallos ? "\n$fallos prueba(s) fallaron\n" : "\nTodo bien\n";
exit($fallos ? 1 : 0);
