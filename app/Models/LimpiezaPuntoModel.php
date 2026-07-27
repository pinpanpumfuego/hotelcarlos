<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lo que se repasó en una limpieza concreta.
 *
 * El texto se copia del checklist en lugar de enlazarse: si mañana cambia el
 * checklist, lo que se hizo ayer tiene que seguir diciendo lo que decía. Una
 * lista que se reescribe hacia atrás no sirve para responder a una reclamación.
 */
class LimpiezaPuntoModel extends Model
{
    protected $table         = 'limpieza_puntos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['limpieza_id', 'punto_id', 'texto', 'exige_foto', 'hecho', 'foto', 'nota'];
    protected $useTimestamps = true;

    public function deTarea(int $limpiezaId): array
    {
        return $this->where('limpieza_id', $limpiezaId)->orderBy('id')->findAll();
    }

    /**
     * Puntos críticos marcados como hechos pero sin foto.
     *
     * Es lo que impide terminar la tarea: la foto es lo que convierte un «ya
     * está» en algo comprobable.
     */
    public function criticosSinFoto(int $limpiezaId): array
    {
        return $this->where('limpieza_id', $limpiezaId)
            ->where('exige_foto', 1)
            ->groupStart()
                ->where('foto', null)
                ->orWhere('foto', '')
            ->groupEnd()
            ->findAll();
    }

    public function sinHacer(int $limpiezaId): array
    {
        return $this->where('limpieza_id', $limpiezaId)->where('hecho', 0)->findAll();
    }

    /** Marca un punto, con su foto y su nota. */
    public function marcar(int $id, bool $hecho, ?string $foto = null, ?string $nota = null): bool
    {
        $datos = ['hecho' => $hecho ? 1 : 0];

        if ($foto !== null) {
            $datos['foto'] = $foto;
        }
        if ($nota !== null) {
            $datos['nota'] = mb_substr($nota, 0, 300) ?: null;
        }

        return (bool) $this->update($id, $datos);
    }

    /**
     * Devuelve todos los puntos a sin hacer.
     *
     * Se usa al rechazar una tarea: hay que repasarlos otra vez, no dar por
     * buenos los de la vez anterior. Las fotos se conservan porque son la
     * prueba de lo que se hizo entonces.
     */
    public function reiniciar(int $limpiezaId): void
    {
        $this->builder()->where('limpieza_id', $limpiezaId)->update(['hecho' => 0]);
    }

    /** Cuántos van y cuántos faltan, para la barra de progreso del móvil. */
    public function progreso(int $limpiezaId): array
    {
        $todos  = $this->where('limpieza_id', $limpiezaId)->countAllResults();
        $hechos = $this->where('limpieza_id', $limpiezaId)->where('hecho', 1)->countAllResults();

        return [
            'total'  => $todos,
            'hechos' => $hechos,
            'pct'    => $todos > 0 ? (int) round($hechos / $todos * 100) : 0,
        ];
    }
}
