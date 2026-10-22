<?php
// includes/idioma.php — idioma de la interfaz (es / en). El texto original está en español: t('Texto') devuelve su traducción
// si el idioma elegido es inglés y existe en includes/lang/en.php; si no, el mismo texto (las pantallas sin traducir quedan en español).

const IDIOMAS = ['es' => 'Español', 'en' => 'English'];

function idioma_actual(): string
{
    static $i = null;
    if ($i !== null) return $i;
    $c = $_COOKIE['idioma'] ?? ($_SESSION['idioma'] ?? 'es');
    // Un parámetro manipulado (?idioma[]=…) no debe romper la página: solo se aceptan textos
    if (!is_string($c)) $c = 'es';
    return $i = isset(IDIOMAS[$c]) ? $c : 'es';
}

/** Traduce un texto de la interfaz. Los textos compuestos «Sección - Proyecto» se traducen por la primera parte */
function t(string $texto, array $vars = []): string
{
    static $dic = null;
    if (idioma_actual() === 'es') $r = $texto;
    else {
        $dic ??= require __DIR__ . '/lang/en.php';
        if (isset($dic[$texto])) $r = $dic[$texto];
        elseif (str_contains($texto, ' - ') && isset($dic[explode(' - ', $texto, 2)[0]])) { [$a, $b] = explode(' - ', $texto, 2); $r = $dic[$a] . ' - ' . $b; }
        else $r = $texto;
    }
    foreach ($vars as $k => $v) $r = str_replace('{' . $k . '}', (string)$v, $r);
    return $r;
}

function guardar_idioma(string $i): void
{
    $i = isset(IDIOMAS[$i]) ? $i : 'es';
    $_SESSION['idioma'] = $i;
    setcookie('idioma', $i, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax']);
}
