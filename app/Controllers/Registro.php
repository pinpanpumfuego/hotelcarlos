<?php

namespace App\Controllers;

use App\Models\AcompananteModel;
use App\Models\DocumentoModel;
use App\Models\HuespedModel;
use App\Models\RegistroModel;

/**
 * Registro en línea del huésped (autocheck-in), accesible sin cuenta
 * mediante un enlace con token que caduca tras la salida.
 */
class Registro extends BaseController
{
    private RegistroModel $registros;
    private AcompananteModel $acompanantes;
    private DocumentoModel $documentos;

    /** Tipos de archivo admitidos para el documento de identidad. */
    private const MIMES_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];
    private const TAMANO_MAXIMO    = 8388608; // 8 MB

    public function __construct()
    {
        $this->registros    = new RegistroModel();
        $this->acompanantes = new AcompananteModel();
        $this->documentos   = new DocumentoModel();
    }

    /** Pantalla del huésped. */
    public function index(string $token)
    {
        $registro = $this->registros->porToken($token);
        if ($registro === null) {
            return view('registro/caducado', ['hotel' => config('Hotel')]);
        }

        $datos = $this->registros->conReserva((int) $registro['id']);

        return view('registro/index', [
            'hotel'        => config('Hotel'),
            'registro'     => $datos,
            'token'        => $token,
            'acompanantes' => $this->acompanantes->deRegistro((int) $registro['id']),
            'documentos'   => $this->documentos->deRegistro((int) $registro['id']),
            'motivos'      => RegistroModel::MOTIVOS_VIAJE,
            'tiposDoc'     => AcompananteModel::TIPOS_DOCUMENTO,
            'soloLectura'  => in_array($registro['estado'], ['enviado', 'aprobado'], true),
        ]);
    }

    /** Guarda los datos del titular. */
    public function guardarDatos(string $token)
    {
        $registro = $this->registros->porToken($token);
        if ($registro === null) {
            return redirect()->to('registro/' . $token);
        }
        if (in_array($registro['estado'], ['enviado', 'aprobado'], true)) {
            return redirect()->to('registro/' . $token)->with('error', 'El registro ya fue enviado y no se puede modificar.');
        }

        $post = $this->request->getPost();

        // Datos del titular que viven en su ficha de huésped
        $huespedes = new HuespedModel();
        $huespedes->update($registro['huesped_id'] ?? $this->registros->conReserva((int) $registro['id'])['huesped_id'], [
            'nombre'       => trim((string) ($post['nombre'] ?? '')),
            'apellidos'    => trim((string) ($post['apellidos'] ?? '')),
            'nacionalidad' => trim((string) ($post['nacionalidad'] ?? 'Colombia')),
            'telefono'     => trim((string) ($post['telefono'] ?? '')),
            'email'        => strtolower(trim((string) ($post['email'] ?? ''))),
        ]);

        $this->registros->update($registro['id'], [
            'motivo_viaje'      => array_key_exists($post['motivo_viaje'] ?? '', RegistroModel::MOTIVOS_VIAJE)
                ? $post['motivo_viaje'] : null,
            'pais_residencia'   => trim((string) ($post['pais_residencia'] ?? '')) ?: null,
            'ciudad_residencia' => trim((string) ($post['ciudad_residencia'] ?? '')) ?: null,
            'direccion'         => trim((string) ($post['direccion'] ?? '')) ?: null,
            'fecha_nacimiento'  => $this->fechaValida($post['fecha_nacimiento'] ?? ''),
            'ocupacion'         => trim((string) ($post['ocupacion'] ?? '')) ?: null,
            'placa_vehiculo'    => strtoupper(trim((string) ($post['placa_vehiculo'] ?? ''))) ?: null,
            'hora_llegada'      => trim((string) ($post['hora_llegada'] ?? '')) ?: null,
            'observaciones'     => trim((string) ($post['observaciones'] ?? '')) ?: null,
        ]);

        $this->recalcularMarcas((int) $registro['id']);

        return redirect()->to('registro/' . $token . '#acompanantes')->with('ok', 'Datos guardados.');
    }

    /** Añade un acompañante. */
    public function guardarAcompanante(string $token)
    {
        $registro = $this->registros->porToken($token);
        if ($registro === null || in_array($registro['estado'], ['enviado', 'aprobado'], true)) {
            return redirect()->to('registro/' . $token);
        }

        $nombre    = trim((string) $this->request->getPost('nombre'));
        $apellidos = trim((string) $this->request->getPost('apellidos'));

        if ($nombre === '' || $apellidos === '') {
            return redirect()->to('registro/' . $token . '#acompanantes')->with('error', 'Indica nombre y apellidos del acompañante.');
        }

        $nacimiento = $this->fechaValida((string) $this->request->getPost('fecha_nacimiento'));
        $esMenor    = AcompananteModel::esMenor($nacimiento);

        $tipoDoc = (string) $this->request->getPost('tipo_documento');

        $this->acompanantes->insert([
            'registro_id'      => $registro['id'],
            'nombre'           => $nombre,
            'apellidos'        => $apellidos,
            'tipo_documento'   => array_key_exists($tipoDoc, AcompananteModel::TIPOS_DOCUMENTO) ? $tipoDoc : 'CC',
            'num_documento'    => trim((string) $this->request->getPost('num_documento')) ?: null,
            'nacionalidad'     => trim((string) $this->request->getPost('nacionalidad')) ?: 'Colombia',
            'fecha_nacimiento' => $nacimiento,
            'es_menor'         => $esMenor ? 1 : 0,
            'parentesco'       => trim((string) $this->request->getPost('parentesco')) ?: null,
        ]);

        $this->recalcularMarcas((int) $registro['id']);

        $aviso = $esMenor
            ? ' Al ser menor de edad, deberás aportar el documento del menor y la autorización de viaje si no viaja con ambos padres.'
            : '';

        return redirect()->to('registro/' . $token . '#acompanantes')->with('ok', 'Acompañante añadido.' . $aviso);
    }

    public function eliminarAcompanante(string $token, int $id)
    {
        $registro = $this->registros->porToken($token);
        if ($registro === null || in_array($registro['estado'], ['enviado', 'aprobado'], true)) {
            return redirect()->to('registro/' . $token);
        }

        $acompanante = $this->acompanantes->find($id);
        if ($acompanante !== null && (int) $acompanante['registro_id'] === (int) $registro['id']) {
            $this->acompanantes->delete($id);
            $this->recalcularMarcas((int) $registro['id']);
        }

        return redirect()->to('registro/' . $token . '#acompanantes')->with('ok', 'Acompañante eliminado.');
    }

    /** Sube un documento de identidad. */
    public function subirDocumento(string $token)
    {
        $registro = $this->registros->porToken($token);
        if ($registro === null || in_array($registro['estado'], ['enviado', 'aprobado'], true)) {
            return redirect()->to('registro/' . $token);
        }

        $archivo = $this->request->getFile('documento');
        if ($archivo === null || ! $archivo->isValid()) {
            return redirect()->to('registro/' . $token . '#documentos')->with('error', 'No se pudo leer el archivo. Inténtalo de nuevo.');
        }

        if ($archivo->getSize() > self::TAMANO_MAXIMO) {
            return redirect()->to('registro/' . $token . '#documentos')->with('error', 'El archivo pesa más de 8 MB. Haz la foto con menos calidad o recórtala.');
        }

        // Se comprueba el tipo real del archivo, no la extensión que diga el nombre
        $mime = $archivo->getMimeType();
        if (! in_array($mime, self::MIMES_PERMITIDOS, true)) {
            return redirect()->to('registro/' . $token . '#documentos')
                ->with('error', 'Formato no admitido. Sube una foto (JPG, PNG) o un PDF.');
        }

        $tipo   = (string) $this->request->getPost('tipo');
        $tipo   = array_key_exists($tipo, DocumentoModel::TIPOS) ? $tipo : 'otro';
        $nombre = $archivo->getRandomName();

        $archivo->move(DocumentoModel::carpeta(), $nombre);

        $this->documentos->insert([
            'registro_id'     => $registro['id'],
            'tipo'            => $tipo,
            'archivo'         => $nombre,
            'nombre_original' => mb_substr($archivo->getClientName(), 0, 255),
            'mime'            => $mime,
            'tamano'          => $archivo->getSize(),
        ]);

        return redirect()->to('registro/' . $token . '#documentos')->with('ok', 'Documento subido correctamente.');
    }

    public function eliminarDocumento(string $token, int $id)
    {
        $registro = $this->registros->porToken($token);
        if ($registro === null || in_array($registro['estado'], ['enviado', 'aprobado'], true)) {
            return redirect()->to('registro/' . $token);
        }

        $doc = $this->documentos->find($id);
        if ($doc !== null && (int) $doc['registro_id'] === (int) $registro['id']) {
            $this->documentos->eliminarConArchivo($id);
        }

        return redirect()->to('registro/' . $token . '#documentos')->with('ok', 'Documento eliminado.');
    }

    /** Firma electrónica simple y envío definitivo. */
    public function enviar(string $token)
    {
        $registro = $this->registros->porToken($token);
        if ($registro === null) {
            return redirect()->to('registro/' . $token);
        }
        if (in_array($registro['estado'], ['enviado', 'aprobado'], true)) {
            return redirect()->to('registro/' . $token)->with('error', 'El registro ya fue enviado.');
        }

        $datos = $this->registros->conReserva((int) $registro['id']);

        // Consentimientos obligatorios
        if (! $this->request->getPost('acepta_datos') || ! $this->request->getPost('acepta_reglamento')
            || ! $this->request->getPost('acepta_escnna')) {
            return redirect()->to('registro/' . $token . '#firma')
                ->with('error', 'Para enviar el registro debes aceptar las tres autorizaciones.');
        }

        // Datos mínimos exigidos para el registro de alojamiento
        $faltan = [];
        if (trim((string) $datos['num_documento']) === '') {
            $faltan[] = 'número de documento';
        }
        if ($registro['fecha_nacimiento'] === null) {
            $faltan[] = 'fecha de nacimiento';
        }
        if ($registro['motivo_viaje'] === null) {
            $faltan[] = 'motivo del viaje';
        }
        if ($registro['ciudad_residencia'] === null) {
            $faltan[] = 'ciudad de residencia';
        }
        if ($faltan !== []) {
            return redirect()->to('registro/' . $token)
                ->with('error', 'Faltan datos obligatorios: ' . implode(', ', $faltan) . '.');
        }

        if ($this->documentos->where('registro_id', $registro['id'])->countAllResults() === 0) {
            return redirect()->to('registro/' . $token . '#documentos')
                ->with('error', 'Debes aportar al menos una foto de tu documento de identidad.');
        }

        // Firma dibujada en el móvil, recibida como imagen PNG
        $firmaArchivo = null;
        $firma        = (string) $this->request->getPost('firma');
        if (str_starts_with($firma, 'data:image/png;base64,')) {
            $contenido = base64_decode(substr($firma, strlen('data:image/png;base64,')), true);
            if ($contenido !== false && strlen($contenido) < 2097152) {
                $firmaArchivo = 'firma_' . $registro['id'] . '_' . bin2hex(random_bytes(6)) . '.png';
                file_put_contents(WRITEPATH . 'uploads/firmas/' . $firmaArchivo, $contenido);
            }
        }

        if ($firmaArchivo === null) {
            return redirect()->to('registro/' . $token . '#firma')->with('error', 'Falta tu firma. Dibújala en el recuadro.');
        }

        $this->registros->update($registro['id'], [
            'estado'            => 'enviado',
            'enviado_en'        => date('Y-m-d H:i:s'),
            'acepta_datos'      => 1,
            'acepta_reglamento' => 1,
            'acepta_escnna'     => 1,
            'acepta_marketing'  => $this->request->getPost('acepta_marketing') ? 1 : 0,
            'version_politica'  => RegistroModel::VERSION_POLITICA,
            'firma_archivo'     => $firmaArchivo,
            'firmado_en'        => date('Y-m-d H:i:s'),
            'firma_ip'          => $this->request->getIPAddress(),
            'firma_dispositivo' => mb_substr((string) $this->request->getUserAgent(), 0, 255),
            'motivo_rechazo'    => null,
        ]);

        $this->recalcularMarcas((int) $registro['id']);

        return redirect()->to('registro/' . $token)->with('ok', '¡Registro enviado! El hotel lo revisará antes de tu llegada.');
    }

    // ─────────────────────────── Ayudantes ───────────────────────────

    /** Marca si hay menores o extranjeros, para los avisos del hotel. */
    private function recalcularMarcas(int $registroId): void
    {
        $datos        = $this->registros->conReserva($registroId);
        $acompanantes = $this->acompanantes->deRegistro($registroId);

        $hayMenores = false;
        foreach ($acompanantes as $a) {
            if ((int) $a['es_menor'] === 1) {
                $hayMenores = true;
                break;
            }
        }

        $nacionalidades = array_merge(
            [$datos['nacionalidad'] ?? 'Colombia'],
            array_column($acompanantes, 'nacionalidad')
        );
        $hayExtranjeros = false;
        foreach ($nacionalidades as $n) {
            if (mb_strtolower(trim((string) $n)) !== 'colombia' && trim((string) $n) !== '') {
                $hayExtranjeros = true;
                break;
            }
        }

        $this->registros->update($registroId, [
            'hay_menores'     => $hayMenores ? 1 : 0,
            'hay_extranjeros' => $hayExtranjeros ? 1 : 0,
        ]);
    }

    private function fechaValida(string $fecha): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', trim($fecha));

        return $d !== false ? $d->format('Y-m-d') : null;
    }
}
