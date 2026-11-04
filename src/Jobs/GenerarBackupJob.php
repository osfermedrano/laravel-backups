<?php

namespace Osfermedrano\Backups\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Osfermedrano\Backups\Backup\EjecutorBackup;
use Osfermedrano\Backups\Backup\EstadoBackup;
use Osfermedrano\Backups\Backup\NotificadorBackup;
use Osfermedrano\Backups\Backup\ResultadoBackup;
use Osfermedrano\Backups\Drive\SubidaDrive;

/**
 * Backup de la BD en cola. $solicitanteId null = automático (scheduler).
 */
class GenerarBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public ?int $solicitanteId = null) {}

    public function handle(NotificadorBackup $notificador, EstadoBackup $estado): void
    {
        if ($this->solicitanteId === null) {
            $estado->marcar(null, $this->tipo()); // automático: bloquea el botón manual mientras corre
        }

        try {
            $resultado = blank(config('backup.backup.password'))
                ? ResultadoBackup::fallo('Falta configurar la contraseña de cifrado (BACKUP_ARCHIVE_PASSWORD en .env). No se generan backups sin cifrar.')
                : $this->generar();

            // Copia remota (solo si el local salió bien y Drive está conectado). Nunca lanza.
            $resultado = app(SubidaDrive::class)->subir($resultado);
        } catch (\Throwable $e) {
            Log::error('Backup: error inesperado', ['exception' => $e]);
            $resultado = ResultadoBackup::fallo('Error inesperado: '.$e->getMessage());
        } finally {
            $estado->liberar($this->tipo());
        }

        if (! $resultado->ok) {
            Log::error('Backup fallido: '.$resultado->motivo);
        }

        $notificador->notificar($resultado, $this->solicitante());
    }

    /** Si el worker mata el job (timeout), no dejar la marca "en proceso" colgada. */
    public function failed(?\Throwable $e): void
    {
        app(EstadoBackup::class)->liberar($this->tipo());
    }

    protected function tipo(): string
    {
        return 'bd';
    }

    protected function generar(): ResultadoBackup
    {
        return app(EjecutorBackup::class)->ejecutar();
    }

    private function solicitante(): ?object
    {
        if ($this->solicitanteId === null) {
            return null;
        }

        $modelo = config('backups.user_model');

        return $modelo::find($this->solicitanteId);
    }
}
