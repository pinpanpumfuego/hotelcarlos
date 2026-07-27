<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // `web` trae url_web() e idiomas_web(), que usan tanto las vistas
        // públicas como la pantalla de traducciones del panel. Se carga aquí y
        // no en la plantilla porque en CodeIgniter la vista hija se ejecuta
        // **antes** que el layout: cargarlo allí llegaba tarde.
        // Se cargan aquí y no en el layout porque en CodeIgniter la vista hija
        // se ejecuta **antes** que el layout: si `puede()` se cargara allí, la
        // primera vista que lo usara reventaría con «función no definida».
        $this->helpers = ['web', 'permisos'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }
}
