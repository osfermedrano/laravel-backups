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
