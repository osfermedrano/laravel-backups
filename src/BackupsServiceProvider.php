<?php

namespace Osfermedrano\Backups;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Osfermedrano\Backups\Backup\RepositorioBackups;
use Osfermedrano\Backups\Console\LimpiarDrive;
use Osfermedrano\Backups\Drive\DestinoDrive;
use Osfermedrano\Backups\Drive\GoogleDriveDestino;
use Osfermedrano\Backups\Jobs\GenerarBackupAdjuntosJob;
use Osfermedrano\Backups\Jobs\GenerarBackupJob;
use Osfermedrano\Backups\View\DatosPanel;

class BackupsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/backups.php', 'backups');

        $this->app->bind(DestinoDrive::class, GoogleDriveDestino::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/backups.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'backups');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        View::composer('backups::panel', DatosPanel::class);

        if (! Gate::has(config('backups.gate', 'backups.gestionar'))) {
            self::definirGatePorDefecto();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([LimpiarDrive::class]);

            $this->publishes([__DIR__.'/../config/backups.php' => config_path('backups.php')], 'backups-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/backups')], 'backups-views');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'backups-migrations');
        }

        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => self::programar($schedule));
    }

    /**
     * Gate por defecto: permiso de Spatie si el usuario lo usa; si no, nadie
     * (cada proyecto sin Spatie lo redefine, ej. fn ($u) => $u->is_admin).
     */
    public static function definirGatePorDefecto(): void
    {
        Gate::define(config('backups.gate', 'backups.gestionar'), function ($user) {
            if (! method_exists($user, 'hasPermissionTo')) {
                return false;
            }

            try {
                return (bool) $user->hasPermissionTo(config('backups.permiso_spatie', 'backup.gestionar'));
            } catch (\Throwable) {
                return false; // el permiso no existe en Spatie
            }
        });
    }

    /** Backup diario, limpieza local (Spatie), limpieza espejo en Drive y adjuntos semanal (si están activos). */
    public static function programar(Schedule $schedule): Schedule
    {
        if (! filter_var(config('backups.programacion.activa', true), FILTER_VALIDATE_BOOL)) {
            return $schedule;
        }

        $schedule->job(new GenerarBackupJob)->dailyAt((string) config('backups.programacion.backup', '03:00'));
        $schedule->command('backup:clean --disable-notifications')->dailyAt((string) config('backups.programacion.limpieza', '03:30'));
        $schedule->command('backups:limpiar-drive')->dailyAt((string) config('backups.programacion.limpieza_drive', '03:40'));

        if (RepositorioBackups::adjuntosActivos()) {
            $schedule->job(new GenerarBackupAdjuntosJob)->weeklyOn(
                (int) config('backups.programacion.adjuntos_dia', 0),
                (string) config('backups.programacion.adjuntos_hora', '04:00'),
            );
        }

        return $schedule;
    }
}
