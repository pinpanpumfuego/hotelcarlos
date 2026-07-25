<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UsuarioAdmin extends Seeder
{
    public function run()
    {
        $existe = $this->db->table('usuarios')->where('email', 'admin@hotelcarlos.local')->countAllResults();
        if ($existe > 0) {
            return;
        }

        $ahora = date('Y-m-d H:i:s');

        $this->db->table('usuarios')->insert([
            'nombre'     => 'Administrador',
            'email'      => 'admin@hotelcarlos.local',
            'clave_hash' => password_hash('Admin2026!', PASSWORD_DEFAULT),
            'rol'        => 'gerencia',
            'activo'     => 1,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
}
