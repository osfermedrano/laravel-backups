{{-- Como el app-layout de Breeze: el header solo se dibuja si llega el slot. --}}
<div>
    @isset($header)
        <header>{{ $header }}</header>
    @endisset
    <main>{{ $slot }}</main>
</div>
