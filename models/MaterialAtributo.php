<?php

namespace Model;

/** Norma, certificación o especificación de un material (búsqueda ampliada de normativa) */
class MaterialAtributo extends ActiveRecord
{
    protected static $tabla = 'material_atributos';
    protected static $columnasDB = ['id', 'proyecto_id', 'material_id', 'tipo', 'codigo', 'titulo', 'valor', 'unidad', 'emisor', 'fuente_url', 'notas', 'origen', 'estado', 'creado_por'];
    public const TIPOS = ['norma' => 'Norma', 'certificacion' => 'Certificación', 'especificacion' => 'Especificación'];
    public const ESTADOS = ['propuesta' => 'Propuesta', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada'];

    public $id, $proyecto_id, $material_id, $tipo, $codigo, $titulo, $valor, $unidad, $emisor, $fuente_url, $notas, $origen, $estado, $creado_por;
    public $creado, $actualizado;

    public function __construct($args = [])
    {
        $this->id = $args['id'] ?? null;
        $this->proyecto_id = $args['proyecto_id'] ?? null;
        $this->material_id = $args['material_id'] ?? null;
        $this->tipo = in_array($args['tipo'] ?? '', array_keys(self::TIPOS), true) ? $args['tipo'] : 'norma';
        $this->codigo = mb_substr(trim((string)($args['codigo'] ?? '')), 0, 80);
        $this->titulo = mb_substr(trim((string)($args['titulo'] ?? '')), 0, 200);
        $this->valor = mb_substr(trim((string)($args['valor'] ?? '')), 0, 200);
        $this->unidad = mb_substr(trim((string)($args['unidad'] ?? '')), 0, 30);
        $this->emisor = mb_substr(trim((string)($args['emisor'] ?? '')), 0, 120);
        $this->fuente_url = mb_substr(trim((string)($args['fuente_url'] ?? '')), 0, 500);
        $this->notas = mb_substr(trim((string)($args['notas'] ?? '')), 0, 500);
        $this->origen = in_array($args['origen'] ?? '', ['manual', 'ia', 'documento'], true) ? $args['origen'] : 'manual';
        $this->estado = in_array($args['estado'] ?? '', array_keys(self::ESTADOS), true) ? $args['estado'] : 'aceptada';
        $this->creado_por = mb_substr(trim((string)($args['creado_por'] ?? '')), 0, 150);
    }

    public function validar(): array
    {
        self::$alertas = [];
        if (!$this->material_id) self::$alertas['error'][] = 'El material es obligatorio';
        if ($this->codigo === '' && $this->titulo === '' && $this->valor === '') self::$alertas['error'][] = 'Escribe el código, el nombre o el valor del atributo';
        if ($this->fuente_url !== '' && !filter_var($this->fuente_url, FILTER_VALIDATE_URL)) self::$alertas['error'][] = 'La fuente debe ser una URL válida (http:// o https://)';
        return self::$alertas;
    }

    public static function deMaterial(int $proyectoId, int $materialId, bool $soloAceptados = false): array
    {
        $w = "proyecto_id = $proyectoId AND material_id = $materialId" . ($soloAceptados ? " AND estado = 'aceptada'" : '');
        try {
            return self::consultarSQL("SELECT * FROM material_atributos WHERE $w ORDER BY FIELD(tipo, 'norma', 'certificacion', 'especificacion'), codigo, titulo, id");
        } catch (\Throwable $e) {   // migración pendiente
            return [];
        }
    }

    /** Texto para el perfil de búsqueda: códigos, títulos, valores y emisores de los atributos aceptados */
    public static function textoBusqueda(int $proyectoId, int $materialId): string
    {
        $partes = [];
        foreach (self::deMaterial($proyectoId, $materialId, true) as $a) {
            $partes[] = trim($a->codigo . ' ' . $a->titulo . ' ' . $a->valor . ' ' . $a->unidad . ' ' . $a->emisor);
        }
        return trim(implode(' ', $partes));
    }

    /** Sincroniza los atributos que vienen del formulario del material (edita, crea y quita) */
    public static function sincronizarDesdePost(int $proyectoId, int $materialId, array $post, string $usuario = ''): int
    {
        $ids = (array)($post['atributo_id'] ?? []);
        $tipos = (array)($post['atributo_tipo'] ?? []);
        $codigos = (array)($post['atributo_codigo'] ?? []);
        $titulos = (array)($post['atributo_titulo'] ?? []);
        $valores = (array)($post['atributo_valor'] ?? []);
        $unidades = (array)($post['atributo_unidad'] ?? []);
        $emisores = (array)($post['atributo_emisor'] ?? []);
        $fuentes = (array)($post['atributo_fuente'] ?? []);
        $notas = (array)($post['atributo_notas'] ?? []);
        $quitar = (array)($post['atributo_quitar'] ?? []);
        $n = 0;
        $max = max(count($codigos), count($ids));
        for ($i = 0; $i < $max; $i++) {
            $id = (int)($ids[$i] ?? 0);
            $codigo = (string)($codigos[$i] ?? '');
            $titulo = (string)($titulos[$i] ?? '');
            $valor = (string)($valores[$i] ?? '');
            if ($id && in_array((string)$id, array_map('strval', $quitar), true)) {
                $a = self::find($id);
                if ($a && (int)$a->proyecto_id === $proyectoId && (int)$a->material_id === $materialId) $a->eliminar();
                continue;
            }
            if ($id) {
                $a = self::find($id);
                if (!$a || (int)$a->proyecto_id !== $proyectoId || (int)$a->material_id !== $materialId) continue;
            } else {
                if (trim($codigo) === '' && trim($titulo) === '' && trim($valor) === '') continue;
                $a = new self(['proyecto_id' => $proyectoId, 'material_id' => $materialId, 'origen' => 'manual', 'estado' => 'aceptada', 'creado_por' => $usuario]);
            }
            $a->tipo = $tipos[$i] ?? $a->tipo;
            $a->codigo = $codigo;
            $a->titulo = $titulo;
            $a->valor = $valor;
            $a->unidad = (string)($unidades[$i] ?? '');
            $a->emisor = (string)($emisores[$i] ?? '');
            $a->fuente_url = (string)($fuentes[$i] ?? '');
            $a->notas = (string)($notas[$i] ?? '');
            if ($a->validar()) continue;
            $r = $a->guardar();
            if ($r === false) continue;
            if (is_array($r) && empty($r['resultado']) && !$a->id) continue;
            if (is_array($r) && !empty($r['id'])) $a->id = (int)$r['id'];
            $n++;
        }
        return $n;
    }
}
