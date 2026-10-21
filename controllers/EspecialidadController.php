<?php

namespace Controllers;

use MVC\Router;
use Model\Especialidad;

/** Catálogo de especialidades por proyecto (usado por materiales y submittals) */
class EspecialidadController
{
    /** Vuelve a la lista: al Directorio si el cambio se hizo desde ahí */
    private static function lista(int $proyectoId, string $extra = ''): string
    {
        return (($_POST['volver'] ?? '') === 'directorio' ? '/directorio?tab=especialidades&' : '/especialidades?') . 'proyecto=' . $proyectoId . $extra;
    }

    public static function index(Router $router)
    {
        $proyecto = proyecto_de_peticion(true);
        $router->render('especialidades/index', [
            'titulo' => 'Especialidades - ' . $proyecto->nombre, 'proyecto' => $proyecto,
            'especialidades' => Especialidad::deProyecto((int)$proyecto->id), 'alertas' => isset($_GET['error']) ? ['error' => [substr((string)$_GET['error'], 0, 300)]] : [],
        ]);
    }

    /** Crea (sin id) o renombra (con id) */
    public static function guardar()
    {
        $proyecto = proyecto_de_peticion(true);
        $esp = ($_POST['id'] ?? '') !== '' ? Especialidad::deProyectoPorId((int)$proyecto->id, $_POST['id']) : new Especialidad();
        if ($esp) {
            $anterior = (string)$esp->nombre;
            $esp->nombre = trim(preg_replace('/\s+/', ' ', $_POST['nombre'] ?? ''));
            $esp->proyecto_id = $proyecto->id;
            $errores = $esp->validar();
            if (!$errores) {
                $esp->guardar();
                if ($anterior !== '') $esp->renombrar($anterior);
            } else {
                header('Location: ' . self::lista((int)$proyecto->id, '&error=' . urlencode($errores['error'][0])));
                exit;
            }
        }
        header('Location: ' . self::lista((int)$proyecto->id));
        exit;
    }

    public static function eliminar()
    {
        $proyecto = proyecto_de_peticion(true);
        if ($esp = Especialidad::deProyectoPorId((int)$proyecto->id, $_POST['id'] ?? null)) {
            $uso = Especialidad::deProyecto((int)$proyecto->id);
            foreach ($uso as $u) {
                if ((int)$u->id === (int)$esp->id && ($u->n_materiales || $u->n_submittals)) {
                    $msg = "«{$esp->nombre}» está en uso ({$u->n_materiales} material(es), {$u->n_submittals} submittal(s)). Cámbialos antes de eliminarla.";
                    header('Location: ' . self::lista((int)$proyecto->id, '&error=' . urlencode($msg)));
                    exit;
                }
            }
            $esp->eliminar();
        }
        header('Location: ' . self::lista((int)$proyecto->id));
        exit;
    }
}
