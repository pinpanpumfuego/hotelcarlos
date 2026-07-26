<?php

namespace App\Models;

use CodeIgniter\Model;

class ComandaLineaModel extends Model
{
    protected $table         = 'comanda_lineas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['comanda_id', 'producto_id', 'nombre_producto', 'destino', 'precio_unitario',
        'cantidad', 'entregado', 'servido', 'listo_en', 'enviado_cocina', 'notas',
        'composicion', 'empleado_id'];
    protected $useTimestamps = true;

    public function deComanda(int $comandaId): array
    {
        return $this->where('comanda_id', $comandaId)
            ->orderBy('id')
            ->findAll();
    }

    /**
     * Igual, pero diciendo quién metió cada línea.
     *
     * Hace falta cuando varios camareros tocan la misma mesa en un turno: sin
     * esto, el que cobra no sabe a quién preguntar por un plato raro. El join
     * es `left` porque una línea puede no tener empleado (el TPV compartido
     * puede estar apagado).
     */
    public function deComandaConCamarero(int $comandaId): array
    {
        return $this->select('comanda_lineas.*,
                              TRIM(CONCAT(empleados.nombre, " ", COALESCE(empleados.apellidos, ""))) AS camarero')
            ->join('empleados', 'empleados.id = comanda_lineas.empleado_id', 'left')
            ->where('comanda_lineas.comanda_id', $comandaId)
            ->orderBy('comanda_lineas.id')
            ->findAll();
    }

    /** Dónde se prepara cada cosa, para hablarle claro al camarero. */
    public const DESTINOS = [
        'cocina'  => 'Cocina',
        'barra'   => 'Barra',
        'directo' => 'Entrega directa',
    ];

    /**
     * Manda a preparación lo que aún no se ha enviado, cada cosa a su sitio.
     *
     * Vive aquí y no en un controlador porque hay dos puertas de entrada: el
     * TPV de la barra y el comandero del móvil. Estaba duplicado y el móvil se
     * dejaba el caso de `directo`, así que un agua pedida desde el teléfono se
     * quedaba esperando en una cocina que nunca la iba a sacar.
     *
     * Lo de destino `directo` (una botella, una bolsa de papas) no se prepara:
     * se coge y se entrega, así que se marca servido en el acto.
     *
     * @return array<string,int> cuántas líneas fueron a cada destino
     */
    public function enviarAPreparacion(int $comandaId): array
    {
        $nuevas = $this->where('comanda_id', $comandaId)->where('enviado_cocina', 0)->findAll();
        $ahora  = date('Y-m-d H:i:s');
        $conteo = ['cocina' => 0, 'barra' => 0, 'directo' => 0];

        foreach ($nuevas as $linea) {
            $destino = $linea['destino'] ?? 'cocina';
            $conteo[$destino] = ($conteo[$destino] ?? 0) + 1;

            $this->update($linea['id'], $destino === 'directo'
                ? ['enviado_cocina' => 1, 'entregado' => 1, 'servido' => 1, 'listo_en' => $ahora]
                : ['enviado_cocina' => 1]);
        }

        return $conteo;
    }

    /**
     * Pendientes de una zona de preparación ('cocina' o 'barra'):
     * solo lo que el mesero ya envió y aún no está listo.
     * El reloj cuenta desde el envío, no desde que se abrió la comanda.
     */
    public function pendientesDeZona(string $zona): array
    {
        return $this->select('comanda_lineas.*, comandas.numero, comandas.mesa,
                              comandas.cliente_nombre, unidades.nombre AS unidad_nombre,
                              comanda_lineas.updated_at AS enviado_en')
            ->join('comandas', 'comandas.id = comanda_lineas.comanda_id')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.estado', 'abierta')
            ->where('comanda_lineas.enviado_cocina', 1)
            ->where('comanda_lineas.entregado', 0)
            ->where('comanda_lineas.destino', $zona)
            ->orderBy('comanda_lineas.updated_at')
            ->orderBy('comanda_lineas.id')
            ->findAll();
    }

    /** Compatibilidad: pendientes de cocina y barra juntos. */
    public function pendientesCocina(): array
    {
        return $this->select('comanda_lineas.*, comandas.numero, comandas.mesa,
                              comandas.cliente_nombre, unidades.nombre AS unidad_nombre,
                              comanda_lineas.updated_at AS enviado_en')
            ->join('comandas', 'comandas.id = comanda_lineas.comanda_id')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.estado', 'abierta')
            ->where('comanda_lineas.enviado_cocina', 1)
            ->where('comanda_lineas.entregado', 0)
            ->whereIn('comanda_lineas.destino', ['cocina', 'barra'])
            ->orderBy('comanda_lineas.updated_at')
            ->orderBy('comanda_lineas.id')
            ->findAll();
    }

    /**
     * Qué platos están listos y en qué mesa.
     *
     * El contador suelto no le sirve al camarero: «2 platos listos» no dice a
     * dónde ir. Esto sí, y por eso lo usa el comandero del móvil.
     */
    public function listosDetalle(): array
    {
        return $this->select('comanda_lineas.id, comanda_lineas.nombre_producto,
                              comanda_lineas.cantidad, comanda_lineas.destino,
                              comanda_lineas.listo_en, comandas.id AS comanda_id,
                              comandas.numero, comandas.mesa, unidades.nombre AS unidad_nombre')
            ->join('comandas', 'comandas.id = comanda_lineas.comanda_id')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.estado', 'abierta')
            ->where('comanda_lineas.entregado', 1)
            ->where('comanda_lineas.servido', 0)
            ->orderBy('comanda_lineas.listo_en')
            ->findAll();
    }

    /** Cuántos platos hay listos en cocina esperando a ser servidos. */
    public function listosParaServir(): int
    {
        return $this->join('comandas', 'comandas.id = comanda_lineas.comanda_id')
            ->where('comandas.estado', 'abierta')
            ->where('comanda_lineas.entregado', 1)
            ->where('comanda_lineas.servido', 0)
            ->countAllResults();
    }
}
