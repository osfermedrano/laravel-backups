{{-- Panel de backups (osfermedrano/laravel-backups). Incluible: @include('backups::panel') --}}
@php
    $ConexionDrive = \Osfermedrano\Backups\Drive\ConexionDrive::class;
    $Repositorio = \Osfermedrano\Backups\Backup\RepositorioBackups::class;

    $horaBackup = (string) config('backups.programacion.backup', '03:00');
    $programado = (bool) config('backups.programacion.activa', true);
    $proximo = null;
    if ($programado) {
        $proximo = now()->setTimeFromTimeString($horaBackup);
        if ($proximo->isPast()) {
            $proximo->addDay();
        }
    }
    $totalCopias = $ultimos->count() + $anteriores;
    $horasAlerta = (int) config('backups.horas_alerta', 26);

    // [tono, título, detalle]
    $salud = match (true) {
        $sinPassword => ['rose', 'Sin configurar', 'Falta la contraseña de cifrado'],
        $enProceso => ['violet', 'En proceso', 'Generando un backup…'],
        ! $ultimo => ['amber', 'Sin backups', 'Todavía no se generó ninguno'],
        $ultimoViejo => ['rose', 'Desactualizado', "Más de {$horasAlerta} h sin backup"],
        default => ['emerald', 'Al día', 'La base está respaldada'],
    };

    $tono = [
        'emerald' => ['punto' => 'bg-emerald-500', 'texto' => 'text-emerald-600 dark:text-emerald-400', 'caja' => 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300'],
        'amber' => ['punto' => 'bg-amber-500', 'texto' => 'text-amber-600 dark:text-amber-400', 'caja' => 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-300'],
        'rose' => ['punto' => 'bg-rose-500', 'texto' => 'text-rose-600 dark:text-rose-400', 'caja' => 'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300'],
        'violet' => ['punto' => 'bg-violet-500', 'texto' => 'text-violet-600 dark:text-violet-400', 'caja' => 'bg-violet-50 border-violet-200 text-violet-800 dark:bg-violet-500/10 dark:border-violet-500/30 dark:text-violet-300'],
        'gray' => ['punto' => 'bg-gray-400', 'texto' => 'text-gray-500 dark:text-zinc-400', 'caja' => 'bg-gray-50 border-gray-200 text-gray-700 dark:bg-zinc-700 dark:border-zinc-600 dark:text-zinc-300'],
    ];

    // Drive: [tono, etiqueta]
    $driveEstado = match (true) {
        ! $drive->configurado() => ['gray', 'Sin configurar'],
        $drive->estado() === $ConexionDrive::INVALIDO => ['rose', 'Requiere reconexión'],
        $drive->conectado() => ['emerald', 'Conectado'],
        default => ['amber', 'Sin conectar'],
    };

    $tarjeta = 'rounded-2xl bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 shadow-sm';
    $kpiEtiqueta = 'flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-zinc-500';
    $kpiValor = 'mt-3 text-2xl font-bold text-gray-900 dark:text-zinc-50 leading-tight';
    $kpiDetalle = 'mt-1 text-xs text-gray-500 dark:text-zinc-400';
    $aviso = 'flex items-start gap-3 px-4 py-3 rounded-xl border text-sm';
@endphp

<div class="space-y-5" data-backups-panel>

    {{-- Mensajes flash --}}
    @if ($mensaje)
        <div class="{{ $aviso }} {{ $tono['emerald']['caja'] }}">
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            <span>{{ $mensaje }}</span>
        </div>
    @endif
    @if ($error)
        <div class="{{ $aviso }} {{ $tono['rose']['caja'] }}">
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            <span>{{ $error }}</span>
        </div>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        {{-- Estado --}}
        <div class="{{ $tarjeta }} p-5 relative overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-1 {{ $tono[$salud[0]]['punto'] }}"></div>
            <p class="{{ $kpiEtiqueta }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
                Estado
            </p>
            <p class="{{ $kpiValor }} flex items-center gap-2 {{ $tono[$salud[0]]['texto'] }}">
                <span class="relative flex h-2.5 w-2.5">
                    @if ($enProceso)
                        <span class="absolute inline-flex h-full w-full rounded-full {{ $tono[$salud[0]]['punto'] }} opacity-75 animate-ping"></span>
                    @endif
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full {{ $tono[$salud[0]]['punto'] }}"></span>
                </span>
                {{ $salud[1] }}
            </p>
            <p class="{{ $kpiDetalle }}">{{ $salud[2] }}</p>
        </div>

        {{-- Último --}}
        <div class="{{ $tarjeta }} p-5">
            <p class="{{ $kpiEtiqueta }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                Último backup
            </p>
            @if ($ultimo)
                <p class="{{ $kpiValor }}">{{ $ultimo['fecha']->diffForHumans(null, true) }}</p>
                <p class="{{ $kpiDetalle }}">{{ $ultimo['fecha']->format('d/m/Y H:i') }} · {{ $Repositorio::tamanio($ultimo['bytes']) }}</p>
            @else
                <p class="{{ $kpiValor }} text-gray-300 dark:text-zinc-600">—</p>
                <p class="{{ $kpiDetalle }}">Nunca</p>
            @endif
        </div>

        {{-- Próximo --}}
        <div class="{{ $tarjeta }} p-5">
            <p class="{{ $kpiEtiqueta }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                Próximo automático
            </p>
            @if ($programado)
                <p class="{{ $kpiValor }}">{{ $proximo->diffForHumans(null, true) }}</p>
                <p class="{{ $kpiDetalle }}">Todos los días a las {{ $horaBackup }}</p>
            @else
                <p class="{{ $kpiValor }} text-gray-300 dark:text-zinc-600">Off</p>
                <p class="{{ $kpiDetalle }}">Programación desactivada</p>
            @endif
        </div>

        {{-- Copias --}}
        <div class="{{ $tarjeta }} p-5">
            <p class="{{ $kpiEtiqueta }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
                Copias guardadas
            </p>
            <p class="{{ $kpiValor }}">{{ $totalCopias }}</p>
            <p class="{{ $kpiDetalle }}">
                {{ $sinPassword ? 'Cifrado sin configurar' : 'Zip cifrado' }} · retención automática
            </p>
        </div>
    </div>

    {{-- Avisos --}}
    @if ($sinPassword)
        <div class="{{ $aviso }} {{ $tono['rose']['caja'] }}">
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"/></svg>
            <span>Falta definir <code class="font-mono font-bold">BACKUP_ARCHIVE_PASSWORD</code> en el <code class="font-mono">.env</code>. Sin esa contraseña no se generan backups. Guardala en un gestor de contraseñas: sin ella los backups no se pueden abrir.</span>
        </div>
    @endif

    @if ($enProceso)
        <div class="{{ $aviso }} {{ $tono['violet']['caja'] }} items-center justify-between">
            <span class="flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path></svg>
                <span><strong>Backup en proceso…</strong> Podés seguir trabajando; te avisamos cuando termine.</span>
            </span>
            <a href="" class="font-semibold underline shrink-0">Actualizar</a>
        </div>
        <script>setTimeout(function () { location.reload(); }, 15000);</script>
    @endif

    @if (! $ultimo && ! $sinPassword)
        <div class="{{ $aviso }} {{ $tono['amber']['caja'] }}">
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
            <span>Todavía no hay backups. Generá el primero ahora.</span>
        </div>
    @elseif ($ultimo && $ultimoViejo)
        <div class="{{ $aviso }} {{ $tono['rose']['caja'] }}">
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.730 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
            <span><strong>El último backup tiene más de {{ $horasAlerta }} horas</strong> ({{ $ultimo['fecha']->diffForHumans() }}). Revisá que el scheduler y el worker de colas estén corriendo.</span>
        </div>
    @endif

    {{-- Listado --}}
    <section class="{{ $tarjeta }} overflow-hidden">
        <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 dark:border-zinc-700">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-zinc-50">Últimos backups</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">Base de datos completa, cifrada. Los viejos se depuran según la retención.</p>
            </div>
            <form method="POST" action="{{ route('backups.generar') }}" class="shrink-0 flex flex-col sm:flex-row sm:items-center gap-3"
                onsubmit="var b=this.querySelector('button');b.disabled=true;b.querySelector('span').textContent='Encolando...';">
                @csrf
                @if ($adjuntosActivos)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-zinc-200">
                        <input type="checkbox" name="tipos[]" value="bd" checked class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                        Base de datos
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-zinc-200">
                        <input type="checkbox" name="tipos[]" value="adjuntos" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                        Adjuntos
                    </label>
                @endif
                <button type="submit" @disabled($enProceso || $sinPassword)
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-800 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    <span>{{ $enProceso ? 'Generando…' : 'Generar backup ahora' }}</span>
                </button>
            </form>
        </div>

        @if ($ultimos->isNotEmpty())
            <ul class="divide-y divide-gray-100 dark:divide-zinc-700">
                @foreach ($ultimos as $b)
                    <li class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 dark:hover:bg-zinc-700/40 transition-colors">
                        <div class="h-9 w-9 shrink-0 rounded-lg flex items-center justify-center {{ $loop->first ? 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300' : 'bg-gray-100 text-gray-400 dark:bg-zinc-700 dark:text-zinc-400' }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-zinc-100 flex items-center gap-2">
                                {{ $b['fecha']->format('d/m/Y H:i') }}
                                @if ($loop->first)
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Más reciente</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $b['fecha']->diffForHumans() }}</p>
                        </div>
                        <span class="hidden sm:inline text-sm tabular-nums text-gray-600 dark:text-zinc-300">{{ $Repositorio::tamanio($b['bytes']) }}</span>
                        <a href="{{ route('backups.descargar', $b['nombre']) }}" title="Descargar"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-gray-700 border border-gray-200 hover:border-violet-300 hover:text-violet-700 hover:bg-violet-50 dark:text-zinc-200 dark:border-zinc-600 dark:hover:bg-violet-500/10 dark:hover:text-violet-300 transition-colors shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            Descargar
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($anteriores > 0)
                <p class="px-5 py-3 text-xs text-gray-400 dark:text-zinc-500 border-t border-gray-100 dark:border-zinc-700">
                    y {{ $anteriores }} anteriores (se conservan según la retención).
                </p>
            @endif
        @else
            <div class="px-5 py-14 text-center">
                <div class="mx-auto h-12 w-12 rounded-full bg-gray-100 dark:bg-zinc-700 text-gray-400 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.250h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                </div>
                <p class="text-sm font-semibold text-gray-700 dark:text-zinc-200">Sin backups todavía</p>
                <p class="text-xs text-gray-400 mt-1">Generá el primero con el botón de arriba.</p>
            </div>
        @endif
    </section>

    {{-- Adjuntos --}}
    @if ($adjuntosActivos)
        <section class="{{ $tarjeta }} overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-zinc-700">
                <h3 class="text-base font-bold text-gray-900 dark:text-zinc-50">Backups de adjuntos</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">Archivos adjuntos, cifrados. Se conservan los últimos {{ max(1, (int) config('backups.adjuntos.conservar', 4)) }}.</p>
            </div>
            @if ($adjuntos->isNotEmpty())
                <ul class="divide-y divide-gray-100 dark:divide-zinc-700">
                    @foreach ($adjuntos as $a)
                        <li class="flex items-center gap-4 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-zinc-100">{{ $a['fecha']->format('d/m/Y H:i') }}</p>
                                <p class="text-xs text-gray-500 dark:text-zinc-400">{{ $a['fecha']->diffForHumans() }}</p>
                            </div>
                            <span class="hidden sm:inline text-sm tabular-nums text-gray-600 dark:text-zinc-300">{{ $Repositorio::tamanio($a['bytes']) }}</span>
                            <a href="{{ route('backups.descargar', $a['nombre']) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-gray-700 border border-gray-200 hover:border-violet-300 hover:text-violet-700 hover:bg-violet-50 dark:text-zinc-200 dark:border-zinc-600 dark:hover:bg-violet-500/10 dark:hover:text-violet-300 transition-colors shrink-0">Descargar</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="px-5 py-6 text-sm text-gray-500 dark:text-zinc-400">Todavía no hay backups de adjuntos.</p>
            @endif
        </section>
    @endif

    {{-- Google Drive --}}
    <section class="{{ $tarjeta }} p-5">
        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <div class="flex items-start gap-4 flex-1 min-w-0">
                <div class="h-10 w-10 shrink-0 rounded-xl bg-gray-100 dark:bg-zinc-700 flex items-center justify-center">
                    <svg class="w-5 h-5" viewBox="0 0 87.3 78" aria-hidden="true"><path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3L27.5 53H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/><path d="M43.65 25 29.9 1.2c-1.35.8-2.5 1.9-3.3 3.3L1.2 48.5A9.06 9.06 0 0 0 0 53h27.5z" fill="#00ac47"/><path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75L86.1 57.5c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.850 11.5z" fill="#ea4335"/><path d="M43.65 25 57.4 1.2C56.05.4 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/><path d="M59.8 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/><path d="M73.4 26.5 60.7 4.5c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25 59.8 53h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/></svg>
                </div>
                <div class="min-w-0 text-sm">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-bold text-gray-900 dark:text-zinc-50">Copia en Google Drive</h3>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $tono[$driveEstado[0]]['caja'] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $tono[$driveEstado[0]]['punto'] }}"></span>{{ $driveEstado[1] }}
                        </span>
                    </div>
                    @if (! $drive->configurado())
                        <p class="text-gray-500 dark:text-zinc-400 mt-1">Para activarla, completá <code class="font-mono text-xs">GOOGLE_DRIVE_CLIENT_ID</code> y <code class="font-mono text-xs">GOOGLE_DRIVE_CLIENT_SECRET</code> en el <code class="font-mono text-xs">.env</code>.</p>
                    @elseif ($drive->estado() === $ConexionDrive::INVALIDO)
                        <p class="text-rose-600 dark:text-rose-400 mt-1">{{ $drive->motivo() }}. Los backups siguen guardándose en local, pero no se copian a Drive.</p>
                    @elseif ($drive->conectado())
                        <p class="text-gray-600 dark:text-zinc-300 mt-1">Conectado como {{ $drive->email() ?? 'tu cuenta' }} · carpeta "{{ $drive->carpeta() }}"</p>
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-0.5">Cada backup se copia ahí. La app solo puede ver y crear sus propios archivos (permiso drive.file).</p>
                    @else
                        <p class="text-gray-500 dark:text-zinc-400 mt-1">Los backups se guardan solo en este servidor. Conectá una cuenta para tener una copia externa.</p>
                    @endif
                </div>
            </div>

            @if ($drive->configurado())
                <div class="shrink-0">
                    @if ($drive->conectado())
                        <form method="POST" action="{{ route('backups.google.desconectar') }}" onsubmit="return confirm('¿Desconectar Google Drive? Los backups ya subidos quedan en tu Drive.')">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold text-rose-600 border border-rose-200 hover:bg-rose-50 dark:text-rose-300 dark:border-rose-500/30 dark:hover:bg-rose-500/10 transition-colors">Desconectar</button>
                        </form>
                    @else
                        <a href="{{ route('backups.google.conectar') }}" class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 shadow-sm transition-colors">
                            {{ $drive->estado() === $ConexionDrive::INVALIDO ? 'Reconectar Google Drive' : 'Conectar Google Drive' }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>
</div>
