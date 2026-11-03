<?php

namespace Osfermedrano\Backups\Tests\Feature;

use Google\Client;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Osfermedrano\Backups\Drive\ClienteGoogle;
use Osfermedrano\Backups\Drive\ConexionDrive;
use Osfermedrano\Backups\Drive\GoogleDriveDestino;
use Osfermedrano\Backups\Tests\TestCase;

/**
 * Implementación REAL contra la API de Drive, con HTTP simulado (sin red).
 * Regresión: "Call to undefined method GuzzleHttp\Psr7\Request::getId()" al subir
 * (la carpeta se resolvía con setDefer(true) activo).
 */
class GoogleDriveDestinoTest extends TestCase
{
    private array $historial = [];

    private function destinoConRespuestas(array $respuestas): GoogleDriveDestino
    {
        $stack = HandlerStack::create(new MockHandler($respuestas));
        $stack->push(Middleware::history($this->historial));
        $http = new Guzzle(['handler' => $stack]);

        $google = new class($http) extends ClienteGoogle
        {
            public function __construct(private Guzzle $http) {}

            public function nuevo(): Client
            {
                $c = parent::nuevo();
                $c->setHttpClient($this->http);

                return $c;
            }
        };

        config(['backups.google_drive.client_id' => 'id', 'backups.google_drive.client_secret' => 's', 'backups.google_drive.folder' => 'Backups mi-app']);
        app(ConexionDrive::class)->guardar('rt-1', 'yo@gmail.com');

        return new GoogleDriveDestino($google, app(ConexionDrive::class));
    }

    private function respuesta(array $data, int $status = 200, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($data));
    }

    public function test_primer_backup_crea_carpeta_y_sube_dentro_de_ella()
    {
        $archivo = $this->backupFalso('2026-11-02-03-00-00', 2048);
        $ruta = \Illuminate\Support\Facades\Storage::disk('local')->path($archivo);

        $destino = $this->destinoConRespuestas([
            $this->respuesta(['access_token' => 'at', 'expires_in' => 3600]),                      // refresh token → access token
            $this->respuesta(['id' => 'carpeta-123']),                                             // crear carpeta
            new Response(200, ['Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=xyz']), // iniciar subida resumable
            $this->respuesta(['id' => 'archivo-1']),                                               // chunk final
        ]);

        $destino->subir($ruta, '2026-11-02-03-00-00.zip');

        $this->assertSame('carpeta-123', app(ConexionDrive::class)->carpetaId());

        // La carpeta se creó con el nombre de la app y tipo carpeta
        $crearCarpeta = json_decode((string) $this->historial[1]['request']->getBody(), true);
        $this->assertSame('Backups mi-app', $crearCarpeta['name']);
        $this->assertSame('application/vnd.google-apps.folder', $crearCarpeta['mimeType']);

        // El zip se subió DENTRO de la carpeta
        $inicio = json_decode((string) $this->historial[2]['request']->getBody(), true);
        $this->assertSame('2026-11-02-03-00-00.zip', $inicio['name']);
        $this->assertSame(['carpeta-123'], $inicio['parents']);
        $this->assertSame(2048, strlen((string) $this->historial[3]['request']->getBody()));
    }

    public function test_reutiliza_la_carpeta_guardada()
    {
        $archivo = $this->backupFalso('2026-11-02-03-00-00', 100);
        $destino = $this->destinoConRespuestas([
            $this->respuesta(['access_token' => 'at', 'expires_in' => 3600]),
            $this->respuesta(['id' => 'carpeta-123', 'trashed' => false]),                         // GET carpeta existente
            new Response(200, ['Location' => 'https://www.googleapis.com/upload/drive/v3/files?upload_id=xyz']),
            $this->respuesta(['id' => 'archivo-2']),
        ]);
        app(ConexionDrive::class)->guardarCarpetaId('carpeta-123');

        $destino->subir(\Illuminate\Support\Facades\Storage::disk('local')->path($archivo), '2026-11-02-03-00-00.zip');

        $this->assertSame('GET', $this->historial[1]['request']->getMethod());
        $this->assertCount(4, $this->historial, 'No debe crear otra carpeta');
    }

    public function test_listar_y_borrar_solo_dentro_de_la_carpeta()
    {
        $destino = $this->destinoConRespuestas([
            $this->respuesta(['access_token' => 'at', 'expires_in' => 3600]),
            $this->respuesta(['id' => 'carpeta-123', 'trashed' => false]),
            $this->respuesta(['files' => [['id' => 'f1', 'name' => '2026-10-01-03-00-00.zip'], ['id' => 'f2', 'name' => 'otro.zip']]]),
            $this->respuesta(['id' => 'carpeta-123', 'trashed' => false]),
            $this->respuesta(['files' => [['id' => 'f1', 'name' => '2026-10-01-03-00-00.zip']]]),
            new Response(204),
        ]);
        app(ConexionDrive::class)->guardarCarpetaId('carpeta-123');

        $this->assertSame(['2026-10-01-03-00-00.zip', 'otro.zip'], $destino->listar());
        $destino->borrar('2026-10-01-03-00-00.zip');

        parse_str($this->historial[2]['request']->getUri()->getQuery(), $q);
        $this->assertStringContainsString("'carpeta-123' in parents", $q['q']);
        $this->assertSame('DELETE', $this->historial[5]['request']->getMethod());
        $this->assertStringEndsWith('/files/f1', $this->historial[5]['request']->getUri()->getPath());
    }
}
