<?php

namespace Osfermedrano\Backups\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Osfermedrano\Backups\Backup\EstadoBackup;
use Osfermedrano\Backups\Jobs\GenerarBackupJob;
use Osfermedrano\Backups\Tests\TestCase;

/** CA7, CA11 (+ paridad con BackupsComponenteTest de abonados). */
class PanelTest extends TestCase
{
    public function test_panel_requiere_permiso_y_login()
    {
        $this->get(route('backups.index'))->assertRedirect(route('login'));
        $this->actingAs($this->usuarioComun())->get(route('backups.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('backups.index'))->assertOk()->assertSee('Últimos backups');
    }

    public function test_generar_encola_job_sin_ejecutarlo()
    {
        Queue::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('backups.generar'))
            ->assertRedirect()
            ->assertSessionHas('backups_mensaje');

        Queue::assertPushed(GenerarBackupJob::class, fn ($job) => $job->solicitanteId === $admin->id);
        $this->actingAs($admin)->get(route('backups.index'))->assertSee('Backup en proceso');
    }

    public function test_no_encola_dos_backups_simultaneos()
    {
        Queue::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('backups.generar'));
        $this->actingAs($admin)->post(route('backups.generar'))
            ->assertSessionHas('backups_error', fn ($m) => str_contains($m, 'Ya hay un backup en proceso'));

        Queue::assertPushed(GenerarBackupJob::class, 1);
    }

    public function test_generar_requiere_permiso()
    {
        Queue::fake();

        $this->actingAs($this->usuarioComun())->post(route('backups.generar'))->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_sin_password_advierte_y_no_encola()
    {
        Queue::fake();
        config(['backup.backup.password' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('backups.index'))->assertSee('BACKUP_ARCHIVE_PASSWORD');
        $this->actingAs($admin)->post(route('backups.generar'))->assertSessionHas('backups_error');

        Queue::assertNothingPushed();
        $this->assertFalse(app(EstadoBackup::class)->enProceso());
    }

    public function test_lista_mas_recientes_primero_con_link_descargar()
    {
        $this->backupFalso('2026-10-30-03-00-00', 1024);
        $this->backupFalso('2026-11-01-03-00-00', 2 * 1024 * 1024);
        $this->backupFalso('2026-10-31-03-00-00');

        $this->actingAs($this->admin())->get(route('backups.index'))
            ->assertSeeInOrder(['01/11/2026 03:00', '31/10/2026 03:00', '30/10/2026 03:00'])
            ->assertSee('2 MB')
            ->assertSee('1 KB')
            ->assertSeeInOrder([
                route('backups.descargar', '2026-11-01-03-00-00.zip'),
                route('backups.descargar', '2026-10-31-03-00-00.zip'),
                route('backups.descargar', '2026-10-30-03-00-00.zip'),
            ], false);
    }

    public function test_muestra_solo_los_ultimos_10()
    {
        foreach (range(1, 12) as $dia) {
            $this->backupFalso(sprintf('2026-10-%02d-03-00-00', $dia));
        }

        $this->actingAs($this->admin())->get(route('backups.index'))
            ->assertSee('12/10/2026 03:00')
            ->assertSee('03/10/2026 03:00')
            ->assertDontSee('02/10/2026 03:00')
            ->assertSee('y 2 anteriores');
    }

    public function test_avisa_si_no_hay_backups_o_el_ultimo_es_viejo()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00'));
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('backups.index'))->assertSee('Todavía no hay backups');

        $this->backupFalso('2026-11-01-03-00-00');
        $this->actingAs($admin)->get(route('backups.index'))->assertSee('El último backup tiene más de 26 horas');

        $this->backupFalso('2026-11-02-03-00-00');
        $this->actingAs($admin)->get(route('backups.index'))->assertDontSee('El último backup tiene más de 26 horas');

        Carbon::setTestNow();
    }

    public function test_descarga_requiere_permiso_y_rechaza_nombres_invalidos()
    {
        $this->backupFalso('2026-11-01-03-00-00');

        $this->actingAs($this->admin())->get(route('backups.descargar', '2026-11-01-03-00-00.zip'))
            ->assertOk()->assertDownload('2026-11-01-03-00-00.zip');

        $this->actingAs($this->usuarioComun())->get(route('backups.descargar', '2026-11-01-03-00-00.zip'))->assertForbidden();

        foreach (['..%2F..%2F.env', 'no-existe.zip', 'mi-app.zip'] as $malo) {
            $this->actingAs($this->admin())->get(url(config('backups.rutas.prefijo').'/'.$malo.'/descargar'))->assertNotFound();
        }

        auth()->logout();
        $this->get(route('backups.descargar', '2026-11-01-03-00-00.zip'))->assertRedirect(route('login'));
    }

    // CA11 — embebible en la página de configuración del proyecto
    public function test_panel_se_puede_incluir_en_otra_vista()
    {
        $this->backupFalso('2026-11-01-03-00-00');
        $this->actingAs($this->admin());

        $html = view('backups::panel')->render();

        $this->assertStringContainsString('Últimos backups', $html);
        $this->assertStringContainsString(route('backups.descargar', '2026-11-01-03-00-00.zip'), $html);
        $this->assertStringContainsString('name="_token"', $html, 'Los formularios deben tener CSRF');
    }

    public function test_funciona_sin_livewire()
    {
        $this->assertFalse(class_exists(\Livewire\Livewire::class), 'El entorno de tests del paquete no debe tener Livewire');
        $this->actingAs($this->admin())->get(route('backups.index'))->assertOk();
    }
}
