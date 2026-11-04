<?php

namespace Osfermedrano\Backups\Backup;

use Illuminate\Support\Facades\Cache;

/**
 * Marca "hay un backup en proceso" para no encolar dos a la vez.
 * Vence sola (por si el worker muere a mitad sin ejecutar el finally).
 */
class EstadoBackup
{
    private const CLAVE = 'backups:en-proceso';

    private const MINUTOS = 15;

    /** @return bool false si ya había uno en proceso */
    public function marcar(?int $userId, string $tipo = 'bd'): bool
    {
        return Cache::add(self::clave($tipo), $userId ?? 0, now()->addMinutes(self::MINUTOS));
    }

    /**
     * Marca todos los tipos, o ninguno si ya hay alguno en proceso.
     *
     * @param  string[]  $tipos
     */
    public function marcarVarios(array $tipos, ?int $userId): bool
    {
        if ($this->enProceso()) {
            return false;
        }

        $marcados = [];

        foreach ($tipos as $tipo) {
            if (! $this->marcar($userId, $tipo)) {
                array_walk($marcados, fn ($t) => $this->liberar($t));

                return false;
            }

            $marcados[] = $tipo;
        }

        return true;
    }

    public function enProceso(): bool
    {
        return Cache::has(self::clave('bd')) || Cache::has(self::clave('adjuntos'));
    }

    public function liberar(string $tipo = 'bd'): void
    {
        Cache::forget(self::clave($tipo));
    }

    private static function clave(string $tipo): string
    {
        return $tipo === 'bd' ? self::CLAVE : self::CLAVE.':'.$tipo;
    }
}
