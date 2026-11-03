<?php

namespace Osfermedrano\Backups\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Osfermedrano\Backups\Backup\EjecutorBackup;
use Osfermedrano\Backups\Backup\EstadoBackup;
use Osfermedrano\Backups\Backup\ResultadoBackup;
use Osfermedrano\Backups\Jobs\GenerarBackupJob;
use Osfermedrano\Backups\Notifications\BackupFinalizado;
use Osfermedrano\Backups\Tests\Fixtures\User;
use Osfermedrano\Backups\Tests\TestCase;

/** CA4, CA6. */
class GenerarBackupJobTest extends TestCase
{
    private function ejecutorQueDevuelve(ResultadoBackup $r): void
    {
        $this->mock(EjecutorBackup::class, fn ($m) => $m->shouldReceive('ejecutar')->once()->andReturn($r));
    }

    private function correr(?int $solicitante): void
    {
        app()->call([new GenerarBackupJob($solicitante), 'handle']);
    }

    // CA4 — fuerza solo-BD y sin notificaciones de Spatie, sin depender de la config del proyecto
    /**
     * Reemplaza backup:run de Spatie por uno falso (la consola de Testbench es final: no se mockea Artisan).
     *
     * @return \ArrayObject opciones con que lo llamaron
     */
    private function backupRunFalso(callable $hacer): \ArrayObject
    {
        $llamadas = new \ArrayObject;
        $cmd = new class($hacer, $llamadas) extends \Illuminate\Console\Command
        {
            protected $signature = 'backup:run {--only-db} {--only-files} {--disable-notifications} {--filename=} {--only-to-disk=} {--timeout=}';

            public function __construct(private $hacer, private \ArrayObject $llamadas)
            {
                parent::__construct();
            }

            public function handle(): int
            {
                $this->llamadas->append(['only-db' => $this->option('only-db'), 'disable-notifications' => $this->option('disable-notifications')]);

                return ($this->hacer)($this);
            }
        };
        $this->app[\Illuminate\Contracts\Console\Kernel::class]->registerCommand($cmd);

        return $llamadas;
    }

    public function test_ejecutor_corre_spatie_solo_bd_y_sin_sus_notificaciones()
    {
        $llamadas = $this->backupRunFalso(function ($cmd) {
            $this->backupFalso('2026-11-02-03-00-00', 4096);
            $cmd->line('Backup completed!');

            return 0;
        });

        $r = app(EjecutorBackup::class)->ejecutar();

        $this->assertSame([['only-db' => true, 'disable-notifications' => true]], $llamadas->getArrayCopy());
        $this->assertTrue($r->ok);
        $this->assertSame('2026-11-02-03-00-00.zip', $r->nombre());
        $this->assertSame(4096, $r->bytes);
    }

    public function test_ejecutor_traduce_falta_de_mysqldump()
    {
        $this->backupRunFalso(function ($cmd) {
            $cmd->line("Backup failed because: 'mysqldump' is not recognized as an internal command");

            return 1;
        });

        $r = app(EjecutorBackup::class)->ejecutar();

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('DB_DUMP_BINARY_PATH', $r->motivo);
    }

    // CA4
    public function test_sin_password_no_ejecuta_y_notifica_fallo()
    {
        Notification::fake();
        config(['backup.backup.password' => null]);
        $this->mock(EjecutorBackup::class, fn ($m) => $m->shouldNotReceive('ejecutar'));
        $admin = $this->admin();

        $this->correr($admin->id);

        Notification::assertSentTo($admin, BackupFinalizado::class,
            fn ($n) => ! $n->resultado->ok && str_contains($n->resultado->motivo, 'contraseña'));
    }

    public function test_exito_notifica_database_al_solicitante()
    {
        Notification::fake();
        $admin = $this->admin();
        $otro = $this->admin();
        $this->ejecutorQueDevuelve(ResultadoBackup::ok('mi-app/2026-11-02-03-00-00.zip', 2048));

        $this->correr($admin->id);

        Notification::assertSentTo($admin, BackupFinalizado::class, fn ($n, $ch) => $n->resultado->ok && in_array('database', $ch));
        Notification::assertNotSentTo($otro, BackupFinalizado::class);
    }

    public function test_automatico_notifica_a_todos_los_que_pasan_el_gate()
    {
        Notification::fake();
        $a1 = $this->admin();
        $a2 = $this->admin();
        $comun = $this->usuarioComun();
        $this->ejecutorQueDevuelve(ResultadoBackup::ok('mi-app/x.zip', 10));

        $this->correr(null);

        Notification::assertSentTo([$a1, $a2], BackupFinalizado::class);
        Notification::assertNotSentTo($comun, BackupFinalizado::class);
    }

    public function test_solicitante_borrado_notifica_a_gestores()
    {
        Notification::fake();
        $admin = $this->admin();
        $this->ejecutorQueDevuelve(ResultadoBackup::ok('mi-app/x.zip', 10));

        $this->correr(999999);

        Notification::assertSentTo($admin, BackupFinalizado::class);
    }

    public function test_fallo_notifica_motivo_y_libera_estado()
    {
        Notification::fake();
        $admin = $this->admin();
        app(EstadoBackup::class)->marcar($admin->id);
        $this->ejecutorQueDevuelve(ResultadoBackup::fallo('No se encontró mysqldump'));

        $this->correr($admin->id);

        Notification::assertSentTo($admin, BackupFinalizado::class, fn ($n) => $n->resultado->motivo === 'No se encontró mysqldump');
        $this->assertFalse(app(EstadoBackup::class)->enProceso());
    }

    public function test_excepcion_inesperada_notifica_y_libera_estado()
    {
        Notification::fake();
        $admin = $this->admin();
        app(EstadoBackup::class)->marcar($admin->id);
        $this->mock(EjecutorBackup::class, fn ($m) => $m->shouldReceive('ejecutar')->andThrow(new \RuntimeException('disco lleno')));

        $this->correr($admin->id);

        Notification::assertSentTo($admin, BackupFinalizado::class, fn ($n) => ! $n->resultado->ok);
        $this->assertFalse(app(EstadoBackup::class)->enProceso());
    }

    public function test_mail_exitoso_tiene_boton_descargar_y_fallido_no()
    {
        $url = route('backups.descargar', '2026-11-02-03-00-00.zip');

        $ok = new BackupFinalizado(ResultadoBackup::ok('mi-app/2026-11-02-03-00-00.zip', 2048));
        $this->assertStringContainsString($url, (string) $ok->toMail(new User)->render());
        $this->assertSame($url, $ok->toArray(new User)['url']);

        $mal = new BackupFinalizado(ResultadoBackup::fallo('disco lleno'));
        $this->assertStringNotContainsString('/descargar', (string) $mal->toMail(new User)->render());
        $this->assertNull($mal->toArray(new User)['url']);
    }

    public function test_notificacion_database_tiene_mensaje_legible_y_formato_estable()
    {
        $ok = (new BackupFinalizado(ResultadoBackup::ok('mi-app/2026-11-02-03-00-00.zip', 3 * 1024 * 1024)))->toArray(new User);
        $this->assertStringContainsString('Backup generado', $ok['mensaje']);
        $this->assertStringContainsString('3 MB', $ok['mensaje']);
        // Claves que usan las campanas de los proyectos: no cambiarlas
        $this->assertSame(['tipo', 'ok', 'mensaje', 'url'], array_keys($ok));

        $mal = (new BackupFinalizado(ResultadoBackup::fallo('disco lleno')))->toArray(new User);
        $this->assertStringContainsString('Backup fallido: disco lleno', $mal['mensaje']);
    }
}
