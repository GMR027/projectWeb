<?php
/**
 * Navegación global (Inicio, Proyectos, Portafolio, Usuarios…) y datos comunes.
 * Fuente única del menú lateral, la barra de contexto de las páginas sin proyecto
 * y los accesos de la cuenta. Se calcula una vez por página en views/layout.php.
 */
require_once __DIR__ . '/herramientas-proyecto.php';

/**
 * @return array<int, array{url:string,icon:string,label:string,corto:string,match:string,key:string,ayuda:string,manual:string,badge?:int}>
 */
function menu_global_nav(): array
{
    $rol = $_SESSION['rol'] ?? 'visitante';
    $ayudas = ayudas_herramientas();
    $item = static function (string $url, string $icon, string $label, string $corto, string $match, array $extra = []) use ($ayudas): array {
        return array_merge([
            'url' => $url,
            'icon' => $icon,
            'label' => $label,
            'corto' => $corto,
            'match' => $match,
            'key' => 'g:' . ltrim($match, '/'),
            'ayuda' => $ayudas[$match][0] ?? '',
            'manual' => $ayudas[$match][1] ?? '',
        ], $extra);
    };

    $menu = [
        $item('/dashboard', 'fa-house', 'Inicio', 'Inicio', '/dashboard'),
        $item('/proyectos', 'fa-building', 'Proyectos', 'Proyectos', '/proyectos'),
    ];

    if ($rol === 'admin') {
        $menu[] = $item('/portafolio', 'fa-table-columns', 'Tablero de portafolio', 'Portafolio', '/portafolio');
        $menu[] = $item('/usuarios', 'fa-users-gear', 'Usuarios y accesos', 'Usuarios', '/usuarios');
        if (\Model\Organizacion::disponible()) {
            $menu[] = $item('/organizaciones', 'fa-building-user', 'Organizaciones y planes', 'Organizaciones', '/organizaciones');
        }
        $menu[] = $item('/bandeja', 'fa-inbox', 'Bandeja de entrada', 'Bandeja', '/bandeja', ['badge' => bandeja_sin_leer()]);
        $menu[] = $item('/sistema', 'fa-gear', 'Ajustes de app', 'Sistema', '/sistema');
    } elseif ($rol === 'cliente') {
        $menu[1]['label'] = 'Mis Proyectos';
        $menu[] = $item('/portafolio', 'fa-table-columns', 'Tablero de portafolio', 'Portafolio', '/portafolio');
        $menu[] = $item('/usuarios', 'fa-user-group', 'Mi equipo', 'Mi equipo', '/usuarios');
        $menu[] = $item('/soporte', 'fa-headset', 'Soporte', 'Soporte', '/soporte', ['badge' => soporte_respuestas_nuevas()]);
    }

    return $menu;
}
