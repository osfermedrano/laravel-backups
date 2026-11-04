# Backup opcional de adjuntos

- Fecha: 2026-11-04
- Estado: implementada
- Pedido original: "tambien quiero que me de la opcion de hacer backup de los adjuntos. opcional. osea, tener un check de que haga uno u otro o ambos. si es ambos, debe generar dos zip" + "actualiza el paquete para incluir los adjuntos opcionales"
- Origen: integración en SIGEDH.

## Problema / Objetivo
El paquete respalda solo la BD. Restaurar recupera registros de adjuntos, no sus archivos.
Se agrega backup opcional de una carpeta de adjuntos, en zip cifrado aparte. Por defecto queda apagado:
los proyectos existentes no cambian.

## Criterios de aceptación
- [ ] CA1 — Con `adjuntos.activo = false`, panel, generación y programación quedan como en v1.2.0.
- [ ] CA2 — Con adjuntos activos, el panel ofrece BD y adjuntos. Se encola un job por tipo elegido; con ambos, dos jobs y dos zips.
- [ ] CA3 — Sin ningún tipo elegido, o con solo tipos inválidos, no se encola nada y se muestra error.
- [ ] CA4 — El zip de adjuntos conserva la estructura de carpetas, va cifrado AES-256 y no se lee sin la contraseña.
- [ ] CA5 — El zip incluye solo `adjuntos.ruta`: nunca `.env` ni otros backups. Con `ruta` vacía falla.
- [ ] CA6 — Sin archivos que respaldar falla con motivo claro y no deja zip.
- [ ] CA7 — Se conservan los últimos `adjuntos.conservar` zips, solo tras un backup exitoso. La retención y `backup:clean` de BD no se afectan entre sí.
- [ ] CA8 — El panel lista los zips de adjuntos con descarga autenticada, sin mezclarlos con BD. El último de BD y su alerta siguen mirando solo BD.
- [ ] CA9 — Con Drive conectado el zip de adjuntos se sube, y `backups:limpiar-drive` no lo borra mientras exista en local.
- [ ] CA10 — Con adjuntos activos se programa un solo job semanal, sin duplicar el de BD.
- [ ] CA11 — Si un tipo falla, el otro igual termina y cada uno avisa su resultado.
- [ ] CA12 — Un tipo en proceso bloquea volver a generar. Al terminar uno, el otro sigue figurando en proceso.

## Casos borde y errores
- Sin contraseña: no se genera, igual que BD.
- `setEncryptionName` falla: se aborta, nunca queda zip sin cifrar.
- Falla a mitad: se borra el temporal y no se aplica retención.
- Nombre `adjuntos-Y-m-d-H-i-s.zip`: no choca con BD ni en descarga ni en Drive.

## Fuera de alcance
- Incremental (restic). Discos no locales como origen. Restauración desde el panel.

## Diseño propuesto
- `config/backups.php`: bloque `adjuntos` (`activo`, `disco`, `ruta`, `conservar`) y `programacion.adjuntos_dia`, `programacion.adjuntos_hora`.
- `Backup/EmpaquetadorAdjuntos`: zip propio con `ZipArchive`. No usa Spatie.
- `Jobs/GenerarBackupAdjuntosJob` extiende `GenerarBackupJob`, que expone `generar()` y `tipo()`.
- `RepositorioBackups`: `listarAdjuntos()`, `listarTodos()`, patrón nuevo en `esNombreDeBackup()`.
- `EstadoBackup`: marca por tipo.
- `BackupsController::generar`, `DatosPanel`, `panel.blade.php`, `LimpiarDrive`, `BackupsServiceProvider::programar`, README.

### Por qué no `backup:run --only-files --config=...`
- `--config` no existe en spatie/laravel-backup 9.0.0 y el paquete soporta ^9.
- `resolveConfig()` deja `config('backup')` cambiado para todo el proceso: en `queue:work` afectaría al siguiente backup de BD.
- Compartir carpeta rompe `backup:clean`: `DefaultStrategy` agrupa por fecha y conserva uno por período.

## Plan de tests
`tests/Feature/BackupAdjuntosTest.php`, un test o más por CA.

## Notas de implementación
- Sin desvíos de los criterios.
- Verificado solo con Laravel 13 y spatie/laravel-backup 10 en local. Laravel 11/12 y spatie ^9 quedan para la matriz de CI.
- Sin test directo: el aborto cuando `setEncryptionName` falla, porque no se puede forzar con `ZipArchive` real.
- Pruebas de mutación manuales: quitar cifrado, respaldar todo el disco, quitar retención y limpiar Drive solo con BD hacen fallar los tests.
