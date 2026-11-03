<?php

namespace Osfermedrano\Backups\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Osfermedrano\Backups\Backup\RepositorioBackups;
use Osfermedrano\Backups\Drive\ConexionDrive;
use Osfermedrano\Backups\Drive\DestinoDrive;
use Osfermedrano\Backups\Drive\SubidaDrive;

/**
 * Retención en Drive = espejo de la local: borra en Drive los backups que la limpieza
 * local (backup:clean) ya eliminó. Nunca toca archivos que no sean backups de la app.
 */
class LimpiarDrive extends Command
{
    protected $signature = 'backups:limpiar-drive';

    protected $description = 'Aplica en Google Drive la misma retención que en local';

    public function handle(ConexionDrive $conexion, RepositorioBackups $repositorio): int
    {
        if (! $conexion->conectado()) {
            $this->info('Google Drive no conectado: nada que limpiar.');

            return self::SUCCESS;
        }

        try {
            $destino = app(DestinoDrive::class);
            $locales = $repositorio->listar()->pluck('nombre')->all();

            $borrar = array_filter(
                $destino->listar(),
                fn ($nombre) => RepositorioBackups::esNombreDeBackup($nombre) && ! in_array($nombre, $locales, true)
            );

            foreach ($borrar as $nombre) {
                $destino->borrar($nombre);
                $this->line("Borrado en Drive: {$nombre}");
            }

            $this->info(count($borrar).' backup(s) borrados en Drive.');
        } catch (\Throwable $e) {
            $motivo = app(SubidaDrive::class)->motivo($e);
            Log::error("Backup: falló la limpieza en Google Drive: {$motivo}");
            $this->error("No se pudo limpiar Drive: {$motivo}");
        }

        return self::SUCCESS;
    }
}
