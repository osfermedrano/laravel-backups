<?php

namespace Osfermedrano\Backups\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Osfermedrano\Backups\Backup\NotificadorBackup;
use Osfermedrano\Backups\Backup\ResultadoBackup;
use Osfermedrano\Backups\Notifications\BackupFinalizado;
use Osfermedrano\Backups\Support\MailConfigurado;
use Osfermedrano\Backups\Tests\TestCase;

/** CA6 — mail best-effort. */
class NotificadorBackupTest extends TestCase
{
    public function test_mail_configurado_segun_mailer_y_host()
    {
        config(['mail.default' => 'array']);
        $this->assertFalse(MailConfigurado::ok());

        config(['mail.default' => 'log']);
        $this->assertFalse(MailConfigurado::ok());

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '']);
        $this->assertFalse(MailConfigurado::ok());

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.gmail.com']);
        $this->assertTrue(MailConfigurado::ok());
    }

    public function test_sin_mail_igual_notifica_database()
    {
        Notification::fake();
        config(['mail.default' => 'array']);
        $admin = $this->admin();

        app(NotificadorBackup::class)->notificar(ResultadoBackup::ok('mi-app/x.zip', 10), $admin);

        Notification::assertSentTo($admin, BackupFinalizado::class, fn ($n, $ch) => $ch === ['database']);
        Notification::assertSentTimes(BackupFinalizado::class, 1);
    }

    public function test_con_mail_envia_database_y_mail_por_separado()
    {
        Notification::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.gmail.com']);
        $admin = $this->admin();

        app(NotificadorBackup::class)->notificar(ResultadoBackup::ok('mi-app/x.zip', 10), $admin);

        Notification::assertSentTo($admin, BackupFinalizado::class, fn ($n, $ch) => $ch === ['database']);
        Notification::assertSentTo($admin, BackupFinalizado::class, fn ($n, $ch) => $ch === ['mail']);
    }

    public function test_smtp_caido_no_rompe_y_queda_la_notificacion_database()
    {
        Log::spy();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $admin = $this->admin();

        app(NotificadorBackup::class)->notificar(ResultadoBackup::ok('mi-app/x.zip', 10), $admin);

        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'mail'))->once();
    }
}
