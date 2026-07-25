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
        return view('web/alojamientos', [
            'hotel'        => config('Hotel'),
            'tipos'        => (new TipoUnidadModel())->orderBy('tarifa_base')->findAll(),
            'tituloPagina' => 'Alojamientos',
            'paginaActiva' => 'alojamientos',
            'descripcion'  => 'Habitaciones, cabañas y glamping. Tarifas por noche y capacidad.',
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
