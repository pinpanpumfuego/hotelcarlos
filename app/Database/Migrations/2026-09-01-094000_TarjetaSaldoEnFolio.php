<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La tarjeta de saldo como método de pago del folio.
 *
 * Sin esto los cobros con tarjeta caían en «Otro», y «Otro» en un informe de
 * medios de pago es exactamente la casilla que nadie sabe explicar a fin de mes.
 */
class TarjetaSaldoEnFolio extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE folio_movimientos MODIFY metodo
             ENUM('efectivo','tarjeta','transferencia','wompi','bono','tarjeta_saldo','otro') NULL"
        );
    }

    public function down(): void
    {
        // Los pagos ya hechos con tarjeta pasan a «Otro»: se pierde el detalle,
        // pero no el dinero. Estrechar el ENUM sin esto los dejaría en blanco.
        $this->db->query("UPDATE folio_movimientos SET metodo = 'otro' WHERE metodo = 'tarjeta_saldo'");

        $this->db->query(
            "ALTER TABLE folio_movimientos MODIFY metodo
             ENUM('efectivo','tarjeta','transferencia','wompi','bono','otro') NULL"
        );
    }
}
