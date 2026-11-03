<?php

namespace Osfermedrano\Backups\Http\Controllers;

use Illuminate\Routing\Controller;
use Osfermedrano\Backups\Backup\EstadoBackup;
use Osfermedrano\Backups\Backup\RepositorioBackups;
use Osfermedrano\Backups\Jobs\GenerarBackupJob;

/** Panel, generación manual y descarga. Rutas con auth + can:{gate}. */
class BackupsController extends Controller
{
    use VuelveAlPanel;

    public function index()
    {
        if ($layout = config('backups.layout')) {
            return view('backups::pagina-layout', ['layout' => $layout, 'seccion' => config('backups.seccion', 'content')]);
        }

        if ($componente = config('backups.componente')) {
            return view('backups::pagina-componente', ['componente' => $componente]);
        }

        return view('backups::pagina');
    }

    public function generar(EstadoBackup $estado)
    {
        if (blank(config('backup.backup.password'))) {
            return $this->volver('backups_error', 'Falta configurar BACKUP_ARCHIVE_PASSWORD en el .env. No se generan backups sin cifrar.');
        }

        if (! $estado->marcar(auth()->id())) {
            return $this->volver('backups_error', 'Ya hay un backup en proceso. Te avisamos cuando termine.');
        }

        GenerarBackupJob::dispatch(auth()->id());

        return $this->volver('backups_mensaje', 'Backup en cola. Te avisamos con una notificación (y por mail si está configurado) cuando termine.');
    }

    public function descargar(string $archivo, RepositorioBackups $repositorio)
    {
        $ruta = $repositorio->buscar($archivo);
        abort_unless($ruta, 404);

        return $repositorio->disco()->download($ruta, $archivo);
    }
}
