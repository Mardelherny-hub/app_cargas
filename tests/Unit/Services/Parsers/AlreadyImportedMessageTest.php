<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\ManifestImport;
use App\Models\User;
use App\Models\Voyage;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\TfpTextParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

/**
 * Al repetir un archivo, el rechazo dice cuándo y en qué viaje se importó y
 * qué hacer, sin mostrar identificadores internos. Datos ficticios.
 */
class AlreadyImportedMessageTest extends TestCase
{
    use BuildsManifestImportSchema;

    private const EXPECTED = 'Este archivo ya fue importado el 24/11/2026 10:30 en el viaje QA-0092. '
        . 'Si necesita importarlo de nuevo, primero revierta esa importación desde el Historial de Importaciones.';

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();

        $this->file = tempnam(sys_get_temp_dir(), 'qa-manifiesto-');
        file_put_contents($this->file, "CONTENIDO QA\n");

        $voyage = Voyage::create(['voyage_number' => 'QA-0092', 'company_id' => self::COMPANY]);
        $import = ManifestImport::create([
            'company_id' => self::COMPANY,
            'voyage_id' => $voyage->id,
            'file_hash' => ManifestImport::generateFileHash($this->file),
            'status' => 'completed',
        ]);
        $import->forceFill(['created_at' => Carbon::parse('2026-11-24 10:30:00')])->save();

        $user = new User(['name' => 'Usuario QA']);
        $user->forceFill(['id' => self::USER, 'userable_type' => 'App\\Models\\Company', 'userable_id' => self::COMPANY]);
        Auth::setUser($user);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function rejection(string $parserClass): string
    {
        $method = new ReflectionMethod($parserClass, 'createImportRecord');
        $method->setAccessible(true);

        try {
            $method->invoke(new $parserClass(), $this->file);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $this->fail('El archivo repetido no fue rechazado.');
    }

    public function test_navsur_rejection_names_date_and_voyage_without_internal_id(): void
    {
        $this->assertSame(self::EXPECTED, $this->rejection(NavsurTextParser::class));
    }

    public function test_tfp_rejection_names_date_and_voyage_without_internal_id(): void
    {
        $this->assertSame(self::EXPECTED, $this->rejection(TfpTextParser::class));
    }

    public function test_no_parser_shows_the_internal_import_id(): void
    {
        foreach (glob(app_path('Services/Parsers/*.php')) as $file) {
            $this->assertStringNotContainsString('importado anteriormente (ID:', file_get_contents($file), basename($file));
        }
    }
}
