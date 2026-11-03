<?php

namespace Osfermedrano\Backups\Drive;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Osfermedrano\Backups\Ajuste;

/**
 * Estado de la conexión a Google Drive, en la tabla propia `backups_ajustes`.
 * El refresh token se guarda cifrado con APP_KEY y solo se descifra al subir.
 */
class ConexionDrive
{
    public const CLAVE_TOKEN = 'gdrive_refresh_token';

    private const CLAVE_EMAIL = 'gdrive_email';

    private const CLAVE_ESTADO = 'gdrive_estado';

    private const CLAVE_MOTIVO = 'gdrive_motivo';

    private const CLAVE_CARPETA = 'gdrive_folder_id';

    public const CONECTADO = 'conectado';

    public const INVALIDO = 'invalido';

    public const DESCONECTADO = 'desconectado';

    /** ¿Están el client ID y el secreto en el .env? */
    public function configurado(): bool
    {
        return filled(config('backups.google_drive.client_id')) && filled(config('backups.google_drive.client_secret'));
    }

    public function estado(): string
    {
        return Ajuste::valor(self::CLAVE_ESTADO) ?: self::DESCONECTADO;
    }

    public function conectado(): bool
    {
        return $this->estado() === self::CONECTADO && $this->refreshToken() !== null;
    }

    public function email(): ?string
    {
        return Ajuste::valor(self::CLAVE_EMAIL);
    }

    public function motivo(): ?string
    {
        return Ajuste::valor(self::CLAVE_MOTIVO);
    }

    public function carpeta(): string
    {
        return (string) config('backups.google_drive.folder');
    }

    public function refreshToken(): ?string
    {
        $cifrado = Ajuste::valor(self::CLAVE_TOKEN);
        if (blank($cifrado)) {
            return null;
        }

        try {
            return Crypt::decryptString($cifrado);
        } catch (DecryptException) {
            return null; // APP_KEY cambió: hay que reconectar
        }
    }

    public function guardar(string $refreshToken, ?string $email): void
    {
        Ajuste::poner(self::CLAVE_TOKEN, Crypt::encryptString($refreshToken));
        Ajuste::poner(self::CLAVE_EMAIL, $email);
        Ajuste::poner(self::CLAVE_ESTADO, self::CONECTADO);
        Ajuste::poner(self::CLAVE_MOTIVO, null);
    }

    public function marcarInvalido(string $motivo): void
    {
        Ajuste::poner(self::CLAVE_ESTADO, self::INVALIDO);
        Ajuste::poner(self::CLAVE_MOTIVO, $motivo);
    }

    /** Borra la conexión. Conserva el ID de carpeta: al reconectar se reutiliza la misma. */
    public function desconectar(): void
    {
        Ajuste::quitar([self::CLAVE_TOKEN, self::CLAVE_EMAIL, self::CLAVE_ESTADO, self::CLAVE_MOTIVO]);
    }

    public function carpetaId(): ?string
    {
        return Ajuste::valor(self::CLAVE_CARPETA);
    }

    public function guardarCarpetaId(string $id): void
    {
        Ajuste::poner(self::CLAVE_CARPETA, $id);
    }
}
