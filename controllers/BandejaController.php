<?php

namespace Controllers;

use MVC\Router;
use Model\Contacto;
use Model\Soporte;
use Model\SoporteRespuesta;
use Model\Usuario;

/** Bandeja de entrada del administrador: mensajes de contacto (público) y de soporte (clientes) */
class BandejaController
{
    private static function modelo(string $tipo): ?string
    {
        return ['contacto' => Contacto::class, 'soporte' => Soporte::class][$tipo] ?? null;
    }

    public static function index(Router $router)
    {
        redirigir_si_no_admin();
        $filtro = in_array($_GET['tipo'] ?? '', ['contacto', 'soporte'], true) ? $_GET['tipo'] : 'todos';
        $items = [];
        if ($filtro !== 'soporte') {
            foreach (Contacto::all('id DESC') as $c) {
                $items[] = ['tipo' => 'contacto', 'id' => $c->id, 'leido' => (bool)$c->leido, 'de' => $c->nombre . ($c->empresa ? ' · ' . $c->empresa : ''),
                    'asunto' => ($c->plan ? $c->plan . ': ' : '') . mb_strimwidth($c->mensaje, 0, 90, '…'), 'fecha' => $c->creado,
                    'titulo' => 'Mensaje de contacto', 'correo' => $c->correo, 'cuerpoTitulo' => 'Mensaje', 'cuerpo' => $c->mensaje,
                    'meta' => array_filter([
                        'Nombre' => $c->nombre, 'Empresa' => $c->empresa, 'Puesto' => $c->puesto, 'Correo' => $c->correo, 'Teléfono' => $c->telefono,
                        'Plan de interés' => $c->plan ?: '—', 'Proyectos simultáneos' => $c->proyectos_aprox ?: '—',
                        'Prefiere contacto por' => \Model\Contacto::PREFERENCIAS[$c->preferencia] ?? $c->preferencia, 'Cómo nos conoció' => $c->origen ?: '—',
                    ], fn($v) => $v !== '' && $v !== null)];
            }
        }
        if ($filtro !== 'contacto') {
            $usuarios = [];
            foreach (Usuario::all('id ASC') as $u) $usuarios[$u->id] = $u;
            $soportes = Soporte::all('id DESC');
            $respuestas = SoporteRespuesta::deSoporte(array_map(fn($x) => (int)$x->id, $soportes));
            foreach ($soportes as $s) {
                $u = $usuarios[$s->usuario_id] ?? null;
                $items[] = ['tipo' => 'soporte', 'id' => $s->id, 'leido' => (bool)$s->leido, 'de' => $u->nombre ?? 'Usuario eliminado',
                    'asunto' => $s->tema, 'fecha' => $s->creado,
                    'titulo' => 'Mensaje de soporte', 'correo' => $u->email ?? '', 'cuerpoTitulo' => 'Información', 'cuerpo' => $s->informacion,
                    'meta' => array_filter(['Usuario' => $u->nombre ?? 'Usuario eliminado', 'Correo' => $u->email ?? '', 'Tema' => $s->tema]),
                    'respuestas' => array_map(fn($r) => $r->aArray(), $respuestas[(int)$s->id] ?? [])];
            }
        }
        usort($items, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
        $router->render('bandeja/index', [
            'titulo' => 'Bandeja de entrada', 'items' => $items, 'filtro' => $filtro,
            'sinLeer' => bandeja_sin_leer(),
        ]);
    }

    public static function ver(Router $router)
    {
        redirigir_si_no_admin();
        $tipo = $_GET['tipo'] ?? '';
        $clase = self::modelo($tipo);
        $m = $clase ? $clase::find(filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$m) {
            header('Location: /bandeja');
            exit;
        }
        if (!$m->leido) {
            $m->leido = 1;
            $m->guardar();
        }
        $remitente = $tipo === 'soporte' ? Usuario::find($m->usuario_id) : null;
        $creado = $m->creado;
        $router->render('bandeja/ver', ['titulo' => 'Mensaje', 'tipo' => $tipo, 'm' => $m, 'remitente' => $remitente, 'creado' => $creado]);
    }

    /** Marcar como leído / no leído o eliminar */
    public static function accion()
    {
        redirigir_si_no_admin();
        $clase = self::modelo($_POST['tipo'] ?? '');
        $m = ($clase && $_SERVER['REQUEST_METHOD'] === 'POST') ? $clase::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)) : null;
        if ($m) {
            switch ($_POST['accion'] ?? '') {
                case 'no_leido': $m->leido = 0; $m->guardar(); break;
                case 'leido': $m->leido = 1; $m->guardar(); break;
                case 'eliminar':
                    if ($m instanceof Soporte) SoporteRespuesta::borrarFotosDeSoporte((int)$m->id);
                    $m->eliminar();
                    break;
            }
        }
        header('Location: /bandeja' . (in_array($_POST['volver'] ?? '', ['contacto', 'soporte'], true) ? '?tipo=' . $_POST['volver'] : ''));
        exit;
    }

    // ---- JSON (modales de la bandeja) ----

    private static function jsonAdmin(): void
    {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_admin()) json_respuesta(['success' => false, 'error' => 'No autorizado'], 403);
    }

    /** Marca un mensaje como leído al abrirlo */
    public static function leer()
    {
        self::jsonAdmin();
        $clase = self::modelo($_POST['tipo'] ?? '');
        $m = $clase ? $clase::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT)) : null;
        if (!$m) json_respuesta(['success' => false, 'error' => 'Mensaje no encontrado'], 404);
        if (!$m->leido) {
            $m->leido = 1;
            $m->guardar();
        }
        json_respuesta(['success' => true, 'sinLeer' => bandeja_sin_leer()]);
    }

    /** Crea (sin id) o edita (con id) una respuesta a un mensaje de soporte, con fotos adjuntas */
    public static function respuestaGuardar()
    {
        self::jsonAdmin();
        $soporte = Soporte::find(filter_var($_POST['soporte_id'] ?? null, FILTER_VALIDATE_INT));
        if (!$soporte) json_respuesta(['success' => false, 'error' => 'Mensaje no encontrado'], 404);

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id) {
            $r = SoporteRespuesta::find($id);
            if (!$r || (int)$r->soporte_id !== (int)$soporte->id) json_respuesta(['success' => false, 'error' => 'Respuesta no encontrada'], 404);
            $r->titulo = trim((string)($_POST['titulo'] ?? ''));       // la fecha original se conserva
            $r->descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $r->visto = 0;   // cambió: el cliente vuelve a recibir el aviso
        } else {
            $r = new SoporteRespuesta(['soporte_id' => $soporte->id, 'titulo' => $_POST['titulo'] ?? '', 'descripcion' => $_POST['descripcion'] ?? '']);
        }
        if ($alertas = $r->validar()) json_respuesta(['success' => false, 'error' => $alertas['error'][0]], 422);

        // Fotos nuevas válidas (se comprueban antes de guardar nada)
        $nuevas = [];
        $f = $_FILES['fotos'] ?? null;
        if ($f && is_array($f['name'])) {
            foreach ($f['name'] as $i => $nombre) {
                if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                if ($f['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) json_respuesta(['success' => false, 'error' => "No se pudo subir «{$nombre}»"], 422);
                if ($f['size'][$i] > SoporteRespuesta::MAX_BYTES_FOTO) json_respuesta(['success' => false, 'error' => "«{$nombre}» pesa más de 8 MB"], 422);
                $nuevas[] = $f['tmp_name'][$i];
            }
        }
        $quitar = array_map('intval', (array)($_POST['quitar_fotos'] ?? []));
        $actuales = $r->id ? array_diff_key($r->fotos(), array_flip($quitar)) : [];
        if (count($actuales) + count($nuevas) > SoporteRespuesta::MAX_FOTOS) json_respuesta(['success' => false, 'error' => 'Máximo ' . SoporteRespuesta::MAX_FOTOS . ' fotos por respuesta'], 422);

        $guardadas = [];
        foreach ($nuevas as $tmp) {
            $arch = \Classes\Imagenes::guardarEn(SoporteRespuesta::CARPETA, $tmp);
            if (!$arch) {
                foreach ($guardadas as $g) \Classes\Imagenes::eliminarEn(SoporteRespuesta::CARPETA, $g);
                json_respuesta(['success' => false, 'error' => 'Solo se permiten imágenes (jpg, png, webp o gif)'], 422);
            }
            $guardadas[] = $arch;
        }

        if ($r->id) {
            $r->guardar();
            foreach ($quitar as $fid) $r->quitarFoto($fid);
        } else {
            $r->id = $r->guardar()['id'];
        }
        foreach ($guardadas as $g) $r->agregarFoto($g);
        if (!$soporte->leido) {   // responder implica haberlo leído
            $soporte->leido = 1;
            $soporte->guardar();
        }
        json_respuesta(['success' => true, 'respuesta' => $r->aArray(), 'sinLeer' => bandeja_sin_leer()]);
    }

    public static function respuestaEliminar()
    {
        self::jsonAdmin();
        $r = SoporteRespuesta::find(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        if (!$r) json_respuesta(['success' => false, 'error' => 'Respuesta no encontrada'], 404);
        $r->eliminarConFotos();
        json_respuesta(['success' => true]);
    }
}
