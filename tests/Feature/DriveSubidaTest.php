<?php

namespace Osfermedrano\Backups\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Osfermedrano\Backups\Backup\EjecutorBackup;
use Osfermedrano\Backups\Backup\ResultadoBackup;
use Osfermedrano\Backups\Drive\ConexionDrive;
use Osfermedrano\Backups\Drive\DestinoDrive;
use Osfermedrano\Backups\Jobs\GenerarBackupJob;
use Osfermedrano\Backups\Notifications\BackupFinalizado;
use Osfermedrano\Backups\Tests\TestCase;

/**
 * Fase 2 — CA13, CA14, CA15. Spec: docs/specs/2026-11-02-backup-bd.md
 */
class DriveSubidaTest extends TestCase
{
    private string $archivo;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->archivo = $this->backupFalso('2026-11-02-03-00-00');
    }

    private function conectado(): void
    {
        app(ConexionDrive::class)->guardar('rt-1', 'yo@gmail.com');
    }

    private function backupLocalOk(): void
    {
        $this->mock(EjecutorBackup::class, fn ($m) => $m->shouldReceive('ejecutar')->andReturn(ResultadoBackup::ok($this->archivo, 2048)));
    }

    private function correrJob(): void
    {
        app()->call([new GenerarBackupJob($this->admin()->id), 'handle']);
    }

    private function notificado(callable $cond): void
    {
        Notification::assertSentTo(\Osfermedrano\Backups\Tests\Fixtures\User::first(), BackupFinalizado::class, fn ($n) => $cond($n->resultado, $n->mensaje()));
    }

    // CA13
    public function test_local_ok_y_drive_conectado_sube_y_notifica_ambos()
    {
        $this->conectado();
        $this->backupLocalOk();
        $ruta = Storage::disk('local')->path($this->archivo);
        $this->mock(DestinoDrive::class, fn ($m) => $m->shouldReceive('subir')->once()->with($ruta, '2026-11-02-03-00-00.zip'));

        $this->correrJob();

        $this->notificado(fn ($r, $msg) => $r->ok && $r->drive === true && str_contains($msg, 'Local ✓ · Drive ✓'));
    }

    // CA14
    public function test_drive_falla_local_queda_ok_y_notifica_motivo()
    {
        $this->conectado();
        $this->backupLocalOk();
        $this->mock(DestinoDrive::class, fn ($m) => $m->shouldReceive('subir')->andThrow(new \RuntimeException('sin conexión')));

        $this->correrJob();

        $this->notificado(fn ($r, $msg) => $r->ok && $r->drive === false && str_contains($msg, 'Local ✓ · Drive ✗ (sin conexión)'));
        Storage::disk('local')->assertExists($this->archivo);
    }

    public function test_token_revocado_marca_conexion_invalida()
    {
        $this->conectado();
        $this->backupLocalOk();
        $this->mock(DestinoDrive::class, fn ($m) => $m->shouldReceive('subir')->andThrow(new \RuntimeException('{"error": "invalid_grant", "error_description": "Token has been expired or revoked."}')));

        $this->correrJob();

        $conexion = app(ConexionDrive::class);
        $this->assertSame(ConexionDrive::INVALIDO, $conexion->estado());
        $this->assertFalse($conexion->conectado());
        $this->notificado(fn ($r, $msg) => str_contains($msg, 'Drive ✗') && str_contains($msg, 'reconectar'));
    }

    public function test_drive_no_conectado_no_intenta_ni_menciona_drive()
    {
        $this->backupLocalOk();
        $this->mock(DestinoDrive::class, fn ($m) => $m->shouldNotReceive('subir'));

        $this->correrJob();

        $this->notificado(fn ($r, $msg) => $r->ok && $r->drive === null && ! str_contains($msg, 'Drive'));
    }

    public function test_backup_local_fallido_no_sube()
    {
        $this->conectado();
        $this->mock(EjecutorBackup::class, fn ($m) => $m->shouldReceive('ejecutar')->andReturn(ResultadoBackup::fallo('disco lleno')));
        $this->mock(DestinoDrive::class, fn ($m) => $m->shouldNotReceive('subir'));

        $this->correrJob();

        $this->notificado(fn ($r) => ! $r->ok);
    }

    // CA15
    public function test_limpieza_borra_en_drive_solo_lo_que_ya_no_esta_en_local()
    {
        $this->conectado();
        $this->backupFalso('2026-11-01-03-00-00');
        $this->mock(DestinoDrive::class, function ($m) {
            $m->shouldReceive('listar')->andReturn([
                '2026-11-02-03-00-00.zip', '2026-11-01-03-00-00.zip', // siguen en local
                '2026-10-01-03-00-00.zip',                            // limpiado en local
                'notas-mias.txt',                                     // ajeno al patrón: nunca se toca
            ]);
            $m->shouldReceive('borrar')->once()->with('2026-10-01-03-00-00.zip');
        });

        $this->assertSame(0, Artisan::call('backups:limpiar-drive'));
    }

    public function test_limpieza_sin_conexion_no_hace_nada()
    {
        $this->mock(DestinoDrive::class, fn ($m) => $m->shouldNotReceive('listar'));

        $this->assertSame(0, Artisan::call('backups:limpiar-drive'));
    }

    public function test_limpieza_drive_programada_despues_de_la_local()
    {
        $evento = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'backups:limpiar-drive'));

        $this->assertNotNull($evento);
        $this->assertSame('40 3 * * *', $evento->expression);
    }
}
