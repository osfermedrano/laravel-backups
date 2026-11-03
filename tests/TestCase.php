<?php

namespace Osfermedrano\Backups\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;
use Osfermedrano\Backups\BackupsServiceProvider;
use Osfermedrano\Backups\Tests\Fixtures\User;

/**
 * Proyecto simulado: SIN Livewire ni Spatie Permission; autoriza con is_admin.
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            \Spatie\Backup\BackupServiceProvider::class,
            BackupsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('backup.backup.name', 'mi-app');
        $app['config']->set('backup.backup.password', 'secreto-de-prueba');
        $app['config']->set('backups.user_model', User::class);
        $app['config']->set('services.google_drive', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function defineRoutes($router): void
    {
        // El proyecto real tiene login; el middleware auth redirige acá
        Route::get('/login', fn () => 'login')->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Así lo haría un proyecto sin Spatie en su AppServiceProvider
        Gate::define('backups.gestionar', fn ($user) => (bool) $user->is_admin);
    }

    protected function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => uniqid().'@admin.com', 'password' => 'x', 'is_admin' => true]);
    }

    protected function usuarioComun(): User
    {
        return User::create(['name' => 'Común', 'email' => uniqid().'@user.com', 'password' => 'x', 'is_admin' => false]);
    }

    /** Zip falso con el nombre que usa Spatie (Y-m-d-H-i-s.zip). */
    protected function backupFalso(string $fecha, int $bytes = 2048): string
    {
        $ruta = 'mi-app/'.$fecha.'.zip';
        Storage::disk('local')->put($ruta, str_repeat('x', $bytes));

        return $ruta;
    }
}
