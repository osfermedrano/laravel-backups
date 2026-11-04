<?php

namespace Osfermedrano\Backups\Jobs;

use Osfermedrano\Backups\Backup\EmpaquetadorAdjuntos;
use Osfermedrano\Backups\Backup\ResultadoBackup;

/**
 * Backup de la carpeta de adjuntos en cola. Mismo flujo que el de BD:
 * contraseña obligatoria, copia a Drive, aviso y marca "en proceso" propia.
 */
class GenerarBackupAdjuntosJob extends GenerarBackupJob
{
    protected function tipo(): string
    {
        return 'adjuntos';
    }

    protected function generar(): ResultadoBackup
    {
        return app(EmpaquetadorAdjuntos::class)->ejecutar();
    }
}
