<?php

namespace Osfermedrano\Backups\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Osfermedrano\Backups\BackupsServiceProvider;
use Osfermedrano\Backups\Tests\Fixtures\UserConSpatie;
use Osfermedrano\Backups\Tests\TestCase;

/** CA3, CA4, CA12 — se adapta a proyectos distintos. */
class CompatibilidadTest extends TestCase
{
    private function gateDefaultDelPaquete(): void
    {
        // Sin la redefinición del TestCase: el Gate que trae el paquete
        BackupsServiceProvider::definirGatePorDefecto();
    }

    // CA3
    public function test_gate_por_defecto_usa_spatie_si_el_usuario_lo_tiene()
    {
        $this->gateDefaultDelPaquete();

        $con = UserConSpatie::create(['name' => 'a', 'email' => 'a@x.com', 'password' => 'x', 'permiso_backup' => true]);
        $sin = UserConSpatie::create(['name' => 'b', 'email' => 'b@x.com', 'password' => 'x', 'permiso_backup' => false]);
        $permisoInexistente = UserConSpatie::create(['name' => 'c', 'email' => 'c@x.com', 'password' => 'x', 'permiso_backup' => null]);

        $this->assertTrue(Gate::forUser($con)->allows('backups.gestionar'));
        $this->assertFalse(Gate::forUser($sin)->allows('backups.gestionar'));
        $this->assertFalse(Gate::forUser($permisoInexistente)->allows('backups.gestionar'), 'Permiso inexistente en Spatie no debe romper');
    }

    public function test_gate_por_defecto_niega_si_el_proyecto_no_usa_spatie()
    {
        $this->gateDefaultDelPaquete();

        $this->assertFalse(Gate::forUser($this->admin())->allows('backups.gestionar'), 'Sin Spatie ni Gate propio: nadie, hasta que el proyecto defina quién');
    }

    public function test_el_proyecto_puede_redefinir_el_gate()
    {
        // TestCase lo redefine con is_admin (como haría cada proyecto)
        $this->assertTrue(Gate::forUser($this->admin())->allows('backups.gestionar'));
        $this->assertFalse(Gate::forUser($this->usuarioComun())->allows('backups.gestionar'));
    }

    // CA4
    public function test_no_pisa_la_config_de_spatie_del_proyecto()
    {
        $this->assertSame('mi-app', config('backup.backup.name'));
        $this->assertSame('secreto-de-prueba', config('backup.backup.password'));
    }

    // CA12
    public function test_programa_backup_limpieza_y_limpieza_drive()
    {
        $eventos = collect(app(Schedule::class)->events());

        $this->assertSame('0 3 * * *', $eventos->first(fn ($e) => str_contains((string) $e->description, 'GenerarBackupJob'))?->expression);
        $this->assertSame('30 3 * * *', $eventos->first(fn ($e) => str_contains((string) $e->command, 'backup:clean'))?->expression);
        $this->assertSame('40 3 * * *', $eventos->first(fn ($e) => str_contains((string) $e->command, 'backups:limpiar-drive'))?->expression);
    }

    public function test_horarios_configurables_y_programacion_desactivable()
    {
        config(['backups.programacion.backup' => '01:15']);
        $this->assertSame('15 1 * * *', collect(BackupsServiceProvider::programar(new Schedule)->events())
            ->first(fn ($e) => str_contains((string) $e->description, 'GenerarBackupJob'))?->expression);

        config(['backups.programacion.activa' => false]);
        $this->assertCount(0, BackupsServiceProvider::programar(new Schedule)->events());
    }
}
