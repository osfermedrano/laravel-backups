<?php

return [

    /*
    | Rutas del panel, la descarga y la conexión de Google Drive.
    | El paquete agrega siempre "can:{gate}" a este middleware.
    */
    'rutas' => [
        'prefijo' => 'admin/backups',
        'middleware' => ['web', 'auth'],
    ],

    // Gate que autoriza todo (panel, descarga, Drive). Redefinilo en tu AppServiceProvider si no usás Spatie Permission.
    'gate' => 'backups.gestionar',

    // Permiso de Spatie Permission que usa el Gate por defecto (si el usuario tiene hasPermissionTo).
    'permiso_spatie' => 'backup.gestionar',

    'user_model' => env('BACKUPS_USER_MODEL', 'App\Models\User'),

    /*
    | A quién avisar cuando el backup es automático (o el solicitante ya no existe).
    | null = todos los usuarios que pasan el Gate. O una clase invocable que devuelva
    | un iterable de usuarios (sin closures, para no romper config:cache).
    */
    'destinatarios' => null,

    'programacion' => [
        'activa' => env('BACKUPS_PROGRAMACION', true),
        'backup' => '03:00',
        'limpieza' => '03:30',
        'limpieza_drive' => '03:40',
    ],

    // Panel
    'ultimos' => 10,
    'horas_alerta' => 26,

    /*
    | Página propia (/{prefijo}): layout del proyecto.
    | - layout: vista a extender con @extends (ej. 'layouts.app') + seccion.
    | - componente: componente Blade de layout (ej. 'app-layout' para <x-app-layout>).
    | Ambos null = página simple propia del paquete.
    */
    'layout' => null,
    'seccion' => 'content',
    'componente' => null,

    /*
    | Header de la página propia (slot "header" del componente o sección "header" del layout),
    | para layouts que dibujan título, tema o notificaciones solo si lo reciben.
    | titulo null = sin header. vista = vista propia del proyecto (recibe $titulo); null = <h2> simple.
    */
    'header' => [
        'titulo' => null,
        'vista' => null,
    ],

    // A dónde volver tras generar/conectar/desconectar (panel embebido). null = página del paquete.
    // Ej: ['ruta' => 'admin.configuracion', 'parametros' => ['tab' => 'backups']]
    'volver_a' => null,

    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_DRIVE_REDIRECT_URI'), // null = ruta backups.google.callback
        'folder' => env('GOOGLE_DRIVE_FOLDER', 'Backups '.env('APP_NAME', 'Laravel')),
    ],
];
