<?php

namespace Osfermedrano\Backups\Backup;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Zip cifrado (AES-256) de la carpeta de adjuntos, aparte del backup de BD.
 * No usa backup:run --config: esa opción no existe en spatie ^9.0 y deja config('backup')
 * cambiado para todo el proceso, lo que afectaría al siguiente backup en un worker.
 */
class EmpaquetadorAdjuntos
{
    public function __construct(private RepositorioBackups $repositorio) {}

    public function ejecutar(): ResultadoBackup
    {
        $ruta = trim((string) config('backups.adjuntos.ruta'), '/');

        if ($ruta === '') {
            return ResultadoBackup::fallo('Falta configurar backups.adjuntos.ruta.');
        }

        $origen = Storage::disk((string) config('backups.adjuntos.disco', 'local'));
        $archivos = $origen->allFiles($ruta);

        if ($archivos === []) {
            return ResultadoBackup::fallo('No hay adjuntos para respaldar.');
        }

        $temporal = tempnam(sys_get_temp_dir(), 'backup-adjuntos');

        try {
            $this->comprimir($origen, $archivos, $ruta, $temporal);

            $destino = $this->repositorio->carpetaAdjuntos().'/'.$this->repositorio->nombreAdjuntos();
            $this->guardar($temporal, $destino);
            $bytes = (int) filesize($temporal);
        } finally {
            @unlink($temporal);
        }

        $this->aplicarRetencion();

        return ResultadoBackup::ok($destino, $bytes);
    }

    /** @param string[] $archivos */
    private function comprimir(Filesystem $origen, array $archivos, string $ruta, string $temporal): void
    {
        $zip = new ZipArchive;

        if ($zip->open($temporal, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el zip de adjuntos.');
        }

        $zip->setPassword((string) config('backup.backup.password'));

        foreach ($archivos as $archivo) {
            $nombre = substr($archivo, strlen($ruta) + 1);

            // Si no se puede cifrar, se aborta: nunca queda un zip sin cifrar.
            if (! $zip->addFile($origen->path($archivo), $nombre) || ! $zip->setEncryptionName($nombre, ZipArchive::EM_AES_256)) {
                $zip->unchangeAll();
                $zip->close();

                throw new RuntimeException("No se pudo agregar y cifrar {$nombre}.");
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException('No se pudo cerrar el zip de adjuntos.');
        }
    }

    private function guardar(string $temporal, string $destino): void
    {
        $flujo = fopen($temporal, 'r');

        try {
            if (! $this->repositorio->disco()->writeStream($destino, $flujo)) {
                throw new RuntimeException('No se pudo guardar el zip de adjuntos.');
            }
        } finally {
            if (is_resource($flujo)) {
                fclose($flujo);
            }
        }
    }

    /** Solo tras un backup exitoso: un fallo nunca borra copias buenas. */
    private function aplicarRetencion(): void
    {
        $conservar = max(1, (int) config('backups.adjuntos.conservar', 4));

        $this->repositorio->listarAdjuntos()
            ->slice($conservar)
            ->each(fn ($backup) => $this->repositorio->disco()->delete($backup['ruta']));
    }
}
