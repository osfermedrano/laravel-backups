<?php

namespace Osfermedrano\Backups\Tests\Feature;

use Illuminate\Support\Facades\View;
use Osfermedrano\Backups\Tests\TestCase;

/** CA14: la página propia le pasa el header al layout del proyecto. */
class PaginaHeaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        View::addLocation(__DIR__.'/../Fixtures/views');
    }

    private function pagina(): string
    {
        return $this->actingAs($this->admin())->get(route('backups.index'))->assertOk()->getContent();
    }

    public function test_sin_titulo_no_hay_header_como_antes()
    {
        config(['backups.componente' => 'layout-prueba']);

        $html = $this->pagina();

        $this->assertStringNotContainsString('<header>', $html);
        $this->assertStringContainsString('Últimos backups', $html);
    }

    public function test_con_componente_el_titulo_va_al_slot_header()
    {
        config(['backups.componente' => 'layout-prueba', 'backups.header.titulo' => 'Backups']);

        $this->assertMatchesRegularExpression('#<header>\s*<h2[^>]*>\s*Backups\s*</h2>\s*</header>#', $this->pagina());
    }

    public function test_con_layout_el_titulo_va_a_la_seccion_header()
    {
        config(['backups.layout' => 'layout-prueba', 'backups.header.titulo' => 'Backups']);

        $this->assertMatchesRegularExpression('#<header>\s*<h2[^>]*>\s*Backups\s*</h2>\s*</header>#', $this->pagina());
    }

    public function test_vista_propia_del_header_recibe_el_titulo()
    {
        config([
            'backups.componente' => 'layout-prueba',
            'backups.header.titulo' => 'Copias de seguridad',
            'backups.header.vista' => 'header-propio',
        ]);

        $this->assertMatchesRegularExpression('#<header>\s*<h1 class="header-propio">Copias de seguridad</h1>\s*</header>#', $this->pagina());
    }
}
