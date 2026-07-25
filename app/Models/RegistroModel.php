<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Registro en línea del huésped (autocheck-in).
 * Reúne los datos de la Tarjeta de Registro de Alojamiento y la
 * trazabilidad de los consentimientos aceptados.
 */
class RegistroModel extends Model
{
    protected $table         = 'registros';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'reserva_id', 'token', 'estado', 'expira_en',
        'motivo_viaje', 'pais_residencia', 'ciudad_residencia', 'direccion',
        'fecha_nacimiento', 'ocupacion', 'placa_vehiculo', 'hora_llegada', 'observaciones',
        'acepta_datos', 'acepta_reglamento', 'acepta_escnna', 'version_politica', 'acepta_marketing',
        'firma_archivo', 'firmado_en', 'firma_ip', 'firma_dispositivo',
        'enviado_en', 'revisado_por', 'revisado_en', 'motivo_rechazo',
        'hay_menores', 'hay_extranjeros', 'reportado_sire', 'reportado_tra',
    ];
    protected $useTimestamps = true;

    /** Versión del aviso de privacidad que se está aceptando. */
    public const VERSION_POLITICA = '2026-07';

    public const MOTIVOS_VIAJE = [
        'ocio'       => 'Ocio / vacaciones',
        'negocios'   => 'Negocios',
        'familiar'   => 'Visita a familiares',
        'salud'      => 'Salud',
        'estudios'   => 'Estudios',
        'transito'   => 'Tránsito',
        'otro'       => 'Otro',
    ];

    public const ESTADOS = [
        'pendiente' => 'Pendiente de completar',
        'enviado'   => 'Enviado, por revisar',
        'aprobado'  => 'Aprobado',
        'rechazado' => 'Devuelto al huésped',
    ];

    /** Crea (o recupera) el registro de una reserva, con enlace válido hasta la salida. */
    public function paraReserva(array $reserva): array
    {
        $existente = $this->where('reserva_id', $reserva['id'])->first();
        if ($existente !== null) {
            return $existente;
        }

        // Token largo y aleatorio: es la única llave del enlace que recibe el huésped
        $token = bin2hex(random_bytes(24));

        $id = $this->insert([
            'reserva_id' => $reserva['id'],
            'token'      => $token,
            'estado'     => 'pendiente',
            // El enlace deja de servir un día después de la salida
            'expira_en'  => (new \DateTime($reserva['fecha_salida']))->modify('+1 day')->format('Y-m-d H:i:s'),
        ]);

        return $this->find($id);
    }

    /** Busca por token, solo si sigue vigente. */
    public function porToken(string $token): ?array
    {
        $registro = $this->where('token', $token)->first();
        if ($registro === null) {
            return null;
        }

        if ($registro['expira_en'] !== null && strtotime($registro['expira_en']) < time()) {
            return null;
        }

        return $registro;
    }

    /** Registro con los datos de su reserva y huésped titular. */
    public function conReserva(int $id): ?array
    {
        return $this->select('registros.*, reservas.codigo, reservas.fecha_entrada, reservas.fecha_salida,
                              reservas.adultos, reservas.ninos, reservas.estado AS reserva_estado,
                              huespedes.id AS huesped_id, huespedes.nombre, huespedes.apellidos,
                              huespedes.tipo_documento, huespedes.num_documento, huespedes.nacionalidad,
                              huespedes.telefono, huespedes.email,
                              unidades.nombre AS unidad_nombre')
            ->join('reservas', 'reservas.id = registros.reserva_id')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->where('registros.id', $id)
            ->first();
    }

    /** Registros enviados, pendientes de que el hotel los revise. */
    public function porRevisar(): array
    {
        return $this->select('registros.*, reservas.codigo, reservas.fecha_entrada,
                              huespedes.nombre, huespedes.apellidos, unidades.nombre AS unidad_nombre')
            ->join('reservas', 'reservas.id = registros.reserva_id')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->where('registros.estado', 'enviado')
            ->orderBy('reservas.fecha_entrada')
            ->findAll();
    }

    /** Resto de registros, para seguimiento. */
    public function otros(int $limite = 25): array
    {
        return $this->select('registros.*, reservas.codigo, reservas.fecha_entrada,
                              huespedes.nombre, huespedes.apellidos, unidades.nombre AS unidad_nombre')
            ->join('reservas', 'reservas.id = registros.reserva_id')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->whereIn('registros.estado', ['pendiente', 'aprobado', 'rechazado'])
            ->orderBy('reservas.fecha_entrada', 'DESC')
            ->findAll($limite);
    }

    /** ¿Cuántos registros esperan revisión? */
    public function pendientesDeRevision(): int
    {
        return $this->where('estado', 'enviado')->countAllResults();
    }
}
