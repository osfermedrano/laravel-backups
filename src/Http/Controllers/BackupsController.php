<?php

namespace Osfermedrano\Backups\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Osfermedrano\Backups\Backup\EstadoBackup;
use Osfermedrano\Backups\Backup\RepositorioBackups;
use Osfermedrano\Backups\Jobs\GenerarBackupAdjuntosJob;
use Osfermedrano\Backups\Jobs\GenerarBackupJob;

/** Panel, generación manual y descarga. Rutas con auth + can:{gate}. */
class BackupsController extends Controller
{
    use VuelveAlPanel;

    public function index()
    {
        $titulo = config('backups.header.titulo');

        if ($layout = config('backups.layout')) {
            return view('backups::pagina-layout', ['layout' => $layout, 'seccion' => config('backups.seccion', 'content'), 'titulo' => $titulo]);
        }

        if ($componente = config('backups.componente')) {
            return view('backups::pagina-componente', ['componente' => $componente, 'titulo' => $titulo]);
        }

        return view('backups::pagina');
    }

    public function generar(Request $request, EstadoBackup $estado)
    {
        if (blank(config('backup.backup.password'))) {
            return $this->volver('backups_error', 'Falta configurar BACKUP_ARCHIVE_PASSWORD en el .env. No se generan backups sin cifrar.');
        }

        $tipos = $this->tiposPedidos($request);

        if ($tipos === []) {
            return $this->volver('backups_error', 'Elegí al menos un tipo de backup.');
        }

        if (! $estado->marcarVarios($tipos, auth()->id())) {
            return $this->volver('backups_error', 'Ya hay un backup en proceso. Te avisamos cuando termine.');
        }

        foreach ($tipos as $tipo) {
            $tipo === 'adjuntos'
                ? GenerarBackupAdjuntosJob::dispatch(auth()->id())
                : GenerarBackupJob::dispatch(auth()->id());
        }

        return $this->volver('backups_mensaje', 'Backup en cola. Te avisamos con una notificación (y por mail si está configurado) cuando termine.');
    }

    /** @return string[] sin adjuntos activos, siempre solo BD (como en versiones anteriores) */
    private function tiposPedidos(Request $request): array
    {
        if (! RepositorioBackups::adjuntosActivos()) {
            return ['bd'];
        }

        return array_values(array_intersect(['bd', 'adjuntos'], (array) $request->input('tipos', [])));
    }

    public function descargar(string $archivo, RepositorioBackups $repositorio)
    {
        $ruta = $repositorio->buscar($archivo);
        abort_unless($ruta, 404);

        return $repositorio->disco()->download($ruta, $archivo);
    }
}
