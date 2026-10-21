<?php

namespace Controllers;

use MVC\Router;
use Classes\Catalogo;
use Model\Especialidad;
use Model\Personal;
use Model\PersonalContratista;

/** Directorio (Administración): personal, departamentos, categorías y especialidades del proyecto en un solo lugar; las demás herramientas leen de aquí */
class DirectorioController
{
    public const TABS = [
        'personal' => ['Personal del proyecto', 'fa-users'],
        'contratista' => ['Personal del contratista', 'fa-helmet-safety'],
        'departamentos' => ['Departamentos', 'fa-sitemap'],
        'categorias' => ['Categorías', 'fa-tags'],
        'especialidades' => ['Especialidades', 'fa-screwdriver-wrench'],
    ];

    private static function tab(): string
    {
        $t = (string)($_REQUEST['tab'] ?? '');
        return isset(self::TABS[$t]) ? $t : 'personal';
    }

    private static function volver(int $pid, string $tab, string $clave = '', string $msg = ''): void
    {
        header('Location: /directorio?proyecto=' . $pid . '&tab=' . $tab . ($clave ? '&' . $clave . '=' . urlencode($msg) : ''));
        exit;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $tab = self::tab();
        $datos = [];
        if ($tab === 'personal') { $datos['personal'] = Personal::deProyecto($pid); $datos['contactos'] = Personal::contactosDeProyecto($pid); }
        elseif ($tab === 'contratista') $datos['contratista'] = PersonalContratista::deProyecto($pid);
        elseif ($tab === 'departamentos') $datos['departamentos'] = Catalogo::departamentosConUso($pid);
        elseif ($tab === 'categorias') {
            foreach (Catalogo::AMBITOS as $a => $def) $datos['categorias'][$a] = ['titulo' => $def['titulo'], 'lista' => Catalogo::categoriasConUso($pid, $a, $a === 'punch' ? \Classes\Punch::categoriasSugeridas() : [])];
        } else $datos['especialidades'] = Especialidad::deProyecto($pid);
        $conteos = [
            'personal' => count($datos['personal'] ?? Personal::deProyecto($pid)),
            'contratista' => count($datos['contratista'] ?? PersonalContratista::deProyecto($pid)),
            'departamentos' => count($datos['departamentos'] ?? Catalogo::departamentos($pid)),
        ];
        $router->render('directorio/index', [
            'titulo' => 'Directorio - ' . $proyecto->nombre, 'proyecto' => $proyecto, 'tab' => $tab, 'datos' => $datos, 'conteos' => $conteos,
            'ok' => isset($_GET['ok']) ? substr((string)$_GET['ok'], 0, 200) : '', 'error' => isset($_GET['error']) ? substr((string)$_GET['error'], 0, 300) : '',
        ]);
    }

    /** Agrega (sin «anterior») o renombra (con «anterior») un departamento en todas las herramientas */
    public static function departamentoGuardar()
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $nuevo = Catalogo::limpiar((string)($_POST['nombre'] ?? ''));
        $anterior = (string)($_POST['anterior'] ?? '');
        if ($nuevo === '') self::volver($pid, 'departamentos', 'error', 'Escribe el nombre del departamento');
        if ($anterior === '') {
            Catalogo::agregarDepartamento($pid, $nuevo);
            self::volver($pid, 'departamentos', 'ok', "Departamento «{$nuevo}» agregado");
        }
        $n = Catalogo::renombrarDepartamento($pid, $anterior, $nuevo);
        self::volver($pid, 'departamentos', 'ok', $n ? "«{$anterior}» ahora es «{$nuevo}» ($n registro(s) actualizados en las herramientas)" : "Departamento renombrado a «{$nuevo}»");
    }

    public static function departamentoEliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $nombre = (string)($_POST['nombre'] ?? '');
        $ok = Catalogo::eliminarDepartamento($pid, $nombre);
        self::volver($pid, 'departamentos', $ok ? 'ok' : 'error', $ok ? 'Departamento eliminado' : "«{$nombre}» está en uso. Cámbialo o renómbralo (al renombrarlo a otro existente se unen).");
    }

    public static function categoriaGuardar()
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $ambito = (string)($_POST['ambito'] ?? '');
        if (!isset(Catalogo::AMBITOS[$ambito])) self::volver($pid, 'categorias', 'error', 'Ámbito de categoría no válido');
        $nuevo = Catalogo::limpiar((string)($_POST['nombre'] ?? ''));
        $anterior = (string)($_POST['anterior'] ?? '');
        if ($nuevo === '') self::volver($pid, 'categorias', 'error', 'Escribe el nombre de la categoría');
        if ($anterior === '') {
            Catalogo::agregarCategoria($pid, $ambito, $nuevo);
            self::volver($pid, 'categorias', 'ok', "Categoría «{$nuevo}» agregada");
        }
        $n = Catalogo::renombrarCategoria($pid, $ambito, $anterior, $nuevo);
        self::volver($pid, 'categorias', 'ok', $n ? "«{$anterior}» ahora es «{$nuevo}» ($n registro(s) actualizados)" : "Categoría renombrada a «{$nuevo}»");
    }

    public static function categoriaEliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        $pid = (int)$proyecto->id;
        $ambito = (string)($_POST['ambito'] ?? '');
        $nombre = (string)($_POST['nombre'] ?? '');
        $ok = Catalogo::eliminarCategoria($pid, $ambito, $nombre);
        self::volver($pid, 'categorias', $ok ? 'ok' : 'error', $ok ? 'Categoría eliminada' : "«{$nombre}» está en uso. Cámbiala antes de eliminarla.");
    }
}
