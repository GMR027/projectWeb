<?php

namespace Controllers;

use Classes\PorDondeEmpezar;
use Model\Archivo;
use Model\CalendarioFestivo;
use Model\Material;
use Model\Partida;
use Model\Personal;

/** API de «Por dónde empezar»: captura rápida desde el modal. Guarda con los mismos modelos que cada herramienta */
class EmpezarController
{
    private static function fallo(array $alertas): void
    {
        $msgs = [];
        foreach ($alertas as $lista) foreach ((array)$lista as $m) $msgs[] = $m;
        json_respuesta(['success' => false, 'error' => implode(' · ', $msgs) ?: 'No se pudo guardar'], 422);
    }

    private static function ok(\Model\Proyecto $p, string $mensaje): void
    {
        $pasos = PorDondeEmpezar::pasos((int)$p->id, is_admin());
        json_respuesta(['success' => true, 'mensaje' => $mensaje, 'pasos' => $pasos, 'avance' => PorDondeEmpezar::avance($pasos)]);
    }

    public static function estado()
    {
        $p = proyecto_api('gestionar');
        $pasos = PorDondeEmpezar::pasos((int)$p->id, is_admin());
        json_respuesta(['success' => true, 'pasos' => $pasos, 'avance' => PorDondeEmpezar::avance($pasos)]);
    }

    public static function festivo()
    {
        $p = proyecto_api('gestionar');
        $fecha = (string)($_POST['fecha'] ?? '');
        $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 150);
        $f = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$f || $f->format('Y-m-d') !== $fecha || $motivo === '') self::fallo(['error' => ['Indica una fecha válida y el motivo']]);
        foreach (CalendarioFestivo::whereAll('proyecto_id', $p->id) as $x) if ($x->fecha === $fecha) self::fallo(['error' => ['Esa fecha ya está registrada']]);
        (new CalendarioFestivo(['proyecto_id' => $p->id, 'fecha' => $fecha, 'motivo' => $motivo, 'tipo' => 'festivo']))->guardar();
        self::ok($p, 'Festivo agregado');
    }

    public static function partida()
    {
        $p = proyecto_api('gestionar');
        $partida = new Partida(['proyecto_id' => $p->id, 'codigo' => mb_substr(trim($_POST['codigo'] ?? ''), 0, 20), 'nombre' => mb_substr(trim($_POST['nombre'] ?? ''), 0, 200)]);
        $alertas = $partida->validar();
        foreach (Partida::whereAll('proyecto_id', $p->id) as $x) if ($x->codigo === $partida->codigo) $alertas['error'][] = 'El código ya existe en este proyecto';
        if ($alertas) self::fallo($alertas);
        $partida->orden = count(Partida::whereAll('proyecto_id', $p->id)) + 1;
        $partida->guardar();
        self::ok($p, 'Partida creada. Agrega sus actividades desde el Programa');
    }

    public static function persona()
    {
        $p = proyecto_api('gestionar');
        $x = new Personal(['proyecto_id' => $p->id, 'nombre' => trim($_POST['nombre'] ?? ''), 'departamento' => mb_substr(trim($_POST['departamento'] ?? ''), 0, 100)]);
        if ($alertas = $x->validar()) self::fallo($alertas);
        $x->guardar();
        self::ok($p, 'Persona agregada');
    }

    public static function material()
    {
        $p = proyecto_api('gestionar');
        $m = new Material(['proyecto_id' => $p->id]);
        $m->nombre_base = mb_substr(trim($_POST['nombre'] ?? ''), 0, 255);
        $m->armarNombre();
        $m->especialidad = \Model\Especialidad::resolver((int)$p->id, $_POST['especialidad'] ?? '');
        $cant = str_replace(',', '.', trim($_POST['cantidad'] ?? ''));
        $m->cantidad = $cant === '' ? null : (is_numeric($cant) ? $cant : 'x');
        $m->unidad = mb_substr(trim($_POST['unidad'] ?? ''), 0, 30);
        $m->compra = 'pendiente';
        if ($alertas = $m->validar()) self::fallo($alertas);
        $m->guardar();
        self::ok($p, 'Material agregado');
    }

    public static function contrato()
    {
        $p = proyecto_api('gestionar');
        $tipo = in_array($_POST['tipo'] ?? '', ['inicio', 'fin', 'hito'], true) ? $_POST['tipo'] : 'hito';
        $fecha = (string)($_POST['fecha'] ?? '');
        $f = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$f || $f->format('Y-m-d') !== $fecha) self::fallo(['error' => ['Indica una fecha válida']]);
        $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 200) ?: ['inicio' => 'Inicio de contrato', 'fin' => 'Fin de contrato', 'hito' => 'Hito'][$tipo];
        $penal = mb_substr(trim($_POST['penalizacion'] ?? ''), 0, 200);
        $db = $GLOBALS['db'];
        $ok = $db->query(sprintf("INSERT INTO proyecto_fechas_contrato (proyecto_id, tipo, nombre, fecha, penalizacion, origen) VALUES (%d, '%s', '%s', '%s', '%s', 'Por dónde empezar')",
            (int)$p->id, $tipo, $db->real_escape_string($nombre), $fecha, $db->real_escape_string($penal)));
        if (!$ok) self::fallo(['error' => ['No se pudo guardar la fecha']]);
        self::ok($p, 'Fecha de contrato agregada');
    }

    public static function documento()
    {
        $p = proyecto_api('gestionar');
        $f = $_FILES['archivo'] ?? null;
        if (!$f || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) self::fallo(['error' => ['Selecciona el archivo a subir']]);
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $a = new Archivo(['proyecto_id' => $p->id, 'fecha' => date('Y-m-d'), 'nombre' => mb_substr(trim($_POST['nombre'] ?? '') ?: pathinfo($f['name'], PATHINFO_FILENAME), 0, 200)]);
        $alertas = $a->validar();
        if (!in_array($ext, Archivo::EXTENSIONES, true)) $alertas['error'][] = 'Tipo de archivo no permitido (.' . $ext . ')';
        elseif ($f['size'] > Archivo::MAX_BYTES) $alertas['error'][] = 'El archivo supera los 100 MB';
        elseif (!\Classes\Almacenamiento::cabe((int)$p->id, (int)$f['size'])) $alertas['error'][] = \Classes\Almacenamiento::mensajeLleno((int)$p->id);
        else foreach (\Classes\EscanerArchivos::revisar($f['tmp_name'], $f['name']) as $motivo) $alertas['error'][] = $motivo;
        if ($alertas) self::fallo($alertas);
        $guardado = Archivo::guardarArchivoSubido((int)$p->id, $f['tmp_name'], $ext);
        if (!$guardado) self::fallo(['error' => ['No se pudo guardar el archivo en el servidor']]);
        $a->archivo_guardado = $guardado;
        $a->archivo_original = mb_substr(basename($f['name']), 0, 255);
        $a->tamano = (int)$f['size'];
        $a->guardar();
        self::ok($p, 'Archivo subido');
    }
}
