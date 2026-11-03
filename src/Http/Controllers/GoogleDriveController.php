<?php

namespace Osfermedrano\Backups\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Osfermedrano\Backups\Drive\ClienteGoogle;
use Osfermedrano\Backups\Drive\ConexionDrive;

/**
 * Conexión de Google Drive para backups (OAuth, scope drive.file). Rutas con auth + can:{gate}.
 */
class GoogleDriveController extends Controller
{
    use VuelveAlPanel;

    private const SESION_STATE = 'backups_gdrive_state';

    public function conectar(ClienteGoogle $google, ConexionDrive $conexion)
    {
        if (! $conexion->configurado()) {
            return $this->volver('backups_error', 'Faltan GOOGLE_DRIVE_CLIENT_ID y GOOGLE_DRIVE_CLIENT_SECRET en el .env.');
        }

        $state = Str::random(40);
        session([self::SESION_STATE => $state]);

        return redirect()->away($google->urlAutorizacion($state));
    }

    public function callback(Request $request, ClienteGoogle $google, ConexionDrive $conexion)
    {
        $esperado = $request->session()->pull(self::SESION_STATE);
        abort_unless(is_string($esperado) && hash_equals($esperado, (string) $request->query('state')), 403);

        if ($request->filled('error')) {
            return $this->volver('backups_error', 'Google no autorizó la conexión ('.$request->query('error').').');
        }

        try {
            $datos = $google->intercambiarCodigo((string) $request->query('code'));
        } catch (\Throwable $e) {
            Log::error('Google Drive: error al conectar', ['exception' => $e]);

            return $this->volver('backups_error', 'No se pudo conectar con Google Drive: '.Str::limit($e->getMessage(), 150));
        }

        if (blank($datos['refresh_token'] ?? null)) {
            return $this->volver('backups_error', 'Google no devolvió acceso permanente. Quitá el acceso de esta app en myaccount.google.com/permissions y volvé a conectar.');
        }

        $conexion->guardar($datos['refresh_token'], $datos['email'] ?? null);

        return $this->volver('backups_mensaje', 'Google Drive conectado'.($datos['email'] ? " como {$datos['email']}" : '').'.');
    }

    public function desconectar(ClienteGoogle $google, ConexionDrive $conexion)
    {
        if ($token = $conexion->refreshToken()) {
            try {
                $google->revocar($token);
            } catch (\Throwable $e) {
                Log::warning('Google Drive: no se pudo revocar el token (se borra igual): '.$e->getMessage());
            }
        }

        $conexion->desconectar();

        return $this->volver('backups_mensaje', 'Google Drive desconectado.');
    }
}
