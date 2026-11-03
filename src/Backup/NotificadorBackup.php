<?php

namespace Osfermedrano\Backups\Backup;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Osfermedrano\Backups\Notifications\BackupFinalizado;
use Osfermedrano\Backups\Support\MailConfigurado;

/**
 * Avisa el resultado de un backup: in-app siempre; mail solo si está configurado
 * y sin que una falla de SMTP corte nada.
 */
class NotificadorBackup
{
    public function notificar(ResultadoBackup $resultado, ?object $solicitante = null): void
    {
        foreach ($this->destinatarios($solicitante) as $user) {
            $user->notifyNow(new BackupFinalizado($resultado, ['database']));

            if (! MailConfigurado::ok()) {
                continue;
            }

            try {
                $user->notifyNow(new BackupFinalizado($resultado, ['mail']));
            } catch (\Throwable $e) {
                Log::warning("Backup: no se pudo enviar el mail a {$user->email}: {$e->getMessage()}");
            }
        }
    }

    /** Quien lo pidió; si fue automático (o el usuario ya no existe), los gestores. */
    private function destinatarios(?object $solicitante): iterable
    {
        if ($solicitante) {
            return [$solicitante];
        }

        if ($clase = config('backups.destinatarios')) {
            return app($clase)();
        }

        $modelo = config('backups.user_model');
        $gate = config('backups.gate', 'backups.gestionar');

        return $modelo::query()->lazy()->filter(fn ($u) => Gate::forUser($u)->allows($gate));
    }
}
