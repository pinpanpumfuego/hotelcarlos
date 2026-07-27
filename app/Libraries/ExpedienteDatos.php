<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ConsentimientoModel;
use App\Models\HuespedModel;
use App\Models\HuespedPreferenciaModel;

/**
 * Todo lo que el hotel guarda de una persona, en un documento.
 *
 * Es la forma concreta de cumplir el derecho de acceso: en vez de contestar
 * «tenemos sus datos de reserva», se entrega la lista. Y sirve para lo mismo
 * hacia dentro — antes de borrar a alguien conviene ver qué se está borrando.
 *
 * **Lo que NO incluye a propósito:** el número completo del documento de
 * identidad, las fotos del documento y la firma. Esos archivos existen y se
 * dicen, pero no se meten en un fichero que va a viajar por correo. Se
 * entregan en mano o por un canal que se pueda controlar.
 */
class ExpedienteDatos
{
    /**
     * @return array<string, mixed>|null
     */
    public function de(int $huespedId): ?array
    {
        $huesped = (new HuespedModel())->find($huespedId);

        if ($huesped === null) {
            return null;
        }

        $db  = db_connect();
        $crm = new Crm();

        return [
            'generado_en' => date('Y-m-d H:i:s'),
            'hotel'       => config('Hotel')->nombre,

            'identificacion' => [
                'nombre'    => trim($huesped['nombre'] . ' ' . $huesped['apellidos']),
                // Solo los últimos cuatro: basta para que se reconozca y no
                // convierte el fichero en una copia de su cédula.
                'documento' => $huesped['tipo_documento'] . ' ····'
                    . mb_substr((string) $huesped['num_documento'], -4),
                'email'     => $huesped['email'],
                'telefono'  => $huesped['telefono'],
                'nacionalidad' => $huesped['nacionalidad'],
                'ciudad'    => $huesped['ciudad'],
                'pais'      => $huesped['pais'],
                'empresa'   => $huesped['empresa'],
                'idioma'    => $huesped['idioma'],
                'alta_en'   => $huesped['created_at'],
            ],

            'valor' => $crm->valor($huespedId),

            'estancias' => array_map(
                static fn (array $r): array => [
                    'codigo'  => $r['codigo'],
                    'entrada' => $r['fecha_entrada'],
                    'salida'  => $r['fecha_salida'],
                    'estado'  => $r['estado'],
                    'canal'   => $r['canal'],
                    'total'   => $r['total'],
                ],
                $db->table('reservas')->where('huesped_id', $huespedId)
                    ->orderBy('fecha_entrada', 'DESC')->get()->getResultArray()
            ),

            // Las preferencias sensibles se incluyen: son SUS datos, y el
            // derecho de acceso es justamente a todos ellos.
            'preferencias' => array_map(
                static fn (array $p): array => [
                    'tipo'   => $p['tipo'],
                    'valor'  => $p['valor'],
                    'origen' => $p['origen'],
                    'desde'  => $p['created_at'],
                ],
                (new HuespedPreferenciaModel())->deHuesped($huespedId, true)
            ),

            // El libro entero, no el estado de hoy: es la prueba de qué
            // autorizó y cuándo, que es lo que la ley llama derecho a prueba.
            'autorizaciones' => array_map(
                static fn (array $c): array => [
                    'finalidad' => ConsentimientoModel::FINALIDADES[$c['finalidad']] ?? $c['finalidad'],
                    'canal'     => ConsentimientoModel::CANALES[$c['canal']] ?? $c['canal'],
                    'accion'    => (int) $c['otorgado'] === 1 ? 'autorizó' : 'retiró',
                    'cuando'    => $c['created_at'],
                    'origen'    => $c['origen'],
                    'politica'  => $c['version_politica'],
                ],
                (new ConsentimientoModel())->historial($huespedId)
            ),

            'mensajes' => array_map(
                static fn (array $e): array => [
                    'asunto' => $e['asunto'],
                    'cuando' => $e['enviado_en'] ?? $e['programado_para'],
                    'estado' => $e['estado'],
                    'para'   => $e['finalidad'],
                ],
                $db->table('envios')->where('huesped_id', $huespedId)
                    ->orderBy('created_at', 'DESC')->limit(200)->get()->getResultArray()
            ),

            'opiniones' => array_map(
                static fn (array $e): array => [
                    'cuando'     => $e['created_at'],
                    'general'    => $e['general'],
                    'comentario' => $e['comentario'],
                ],
                $db->query(
                    'SELECT e.* FROM encuestas e JOIN reservas r ON r.id = e.reserva_id WHERE r.huesped_id = ?',
                    [$huespedId]
                )->getResultArray()
            ),

            // Se dice que existen sin adjuntarlos: un fichero que va por correo
            // no es sitio para una copia de una cédula ni para una firma.
            'documentos_aparte' => $this->documentosQueExisten($huespedId),
        ];
    }

