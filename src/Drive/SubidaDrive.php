<?php

namespace Osfermedrano\Backups\Drive;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Osfermedrano\Backups\Backup\RepositorioBackups;
use Osfermedrano\Backups\Backup\ResultadoBackup;

/**
 * Copia a Drive un backup local exitoso. Nunca lanza: una falla de Drive
 * no convierte en fallido un backup local que salió bien.
 */
class SubidaDrive
{
    public function __construct(
        private ConexionDrive $conexion,
        private DestinoDrive $destino,
        private RepositorioBackups $repositorio,
    ) {}

    public function subir(ResultadoBackup $resultado): ResultadoBackup
    {
        if (! $resultado->ok || ! $this->conexion->conectado()) {
            return $resultado;
        }

        try {
            $this->destino->subir($this->repositorio->disco()->path($resultado->archivo), $resultado->nombre());

            return $resultado->conDrive(true);
        } catch (\Throwable $e) {
            Log::warning('Backup: falló la subida a Google Drive: '.$e->getMessage());

            return $resultado->conDrive(false, $this->motivo($e));
        }
    }

    /** Motivo corto y accionable; si el token fue revocado, marca la conexión inválida. */
    public function motivo(\Throwable $e): string
    {
        if (Str::contains($e->getMessage(), 'invalid_grant')) {
            $this->conexion->marcarInvalido('Token revocado o vencido');

            return 'token revocado o vencido: reconectar Google Drive';
        }

        return Str::limit($e->getMessage(), 150);
    }
}
