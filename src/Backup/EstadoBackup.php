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
    public function marcar(?int $userId): bool
    {
        return Cache::add(self::CLAVE, $userId ?? 0, now()->addMinutes(self::MINUTOS));
    }

    public function enProceso(): bool
    {
        return Cache::has(self::CLAVE);
    }

    public function liberar(): void
    {
        Cache::forget(self::CLAVE);
    }
}
