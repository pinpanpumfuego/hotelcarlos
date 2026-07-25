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
