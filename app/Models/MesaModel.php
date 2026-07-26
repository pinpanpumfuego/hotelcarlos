<?php

namespace App\Models;

use CodeIgniter\Model;

class MesaModel extends Model
{
    protected $table         = 'mesas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'zona', 'capacidad', 'orden', 'activa'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'nombre' => 'required|max_length[60]',
    ];

    /** Mesas activas con su comanda abierta (si la tienen). */
    public function conEstado(): array
    {
        $mesas = $this->where('activa', 1)->orderBy('zona')->orderBy('orden')->orderBy('nombre')->findAll();

        // Se trae también quién la lleva: en un turno con varios camareros,
        // saber de un vistazo qué mesas son de quién evita que dos vayan a la
        // misma y que ninguno vaya a otra
        $abiertas = db_connect()->table('comandas')
            ->select('comandas.id, comandas.mesa_id, comandas.total, comandas.created_at,
                      comandas.comensales, comandas.usuario_id, comandas.empleado_id,
                      empleados.nombre AS camarero_nombre')
            ->join('empleados', 'empleados.id = comandas.empleado_id', 'left')
            ->where('comandas.estado', 'abierta')
            ->where('comandas.mesa_id IS NOT NULL')
            ->get()->getResultArray();

        $porMesa = [];
        foreach ($abiertas as $c) {
            $porMesa[$c['mesa_id']] = $c;
        }

        $borradores = (new ComanderoBorradorModel())->porMesa();
        $sinRecibir = (new ComandaLineaModel())->sinRecibir();

        foreach ($mesas as &$m) {
            $comanda           = $porMesa[$m['id']] ?? null;
            $m['comanda_id']   = $comanda['id'] ?? null;
            $m['total']        = (float) ($comanda['total'] ?? 0);
            $m['comensales']   = (int) ($comanda['comensales'] ?? 0);
            $m['abierta_hace'] = $comanda !== null ? (int) ((time() - strtotime($comanda['created_at'])) / 60) : null;
            $m['ocupada']      = $comanda !== null;
            $m['camarero']     = $comanda['camarero_nombre'] ?? null;
            // Del móvil o de la pantalla fija: una comanda del comandero no
            // tiene usuario del sistema, solo empleado
            $m['origen'] = $comanda !== null && $comanda['usuario_id'] === null && $comanda['empleado_id'] !== null
                ? 'movil' : 'pantalla';
            // Lo que un camarero está apuntando ahora mismo en su móvil y
            // todavía no ha enviado. Puede haberlo en una mesa aún «libre».
            $m['tomando'] = $borradores[(int) $m['id']] ?? null;

            // Enviada a cocina pero nadie la ha mirado todavía. Pasados unos
            // minutos deja de ser un despiste y el camarero tiene que saberlo.
            $espera = $comanda !== null ? ($sinRecibir[(int) $comanda['id']] ?? null) : null;
            $m['sin_recibir_min'] = $espera;
            $m['cocina_no_la_ve'] = $espera !== null && $espera >= ComandaLineaModel::ALERTA_SIN_RECIBIR;
        }

        return $mesas;
    }
}
