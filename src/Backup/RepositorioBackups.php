<?php

namespace Osfermedrano\Backups\Backup;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Backups existentes en el primer disco destino de Spatie (carpeta = config('backup.backup.name')).
 */
class RepositorioBackups
{
    /** Nombres que genera Spatie: 2026-11-02-03-00-00.zip */
    private const PATRON = '/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$/';

    public function disco(): Filesystem
    {
        $discos = (array) config('backup.backup.destination.disks', ['local']);

        return Storage::disk($discos[0] ?? 'local');
    }

    /**
     * @return Collection<int, array{nombre: string, ruta: string, bytes: int, fecha: Carbon}> más nuevo primero
     */
    public function listar(): Collection
    {
        $carpeta = (string) config('backup.backup.name');

        return collect($this->disco()->files($carpeta))
            ->filter(fn ($ruta) => self::esNombreDeBackup(basename($ruta)))
            ->map(fn ($ruta) => [
                'nombre' => basename($ruta),
                'ruta' => $ruta,
                'bytes' => $this->disco()->size($ruta),
                'fecha' => Carbon::createFromFormat('Y-m-d-H-i-s', basename($ruta, '.zip')),
            ])
            ->sortByDesc('fecha')
            ->values();
    }

    /** ¿El nombre tiene la forma de un backup generado por la app? */
    public static function esNombreDeBackup(string $nombre): bool
    {
        return (bool) preg_match(self::PATRON, $nombre);
    }

    /** Ruta del backup si el nombre es válido y existe; null en otro caso (evita path traversal). */
    public function buscar(string $nombre): ?string
    {
        if (! self::esNombreDeBackup($nombre)) {
            return null;
        }

        return $this->listar()->firstWhere('nombre', $nombre)['ruta'] ?? null;
    }

    public static function tamanio(int $bytes): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $valor = (float) $bytes;
        while ($valor >= 1024 && $i < count($unidades) - 1) {
            $valor /= 1024;
            $i++;
        }

        return preg_replace('/,0$/', '', number_format($valor, $i === 0 ? 0 : 1, ',', '.')).' '.$unidades[$i];
    }
}
