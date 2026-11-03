<?php

namespace Osfermedrano\Backups\Tests\Feature;

use Osfermedrano\Backups\Ajuste;
use Osfermedrano\Backups\Drive\ClienteGoogle;
use Osfermedrano\Backups\Drive\ConexionDrive;
use Osfermedrano\Backups\Tests\TestCase;

/** CA8, CA9 — conexión OAuth. */
class DriveConexionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'backups.google_drive.client_id' => 'id-de-prueba.apps.googleusercontent.com',
            'backups.google_drive.client_secret' => 'secreto-de-prueba',
            'backups.google_drive.redirect' => 'http://localhost:8000/admin/backups/google/callback',
            'backups.google_drive.folder' => 'Backups mi-app',
        ]);
    }

    private function conexion(): ConexionDrive
    {
        return app(ConexionDrive::class);
    }

    private function vuelveAlPanel()
    {
        return route('backups.index');
    }

    public function test_sin_credenciales_no_ofrece_conectar_y_explica()
    {
        config(['backups.google_drive.client_id' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('backups.index'))
            ->assertSee('GOOGLE_DRIVE_CLIENT_ID')
            ->assertDontSee(route('backups.google.conectar'), false);

        $this->actingAs($admin)->get(route('backups.google.conectar'))->assertRedirect($this->vuelveAlPanel());
    }

    public function test_conectar_redirige_a_google_solo_con_drive_file_offline_y_state()
    {
        $r = $this->actingAs($this->admin())->get(route('backups.google.conectar'));

        $url = $r->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('https://www.googleapis.com/auth/drive.file', $q['scope']);
        $this->assertSame('offline', $q['access_type']);
        $this->assertSame('consent', $q['prompt']);
        $this->assertSame('http://localhost:8000/admin/backups/google/callback', $q['redirect_uri']);
        $this->assertSame($q['state'], session('backups_gdrive_state'));
    }

    public function test_callback_con_state_invalido_rechaza_y_no_guarda()
    {
        $this->mock(ClienteGoogle::class, fn ($m) => $m->shouldNotReceive('intercambiarCodigo'));

        $this->actingAs($this->admin())->withSession(['backups_gdrive_state' => 'esperado'])
            ->get(route('backups.google.callback', ['state' => 'otro', 'code' => 'x']))
            ->assertForbidden();

        $this->assertFalse($this->conexion()->conectado());
    }

    public function test_callback_ok_guarda_token_cifrado_en_tabla_propia_y_email()
    {
        $this->mock(ClienteGoogle::class, fn ($m) => $m->shouldReceive('intercambiarCodigo')->with('codigo-ok')
            ->andReturn(['refresh_token' => 'rt-secreto-123', 'email' => 'yo@gmail.com']));
        $admin = $this->admin();

        $this->actingAs($admin)->withSession(['backups_gdrive_state' => 'st'])
            ->get(route('backups.google.callback', ['state' => 'st', 'code' => 'codigo-ok']))
            ->assertRedirect($this->vuelveAlPanel())
            ->assertSessionHas('backups_mensaje');

        $this->assertTrue($this->conexion()->conectado());
        $this->assertSame('rt-secreto-123', $this->conexion()->refreshToken());
        $this->assertSame('yo@gmail.com', $this->conexion()->email());

        $crudo = Ajuste::where('clave', ConexionDrive::CLAVE_TOKEN)->value('valor');
        $this->assertNotEmpty($crudo);
        $this->assertStringNotContainsString('rt-secreto-123', $crudo);
        $this->actingAs($admin)->get(route('backups.index'))->assertDontSee('rt-secreto-123');
    }

    public function test_callback_sin_refresh_token_o_con_error_no_conecta()
    {
        $this->mock(ClienteGoogle::class, fn ($m) => $m->shouldReceive('intercambiarCodigo')->andReturn(['refresh_token' => null, 'email' => 'yo@gmail.com']));
        $admin = $this->admin();

        $this->actingAs($admin)->withSession(['backups_gdrive_state' => 'st'])
            ->get(route('backups.google.callback', ['state' => 'st', 'code' => 'c']))
            ->assertSessionHas('backups_error');

        $this->actingAs($admin)->withSession(['backups_gdrive_state' => 'st'])
            ->get(route('backups.google.callback', ['state' => 'st', 'error' => 'access_denied']))
            ->assertSessionHas('backups_error');

        $this->assertFalse($this->conexion()->conectado());
    }

    public function test_desconectar_borra_token_aunque_falle_la_revocacion()
    {
        $this->conexion()->guardar('rt-1', 'yo@gmail.com');
        $this->mock(ClienteGoogle::class, fn ($m) => $m->shouldReceive('revocar')->with('rt-1')->andThrow(new \RuntimeException('sin red')));

        $this->actingAs($this->admin())->post(route('backups.google.desconectar'))->assertRedirect($this->vuelveAlPanel());

        $this->assertFalse($this->conexion()->conectado());
        $this->assertNull($this->conexion()->refreshToken());
    }

    public function test_rutas_requieren_permiso()
    {
        $comun = $this->usuarioComun();

        $this->actingAs($comun)->get(route('backups.google.conectar'))->assertForbidden();
        $this->actingAs($comun)->get(route('backups.google.callback', ['state' => 'x', 'code' => 'y']))->assertForbidden();
        $this->actingAs($comun)->post(route('backups.google.desconectar'))->assertForbidden();
    }

    public function test_estado_visible_en_el_panel()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('backups.index'))->assertSee('Conectar Google Drive');

        $this->conexion()->guardar('rt-1', 'yo@gmail.com');
        $this->actingAs($admin)->get(route('backups.index'))
            ->assertSee('Conectado como yo@gmail.com')->assertSee('Backups mi-app')->assertSee('Desconectar');

        $this->conexion()->marcarInvalido('Token revocado');
        $this->actingAs($admin)->get(route('backups.index'))->assertSee('Reconectar')->assertSee('Token revocado');
    }

    public function test_volver_a_configurable_para_panel_embebido()
    {
        \Illuminate\Support\Facades\Route::get('/mi-config', fn () => 'cfg')->name('mi.config');
        config(['backups.volver_a' => ['ruta' => 'mi.config', 'parametros' => ['tab' => 'backups']]]);

        $this->actingAs($this->admin())->post(route('backups.google.desconectar'))
            ->assertRedirect(route('mi.config', ['tab' => 'backups']));
    }
}
