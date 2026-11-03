{{-- Mail de aviso de backup (osfermedrano/laravel-backups). HTML de mail: tablas + estilos inline. --}}
@php
    $color = $ok ? '#059669' : '#e11d48';
    $fondo = $ok ? '#ecfdf5' : '#fff1f2';
    $fila = 'padding:10px 0;border-bottom:1px solid #f1f5f9;font-size:14px;';
    $etiqueta = $fila.'color:#64748b;width:38%;';
    $valor = $fila.'color:#0f172a;font-weight:600;text-align:right;';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $titulo }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
<span style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $resumen }}</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">

                {{-- Marca --}}
                <tr>
                    <td style="padding:0 4px 16px;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">
                        {{ $app }} · Backups
                    </td>
                </tr>

                <tr>
                    <td style="background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            {{-- Franja de estado --}}
                            <tr><td style="height:6px;background:{{ $color }};font-size:0;line-height:0;">&nbsp;</td></tr>

                            <tr>
                                <td style="padding:28px 32px 8px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="width:44px;height:44px;border-radius:12px;background:{{ $fondo }};color:{{ $color }};font-size:22px;font-weight:700;text-align:center;vertical-align:middle;">
                                                {{ $ok ? '✓' : '!' }}
                                            </td>
                                            <td style="padding-left:14px;">
                                                <div style="font-size:20px;font-weight:700;color:#0f172a;">{{ $titulo }}</div>
                                                <div style="font-size:13px;color:#64748b;margin-top:2px;">{{ $fecha }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:12px 32px 0;font-size:15px;line-height:1.6;color:#334155;">
                                    @if ($ok)
                                        Se generó el backup de la base de datos de <strong>{{ $app }}</strong>.
                                    @else
                                        No se pudo generar el backup de la base de datos de <strong>{{ $app }}</strong>.
                                    @endif
                                </td>
                            </tr>

                            @if (! $ok)
                                <tr>
                                    <td style="padding:16px 32px 0;">
                                        <div style="background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;padding:12px 14px;font-size:14px;color:#9f1239;">
                                            <strong>Motivo:</strong> {{ $motivo }}
                                        </div>
                                    </td>
                                </tr>
                            @endif

                            {{-- Detalle --}}
                            <tr>
                                <td style="padding:16px 32px 0;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                        @if ($ok)
                                            <tr><td style="{{ $etiqueta }}">Archivo</td><td style="{{ $valor }}font-family:Consolas,Menlo,monospace;font-size:13px;">{{ $archivo }}</td></tr>
                                            <tr><td style="{{ $etiqueta }}">Tamaño</td><td style="{{ $valor }}">{{ $tamanio }}</td></tr>
                                            <tr><td style="{{ $etiqueta }}">Copia local</td><td style="{{ $valor }}color:#059669;">✓ Guardada</td></tr>
                                            <tr>
                                                <td style="{{ $etiqueta }}">Google Drive</td>
                                                <td style="{{ $valor }}{{ $drive === true ? 'color:#059669;' : ($drive === false ? 'color:#e11d48;' : 'color:#64748b;font-weight:400;') }}">
                                                    @if ($drive === true) ✓ Copiado
                                                    @elseif ($drive === false) ✗ No se copió
                                                    @else No conectado
                                                    @endif
                                                </td>
                                            </tr>
                                            @if ($drive === false && $driveMotivo)
                                                <tr><td colspan="2" style="padding:8px 0 0;font-size:13px;color:#9f1239;">Drive: {{ $driveMotivo }}</td></tr>
                                            @endif
                                        @else
                                            <tr><td style="{{ $etiqueta }}">Servidor</td><td style="{{ $valor }}">{{ $servidor }}</td></tr>
                                            <tr><td style="{{ $etiqueta }}">Revisar</td><td style="{{ $valor }}font-weight:400;">Log de Laravel, scheduler y worker de colas</td></tr>
                                        @endif
                                    </table>
                                </td>
                            </tr>

                            {{-- Acción --}}
                            <tr>
                                <td style="padding:28px 32px 8px;">
                                    <a href="{{ $url }}" style="display:inline-block;background:{{ $ok ? '#0f172a' : '#e11d48' }};color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:12px 22px;border-radius:10px;">
                                        {{ $ok ? 'Descargar backup' : 'Ir al panel de backups' }}
                                    </a>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:16px 32px 28px;font-size:12px;line-height:1.6;color:#94a3b8;">
                                    @if ($ok)
                                        El archivo está cifrado: se abre con la contraseña definida en <code style="font-family:Consolas,Menlo,monospace;color:#64748b;">BACKUP_ARCHIVE_PASSWORD</code>. Necesitás iniciar sesión para descargarlo.
                                    @else
                                        Mientras no se resuelva, la base no tiene backup nuevo. Podés reintentar desde el panel.
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:16px 4px 0;font-size:12px;color:#94a3b8;text-align:center;">
                        Aviso automático de {{ $app }}. Lo recibís porque gestionás los backups.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
