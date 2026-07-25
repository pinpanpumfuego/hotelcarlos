<?php

namespace App\Controllers;

use App\Models\AcompananteModel;
use App\Models\DocumentoModel;
use App\Models\HuespedModel;
use App\Models\RegistroModel;
use App\Models\ReservaModel;

/** Revisión de los registros en línea por parte del hotel. */
class Registros extends BaseController
{
    private RegistroModel $registros;

    public function __construct()
    {
        $this->registros = new RegistroModel();
    }

    public function index()
    {
        return view('registros/index', [
            'titulo'     => 'Registros de llegada',
            'seccion'    => 'registros',
            'porRevisar' => $this->registros->porRevisar(),
            'otros'      => $this->registros->otros(),
            'estados'    => RegistroModel::ESTADOS,
        ]);
    }

    /** Ficha completa del registro, con documentos y consentimientos. */
    public function ver(int $id)
    {
        $registro = $this->registros->conReserva($id);
        if ($registro === null) {
            return redirect()->to('registros')->with('error', 'El registro no existe.');
        }

        return view('registros/ver', [
            'titulo'       => 'Registro ' . $registro['codigo'],
            'seccion'      => 'registros',
            'registro'     => $registro,
            'acompanantes' => (new AcompananteModel())->deRegistro($id),
            'documentos'   => (new DocumentoModel())->deRegistro($id),
            'motivos'      => RegistroModel::MOTIVOS_VIAJE,
            'enlace'       => site_url('registro/' . $registro['token']),
        ]);
    }

    /**
     * Sirve un documento de identidad.
     * Nunca está en la carpeta pública: solo llega aquí personal autenticado.
     */
    public function documento(int $id)
    {
        $doc = (new DocumentoModel())->find($id);
        if ($doc === null) {
            return $this->response->setStatusCode(404)->setBody('Documento no encontrado.');
        }

        $ruta = DocumentoModel::carpeta() . $doc['archivo'];
        if (! is_file($ruta)) {
            return $this->response->setStatusCode(404)->setBody('El archivo ya no está disponible.');
        }

        // Registro de acceso: quién ha visto un documento de identidad y cuándo
        log_message('info', 'Documento de identidad {doc} consultado por el usuario {usuario}.', [
            'doc'     => $id,
            'usuario' => session()->get('usuario_nombre') . ' (#' . session()->get('usuario_id') . ')',
        ]);

        return $this->response
            ->setHeader('Content-Type', $doc['mime'] ?: 'application/octet-stream')
            ->setHeader('Content-Disposition', 'inline; filename="documento-' . $id . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody(file_get_contents($ruta));
    }

    /** Sirve la firma del huésped, igual de protegida que los documentos. */
    public function firma(int $id)
    {
        $registro = $this->registros->find($id);
        if ($registro === null || empty($registro['firma_archivo'])) {
            return $this->response->setStatusCode(404)->setBody('Firma no encontrada.');
        }

        $ruta = WRITEPATH . 'uploads/firmas/' . $registro['firma_archivo'];
        if (! is_file($ruta)) {
            return $this->response->setStatusCode(404)->setBody('El archivo ya no está disponible.');
        }

        return $this->response
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody(file_get_contents($ruta));
    }

    /** Aprueba el registro y vuelca los datos a la ficha del huésped. */
    public function aprobar(int $id)
    {
        $registro = $this->registros->conReserva($id);
        if ($registro === null || $registro['estado'] !== 'enviado') {
            return redirect()->to('registros')->with('error', 'Solo se pueden aprobar registros enviados.');
        }

        $this->registros->update($id, [
            'estado'         => 'aprobado',
            'revisado_por'   => session()->get('usuario_id'),
            'revisado_en'    => date('Y-m-d H:i:s'),
            'motivo_rechazo' => null,
        ]);

        // Los datos verificados pasan a la ficha del huésped
        $notas = trim((string) ($registro['observaciones'] ?? ''));
        if ($notas !== '') {
            (new HuespedModel())->update($registro['huesped_id'], ['notas' => $notas]);
        }

        return redirect()->to('registros/ver/' . $id)
            ->with('ok', 'Registro aprobado. El check-in será inmediato a la llegada.');
    }

    /** Devuelve el registro al huésped indicando qué debe corregir. */
    public function rechazar(int $id)
    {
        $registro = $this->registros->find($id);
        if ($registro === null || $registro['estado'] !== 'enviado') {
            return redirect()->to('registros')->with('error', 'Solo se pueden devolver registros enviados.');
        }

        $motivo = trim((string) $this->request->getPost('motivo'));
        if ($motivo === '') {
            return redirect()->to('registros/ver/' . $id)->with('error', 'Indica qué debe corregir el huésped.');
        }

        $this->registros->update($id, [
            'estado'         => 'rechazado',
            'revisado_por'   => session()->get('usuario_id'),
            'revisado_en'    => date('Y-m-d H:i:s'),
            'motivo_rechazo' => $motivo,
        ]);

        return redirect()->to('registros/ver/' . $id)
            ->with('ok', 'Registro devuelto al huésped con tus indicaciones. Puede volver a enviarlo con el mismo enlace.');
    }

    /** Marca el reporte a las autoridades como realizado. */
    public function marcarReporte(int $id)
    {
        $registro = $this->registros->find($id);
        if ($registro === null) {
            return redirect()->to('registros')->with('error', 'El registro no existe.');
        }

        $cual = (string) $this->request->getPost('cual');
        if (! in_array($cual, ['tra', 'sire'], true)) {
            return redirect()->to('registros/ver/' . $id)->with('error', 'Reporte no válido.');
        }

        $campo = 'reportado_' . $cual;
        $this->registros->update($id, [$campo => $registro[$campo] ? 0 : 1]);

        return redirect()->to('registros/ver/' . $id)->with('ok', 'Marca de reporte actualizada.');
    }

    /** Genera el enlace de registro de una reserva y lo devuelve a recepción. */
    public function generar(int $reservaId)
    {
        $reserva = (new ReservaModel())->find($reservaId);
        if ($reserva === null) {
            return redirect()->to('reservas')->with('error', 'La reserva no existe.');
        }
        if (in_array($reserva['estado'], ['cancelada', 'checkout'], true)) {
            return redirect()->to('reservas/ver/' . $reservaId)
                ->with('error', 'No tiene sentido generar el registro de una reserva cancelada o finalizada.');
        }

        $registro = $this->registros->paraReserva($reserva);

        return redirect()->to('reservas/ver/' . $reservaId)
            ->with('ok', 'Enlace de registro listo: ' . site_url('registro/' . $registro['token']));
    }
}
