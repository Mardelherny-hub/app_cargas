<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ManualTest extends TestCase
{
    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carpeta = sys_get_temp_dir().'/manual-test-'.uniqid();
        $this->crearContenido();

        config([
            'manual.carpeta' => $this->carpeta,
            'manual.mostrar_borradores' => false,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('manual.index'))->assertRedirect(route('login'));
    }

    public function test_navigation_link_is_shown_for_every_role_on_desktop_and_mobile(): void
    {
        $usuarios = [
            'super-admin' => $this->usuario(['super-admin']),
            'company-admin' => $this->usuario(['company-admin'], ['Cargas']),
            'user' => $this->usuario(['user'], ['Cargas']),
        ];

        foreach ($usuarios as $rol => $usuario) {
            $html = $this->actingAs($usuario)->get(route('manual.index'))->assertOk()->getContent();

            $this->assertSame(2, substr_count($html, 'href="'.route('manual.index').'"'), "Enlaces al manual para {$rol}");
            $this->assertStringContainsString('📖 Manual', $html, "Enlace de celular para {$rol}");
        }
    }

    public function test_each_role_sees_only_its_articles(): void
    {
        $this->assertSame(
            ['primeros-pasos/ingresar', 'primeros-pasos/cambiar-clave', 'viajes-y-cargas/armar-convoy'],
            $this->clavesVisibles($this->usuario(['user'], ['Cargas']))
        );

        $this->assertSame(
            ['primeros-pasos/ingresar', 'primeros-pasos/cambiar-clave'],
            $this->clavesVisibles($this->usuario(['company-admin'], ['Desconsolidador']))
        );

        $this->assertSame(
            ['primeros-pasos/ingresar', 'primeros-pasos/cambiar-clave', 'viajes-y-cargas/armar-convoy', 'afip-argentina/solo-admin'],
            $this->clavesVisibles($this->usuario(['super-admin']))
        );
    }

    public function test_drafts_follow_the_environment_setting(): void
    {
        $usuario = $this->usuario(['user'], ['Cargas']);

        $this->assertNotContains('primeros-pasos/borrador', $this->clavesVisibles($usuario));
        $this->assertNotContains('primeros-pasos/en-codigo', $this->clavesVisibles($usuario));
        $this->actingAs($usuario)->get('/manual/primeros-pasos/borrador')->assertNotFound();
        $this->actingAs($this->usuario(['super-admin']))->get('/manual/primeros-pasos/borrador')->assertNotFound();

        config(['manual.mostrar_borradores' => true]);

        $this->assertContains('primeros-pasos/borrador', $this->clavesVisibles($usuario));
        $this->assertContains('primeros-pasos/en-codigo', $this->clavesVisibles($usuario));
        $this->actingAs($usuario)->get('/manual/primeros-pasos/borrador')
            ->assertOk()
            ->assertSee('Borrador');
    }

    public function test_index_sends_search_data_with_text_and_synonyms(): void
    {
        $respuesta = $this->actingAs($this->usuario(['user'], ['Cargas']))->get(route('manual.index'))->assertOk();

        $articulos = collect($respuesta->viewData('articulos'))->keyBy('titulo');

        $this->assertStringContainsString('importación', $articulos['Cómo cambiar la clave']['texto']);
        $this->assertStringContainsString('remolcada', $articulos['Cómo armar un convoy']['texto']);
        $this->assertStringNotContainsString('barcaza', $articulos['Cómo armar un convoy']['texto']);
        $this->assertContains(['barcaza', 'remolcada'], $respuesta->viewData('sinonimos'));
        $this->assertStringNotContainsString('Para qué sirve', $articulos['Cómo ingresar']['texto']);
        $this->assertSame(
            ['primeros-pasos' => 'Primeros pasos', 'viajes-y-cargas' => 'Viajes y cargas'],
            $respuesta->viewData('temas')
        );

        $respuesta->assertSee('Buscar en el manual')
            ->assertSee('autofocus', false)
            ->assertSee('manualBuscador(', false);
    }

    public function test_article_links_are_resolved(): void
    {
        $html = $this->actingAs($this->usuario(['user'], ['Cargas']))
            ->get('/manual/primeros-pasos/ingresar')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="/manual/primeros-pasos/cambiar-clave">Cambiar la clave</a>', $html);
        $this->assertStringContainsString('href="'.route('login', [], false).'">Pantalla de ingreso</a>', $html);
        $this->assertStringContainsString('Pantalla inexistente', $html);
        $this->assertDoesNotMatchRegularExpression('#<a[^>]*>Pantalla inexistente#', $html);
        $this->assertStringNotContainsString('ruta:', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_article_page_shows_breadcrumb_sections_related_and_neighbours(): void
    {
        $respuesta = $this->actingAs($this->usuario(['user'], ['Cargas']))
            ->get('/manual/primeros-pasos/ingresar')
            ->assertOk();

        $articulo = $respuesta->viewData('articulo');
        $html = $respuesta->getContent();

        $this->assertStringContainsString('href="'.route('manual.index', ['tema' => 'primeros-pasos']).'"', $html);

        $this->assertNotEmpty($articulo['secciones']);
        foreach ($articulo['secciones'] as $seccion) {
            $this->assertStringContainsString('href="#'.$seccion['id'].'"', $html);
            $this->assertStringContainsString('id="'.$seccion['id'].'"', $html);
        }

        $this->assertSame(['Cómo cambiar la clave'], array_column($articulo['relacionados'], 'titulo'));
        $this->assertStringNotContainsString('Solo administradores', $html);

        $this->assertNull($articulo['anterior']);
        $this->assertSame('/manual/primeros-pasos/cambiar-clave', $articulo['siguiente']['url']);
        $respuesta->assertSee('Volver al buscador');
    }

    public function test_missing_or_hidden_articles_return_404(): void
    {
        $usuario = $this->usuario(['user'], ['Cargas']);

        $this->actingAs($usuario)->get('/manual/primeros-pasos/no-existe')->assertNotFound();
        $this->actingAs($usuario)->get('/manual/afip-argentina/solo-admin')->assertNotFound();
        $this->actingAs($this->usuario(['user'], ['Transbordos']))->get('/manual/viajes-y-cargas/armar-convoy')->assertNotFound();
        $this->actingAs($usuario)->get('/manual/primeros-pasos/sin-titulo')->assertNotFound();
        $this->actingAs($usuario)->get('/manual/Primeros/ingresar')->assertNotFound();
    }

    public function test_invalid_header_is_discarded_with_a_warning(): void
    {
        Log::spy();

        $this->assertNotContains('primeros-pasos/sin-titulo', $this->clavesVisibles($this->usuario(['super-admin'])));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($mensaje, $contexto) => str_ends_with($contexto['archivo'] ?? '', 'sin-titulo.md'))
            ->once();
    }

    public function test_user_without_roles_sees_an_empty_manual(): void
    {
        $respuesta = $this->actingAs($this->usuario([]))->get(route('manual.index'))->assertOk();

        $this->assertSame([], $respuesta->viewData('articulos'));
        $respuesta->assertSee('No hay artículos disponibles para su usuario.');
    }

    private function clavesVisibles(User $usuario): array
    {
        $articulos = $this->actingAs($usuario)->get(route('manual.index'))->assertOk()->viewData('articulos');

        return array_map(fn ($articulo) => ltrim(substr($articulo['url'], strlen('/manual')), '/'), $articulos);
    }

    private function usuario(array $roles, ?array $rolesEmpresa = null): User
    {
        $usuario = new class extends User
        {
            public array $rolesAsignados = [];

            public function hasRole($roles, ?string $guard = null): bool
            {
                return array_intersect(Arr::wrap($roles), $this->rolesAsignados) !== [];
            }

            public function hasAnyRole(...$roles): bool
            {
                return array_intersect(Arr::flatten($roles), $this->rolesAsignados) !== [];
            }
        };

        $usuario->rolesAsignados = $roles;
        $usuario->forceFill([
            'id' => 1,
            'name' => 'Usuario de prueba',
            'email' => 'prueba@example.com',
            'email_verified_at' => now(),
            'active' => true,
        ]);
        $usuario->exists = true;

        if ($rolesEmpresa !== null) {
            $empresa = new Company();
            $empresa->company_roles = $rolesEmpresa;
            $empresa->active = true;
            $usuario->userable_type = Company::class;
            $usuario->setRelation('userable', $empresa);
        }

        return $usuario;
    }

    private function crearContenido(): void
    {
        $articulo = function (string $ruta, array $cabecera, string $cuerpo): void {
            $defecto = [
                'orden' => 10,
                'roles' => '[super-admin, company-admin, user]',
                'palabras_clave' => '[prueba]',
                'estado' => 'publicado',
            ];
            $entorno = $cabecera['entorno'] ?? 'stage';
            unset($cabecera['entorno']);

            $lineas = [];
            foreach (array_merge($defecto, $cabecera) as $campo => $valor) {
                $lineas[] = "{$campo}: {$valor}";
            }

            File::ensureDirectoryExists(dirname("{$this->carpeta}/{$ruta}"));
            File::put(
                "{$this->carpeta}/{$ruta}",
                "---\n".implode("\n", $lineas)."\nverificado:\n  version: abc1234\n  fecha: 2026-10-30\n  entorno: {$entorno}\n---\n\n{$cuerpo}\n"
            );
        };

        $articulo('primeros-pasos/ingresar.md', [
            'titulo' => 'Cómo ingresar',
            'tema' => 'primeros-pasos',
            'relacionados' => '[primeros-pasos/cambiar-clave, afip-argentina/solo-admin]',
        ], implode("\n\n", [
            '## Para qué sirve',
            'Entrar al sistema.',
            '## Pasos',
            "1. Abra la [Pantalla de ingreso](ruta:login).\n2. Siga con [Cambiar la clave](manual:primeros-pasos/cambiar-clave).\n3. Vea la [Pantalla inexistente](ruta:no.existe).",
            '<script>alert(1)</script>',
        ]));

        $articulo('primeros-pasos/cambiar-clave.md', [
            'titulo' => 'Cómo cambiar la clave',
            'tema' => 'primeros-pasos',
            'orden' => 20,
        ], "## Para qué sirve\n\nCambiar la clave después de la importación.");

        $articulo('primeros-pasos/borrador.md', [
            'titulo' => 'Artículo en preparación',
            'tema' => 'primeros-pasos',
            'orden' => 30,
            'estado' => 'borrador',
        ], 'Texto en preparación.');

        $articulo('primeros-pasos/en-codigo.md', [
            'titulo' => 'Artículo sin recorrido',
            'tema' => 'primeros-pasos',
            'orden' => 40,
            'entorno' => 'codigo',
        ], 'Texto verificado solo contra el sistema.');

        $articulo('primeros-pasos/sin-titulo.md', [
            'tema' => 'primeros-pasos',
            'orden' => 50,
        ], 'Cabecera incompleta.');

        $articulo('viajes-y-cargas/armar-convoy.md', [
            'titulo' => 'Cómo armar un convoy',
            'tema' => 'viajes-y-cargas',
            'roles' => '[company-admin, user]',
            'roles_empresa' => '[Cargas]',
        ], 'Agregue cada remolcada al convoy.');

        $articulo('afip-argentina/solo-admin.md', [
            'titulo' => 'Solo administradores',
            'tema' => 'afip-argentina',
            'roles' => '[super-admin]',
        ], 'Texto para administradores.');

        File::put("{$this->carpeta}/_sinonimos.yml", "- [barcaza, remolcada]\n");
        File::put("{$this->carpeta}/secreto.md", "---\ntitulo: Fuera de tema\n---\n\nNo se sirve.\n");
    }
}
