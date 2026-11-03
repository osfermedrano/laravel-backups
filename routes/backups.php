<?php

use Illuminate\Support\Facades\Route;
use Osfermedrano\Backups\Http\Controllers\BackupsController;
use Osfermedrano\Backups\Http\Controllers\GoogleDriveController;

Route::prefix(config('backups.rutas.prefijo', 'admin/backups'))
    ->middleware([...(array) config('backups.rutas.middleware', ['web', 'auth']), 'can:'.config('backups.gate', 'backups.gestionar')])
    ->name('backups.')
    ->group(function () {
        Route::get('/', [BackupsController::class, 'index'])->name('index');
        Route::post('/generar', [BackupsController::class, 'generar'])->name('generar');
        Route::get('/google/conectar', [GoogleDriveController::class, 'conectar'])->name('google.conectar');
        Route::get('/google/callback', [GoogleDriveController::class, 'callback'])->name('google.callback');
        Route::post('/google/desconectar', [GoogleDriveController::class, 'desconectar'])->name('google.desconectar');
        Route::get('/{archivo}/descargar', [BackupsController::class, 'descargar'])->name('descargar');
    });
