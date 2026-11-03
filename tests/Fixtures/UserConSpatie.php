<?php

namespace Osfermedrano\Backups\Tests\Fixtures;

/** Simula un usuario con Spatie Permission (método hasPermissionTo) sin instalar Spatie. */
class UserConSpatie extends User
{
    public function hasPermissionTo($permiso, $guard = null): bool
    {
        if ($permiso !== 'backup.gestionar') {
            return false;
        }

        // Spatie lanza si el permiso no existe; acá lo simulamos con una columna
        if ($this->permiso_backup === null) {
            throw new \RuntimeException('There is no permission named `backup.gestionar`');
        }

        return (bool) $this->permiso_backup;
    }
}
