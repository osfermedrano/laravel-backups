{{-- Header de la página propia (CA14): la vista del proyecto si hay, si no un título simple. --}}
@if ($vista = config('backups.header.vista'))
    @include($vista, ['titulo' => $titulo])
@else
    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-zinc-100">{{ $titulo }}</h2>
@endif
