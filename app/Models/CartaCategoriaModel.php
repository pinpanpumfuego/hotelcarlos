<?php

namespace App\Models;

use CodeIgniter\Model;

class CartaCategoriaModel extends Model
{
    protected $table         = 'carta_categorias';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'orden', 'color', 'franjas'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'nombre' => 'required|min_length[2]|max_length[80]',
    ];

    public function ordenadas(): array
    {
        return $this->orderBy('orden')->orderBy('nombre')->findAll();
    }

    /**
     * Categorías que se pueden vender en una franja horaria.
     *
     * Sin franjas marcadas, la categoría vale a cualquier hora. Es lo que se
     * espera de una carta recién montada: que salga entera hasta que alguien
     * decida lo contrario, no que desaparezca.
     */
    public function deFranja(?string $franja): array
    {
        $todas = $this->ordenadas();

        if ($franja === null) {
            return $todas;
        }

        return array_values(array_filter($todas, static function (array $c) use ($franja): bool {
            $suyas = array_filter(array_map('trim', explode(',', (string) ($c['franjas'] ?? ''))));

            return $suyas === [] || in_array($franja, $suyas, true);
        }));
    }

    /** Guarda en qué franjas se vende una categoría. */
    public function fijarFranjas(int $id, array $franjas): bool
    {
        $validas = array_values(array_intersect(
            $franjas,
            array_keys(\App\Libraries\Planes::FRANJAS)
        ));

        return (bool) $this->update($id, ['franjas' => $validas === [] ? null : implode(',', $validas)]);
    }
}
