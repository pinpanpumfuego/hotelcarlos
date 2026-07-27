<?php

declare(strict_types=1);

namespace App\Libraries\Permisos;

use App\Models\ConfiguracionModel;
use App\Models\RolModel;

/**
 * Responde a «¿esta persona puede hacer esto?».
 *
 * Se consulta en cada petición en lugar de guardarse en la sesión. Es una
 * consulta a una tabla diminuta y se cachea en memoria mientras dura la
 * petición, así que sale prácticamente gratis; a cambio, cuando gerencia le
 * quita un permiso a un perfil, **surte efecto en el acto**. Con los permisos
 * guardados en la sesión, quien ya estuviera dentro seguiría pudiendo hacerlo
 * hasta que cerrara sesión, que es justo lo contrario de lo que se espera al
 * quitarle un permiso a alguien.
 */
class Permisos
{
    /** Permisos ya resueltos en esta petición: rol_id => lista de claves. */
    private array $cache = [];

    /**
     * Traducción de los tres roles antiguos mientras dure la transición.
     *
     * Las 296 rutas siguen usando el filtro `rol:` y la columna `usuarios.rol`.
     * Hasta que se cambien una por una, un usuario sin `rol_id` tiene que
     * seguir trabajando: si no, el día que se aplique la migración se queda
     * medio hotel fuera.
     */
    private const EQUIVALENCIA_ANTIGUA = [
        'gerencia'  => 'gerencia',
        'recepcion' => 'recepcion',
        'limpieza'  => 'housekeeping',
    ];

    /** ¿Puede el usuario de la sesión hacer esto? */
    public function puede(string $clave): bool
    {
        return in_array($clave, $this->mios(), true);
    }

    /** ¿Puede alguna de estas cosas? Para pintar un menú que agrupa varias. */
    public function puedeAlguno(array $claves): bool
    {
        return array_intersect($claves, $this->mios()) !== [];
    }

    public function puedeTodos(array $claves): bool
    {
        return array_diff($claves, $this->mios()) === [];
    }

    /**
     * Permisos del usuario de la sesión.
     *
     * @return list<string>
     */
    public function mios(): array
    {
        $rolId = session()->get('usuario_rol_id');

        if ($rolId !== null) {
            return $this->deRol((int) $rolId);
        }

        // Sin `rol_id`: usuario de antes de la migración, o sesión vieja.
        $rolAntiguo = (string) session()->get('usuario_rol');
        $clave      = self::EQUIVALENCIA_ANTIGUA[$rolAntiguo] ?? null;

        return $clave === null ? [] : Catalogo::permisosDe($clave);
    }

    /**
     * Permisos de un rol concreto.
     *
     * @return list<string>
     */
    public function deRol(int $rolId): array
    {
        if (isset($this->cache[$rolId])) {
            return $this->cache[$rolId];
        }

        $rol = (new RolModel())->find($rolId);

        if ($rol === null) {
            return $this->cache[$rolId] = [];
        }

        // Gerencia lo tiene todo por definición, no por lo que haya en la tabla.
        // Es la salida de emergencia: si alguien vacía sus permisos por error,
        // no se puede quedar nadie fuera de su propio sistema.
        if ($rol['clave'] === Catalogo::ROL_TOTAL) {
            return $this->cache[$rolId] = Catalogo::claves();
        }

        return $this->cache[$rolId] = (new RolModel())->permisos($rolId);
    }

    /**
     * Tope de descuento que puede aplicar el usuario, en porcentaje.
     *
     * `null` significa sin límite. Devuelve `0.0` si no puede descontar nada,
     * que es distinto de no tener límite y conviene no confundir.
     */
    public function topeDescuento(): ?float
    {
        if (! $this->puede('folio.descuento')) {
            return 0.0;
        }

        if ($this->puede('folio.descuento.sintope')) {
            return null;
        }

        $tope = (new ConfiguracionModel())->obtener('descuento_tope', '15');

        return max(0.0, min(100.0, (float) $tope));
    }

    /** ¿Cabe este descuento dentro de lo que puede aplicar? */
    public function descuentoPermitido(float $porcentaje): bool
    {
        $tope = $this->topeDescuento();

        if ($tope === null) {
            return true;
        }

        return $porcentaje <= $tope;
    }

    /** Olvida lo cacheado. Se usa tras cambiar los permisos de un perfil. */
    public function olvidar(): void
    {
        $this->cache = [];
    }
}
