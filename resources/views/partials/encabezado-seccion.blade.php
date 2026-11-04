{{-- Encabezado de una sección. Recibe $titulo, $detalle, $icono (path de heroicon) y $color (clases). --}}
<div class="flex items-center gap-3 min-w-0">
    <div class="h-10 w-10 shrink-0 rounded-xl flex items-center justify-center {{ $color }}">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icono }}"/></svg>
    </div>
    <div class="min-w-0">
        <h3 class="text-base font-bold text-gray-900 dark:text-zinc-50">{{ $titulo }}</h3>
        <p class="text-xs text-gray-500 dark:text-zinc-400 mt-0.5">{{ $detalle }}</p>
    </div>
</div>
