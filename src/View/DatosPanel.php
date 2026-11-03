<?php

namespace Osfermedrano\Backups\View;

use Illuminate\View\View;
use Osfermedrano\Backups\Backup\EstadoBackup;
use Osfermedrano\Backups\Backup\RepositorioBackups;
use Osfermedrano\Backups\Drive\ConexionDrive;

/** Datos del panel: así se puede incluir en cualquier vista con @include('backups::panel'). */
class DatosPanel
{
    public function __construct(
        private RepositorioBackups $repositorio,
        private EstadoBackup $estado,
        private ConexionDrive $drive,
    ) {}

    public function compose(View $view): void
    {
        $cantidad = (int) config('backups.ultimos', 10);
        $backups = $this->repositorio->listar();
        $ultimo = $backups->first();

        $view->with([
            'drive' => $this->drive,
            'mensaje' => session('backups_mensaje'),
            'error' => session('backups_error'),
            'ultimos' => $backups->take($cantidad),
            'anteriores' => max(0, $backups->count() - $cantidad),
            'enProceso' => $this->estado->enProceso(),
            'sinPassword' => blank(config('backup.backup.password')),
            'ultimo' => $ultimo,
            'ultimoViejo' => $ultimo && $ultimo['fecha']->lt(now()->subHours((int) config('backups.horas_alerta', 26))),
        ]);
    }
}
