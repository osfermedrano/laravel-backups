<?php

namespace Osfermedrano\Backups\Tests\Feature;

use Osfermedrano\Backups\Backup\ResultadoBackup;
use Osfermedrano\Backups\Notifications\BackupFinalizado;
use Osfermedrano\Backups\Tests\TestCase;

/** Diseño del mail de aviso: plantilla propia con el detalle del resultado. */
class MailBackupTest extends TestCase
{
    private function html(ResultadoBackup $resultado): string
    {
        $mail = (new BackupFinalizado($resultado, ['mail']))->toMail($this->admin());

        return (string) $mail->render();
    }

    public function test_mail_exitoso_muestra_detalle_y_link_de_descarga()
    {
        config(['app.name' => 'Mi Sistema']);
        $resultado = ResultadoBackup::ok('mi-app/2026-11-03-03-00-00.zip', 2 * 1024 * 1024)->conDrive(true);
        $mail = (new BackupFinalizado($resultado, ['mail']))->toMail($this->admin());
        $html = (string) $mail->render();

        $this->assertSame('✓ Backup generado · Mi Sistema', $mail->subject);
        $this->assertStringContainsString('Backup generado', $html);
        $this->assertStringContainsString('2026-11-03-03-00-00.zip', $html);
        $this->assertStringContainsString('2 MB', $html);
        $this->assertStringContainsString('Google Drive', $html);
        $this->assertStringContainsString(route('backups.descargar', '2026-11-03-03-00-00.zip'), $html);
        $this->assertStringContainsString('BACKUP_ARCHIVE_PASSWORD', $html);
    }

    public function test_mail_exitoso_avisa_si_fallo_la_copia_a_drive()
    {
        $html = $this->html(ResultadoBackup::ok('mi-app/2026-11-03-03-00-00.zip', 1024)->conDrive(false, 'token vencido'));

        $this->assertStringContainsString('token vencido', $html);
    }

    public function test_mail_fallido_muestra_motivo_y_que_revisar()
    {
        config(['app.name' => 'Mi Sistema']);
        $resultado = ResultadoBackup::fallo('No se encontró mysqldump');
        $mail = (new BackupFinalizado($resultado, ['mail']))->toMail($this->admin());
        $html = (string) $mail->render();

        $this->assertSame('✗ Backup fallido · Mi Sistema', $mail->subject);
        $this->assertStringContainsString('Backup fallido', $html);
        $this->assertStringContainsString('No se encontró mysqldump', $html);
        $this->assertStringContainsString(route('backups.index'), $html);
        $this->assertStringNotContainsString('/descargar', $html);
    }
}