    /** @return list<string> */
    private function documentosQueExisten(int $huespedId): array
    {
        $db    = db_connect();
        $lista = [];

        $registros = $db->query(
            'SELECT r.id, r.firma_archivo FROM registros r
             JOIN reservas res ON res.id = r.reserva_id
             WHERE res.huesped_id = ?',
            [$huespedId]
        )->getResultArray();

        foreach ($registros as $r) {
            if (! empty($r['firma_archivo'])) {
                $lista[] = 'Firma del registro de llegada';
            }

            $docs = $db->table('registro_documentos')->where('registro_id', $r['id'])->countAllResults();

            if ($docs > 0) {
                $lista[] = $docs . ' imagen(es) del documento de identidad';
            }
        }

        return array_values(array_unique($lista));
    }

    /**
     * El expediente en texto plano, listo para imprimir o adjuntar.
     *
     * En texto y no en PDF porque un PDF necesita una librería más y esto tiene
     * que funcionar siempre, incluso el día que algo esté roto.
     */
    public function comoTexto(int $huespedId): ?string
    {
        $d = $this->de($huespedId);

        if ($d === null) {
            return null;
        }

        $l = [];
        $l[] = str_repeat('=', 64);
        $l[] = 'DATOS PERSONALES QUE CONSERVAMOS';
        $l[] = $d['hotel'];
        $l[] = 'Generado el ' . date('d/m/Y H:i', strtotime($d['generado_en']));
        $l[] = str_repeat('=', 64);
        $l[] = '';

        $l[] = '— QUIÉN ES —';

        foreach ($d['identificacion'] as $campo => $valor) {
            if ($valor !== null && $valor !== '') {
                $l[] = '  ' . str_pad(ucfirst(str_replace('_', ' ', $campo)) . ':', 16) . $valor;
            }
        }

        $l[] = '';
        $l[] = '— SUS ESTANCIAS (' . count($d['estancias']) . ') —';

        foreach ($d['estancias'] as $e) {
            $l[] = sprintf('  %s  %s → %s  %s', $e['codigo'], $e['entrada'], $e['salida'], $e['estado']);
        }

        if ($d['preferencias'] !== []) {
            $l[] = '';
            $l[] = '— LO QUE SABEMOS DE SUS GUSTOS Y NECESIDADES —';

            foreach ($d['preferencias'] as $p) {
                $l[] = sprintf('  [%s] %s (lo dijo: %s)', $p['tipo'], $p['valor'], $p['origen']);
            }
        }

        $l[] = '';
        $l[] = '— QUÉ NOS AUTORIZÓ Y CUÁNDO —';

        if ($d['autorizaciones'] === []) {
            $l[] = '  No consta ninguna autorización. No se le escribe para nada que no sea su reserva.';
        }

        foreach ($d['autorizaciones'] as $a) {
            $l[] = sprintf(
                '  %s  %s %s por %s  (%s)',
                date('d/m/Y', strtotime($a['cuando'])),
                $a['accion'],
                $a['finalidad'],
                $a['canal'],
                $a['origen']
            );
        }

        if ($d['mensajes'] !== []) {
            $l[] = '';
            $l[] = '— MENSAJES QUE LE HEMOS MANDADO (' . count($d['mensajes']) . ') —';

            foreach (array_slice($d['mensajes'], 0, 30) as $m) {
                $l[] = sprintf('  %s  %s', date('d/m/Y', strtotime((string) $m['cuando'])), $m['asunto']);
            }
        }

        if ($d['documentos_aparte'] !== []) {
            $l[] = '';
            $l[] = '— ARCHIVOS QUE NO VAN EN ESTE DOCUMENTO —';
            $l[] = '  Existen, pero no se adjuntan aquí por seguridad. Se entregan en mano:';

            foreach ($d['documentos_aparte'] as $doc) {
                $l[] = '  · ' . $doc;
            }
        }

        $l[] = '';
        $l[] = str_repeat('-', 64);
        $l[] = 'Puede pedir que corrijamos, actualicemos o borremos cualquiera de estos datos,';
        $l[] = 'y retirar su autorización cuando quiera.';

        $correo = (new \App\Models\ConfiguracionModel())->obtener('legal_correo_datos', '');

        if ($correo !== '') {
            $l[] = 'Escriba a: ' . $correo;
        }

        return implode("\n", $l) . "\n";
    }
}
