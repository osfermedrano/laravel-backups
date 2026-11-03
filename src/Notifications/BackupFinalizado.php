<?php

namespace Osfermedrano\Backups\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Osfermedrano\Backups\Backup\RepositorioBackups;
use Osfermedrano\Backups\Backup\ResultadoBackup;

/**
 * Aviso de fin de backup. Se envía una vez por canal (ver NotificadorBackup)
 * para que una falla de mail no impida la notificación in-app.
 */
class BackupFinalizado extends Notification
{
    public function __construct(
        public ResultadoBackup $resultado,
        public array $canales = ['database'],
    ) {}

    public function via(object $notifiable): array
    {
        return $this->canales;
    }

    public function url(): ?string
    {
        return $this->resultado->ok ? route('backups.descargar', $this->resultado->nombre()) : null;
    }

    public function mensaje(): string
    {
        if (! $this->resultado->ok) {
            return "Backup fallido: {$this->resultado->motivo}";
        }

        $drive = match ($this->resultado->drive) {
            null => '',
            true => ' · Local ✓ · Drive ✓',
            false => " · Local ✓ · Drive ✗ ({$this->resultado->driveMotivo})",
        };

        return "Backup generado: {$this->resultado->nombre()} (".RepositorioBackups::tamanio($this->resultado->bytes).')'.$drive;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'backup',
            'ok' => $this->resultado->ok,
            'mensaje' => $this->mensaje(),
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->resultado->ok ? 'Backup generado' : 'Backup fallido')
            ->line($this->mensaje());

        if ($this->resultado->ok) {
            return $mail->action('Descargar backup', $this->url())
                ->line('El archivo está cifrado: se abre con la contraseña de BACKUP_ARCHIVE_PASSWORD.');
        }

        return $mail->error()->line('Revisá el log del sistema para más detalle.');
    }
}
