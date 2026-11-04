{{-- Una copia del listado. Recibe $b y $masReciente. --}}
<li class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 dark:hover:bg-zinc-700/40 transition-colors">
    <div class="h-9 w-9 shrink-0 rounded-lg flex items-center justify-center {{ $masReciente ? 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300' : 'bg-gray-100 text-gray-400 dark:bg-zinc-700 dark:text-zinc-400' }}">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
    </div>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-gray-900 dark:text-zinc-100 flex items-center gap-2">
            {{ $b['fecha']->format('d/m/Y H:i') }}
            @if ($masReciente)
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
