# Paquete `osfermedrano/laravel-backups`

- Fecha: 2026-11-03
- Estado: aprobada
- Pedido original: "necesito aplicar este mismo sistema de backups a mis otros proyectos. como lo traslado? haces una skill?" + "todos mis proyectos / mismo stack / licencias separado / tengo github" + nombre `osfermedrano/...`.
- Origen: sistema de backups de `abonados` (specs `2026-11-02-backup-bd.md`, fases 1 y 2).

## Problema / Objetivo
El sistema de backups vive dentro de `abonados`. Copiarlo a 33 proyectos = 33 copias que divergen; en un día se le encontraron 2 bugs. Objetivo: **un paquete Composer** con el núcleo (una sola copia, tests en un lugar, `composer update` para propagar arreglos) + una **skill de integración** aparte para enchufarlo en cada proyecto.

Relevamiento de los 33 proyectos (`c:\laragon\www`): Laravel 10/11/12/13, Livewire 3/4/ninguno, Spatie Permission 5–8/ninguno, Spatie Backup 9/10/ninguno, modelo `Config` solo en 2. → el paquete **no puede depender** de Livewire, Spatie Permission ni del `Config` de abonados.

## Alcance funcional (paridad con abonados)
Backup de BD cifrado (AES-256) en cola, manual + diario, retención 7d/4s/6m/1a, avisos in-app (database notifications) + mail best-effort, últimos 10 con descarga autenticada, alertas (sin password / sin backups / > 26 h), copia en Google Drive (OAuth `drive.file`, carpeta propia, retención espejo, estado conectado/inválido/desconectado).

## Criterios de aceptación

### Compatibilidad
- [ ] CA1 — Instala y pasa su suite en **Laravel 11, 12 y 13** con **PHP 8.2+** (matriz en GitHub Actions). Laravel 10 fuera de alcance.
- [ ] CA2 — **No requiere Livewire.** UI en Blade + controladores (funciona con o sin Livewire en el proyecto).
- [ ] CA3 — **No requiere Spatie Permission.** Autorización por Gate `backups.gestionar`; default: `hasPermissionTo('backup.gestionar')` si el usuario usa Spatie, si no `false`. Cada proyecto puede redefinir el Gate (ej. `$user->is_admin`).
- [ ] CA4 — **No pisa** un `config/backup.php` existente del proyecto. El paquete fuerza en tiempo de ejecución solo lo imprescindible (`--only-db`, sin notificaciones de Spatie) y **se niega a correr sin `BACKUP_ARCHIVE_PASSWORD`**.
- [ ] CA5 — Compatible con `spatie/laravel-backup` ^9 y ^10.

### Funcional (portado de abonados, mismos tests)
- [ ] CA6 — Generar en cola sin duplicados; job automático diario; notificaciones database + mail best-effort (SMTP caído no corta).
- [ ] CA7 — Panel: últimos 10 + "y N anteriores", descarga autenticada (`backups.gestionar`), rechazo de path traversal, alertas.
- [ ] CA8 — Google Drive completo (conectar/callback con state/desconectar, subida resumable, carpeta propia, `invalid_grant` → inválido, retención espejo, nunca toca archivos ajenos).
- [ ] CA9 — Token de Drive cifrado en **tabla propia** del paquete (`backups_ajustes`), nunca en vistas.

### Integración
- [ ] CA10 — `php artisan vendor:publish --tag=backups-config|backups-views|backups-migrations`. Config: prefijo/middleware de rutas, nombre del Gate, usuarios a notificar (callable), horarios, nombre de carpeta Drive, modelo de usuario.
- [ ] CA11 — Panel embebible: `@include('backups::panel')` dentro de la página de configuración de cada proyecto, o página propia `/{prefijo}/backups` usando el layout configurado.
- [ ] CA12 — Scheduler registrado por el paquete (desactivable por config).
- [ ] CA13 — `abonados` migra al paquete: borra `app/Backup/*` y afines, mismo comportamiento; su suite completa sigue verde.

## Fuera de alcance
- Laravel 10 (3 proyectos: htc-os, jornadadecomunicacion, logcentralhtc).
- Campana de notificaciones (cada proyecto decide su UI; el paquete trae un componente Blade opcional si sobra tiempo).
- Restaurar desde la UI. Otros destinos (S3, Dropbox).
- Paquete de licencias (spec y repo aparte, después).
- La skill de integración (siguiente paso, tras CA13).

## Diseño propuesto
- Ubicación de desarrollo: `C:\Users\Oscar\paquetes\laravel-backups` (fuera de `www`). `abonados` lo consume con repositorio Composer tipo `path` durante el desarrollo; luego VCS de GitHub.
- Namespace `Osfermedrano\Backups`. `BackupsServiceProvider` (auto-discovery): config, rutas, vistas, migraciones, comandos, Gate default, scheduler.
- Dependencias: `illuminate/*` ^11|^12|^13, `spatie/laravel-backup` ^9|^10, `google/apiclient` ^2.20. README indica limitar servicios de Google a Drive (`extra.google/apiclient-services` + `Google\Task\Composer::cleanup` en el `composer.json` **del proyecto**).
- Tests: Orchestra Testbench (sqlite), port de los 45 tests de abonados adaptados.
- Repo público `github.com/osfermedrano/laravel-backups`, MIT, versionado semántico (`v1.0.0`).

## Supuestos
- Repo **público** (no contiene secretos; recomendado y explicado al usuario; confirmar antes de crear el repo).
- UI en español.
- Notificar por defecto a todos los usuarios que pasan el Gate.

## Riesgos / Preguntas abiertas
- Proyectos con Spatie Backup propio (gym, estudioJuridico, policonsultorio2, expedientehtc, biblioteca): revisar su `config/backup.php` al integrar (lo hará la skill).
- Alpine.js no está en proyectos sin Livewire: el panel debe funcionar sin JS (formularios POST).
- Publicar en GitHub = acción externa: se confirma con el usuario antes de `gh repo create`.

## Plan de tests
- Paquete: port de `tests/Feature/Backup/*` de abonados (45) + `CompatibilidadTest` (Gate sin Spatie, config existente no pisada) + matriz CI L11/L12/L13.
- abonados: suite completa verde tras migrar (CA13).

## Notas de implementación
- Panel = vista Blade `backups::panel` con view composer (`View\DatosPanel`): incluible en cualquier vista sin pasar datos. Formularios POST + CSRF; JS solo opcional (recarga cada 15 s si hay backup en proceso).
- Página propia en 3 modos: simple (Tailwind CDN), `backups.layout` (@extends) o `backups.componente` (<x-...>).
- Gate por defecto solo si el proyecto no lo definió antes (`Gate::has`). Destinatarios automáticos: todos los que pasan el Gate (`lazy()`), o clase invocable en `backups.destinatarios` (sin closures, compatible con config:cache).
- Disco: primer `backup.backup.destination.disks` de Spatie (respeta la config del proyecto).
- Tests de EjecutorBackup: en vez de mockear Artisan (Testbench tiene consola final) registran un `backup:run` falso que anota las opciones → prueban la llamada real.
- Suite paquete: 51 tests verdes (L13 / Testbench 11).
