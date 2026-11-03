<?php

namespace Osfermedrano\Backups\Drive;

use Google\Client;
use Google\Service\Drive;

/**
 * Fábrica del cliente OAuth de Google (scope único: drive.file). Aislado para mockear en tests.
 */
class ClienteGoogle
{
    public function nuevo(): Client
    {
        $c = new Client;
        $c->setClientId((string) config('backups.google_drive.client_id'));
        $c->setClientSecret((string) config('backups.google_drive.client_secret'));
        $c->setRedirectUri((string) (config('backups.google_drive.redirect') ?: route('backups.google.callback')));
        $c->setScopes([Drive::DRIVE_FILE]);
        $c->setAccessType('offline');
        $c->setPrompt('consent'); // fuerza refresh_token aunque ya se haya autorizado antes
        $c->setIncludeGrantedScopes(false);

        return $c;
    }

    public function urlAutorizacion(string $state): string
    {
        $c = $this->nuevo();
        $c->setState($state);

        return $c->createAuthUrl();
    }

    /** @return array{refresh_token: ?string, email: ?string} */
    public function intercambiarCodigo(string $codigo): array
    {
        $c = $this->nuevo();
        $token = $c->fetchAccessTokenWithAuthCode($codigo);
        if (isset($token['error'])) {
            throw new \RuntimeException($token['error_description'] ?? $token['error']);
        }

        $c->setAccessToken($token);
        $about = (new Drive($c))->about->get(['fields' => 'user(emailAddress)']);

        return ['refresh_token' => $token['refresh_token'] ?? null, 'email' => $about->getUser()?->getEmailAddress()];
    }

    /** Cliente listo para usar con el refresh token guardado. Lanza con "invalid_grant" si fue revocado. */
    public function autorizado(string $refreshToken): Client
    {
        $c = $this->nuevo();
        $token = $c->fetchAccessTokenWithRefreshToken($refreshToken);
        if (isset($token['error'])) {
            throw new \RuntimeException("{$token['error']}: ".($token['error_description'] ?? ''));
        }

        return $c;
    }

    public function revocar(string $refreshToken): void
    {
        $this->nuevo()->revokeToken($refreshToken);
    }
}
