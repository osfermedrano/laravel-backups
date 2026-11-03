<?php

namespace Osfermedrano\Backups\Http\Controllers;

/** Redirige al panel: página del paquete o la del proyecto (config backups.volver_a). */
trait VuelveAlPanel
{
    private function volver(string $clave, string $mensaje)
    {
        $destino = config('backups.volver_a');
        $url = is_array($destino) && filled($destino['ruta'] ?? null)
            ? route($destino['ruta'], $destino['parametros'] ?? [])
            : route('backups.index');

        return redirect()->to($url)->with($clave, $mensaje);
    }
}
