<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La tarjeta de saldo como forma de pago en el restaurante.
 *
 * Se llama `tarjeta_saldo` y no `tarjeta` a propósito: `tarjeta` ya significa
 * datáfono desde el primer día, y confundir las dos haría que el arqueo de caja
 * esperase en el datáfono un dinero que en realidad se cobró hace un mes.
 *
 * `MODIFY ... ENUM` se puede repetir sin daño, que es lo que hace falta aquí:
 * MySQL no sabe deshacer un cambio de estructura a medias.
 */
class TarjetaSaldoEnTpv extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE comanda_pagos MODIFY forma_pago
             ENUM('efectivo','tarjeta','transferencia','wompi','habitacion','bono','tarjeta_saldo') NOT NULL"
        );
        $this->db->query(
            "ALTER TABLE comandas MODIFY forma_pago
             ENUM('efectivo','tarjeta','transferencia','wompi','habitacion','bono','tarjeta_saldo') NULL"
        );
    }

    public function down(): void
    {
        // Antes de estrechar el ENUM hay que sacar de en medio los pagos que ya
        // usan el valor nuevo, o MySQL los convertiría en cadena vacía sin avisar.
        $this->db->query("DELETE FROM comanda_pagos WHERE forma_pago = 'tarjeta_saldo'");
        $this->db->query("UPDATE comandas SET forma_pago = NULL WHERE forma_pago = 'tarjeta_saldo'");

        $this->db->query(
            "ALTER TABLE comanda_pagos MODIFY forma_pago
             ENUM('efectivo','tarjeta','transferencia','wompi','habitacion','bono') NOT NULL"
        );
        $this->db->query(
            "ALTER TABLE comandas MODIFY forma_pago
             ENUM('efectivo','tarjeta','transferencia','wompi','habitacion','bono') NULL"
        );
    }
}
