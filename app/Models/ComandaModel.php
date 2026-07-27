<?php

namespace App\Models;

use CodeIgniter\Model;

class ComandaModel extends Model
{
    protected $table         = 'comandas';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'numero', 'mesa', 'mesa_id', 'comensales', 'reserva_id', 'estado', 'total',
        'cliente_nombre', 'cliente_documento', 'cliente_telefono',
        'descuento', 'motivo_descuento', 'cupon_id', 'propina', 'forma_pago', 'recibido', 'cambio',
        'usuario_id', 'empleado_id', 'autorizo_id', 'liquidacion_id', 'cerrada_en', 'notas',
    ];
    protected $useTimestamps = true;

    public const FORMAS_PAGO = [
        'efectivo'      => 'Efectivo',
        'tarjeta'       => 'Tarjeta',
        'transferencia' => 'Transferencia',
        'wompi'         => 'Wompi',
        'habitacion'    => 'Cargar a la cabaña',
        'bono'          => 'Bono regalo',
    ];

    /**
     * Qué ha hecho cada camarero en un periodo.
     *
     * Se mira quién abrió la comanda, no quién cobró: es quien atendió la mesa
     * y de quien depende la propina.
     */
    public function porCamarero(string $desde, string $hasta): array
    {
        $filas = $this->select('empleados.id, empleados.nombre, empleados.apellidos, empleados.rol_tpv,
                                COUNT(*) AS comandas,
                                SUM(CASE WHEN comandas.estado = "cobrada" THEN comandas.total ELSE 0 END) AS ventas,
                                SUM(CASE WHEN comandas.estado = "cobrada" THEN comandas.propina ELSE 0 END) AS propinas,
                                SUM(CASE WHEN comandas.estado = "cobrada" THEN comandas.descuento ELSE 0 END) AS descuentos,
                                SUM(CASE WHEN comandas.estado = "anulada" THEN 1 ELSE 0 END) AS anuladas,
                                SUM(comandas.comensales) AS comensales')
            ->join('empleados', 'empleados.id = comandas.empleado_id')
            ->where('DATE(comandas.created_at) >=', $desde)
            ->where('DATE(comandas.created_at) <=', $hasta)
            ->groupBy('empleados.id')
            ->orderBy('ventas', 'DESC')
            ->findAll();

        foreach ($filas as &$f) {
            $f['ticket_medio'] = (int) $f['comandas'] > 0
                ? round((float) $f['ventas'] / max(1, (int) $f['comandas']))
                : 0;
        }

        return $filas;
    }

    /** Autorizaciones que ha dado cada encargado: anulaciones y descuentos grandes. */
    public function autorizaciones(string $desde, string $hasta): array
    {
        return $this->select('comandas.numero, comandas.estado, comandas.total, comandas.descuento,
                              comandas.notas, comandas.motivo_descuento, comandas.created_at,
                              quien.nombre AS camarero, jefe.nombre AS autorizo')
            ->join('empleados quien', 'quien.id = comandas.empleado_id', 'left')
            ->join('empleados jefe', 'jefe.id = comandas.autorizo_id')
            ->where('DATE(comandas.created_at) >=', $desde)
            ->where('DATE(comandas.created_at) <=', $hasta)
            ->orderBy('comandas.created_at', 'DESC')
            ->findAll();
    }

    /** Número correlativo diario, p. ej. C-0725-03. */
    public function generarNumero(): string
    {
        $prefijo = 'C-' . date('md') . '-';
        $ultimas = $this->like('numero', $prefijo, 'after')->countAllResults();

        return $prefijo . str_pad((string) ($ultimas + 1), 2, '0', STR_PAD_LEFT);
    }

    /** Comandas abiertas, con el huésped si están asociadas a una reserva. */
    public function abiertas(): array
    {
        return $this->select('comandas.*, reservas.codigo AS reserva_codigo,
                              huespedes.nombre AS h_nombre, huespedes.apellidos AS h_apellidos,
                              unidades.nombre AS unidad_nombre')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.estado', 'abierta')
            ->orderBy('comandas.created_at')
            ->findAll();
    }

    /**
     * Comandas cerradas recientes.
     *
     * Los dos join son `left` a propósito: una comanda tomada desde el
     * comandero del móvil no tiene usuario del sistema, solo empleado. Con un
     * join normal esas comandas desaparecerían del historial sin avisar.
     */
    public function historial(int $limite = 15): array
    {
        return $this->select('comandas.*, reservas.codigo AS reserva_codigo,
                              COALESCE(usuarios.nombre, empleados.nombre) AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = comandas.usuario_id', 'left')
            ->join('empleados', 'empleados.id = comandas.empleado_id', 'left')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->whereIn('comandas.estado', ['cobrada', 'anulada'])
            ->orderBy('comandas.cerrada_en', 'DESC')
            ->findAll($limite);
    }

    /** Recalcula y guarda el total a partir de sus líneas. */
    public function recalcularTotal(int $comandaId): float
    {
        // Solo se cobra lo `normal`. Lo `incluida` lo paga el plan, lo
        // `cortesia` lo regala la casa y lo `devuelta` volvió a cocina — pero
        // las tres siguen ahí, con su precio, porque el consumo existió y el
        // escandallo tiene que saberlo.
        $fila = $this->db->table('comanda_lineas')
            ->select('SUM(precio_unitario * cantidad) AS subtotal', false)
            ->where('comanda_id', $comandaId)
            ->where('estado_linea', 'normal')
            ->get()->getRowArray();

        $total = (float) ($fila['subtotal'] ?? 0);
        $this->update($comandaId, ['total' => $total]);

        return $total;
    }
}
