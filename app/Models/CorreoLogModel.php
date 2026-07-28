<?php

namespace App\Models;

use CodeIgniter\Model;

class CorreoLogModel extends Model
{
    protected $table         = 'correos_log';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['tipo', 'destinatario', 'asunto', 'estado', 'error', 'reserva_id', 'usuario_id'];
    protected $useTimestamps = true;

    public const TIPOS = [
        'solicitud_recibida'   => 'Acuse al huésped (solicitud recibida)',
        'confirmacion_reserva' => 'Confirmación de reserva',
        'enlace_registro'      => 'Enlace de registro',
        'aviso_reserva_web'    => 'Aviso de reserva web (al hotel)',
        'prueba'               => 'Correo de prueba',
        'general'              => 'General',
    ];

    /** Últimos envíos, para la pantalla de Administración. */
    public function ultimos(int $limite = 30): array
    {
        return $this->orderBy('id', 'DESC')->findAll($limite);
    }

    /** Cuántos han fallado o no se enviaron por falta de configuración. */
    public function conProblemas(): int
    {
        return $this->whereIn('estado', ['fallido', 'sin_configurar'])->countAllResults();
    }
}
