{{ $titulo }} — {{ $app }}
{{ $fecha }}

@if ($ok)
Archivo: {{ $archivo }}
Tamaño: {{ $tamanio }}
Copia local: guardada
Google Drive: {{ $drive === true ? 'copiado' : ($drive === false ? 'no se copió'.($driveMotivo ? " ({$driveMotivo})" : '') : 'no conectado') }}

Descargar: {{ $url }}

El archivo está cifrado: se abre con la contraseña de BACKUP_ARCHIVE_PASSWORD.
@else
Motivo: {{ $motivo }}
Servidor: {{ $servidor }}
Revisar: log de Laravel, scheduler y worker de colas.

Panel de backups: {{ $url }}
@endif
