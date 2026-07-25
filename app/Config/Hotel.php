<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Datos de marca y contacto del hotel, usados por la web pública y el panel.
 * Cambia aquí el nombre/teléfonos y se actualiza en todo el sistema.
 */
class Hotel extends BaseConfig
{
    public string $nombre    = 'San Antonio de los Lagos Ecolodge';
    public string $eslogan   = 'Cabañas junto al lago, entre montañas de Colombia';
    public string $telefono  = '+57 300 000 0000';
    public string $whatsapp  = '573000000000'; // solo dígitos, con indicativo del país
    public string $email     = 'reservas@sanantoniodeloslagos.com';
    public string $direccion = '[Municipio y departamento], Colombia';
    public string $dominio   = 'https://sanantoniodeloslagos.com';

    /**
     * Los valores guardados desde el panel (Administración → Datos del hotel)
     * tienen prioridad sobre los de este archivo, que quedan como respaldo.
     */
    public function __construct()
    {
        parent::__construct();

        try {
            $guardados = (new \App\Models\ConfiguracionModel())->grupo('hotel_');
            foreach ($guardados as $clave => $valor) {
                $campo = substr($clave, strlen('hotel_'));
                if ($valor !== null && $valor !== '' && property_exists($this, $campo)) {
                    $this->{$campo} = $valor;
                }
            }
        } catch (\Throwable $e) {
            // Base de datos aún no disponible (instalación o CLI): valores por defecto
        }
    }
}
