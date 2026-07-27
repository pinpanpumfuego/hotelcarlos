<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * Quién puede hacer qué.
     *
     * Compartido a propósito: así la caché de permisos vive una sola vez por
     * petición y consultar `puede()` cincuenta veces al pintar un menú cuesta
     * una consulta, no cincuenta.
     */
    public static function permisos(bool $getShared = true): \App\Libraries\Permisos\Permisos
    {
        if ($getShared) {
            return static::getSharedInstance('permisos');
        }

        return new \App\Libraries\Permisos\Permisos();
    }

    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
