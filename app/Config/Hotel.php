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
}
