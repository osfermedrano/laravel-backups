# osfermedrano/laravel-backups

Backups cifrados de la base de datos para Laravel 11/12/13, con panel, descarga, avisos y copia opcional en Google Drive.

- Backup en cola (manual + diario), solo BD, zip **cifrado** (se niega a correr sin `BACKUP_ARCHIVE_PASSWORD`).
- Avisos in-app (database notifications) y mail *best-effort*: un SMTP caído no corta nada.
- Panel con los últimos 10, descarga autenticada y alertas (sin contraseña / sin backups / último > 26 h).
- Google Drive con OAuth `drive.file`: carpeta propia por app, retención espejo de la local, nunca toca archivos ajenos.
- No requiere Livewire ni Spatie Permission. UI Blade + Tailwind, funciona sin JS.

Usa [spatie/laravel-backup](https://github.com/spatie/laravel-backup) (^9|^10) por debajo.

## Instalación

```bash
composer require osfermedrano/laravel-backups
php artisan migrate   # crea backups_ajustes
```

Limitá los servicios de Google a Drive en el `composer.json` **del proyecto** (si no, `google/apiclient` baja ~200 servicios):

```json
"scripts": { "pre-autoload-dump": "Google\\Task\\Composer::cleanup" },
"extra": { "google/apiclient-services": ["Drive"] }
```

## .env

```dotenv
BACKUP_ARCHIVE_PASSWORD=          # obligatorio; guardala en un gestor de contraseñas
DB_DUMP_BINARY_PATH=              # carpeta de mysqldump si no está en el PATH

# Opcional: Google Drive (un cliente OAuth por app)
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REDIRECT_URI=        # vacío = {APP_URL}/admin/backups/google/callback
GOOGLE_DRIVE_FOLDER="Backups Mi App"
```

Spatie Backup usa su propio `config/backup.php`: si el proyecto no lo tiene, publicalo
(`php artisan vendor:publish --tag=backup-config`) y revisá `password`, `encryption` y la retención.
El paquete no lo modifica: solo fuerza `--only-db` y sin notificaciones de Spatie.

## Autorización

Todo pasa por el Gate `backups.gestionar`. Por defecto: `hasPermissionTo('backup.gestionar')` si el usuario usa
Spatie Permission; si no, **nadie**. Sin Spatie, definilo en tu `AppServiceProvider`:

```php
Gate::define('backups.gestionar', fn ($user) => $user->is_admin);
```

## Panel

- Página propia: `/admin/backups` (ruta `backups.index`). Para usar tu layout: `backups.layout` (`@extends`) o `backups.componente` (ej. `app-layout`).
- Header de la página propia: si tu layout dibuja el header (título, tema, notificaciones) solo cuando recibe `$header` (como Breeze), configurá `backups.header.titulo`. Va al slot `header` (con `componente`) o a la sección `header` (con `layout`). Con `backups.header.vista` usás tu propio componente de título; la vista recibe `$titulo`:

```php
'header' => ['titulo' => 'Backups', 'vista' => 'backups-header'], // resources/views/backups-header.blade.php
```
- Embebido en tu página de configuración:

```blade
@include('backups::panel')
```

y en `config/backups.php`: `'volver_a' => ['ruta' => 'admin.configuracion', 'parametros' => ['tab' => 'backups']]`.

Tailwind: agregá las vistas del paquete al escaneo de clases, por ejemplo `@source '../../vendor/osfermedrano/laravel-backups/resources/views';` (v4) o en `content` (v3).

## Adjuntos (opcional)

Respalda una carpeta de archivos en un zip cifrado aparte. Viene apagado. Se activa con `BACKUPS_ADJUNTOS=true` y se configura en `config/backups.php`:

```php
'adjuntos' => ['activo' => env('BACKUPS_ADJUNTOS', false), 'disco' => 'local', 'ruta' => 'adjuntos', 'conservar' => 4],
```

- El panel ofrece elegir base de datos, adjuntos o ambos. Con ambos genera dos zips.
- Automático: una vez por semana, domingo 04:00 (`programacion.adjuntos_dia`, `programacion.adjuntos_hora`).
- Se guarda en `{backup.name}-adjuntos`, con su propia retención: `backup:clean` no lo toca.
- Cada corrida copia toda la carpeta. Para muchos GB conviene una herramienta incremental como restic.

## Producción

- **Scheduler** (`* * * * * php artisan schedule:run`): el paquete programa backup 03:00, `backup:clean` 03:30 y `backups:limpiar-drive` 03:40 (configurable, desactivable con `BACKUPS_PROGRAMACION=false`).
- **Worker de colas** (Supervisor) con `--timeout` ≥ 600. Tras cada deploy: `php artisan queue:restart`.
- Notificaciones: tabla `notifications` (`php artisan make:notifications-table`) y que el modelo de usuario use `Notifiable`. El aviso guarda `tipo = backup`, `ok`, `mensaje`, `url`.

## Publicables

```bash
php artisan vendor:publish --tag=backups-config
php artisan vendor:publish --tag=backups-views
php artisan vendor:publish --tag=backups-migrations
```

## Restaurar

Descargá el zip (panel o Drive), abrilo con la contraseña de `BACKUP_ARCHIVE_PASSWORD` (7-Zip) e importá el `.sql`:

```bash
mysql -u usuario -p base < db-dumps/mysql-base.sql
```

Adjuntos: abrí el zip con la misma contraseña y copiá su contenido a la carpeta `adjuntos.ruta` del disco configurado.

## Licencia

MIT
