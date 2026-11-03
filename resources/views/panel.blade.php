{{-- Panel de backups (osfermedrano/laravel-backups). Incluible: @include('backups::panel') --}}
@php($ConexionDrive = \Osfermedrano\Backups\Drive\ConexionDrive::class)
<div class="space-y-6" data-backups-panel>
    @if ($mensaje)
        <div class="px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-100 dark:border-emerald-900/30 text-xs text-emerald-700 dark:text-emerald-300">{{ $mensaje }}</div>
    @endif
    @if ($error)
        <div class="px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-900/10 border border-rose-100 dark:border-rose-900/30 text-xs text-rose-700 dark:text-rose-300">{{ $error }}</div>
    @endif

    {{-- Encabezado + acción --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">Backup de la base de datos</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                Zip cifrado con la base completa. Automático todos los días a las {{ config('backups.programacion.backup', '03:00') }}; los viejos se depuran según la retención.
            </p>
        </div>
        <form method="POST" action="{{ route('backups.generar') }}" class="shrink-0"
            onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Encolando...';">
            @csrf
            <button type="submit" @disabled($enProceso || $sinPassword)
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                Generar backup ahora
            </button>
        </form>
    </div>

    {{-- Estados --}}
    @if ($sinPassword)
        <div class="px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-900/10 border border-rose-100 dark:border-rose-900/30 text-xs text-rose-700 dark:text-rose-300">
            Falta definir <code class="font-mono font-bold">BACKUP_ARCHIVE_PASSWORD</code> en el <code>.env</code>. Sin esa contraseña no se generan backups. Guardala en un gestor de contraseñas: sin ella los backups no se pueden abrir.
        </div>
    @endif

    @if ($enProceso)
        <div class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl bg-violet-50 dark:bg-violet-900/10 border border-violet-100 dark:border-violet-900/30 text-xs text-violet-700 dark:text-violet-300">
            <span><strong>Backup en proceso…</strong> Podés seguir trabajando; te avisamos cuando termine.</span>
            <a href="" class="underline shrink-0">Actualizar</a>
        </div>
        <script>setTimeout(function () { location.reload(); }, 15000);</script>
    @endif

    @if (! $ultimo)
        <div class="px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 text-xs text-amber-700 dark:text-amber-300">
            Todavía no hay backups. Generá el primero ahora.
        </div>
    @elseif ($ultimoViejo)
        <div class="px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-900/10 border border-rose-100 dark:border-rose-900/30 text-xs text-rose-700 dark:text-rose-300">
            <strong>El último backup tiene más de {{ config('backups.horas_alerta', 26) }} horas</strong> ({{ $ultimo['fecha']->diffForHumans() }}). Revisá que el scheduler y el worker de colas estén corriendo.
        </div>
    @endif

    {{-- Google Drive --}}
    <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="min-w-0 text-xs">
                <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Copia en Google Drive</p>
                @if (! $drive->configurado())
                    <p class="text-gray-500 mt-0.5">Para activarla, completá <code class="font-mono">GOOGLE_DRIVE_CLIENT_ID</code> y <code class="font-mono">GOOGLE_DRIVE_CLIENT_SECRET</code> en el <code>.env</code>.</p>
                @elseif ($drive->estado() === $ConexionDrive::INVALIDO)
                    <p class="text-rose-600 dark:text-rose-400 mt-0.5">{{ $drive->motivo() }}. Los backups siguen guardándose en local, pero no se copian a Drive.</p>
                @elseif ($drive->conectado())
                    <p class="text-emerald-600 dark:text-emerald-400 mt-0.5">Conectado como {{ $drive->email() ?? 'tu cuenta' }} · carpeta "{{ $drive->carpeta() }}"</p>
                    <p class="text-gray-400 mt-0.5">Cada backup se copia ahí. La app solo puede ver y crear sus propios archivos (permiso drive.file).</p>
                @else
                    <p class="text-gray-500 mt-0.5">Sin conectar. Los backups se guardan solo en este servidor.</p>
                @endif
            </div>

            @if ($drive->configurado())
                <div class="shrink-0">
                    @if ($drive->conectado())
                        <form method="POST" action="{{ route('backups.google.desconectar') }}" onsubmit="return confirm('¿Desconectar Google Drive? Los backups ya subidos quedan en tu Drive.')">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 dark:bg-rose-900/20 dark:text-rose-300">Desconectar</button>
                        </form>
                    @else
                        <a href="{{ route('backups.google.conectar') }}" class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold text-white bg-violet-600 hover:bg-violet-700">
                            {{ $drive->estado() === $ConexionDrive::INVALIDO ? 'Reconectar Google Drive' : 'Conectar Google Drive' }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Últimos backups --}}
    <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h4 class="text-xs font-bold text-gray-700 dark:text-gray-200">Últimos backups</h4>
            @if ($ultimo)
                <span class="text-[11px] text-gray-400">Último: {{ $ultimo['fecha']->diffForHumans() }}</span>
            @endif
        </div>

        @if ($ultimos->isNotEmpty())
            <ul class="divide-y divide-gray-50 dark:divide-gray-700">
                @foreach ($ultimos as $b)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $b['fecha']->format('d/m/Y H:i') }}</p>
                            <p class="text-[11px] text-gray-400">{{ \Osfermedrano\Backups\Backup\RepositorioBackups::tamanio($b['bytes']) }} · Local</p>
                        </div>
                        <a href="{{ route('backups.descargar', $b['nombre']) }}"
                            class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-violet-600 bg-violet-50 hover:bg-violet-100 dark:bg-violet-900/20 dark:text-violet-300 transition-colors shrink-0">
                            Descargar
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($anteriores > 0)
                <p class="px-4 py-2.5 text-[11px] text-gray-400 border-t border-gray-50 dark:border-gray-700">y {{ $anteriores }} anteriores (se conservan según la retención).</p>
            @endif
        @else
            <p class="px-4 py-8 text-center text-xs text-gray-400">Sin backups todavía.</p>
        @endif
    </div>
</div>
