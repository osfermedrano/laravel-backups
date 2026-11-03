<?php

namespace Osfermedrano\Backups\Drive;

use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

/**
 * Implementación real sobre la API de Drive. Con scope drive.file solo ve lo que esta app creó.
 */
class GoogleDriveDestino implements DestinoDrive
{
    private const CARPETA_MIME = 'application/vnd.google-apps.folder';

    private const CHUNK = 5 * 1024 * 1024;

    private ?Drive $servicio = null;

    public function __construct(private ClienteGoogle $google, private ConexionDrive $conexion) {}

    public function subir(string $rutaLocal, string $nombre): void
    {
        $drive = $this->drive();
        $carpeta = $this->carpetaId(); // ANTES de setDefer: con defer activo las llamadas devuelven Request sin ejecutarse
        $cliente = $drive->getClient();
        $cliente->setDefer(true);

        $request = $drive->files->create(new DriveFile(['name' => $nombre, 'parents' => [$carpeta]]), ['fields' => 'id']);
        $subida = new MediaFileUpload($cliente, $request, 'application/zip', null, true, self::CHUNK);
        $subida->setFileSize(filesize($rutaLocal));

        $archivo = fopen($rutaLocal, 'rb');
        try {
            $estado = false;
            while ($estado === false && ! feof($archivo)) {
                $estado = $subida->nextChunk(fread($archivo, self::CHUNK));
            }
        } finally {
            fclose($archivo);
            $cliente->setDefer(false);
        }

        if ($estado === false) {
            throw new \RuntimeException('La subida a Drive no se completó');
        }
    }

    public function listar(): array
    {
        return array_keys($this->archivos());
    }

    public function borrar(string $nombre): void
    {
        foreach ($this->archivos()[$nombre] ?? [] as $id) {
            $this->drive()->files->delete($id);
        }
    }

    /** @return array<string, string[]> nombre => ids */
    private function archivos(): array
    {
        $carpeta = $this->carpetaId();
        $resultado = [];
        $pagina = null;

        do {
            $lista = $this->drive()->files->listFiles([
                'q' => "'{$carpeta}' in parents and trashed = false",
                'fields' => 'nextPageToken, files(id, name)',
                'pageSize' => 1000,
                'pageToken' => $pagina,
            ]);
            foreach ($lista->getFiles() as $f) {
                $resultado[$f->getName()][] = $f->getId();
            }
            $pagina = $lista->getNextPageToken();
        } while ($pagina);

        return $resultado;
    }

    /** Carpeta propia de la app; se crea una vez y se guarda su ID. Si la borraron en Drive, se recrea. */
    private function carpetaId(): string
    {
        $id = $this->conexion->carpetaId();
        if ($id) {
            try {
                $carpeta = $this->drive()->files->get($id, ['fields' => 'id, trashed']);
                if (! $carpeta->getTrashed()) {
                    return $id;
                }
            } catch (\Google\Service\Exception $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }
        }

        $nueva = $this->drive()->files->create(
            new DriveFile(['name' => $this->conexion->carpeta(), 'mimeType' => self::CARPETA_MIME]),
            ['fields' => 'id']
        );
        $this->conexion->guardarCarpetaId($nueva->getId());

        return $nueva->getId();
    }

    private function drive(): Drive
    {
        $token = $this->conexion->refreshToken() ?? throw new \RuntimeException('Google Drive no está conectado');

        return $this->servicio ??= new Drive($this->google->autorizado($token));
    }
}
