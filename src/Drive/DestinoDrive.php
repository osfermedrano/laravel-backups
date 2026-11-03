<?php

namespace Osfermedrano\Backups\Drive;

/** Destino remoto de backups (carpeta única de la app en Google Drive). */
interface DestinoDrive
{
    public function subir(string $rutaLocal, string $nombre): void;

    /** @return string[] nombres de archivo en la carpeta */
    public function listar(): array;

    public function borrar(string $nombre): void;
}
