<?php

namespace App\Controllers;

use App\Models\TipoUnidadModel;

/** Web pública del hotel: accesible sin iniciar sesión. */
class Web extends BaseController
{
    public function inicio()
    {
        return view('web/inicio', [
            'hotel'        => config('Hotel'),
            'tipos'        => (new TipoUnidadModel())->orderBy('tarifa_base')->findAll(),
            'tituloPagina' => 'Cabañas junto al lago en Colombia',
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
            $chips[$t['id']]    = $servicios->fichaDeTipo((int) $t['id']);
        }

        return view('web/alojamientos', [
            'hotel'        => config('Hotel'),
            'tipos'        => $tipos,
            'galerias'     => $galerias,
            'servicios'    => $chips,
            'tituloPagina' => 'Alojamientos',
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
            'experiencias'  => $lista,
            'galerias'      => $galerias,
            'tituloPagina'  => 'Experiencias',
            'paginaActiva'  => 'experiencias',
            'descripcion'   => 'Cabalgatas, paseos en lancha y actividades en la naturaleza durante tu estancia.',
        ]);
    }

    /** Carta pública del restaurante con alérgenos y dietas. */
    public function carta()
    {
        $productos = (new \App\Models\CartaProductoModel())->conCategoria(true);

        $porCategoria = [];
        foreach ($productos as $p) {
            $p['alergenos_lista'] = \App\Models\CartaProductoModel::alergenosDe($p['alergenos'] ?? null);
            $porCategoria[$p['categoria_nombre']][] = $p;
        }

        return view('web/carta', [
            'hotel'        => config('Hotel'),
            'porCategoria' => $porCategoria,
            'dietas'       => \App\Models\CartaProductoModel::DIETAS,
            'alergenos'    => \App\Models\CartaProductoModel::ALERGENOS,
            // La propina se anuncia también aquí: la ley pide informarla en la carta
            'propinaSugerida' => (float) (new \App\Models\ConfiguracionModel())->obtener('tpv_propina_sugerida', '10'),
            'tituloPagina' => 'Restaurante',
            'paginaActiva' => 'carta',
            'descripcion'  => 'Nuestra carta: cocina local con productos de la región. Consulta alérgenos y opciones veganas.',
        ]);
    }

    public function contacto()
    {
        return view('web/contacto', [
            'hotel'        => config('Hotel'),
            'tituloPagina' => 'Contacto',
            'paginaActiva' => 'contacto',
            'descripcion'  => 'Cómo llegar, teléfono, WhatsApp y correo del hotel.',
        ]);
    }
}
