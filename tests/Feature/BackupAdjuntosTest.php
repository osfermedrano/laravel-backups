<?php

namespace Osfermedrano\Backups\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Osfermedrano\Backups\Backup\EmpaquetadorAdjuntos;
use Osfermedrano\Backups\Backup\EstadoBackup;
use Osfermedrano\Backups\Backup\RepositorioBackups;
use Osfermedrano\Backups\Backup\ResultadoBackup;
use Osfermedrano\Backups\BackupsServiceProvider;
use Osfermedrano\Backups\Drive\ConexionDrive;
use Osfermedrano\Backups\Drive\DestinoDrive;
use Osfermedrano\Backups\Jobs\GenerarBackupAdjuntosJob;
use Osfermedrano\Backups\Jobs\GenerarBackupJob;
use Osfermedrano\Backups\Notifications\BackupFinalizado;
use Osfermedrano\Backups\Tests\TestCase;
use ZipArchive;

/** Spec 2026-11-04-backup-adjuntos. */
class BackupAdjuntosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['backups.adjuntos' => ['activo' => true, 'disco' => 'local', 'ruta' => 'adjuntos', 'conservar' => 4]]);
    }

    private function adjunto(string $ruta, string $contenido = 'contenido'): void
    {
        Storage::disk('local')->put("adjuntos/{$ruta}", $contenido);
    }

    private function zipAdjuntos(string $fecha): string
    {
        $ruta = "mi-app-adjuntos/adjuntos-{$fecha}.zip";
        Storage::disk('local')->put($ruta, 'zip');

        return $ruta;
    }

    private function generar(): ResultadoBackup
    {
        return app(EmpaquetadorAdjuntos::class)->ejecutar();
    }

    private function abrir(ResultadoBackup $r): ZipArchive
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($r->archivo)));

        return $zip;
    }

    private function nombres(ZipArchive $zip): array
    {
        return array_map(fn ($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
    }

    // CA1
    public function test_viene_apagado_por_defecto()
    {
        $config = require __DIR__.'/../../config/backups.php';

        $this->assertFalse(filter_var($config['adjuntos']['activo'], FILTER_VALIDATE_BOOL));
    }

    public function test_apagado_el_panel_y_la_generacion_quedan_solo_con_bd()
    {
        Queue::fake();
        config(['backups.adjuntos.activo' => false]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('backups.index'))->assertDontSee('name="tipos[]"', false);
        $this->actingAs($admin)->post(route('backups.generar'), ['tipos' => ['adjuntos']])->assertSessionHas('backups_mensaje');

        Queue::assertPushed(GenerarBackupJob::class, 1);
        Queue::assertNotPushed(GenerarBackupAdjuntosJob::class);
    }

    public function test_apagado_no_programa_adjuntos()
    {
        config(['backups.adjuntos.activo' => false]);

        $this->assertCount(3, BackupsServiceProvider::programar(new Schedule)->events());
    }

    // CA2
    public function test_panel_ofrece_elegir_bd_y_adjuntos()
    {
        $this->actingAs($this->admin())->get(route('backups.index'))
            ->assertSee('name="tipos[]" value="bd"', false)
            ->assertSee('name="tipos[]" value="adjuntos"', false);
    }

    public function test_elegir_ambos_encola_un_job_por_tipo()
    {
        Queue::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('backups.generar'), ['tipos' => ['bd', 'adjuntos']])->assertSessionHas('backups_mensaje');

        Queue::assertPushed(GenerarBackupJob::class, fn ($j) => $j->solicitanteId === $admin->id);
        Queue::assertPushed(GenerarBackupAdjuntosJob::class, fn ($j) => $j->solicitanteId === $admin->id);
    }

    public function test_elegir_solo_adjuntos_no_encola_bd()
    {
        Queue::fake();

        $this->actingAs($this->admin())->post(route('backups.generar'), ['tipos' => ['adjuntos']]);

        Queue::assertPushed(GenerarBackupAdjuntosJob::class, 1);
        Queue::assertNotPushed(GenerarBackupJob::class);
    }

    public function test_elegir_solo_bd_no_encola_adjuntos()
    {
        Queue::fake();

        $this->actingAs($this->admin())->post(route('backups.generar'), ['tipos' => ['bd']]);

        Queue::assertPushed(GenerarBackupJob::class, 1);
        Queue::assertNotPushed(GenerarBackupAdjuntosJob::class);
    }

    // CA3
    public function test_sin_tipo_valido_no_encola_y_muestra_error()
    {
        Queue::fake();
        $admin = $this->admin();

        foreach ([[], ['tipos' => ['otro']]] as $datos) {
            $this->actingAs($admin)->post(route('backups.generar'), $datos)->assertSessionHas('backups_error');
        }

        Queue::assertNothingPushed();
        $this->assertFalse(app(EstadoBackup::class)->enProceso());
    }

    // CA4
    public function test_zip_conserva_carpetas_y_va_cifrado_con_aes_256()
    {
        $this->adjunto('vecinos/1/dni.pdf', 'datos sensibles');

        $r = $this->generar();

        $this->assertTrue($r->ok, (string) $r->motivo);
        $zip = $this->abrir($r);
        $this->assertSame(['vecinos/1/dni.pdf'], $this->nombres($zip));
        $this->assertSame(ZipArchive::EM_AES_256, $zip->statName('vecinos/1/dni.pdf')['encryption_method']);
        $this->assertFalse($zip->getFromName('vecinos/1/dni.pdf'), 'Sin contraseña no debe leerse');

        $zip->setPassword('secreto-de-prueba');
        $this->assertSame('datos sensibles', $zip->getFromName('vecinos/1/dni.pdf'));
    }

    public function test_sin_contrasena_no_genera_zip()
    {
        Notification::fake();
        config(['backup.backup.password' => null]);
        $this->adjunto('a.pdf');
        $admin = $this->admin();

        app()->call([new GenerarBackupAdjuntosJob($admin->id), 'handle']);

        $this->assertCount(0, app(RepositorioBackups::class)->listarAdjuntos());
        Notification::assertSentTo($admin, BackupFinalizado::class, fn ($n) => ! $n->resultado->ok);
    }

    // CA5
    public function test_zip_incluye_solo_la_carpeta_de_adjuntos()
    {
        $this->adjunto('a.pdf');
        Storage::disk('local')->put('.env', 'APP_KEY=secreto');
        Storage::disk('local')->put('otros/privado.pdf', 'x');
        $this->backupFalso('2026-11-02-03-00-00');

        $this->assertSame(['a.pdf'], $this->nombres($this->abrir($this->generar())));
    }

    public function test_ruta_vacia_falla_sin_generar_zip()
    {
        config(['backups.adjuntos.ruta' => '']);
        $this->adjunto('a.pdf');

        $r = $this->generar();

        $this->assertFalse($r->ok);
        $this->assertStringContainsString('backups.adjuntos.ruta', $r->motivo);
        $this->assertCount(0, app(RepositorioBackups::class)->listarAdjuntos());
    }

    // CA6
    public function test_sin_archivos_falla_con_motivo_y_no_deja_zip()
    {
        $r = $this->generar();

        $this->assertFalse($r->ok);
        $this->assertSame('No hay adjuntos para respaldar.', $r->motivo);
        $this->assertCount(0, app(RepositorioBackups::class)->listarAdjuntos());
    }

    // CA7
    public function test_conserva_solo_los_ultimos_tras_un_backup_exitoso()
    {
        config(['backups.adjuntos.conservar' => 2]);
        $this->travelTo('2026-11-04 04:00:00');
        $this->adjunto('a.pdf');
        foreach (['2026-10-01-04-00-00', '2026-10-08-04-00-00', '2026-10-15-04-00-00'] as $fecha) {
            $this->zipAdjuntos($fecha);
        }

        $this->generar();

        $this->assertSame(
            ['adjuntos-2026-11-04-04-00-00.zip', 'adjuntos-2026-10-15-04-00-00.zip'],
            app(RepositorioBackups::class)->listarAdjuntos()->pluck('nombre')->all()
        );
    }

    public function test_un_backup_fallido_no_borra_los_anteriores()
    {
        config(['backups.adjuntos.conservar' => 1]);
        $this->zipAdjuntos('2026-10-01-04-00-00');
        $this->zipAdjuntos('2026-10-08-04-00-00');

        $this->generar();

        $this->assertCount(2, app(RepositorioBackups::class)->listarAdjuntos());
    }

    public function test_retencion_de_adjuntos_no_toca_backups_de_bd()
    {
        config(['backups.adjuntos.conservar' => 1]);
        $this->adjunto('a.pdf');
        $bd = $this->backupFalso('2026-10-01-03-00-00');

        $this->generar();
        $this->travel(1)->minutes();
        $this->generar();

        Storage::disk('local')->assertExists($bd);
        $this->assertCount(1, app(RepositorioBackups::class)->listar());
    }

    // CA8
    public function test_panel_lista_adjuntos_aparte_con_descarga()
    {
        $this->zipAdjuntos('2026-10-15-04-00-00');

        $this->actingAs($this->admin())->get(route('backups.index'))
            ->assertSee('data-seccion="adjuntos"', false)
            ->assertSee(route('backups.descargar', 'adjuntos-2026-10-15-04-00-00.zip'), false)
            ->assertSee('Sin backups');
    }

    public function test_panel_separa_base_de_datos_y_adjuntos_con_su_encabezado()
    {
        Storage::disk('local')->put('mi-app/2026-10-14-03-00-00.zip', 'bd');
        $this->zipAdjuntos('2026-10-15-04-00-00');

        $this->actingAs($this->admin())->get(route('backups.index'))
            ->assertDontSee('Últimos backups')
            ->assertSeeInOrder([
                'Generar backup',
                'name="tipos[]" value="bd"',
                'name="tipos[]" value="adjuntos"',
                'data-seccion="bd"',
                'Base de datos</h3>',
                route('backups.descargar', '2026-10-14-03-00-00.zip'),
                'data-seccion="adjuntos"',
                'Adjuntos</h3>',
                route('backups.descargar', 'adjuntos-2026-10-15-04-00-00.zip'),
            ], false);
    }

    public function test_adjuntos_marca_el_mas_reciente_igual_que_base_de_datos()
    {
        $this->zipAdjuntos('2026-10-15-04-00-00');

        $this->actingAs($this->admin())->get(route('backups.index'))
            ->assertSeeInOrder(['data-seccion="adjuntos"', 'Más reciente'], false);
    }

    public function test_sin_adjuntos_activos_el_panel_no_cambia()
    {
        config(['backups.adjuntos.activo' => false]);

        $this->actingAs($this->admin())->get(route('backups.index'))
            ->assertSee('Últimos backups')
            ->assertSee('Generar backup ahora')
            ->assertDontSee('data-seccion="adjuntos"', false)
            ->assertDontSee('Base de datos</h3>', false);
    }

    public function test_listado_de_bd_no_incluye_adjuntos()
    {
        $this->zipAdjuntos('2026-10-15-04-00-00');

        $this->assertCount(0, app(RepositorioBackups::class)->listar());
    }

    public function test_descarga_de_adjuntos_requiere_permiso()
    {
        $this->zipAdjuntos('2026-10-15-04-00-00');
        $url = route('backups.descargar', 'adjuntos-2026-10-15-04-00-00.zip');

        $this->actingAs($this->usuarioComun())->get($url)->assertForbidden();
        $this->actingAs($this->admin())->get($url)->assertOk();
    }

    public function test_buscar_rechaza_nombres_que_no_son_backups()
    {
        $this->zipAdjuntos('2026-10-15-04-00-00');
        $repositorio = app(RepositorioBackups::class);

        $this->assertNull($repositorio->buscar('../adjuntos-2026-10-15-04-00-00.zip'));
        $this->assertNull($repositorio->buscar('adjuntos-x.zip'));
    }

    // CA9
    public function test_zip_de_adjuntos_se_sube_a_drive()
    {
        Notification::fake();
        $this->adjunto('a.pdf');
        $this->mock(ConexionDrive::class, fn ($m) => $m->shouldReceive('conectado')->andReturn(true));
        $this->mock(DestinoDrive::class, fn ($m) => $m->shouldReceive('subir')->once()
            ->withArgs(fn ($ruta, $nombre) => is_file($ruta) && str_starts_with($nombre, 'adjuntos-')));

        app()->call([new GenerarBackupAdjuntosJob($this->admin()->id), 'handle']);
    }

    public function test_limpiar_drive_conserva_adjuntos_locales_y_borra_los_vencidos()
    {
        $this->zipAdjuntos('2026-10-15-04-00-00');
        $this->mock(ConexionDrive::class, fn ($m) => $m->shouldReceive('conectado')->andReturn(true));
        $this->mock(DestinoDrive::class, function ($m) {
            $m->shouldReceive('listar')->andReturn(['adjuntos-2026-10-15-04-00-00.zip', 'adjuntos-2026-10-01-04-00-00.zip', 'ajeno.zip']);
            $m->shouldReceive('borrar')->once()->with('adjuntos-2026-10-01-04-00-00.zip');
        });

        Artisan::call('backups:limpiar-drive');
    }

    // CA10
    public function test_programa_adjuntos_una_vez_por_semana_sin_duplicar_bd()
    {
        $eventos = collect(BackupsServiceProvider::programar(new Schedule)->events());

        $adjuntos = $eventos->filter(fn ($e) => str_contains((string) $e->description, 'GenerarBackupAdjuntosJob'));

        $this->assertCount(1, $adjuntos);
        $this->assertSame('0 4 * * 0', $adjuntos->first()->expression);
        $this->assertCount(1, $eventos->filter(fn ($e) => str_contains((string) $e->description, 'GenerarBackupJob')));
    }

    public function test_dia_y_hora_de_adjuntos_son_configurables()
    {
        config(['backups.programacion.adjuntos_dia' => 6, 'backups.programacion.adjuntos_hora' => '05:30']);

        $evento = collect(BackupsServiceProvider::programar(new Schedule)->events())
            ->first(fn ($e) => str_contains((string) $e->description, 'GenerarBackupAdjuntosJob'));

        $this->assertSame('30 5 * * 6', $evento->expression);
    }

    // CA11
    public function test_fallo_de_adjuntos_avisa_y_no_afecta_al_backup_de_bd()
    {
        Notification::fake();
        $admin = $this->admin();
        $bd = $this->backupFalso('2026-11-02-03-00-00');

        app()->call([new GenerarBackupAdjuntosJob($admin->id), 'handle']);

        Notification::assertSentTo($admin, BackupFinalizado::class, fn ($n) => $n->resultado->motivo === 'No hay adjuntos para respaldar.');
        Storage::disk('local')->assertExists($bd);
    }

    public function test_exito_avisa_con_link_al_zip_de_adjuntos()
    {
        Notification::fake();
        $this->adjunto('a.pdf');
        $admin = $this->admin();

        app()->call([new GenerarBackupAdjuntosJob($admin->id), 'handle']);

        Notification::assertSentTo($admin, BackupFinalizado::class, fn ($n) => $n->resultado->ok && str_contains((string) $n->url(), '/adjuntos-'));
    }

    // CA12
    public function test_con_un_tipo_en_proceso_no_se_puede_generar_otro()
    {
        Queue::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('backups.generar'), ['tipos' => ['adjuntos']]);
        $this->actingAs($admin)->post(route('backups.generar'), ['tipos' => ['bd']])
            ->assertSessionHas('backups_error', fn ($m) => str_contains($m, 'Ya hay un backup en proceso'));

        Queue::assertPushed(GenerarBackupAdjuntosJob::class, 1);
        Queue::assertNotPushed(GenerarBackupJob::class);
    }

    public function test_al_terminar_adjuntos_bd_sigue_en_proceso()
    {
        Notification::fake();
        $estado = app(EstadoBackup::class);
        $estado->marcarVarios(['bd', 'adjuntos'], 1);

        app()->call([new GenerarBackupAdjuntosJob(1), 'handle']);

        $this->assertTrue($estado->enProceso());
        $estado->liberar('bd');
        $this->assertFalse($estado->enProceso());
    }

    public function test_job_que_muere_libera_solo_su_tipo()
    {
        $estado = app(EstadoBackup::class);
        $estado->marcarVarios(['bd', 'adjuntos'], 1);

        (new GenerarBackupAdjuntosJob(1))->failed(null);

        $this->assertTrue($estado->enProceso());
        $estado->liberar('bd');
        $this->assertFalse($estado->enProceso());
    }
}
