<x-dynamic-component :component="$componente">
    @if (filled($titulo))
        <x-slot name="header">
            @include('backups::header')
        </x-slot>
    @endif

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @include('backups::panel')
    </div>
</x-dynamic-component>
