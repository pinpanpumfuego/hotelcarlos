<?php

namespace App\Controllers;

use App\Libraries\Traductor;
use App\Models\TraduccionModel;

/**
 * Pantalla para traducir el contenido de la web.
 *
 * Una sola pantalla en vez de meter campos de idioma en las siete fichas
 * (cabañas, servicios, carta…). Dos motivos: tocar siete formularios es mucho
 * más trabajo y más frágil, y sobre todo **quien traduce se sienta a traducir**
 * en tandas, no plato a plato mientras hace otra cosa.
 *
 * El español se ve al lado, para poder copiarlo y no tener que ir y volver.
 */
class Traducciones extends BaseController
{
    private TraduccionModel $modelo;

    public function __construct()
    {
        $this->modelo = new TraduccionModel();
    }

    public function index()
    {
        $idioma = Traductor::valido($this->request->getGet('idioma') ?? 'en');
        $tabla  = (string) ($this->request->getGet('tabla') ?? array_key_first(Traductor::CAMPOS));

        if (! isset(Traductor::CAMPOS[$tabla])) {
            $tabla = array_key_first(Traductor::CAMPOS);
        }

        // El original no se traduce a sí mismo
        if ($idioma === Traductor::ORIGINAL) {
            $idioma = 'en';
        }

        return view('traducciones/index', [
            'titulo'   => 'Traducciones',
            'seccion'  => 'traducciones',
            'idiomas'  => Traductor::IDIOMAS,
            'idioma'   => $idioma,
            'grupos'   => Traductor::CAMPOS,
            'tabla'    => $tabla,
            'fichas'   => $this->modelo->paraPanel($tabla, $idioma),
            'avance'   => (new Traductor())->avance(),
        ]);
    }

    public function guardar()
    {
        $idioma = Traductor::valido($this->request->getPost('idioma'));
        $tabla  = (string) $this->request->getPost('tabla');

        if ($idioma === Traductor::ORIGINAL || ! isset(Traductor::CAMPOS[$tabla])) {
            return redirect()->to('traducciones')->with('error', 'No se pudo guardar: idioma o sección no válidos.');
        }

        // textos[registroId][campo] = traducción
        $textos   = (array) ($this->request->getPost('textos') ?? []);
        $camposOk = Traductor::CAMPOS[$tabla]['campos'];
        $guardados = 0;

        foreach ($textos as $registroId => $campos) {
            foreach ((array) $campos as $campo => $texto) {
                // Solo los campos que esta tabla declara traducibles: si no,
                // un formulario manipulado podría escribir cualquier cosa
                if (! in_array($campo, $camposOk, true)) {
                    continue;
                }
                $this->modelo->guardar($tabla, (int) $registroId, $campo, $idioma, (string) $texto);
                $guardados++;
            }
        }

        return redirect()->to('traducciones?idioma=' . $idioma . '&tabla=' . $tabla)
            ->with('ok', $guardados . ($guardados === 1 ? ' texto guardado.' : ' textos guardados.'));
    }
}
