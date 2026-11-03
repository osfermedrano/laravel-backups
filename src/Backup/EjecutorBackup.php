<?php

namespace Osfermedrano\Backups\Backup;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Ejecuta el backup real (spatie/laravel-backup, solo BD, cifrado según config).
 * Aislado para poder mockearlo en tests (mysqldump no corre en sqlite).
 */
class EjecutorBackup
{
    public function __construct(private RepositorioBackups $repositorio) {}

    public function ejecutar(): ResultadoBackup
    {
        $antes = $this->repositorio->listar()->pluck('nombre');

        $codigo = Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);
        $salida = Artisan::output();

        $nuevo = $this->repositorio->listar()->first(fn ($b) => ! $antes->contains($b['nombre']));

        if ($codigo !== 0 || ! $nuevo) {
            return ResultadoBackup::fallo($this->motivo($salida));
        }

        return ResultadoBackup::ok($nuevo['ruta'], $nuevo['bytes']);
    }

    /** Extrae un motivo legible de la salida de Spatie ("Backup failed because: ..."). */
    private function motivo(string $salida): string
    {
        if (Str::contains($salida, ['mysqldump', 'not recognized', 'No such file'])) {
            return 'No se encontró mysqldump (configurar DB_DUMP_BINARY_PATH en .env)';
        }

        $linea = collect(preg_split('/\R/', $salida))
            ->map(fn ($l) => trim($l))
            ->first(fn ($l) => Str::contains($l, 'because'));

        return $linea ? Str::after($linea, 'because') : 'El backup no generó ningún archivo. Revisá el log.';
    }
}
