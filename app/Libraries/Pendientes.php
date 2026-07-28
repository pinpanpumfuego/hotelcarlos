<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ConfiguracionModel;

/**
 * Qué le falta al sistema para estar en marcha.
 *
 * **Por qué existe.** Administración tenía once formularios y ninguna
 * jerarquía: los textos del portal ocupaban lo mismo que las llaves de la
 * pasarela de pagos, así que todo parecía igual de importante y no se
 * encontraba nada. Esto le da la jerarquía que faltaba, ordenada por lo único
 * que importa mientras el hotel se configura: **qué está abierto, qué no cobra
 * y qué no se ha hecho nunca.**
 *
 * Cada aviso dice qué pasa, por qué importa y dónde se arregla. Un aviso que no
 * dice dónde se arregla es una regañina.
 */
class Pendientes
{
    /** @var list<array{clave: string, nivel: string, titulo: string, detalle: string, enlace: ?string}> */
    private array $avisos = [];

    private ConfiguracionModel $config;

    public function __construct()
    {
        $this->config = new ConfiguracionModel();
    }

    /**
     * @return list<array{clave: string, nivel: string, titulo: string, detalle: string, enlace: ?string}>
     */
    public function todos(): array
    {
        $this->avisos = [];

        $this->seguridad();
        $this->cobros();
        $this->legal();
        $this->datos();
        $this->copias();

        // Lo grave primero. Da igual lo bonito que esté el portal del huésped
        // si el fichaje está abierto a internet.
        $orden = ['grave' => 0, 'aviso' => 1, 'suelto' => 2];

        usort($this->avisos, static fn (array $a, array $b): int => $orden[$a['nivel']] <=> $orden[$b['nivel']]);

        return $this->avisos;
    }

    public function cuantos(string $nivel): int
    {
        return count(array_filter($this->todos(), static fn (array $a): bool => $a['nivel'] === $nivel));
    }

    // ── Los grupos ──────────────────────────────────────────────────────

    private function seguridad(): void
    {
        if ($this->config->obtener('fichaje_terminal', '1') === '1') {
            $this->anotar(
                'fichar',
                'grave',
                'El terminal de fichaje está abierto a internet',
                'Cualquiera con la dirección puede fichar como si fuera empleado tuyo. Si el '
                . 'terminal no está en una tablet del hotel, apágalo.',
                'administracion/operacion#fichaje'
            );
        }

        if ((int) $this->config->obtener('fichaje_radio_m', '0') === 0
            && $this->config->obtener('fichaje_movil', '1') === '1') {
            $this->anotar(
                'geocerco',
                'aviso',
                'Se puede fichar desde el móvil, desde cualquier sitio',
                'Sin las coordenadas del hotel no se puede comprobar que quien ficha esté aquí.',
                'administracion/operacion#fichaje'
            );
        }
    }

    private function cobros(): void
    {
        if ($this->config->obtener('wompi_activo', '0') !== '1') {
            $this->anotar(
                'wompi',
                'grave',
                'La web coge reservas pero no puede cobrarlas',
                'Los pagos en línea están apagados. Hacen falta las cuatro llaves de producción '
                . 'de Wompi, que las saca el titular de la cuenta.',
                'administracion/cobros#wompi'
            );
        }

        if (! $this->config->existe('correo_clave')) {
            $this->anotar(
                'correo',
                'grave',
                'No hay servidor de correo configurado',
                'Sin esto no sale ni la confirmación de una reserva ni el enlace de registro: '
                . 'los correos se apuntan como «sin configurar» y ahí se quedan.',
                'administracion/cobros#smtp'
            );
        }

        if (! $this->config->existe('siigo_access_key')) {
            $this->anotar(
                'siigo',
                'aviso',
                'No se pueden emitir facturas electrónicas',
                'Faltan las credenciales de Siigo y su resolución de facturación.',
                'administracion/cobros#siigo'
            );
        }
    }

    private function legal(): void
    {
        $faltan = [];

        foreach ([
            'legal_rnt'          => 'el RNT',
            'legal_nit'          => 'el NIT',
            'legal_correo_datos' => 'el correo de protección de datos',
            'legal_escnna_texto' => 'el texto de prevención ESCNNA',
        ] as $clave => $nombre) {
            if (! $this->config->existe($clave)) {
                $faltan[] = $nombre;
            }
        }

        if ($faltan !== []) {
            $this->anotar(
                'legal',
                'grave',
                'Faltan datos legales obligatorios',
                'Sin ' . implode(', ', $faltan) . '. Son de los que exige la norma para operar, '
                . 'y no los puede inventar el programa.',
                'legal'
            );
        }
    }

    private function datos(): void
    {
        $db = db_connect();

        $sinFoto = (int) $db->query(
            'SELECT COUNT(*) AS n FROM unidades u
             WHERE NOT EXISTS (SELECT 1 FROM medios m WHERE m.unidad_id = u.id)'
        )->getRowArray()['n'];

        if ($sinFoto > 0) {
            $this->anotar(
                'fotos',
                'aviso',
                $sinFoto . ' cabaña(s) sin ninguna foto',
                'Es lo que más vende. Un alojamiento sin fotos en la web se reserva mucho menos.',
                'unidades'
            );
        }

        $tarifas = (int) $db->query('SELECT COUNT(*) AS n FROM tarifas_temporada')->getRowArray()['n'];

        if ($tarifas === 0) {
            $this->anotar(
                'tarifas',
                'aviso',
                'No hay ninguna tarifa cargada',
                'El motor de reservas necesita saber cuánto cuesta cada cabaña en cada temporada.',
                'tarifas'
            );
        }
    }

    private function copias(): void
    {
        $ultima = $this->config->obtener('seguridad_ultima_copia', '');

        if ($ultima === null || $ultima === '') {
            $this->anotar(
                'copia',
                'grave',
                'Nunca se ha registrado una copia de seguridad',
                'Una copia que nadie ha comprobado que se puede restaurar no es una copia. '
                . 'Hazla, restáurala una vez para verificar que sirve, y déjala programada.',
                'administracion/sistema'
            );

            return;
        }

        $dias = (int) ((time() - strtotime($ultima)) / 86400);
        $tope = max(1, (int) $this->config->obtener('seguridad_aviso_dias', '7'));

        if ($dias > $tope) {
            $this->anotar(
                'copia',
                'aviso',
                'La última copia es de hace ' . $dias . ' días',
                'El aviso salta pasados ' . $tope . ' días.',
                'administracion/sistema'
            );
        }
    }

    private function anotar(string $clave, string $nivel, string $titulo, string $detalle, ?string $enlace): void
    {
        $this->avisos[] = compact('clave', 'nivel', 'titulo', 'detalle', 'enlace');
    }
}
