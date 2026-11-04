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

    /** Zips de adjuntos: adjuntos-2026-11-02-03-00-00.zip (no chocan con BD en descarga ni en Drive) */
    private const PATRON_ADJUNTOS = '/^adjuntos-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$/';

    private const PREFIJO_ADJUNTOS = 'adjuntos-';

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
        return $this->leer((string) config('backup.backup.name'), self::PATRON);
    }

    /**
     * Zips de adjuntos: carpeta propia, fuera del alcance de backup:clean.
     *
     * @return Collection<int, array{nombre: string, ruta: string, bytes: int, fecha: Carbon}> más nuevo primero
     */
    public function listarAdjuntos(): Collection
    {
        return $this->leer($this->carpetaAdjuntos(), self::PATRON_ADJUNTOS);
    }

    /** @return Collection<int, array{nombre: string, ruta: string, bytes: int, fecha: Carbon}> */
    public function listarTodos(): Collection
    {
        return $this->listar()->concat($this->listarAdjuntos());
    }

    public function carpetaAdjuntos(): string
    {
        return config('backup.backup.name').'-adjuntos';
    }

    public function nombreAdjuntos(): string
    {
        return self::PREFIJO_ADJUNTOS.now()->format('Y-m-d-H-i-s').'.zip';
    }

    public static function adjuntosActivos(): bool
    {
        return filter_var(config('backups.adjuntos.activo', false), FILTER_VALIDATE_BOOL);
    }

    private function leer(string $carpeta, string $patron): Collection
    {
        return collect($this->disco()->files($carpeta))
            ->filter(fn ($ruta) => preg_match($patron, basename($ruta)))
            ->map(fn ($ruta) => [
                'nombre' => basename($ruta),
                'ruta' => $ruta,
                'bytes' => $this->disco()->size($ruta),
                'fecha' => Carbon::createFromFormat('Y-m-d-H-i-s', str_replace(self::PREFIJO_ADJUNTOS, '', basename($ruta, '.zip'))),
            ])
            ->sortByDesc('fecha')
            ->values();
    }

    /** ¿El nombre tiene la forma de un backup generado por la app? */
    public static function esNombreDeBackup(string $nombre): bool
    {
        return preg_match(self::PATRON, $nombre) || preg_match(self::PATRON_ADJUNTOS, $nombre);
    }

    /** Ruta del backup si el nombre es válido y existe; null en otro caso (evita path traversal). */
    public function buscar(string $nombre): ?string
    {
        if (! self::esNombreDeBackup($nombre)) {
            return null;
        }

        return $this->listarTodos()->firstWhere('nombre', $nombre)['ruta'] ?? null;
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
