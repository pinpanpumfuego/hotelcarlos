<?php

namespace App\Controllers;

use App\Libraries\Traductor;
use App\Models\TipoUnidadModel;

/**
 * Web pública del hotel: accesible sin iniciar sesión.
 *
 * Es la única parte del sistema traducida (español, inglés, francés y alemán).
 * El panel, el TPV, la cocina y el portal del empleado siguen en español: los
 * usa el equipo del hotel, no los clientes.
 *
 * Los textos de la interfaz salen de `app/Language`; los que escribe el hotel
 * (cabañas, experiencias, carta) pasan por el `Traductor`, que devuelve el
 * español cuando falta la traducción.
 */
class Web extends BaseController
{
    private Traductor $t;

    public function __construct()
    {
        $this->t = new Traductor();
    }

    public function inicio()
    {
        $tipos = (new TipoUnidadModel())->orderBy('tarifa_base')->findAll();

        return view('web/inicio', [
            'hotel'        => config('Hotel'),
            'tipos'        => $this->t->filas('tipos_unidad', $tipos, ['nombre', 'descripcion']),
            'tituloPagina' => lang('Web.refugio'),
            'paginaActiva' => 'inicio',
        ]);
    }

    public function alojamientos()
    {
        $tipos     = (new TipoUnidadModel())->orderBy('tarifa_base')->findAll();
        $medios    = new \App\Models\MedioModel();
        $servicios = new \App\Models\ServicioModel();

        // Galería y servicios de cada tipo, para no consultarlos dentro de la vista.
        // La galería junta lo común del tipo con las fotos propias de sus cabañas.
        $unidades  = (new \App\Models\UnidadModel())->orderBy('orden')->orderBy('nombre')->findAll();
        $porCabana = $medios->publicasDeUnidades(array_map('intval', array_column($unidades, 'id')));

        $galerias = [];
        $chips    = [];
        foreach ($tipos as $t) {
            $galeria = $medios->deTipo((int) $t['id']);

            foreach ($unidades as $u) {
                if ((int) $u['tipo_id'] !== (int) $t['id']) {
                    continue;
                }
                foreach ($porCabana[(int) $u['id']] ?? [] as $m) {
                    // Se etiqueta con el nombre de la cabaña: el huésped sabe qué mira
                    $m['alt'] = trim(($m['alt'] ?? '') !== '' ? $u['nombre'] . ' · ' . $m['alt'] : $u['nombre']);
                    $galeria[] = $m;
                }
            }

            $galerias[$t['id']] = $galeria;
            // Los servicios son etiquetas cortas («Wifi», «Chimenea») y también
            // se traducen: son de lo primero que mira quien elige alojamiento
            $chips[$t['id']]    = $this->t->filas(
                'servicios',
                $servicios->fichaDeTipo((int) $t['id']),
                ['nombre']
            );
        }

        return view('web/alojamientos', [
            'hotel'        => config('Hotel'),
            'tipos'        => $this->t->filas('tipos_unidad', $tipos, ['nombre', 'descripcion']),
            'galerias'     => $galerias,
            'servicios'    => $chips,
            'tituloPagina' => lang('Web.alojamientos'),
            'paginaActiva' => 'alojamientos',
            'descripcion'  => 'Habitaciones, cabañas y glamping. Tarifas por noche y capacidad.',
        ]);
    }

    /** Escaparate de experiencias y actividades. */
    public function experiencias()
    {
        $lista  = (new \App\Models\ExperienciaModel())->publicas();
        $medios = new \App\Models\MedioModel();

        $galerias = [];
        foreach ($lista as $e) {
            $galerias[$e['id']] = $medios->deExperiencia((int) $e['id']);
        }

        return view('web/experiencias', [
            'hotel'         => config('Hotel'),
            'experiencias'  => $this->t->filas('experiencias', $lista, ['nombre', 'descripcion', 'incluye']),
            'galerias'      => $galerias,
            'tituloPagina'  => lang('Web.experiencias'),
            'paginaActiva'  => 'experiencias',
            'descripcion'   => 'Cabalgatas, paseos en lancha y actividades en la naturaleza durante tu estancia.',
        ]);
    }

    /** Carta pública del restaurante con alérgenos y dietas. */
    public function carta()
    {
        $productos = (new \App\Models\CartaProductoModel())->conCategoria(true);

        // Solo la descripción: el nombre del plato se queda como está.
        // «Sancocho de gallina» es un nombre propio, no una frase; traducirlo
        // da resultados ridículos y le quita al plato justo lo que lo hace
        // interesante para un extranjero. Se explica al lado, que es lo útil.
        $productos = $this->t->filas('carta_productos', $productos, ['descripcion']);

        $categorias = $this->t->filas(
            'carta_categorias',
            (new \App\Models\CartaCategoriaModel())->ordenadas(),
            ['nombre']
        );
        $nombreCategoria = array_column($categorias, 'nombre', 'id');

        $porCategoria = [];
        foreach ($productos as $p) {
            $p['alergenos_lista'] = \App\Models\CartaProductoModel::alergenosDe($p['alergenos'] ?? null);
            $titulo = $nombreCategoria[$p['categoria_id']] ?? $p['categoria_nombre'];
            $porCategoria[$titulo][] = $p;
        }

        return view('web/carta', [
            'hotel'        => config('Hotel'),
            'porCategoria' => $porCategoria,
            'dietas'       => \App\Models\CartaProductoModel::DIETAS,
            'alergenos'    => \App\Models\CartaProductoModel::ALERGENOS,
            // La propina se anuncia también aquí: la ley pide informarla en la carta
            'propinaSugerida' => (float) (new \App\Models\ConfiguracionModel())->obtener('tpv_propina_sugerida', '10'),
            'tituloPagina' => lang('Web.restaurante'),
            'paginaActiva' => 'carta',
            'descripcion'  => 'Nuestra carta: cocina local con productos de la región. Consulta alérgenos y opciones veganas.',
        ]);
    }

    public function contacto()
    {
        return view('web/contacto', [
            'hotel'        => config('Hotel'),
            'tituloPagina' => lang('Web.contacto'),
            'paginaActiva' => 'contacto',
            'descripcion'  => 'Cómo llegar, teléfono, WhatsApp y correo del hotel.',
        ]);
    }
}
