<?php

namespace App\Models;

use App\Libraries\Traductor;
use CodeIgniter\Model;

class TraduccionModel extends Model
{
    protected $table         = 'traducciones';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['tabla', 'registro_id', 'campo', 'idioma', 'texto', 'usuario_id'];
    protected $useTimestamps = true;

    /**
     * Todo lo traducido de una tabla en un idioma, listo para buscar.
     *
     * @return array<int, array<string, string>> id => campo => texto
     */
    public function deTabla(string $tabla, string $idioma): array
    {
        $filas = $this->select('registro_id, campo, texto')
            ->where('tabla', $tabla)
            ->where('idioma', $idioma)
            ->findAll();

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['registro_id']][$f['campo']] = (string) $f['texto'];
        }

        return $mapa;
    }

    /** Guarda (o borra si queda vacío) una traducción concreta. */
    public function guardar(string $tabla, int $registroId, string $campo, string $idioma, string $texto): void
    {
        $texto  = trim($texto);
        $existe = $this->where('tabla', $tabla)->where('registro_id', $registroId)
            ->where('campo', $campo)->where('idioma', $idioma)->first();

        // Vaciar el campo es decir «no está traducido»: se borra la fila en vez
        // de dejar un texto vacío, que contaría como traducido y no lo está
        if ($texto === '') {
            if ($existe !== null) {
                $this->delete($existe['id']);
            }

            return;
        }

        $datos = [
            'tabla' => $tabla, 'registro_id' => $registroId, 'campo' => $campo,
            'idioma' => $idioma, 'texto' => $texto,
            'usuario_id' => session()->get('usuario_id'),
        ];

        if ($existe !== null) {
            $this->update($existe['id'], $datos);

            return;
        }

        $this->insert($datos);
    }

    /** Cuántas traducciones hay por tabla e idioma. */
    public function conteoPorTablaEIdioma(): array
    {
        $filas = $this->select('tabla, idioma, COUNT(*) AS n')
            ->where("texto <> ''")
            ->groupBy(['tabla', 'idioma'])
            ->findAll();

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[$f['tabla']][$f['idioma']] = (int) $f['n'];
        }

        return $mapa;
    }

    /**
     * Cuántos textos originales hay que traducir en una tabla.
     *
     * Solo cuenta los que tienen algo escrito: una descripción vacía en
     * español no es trabajo pendiente, es que no hay nada que decir.
     */
    public function cuantosOriginales(string $tabla, array $campos): int
    {
        $db    = db_connect();
        $total = 0;

        foreach ($campos as $campo) {
            $total += (int) $db->table($tabla)
                ->where("{$campo} IS NOT NULL")
                ->where("TRIM({$campo}) <> ''")
                ->countAllResults();
        }

        return $total;
    }

    /**
     * Las fichas de una tabla con su original y sus traducciones.
     *
     * Es lo que pinta la pantalla del panel: el español a la izquierda para
     * poder copiarlo, y un campo por cada idioma.
     */
    public function paraPanel(string $tabla, string $idioma): array
    {
        if (! isset(Traductor::CAMPOS[$tabla])) {
            return [];
        }

        $campos = Traductor::CAMPOS[$tabla]['campos'];
        $db     = db_connect();

        // `nombre` para saber de qué ficha hablamos, aunque no se traduzca
        $columnas = array_unique(array_merge(['id'], $campos,
            $db->fieldExists('nombre', $tabla) ? ['nombre'] : []));

        $originales = $db->table($tabla)->select(implode(', ', $columnas))
            ->orderBy('id')->get()->getResultArray();

        $traducidas = $this->deTabla($tabla, $idioma);
        $fichas     = [];

        foreach ($originales as $fila) {
            $id     = (int) $fila['id'];
            $textos = [];

            foreach ($campos as $campo) {
                $original = trim((string) ($fila[$campo] ?? ''));
                if ($original === '') {
                    continue;   // nada que traducir
                }
                $textos[] = [
                    'campo'      => $campo,
                    'original'   => $original,
                    'traducido'  => $traducidas[$id][$campo] ?? '',
                ];
            }

            if ($textos !== []) {
                $fichas[] = [
                    'id'      => $id,
                    'titulo'  => $fila['nombre'] ?? ('#' . $id),
                    'textos'  => $textos,
                ];
            }
        }

        return $fichas;
    }
}
