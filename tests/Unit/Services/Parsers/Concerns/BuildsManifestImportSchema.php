<?php

namespace Tests\Unit\Services\Parsers\Concerns;

use App\Models\BillOfLading;
use App\Models\BillOfLadingContact;
use App\Models\Client;
use App\Models\ClientContactData;
use App\Models\Container;
use App\Models\ManifestImport;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Models\Voyage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema mínimo en SQLite :memory: para importar manifiestos completos
 * sin tocar la base configurada. Las tablas de datos operativos toman sus
 * columnas del $fillable de cada modelo; los catálogos se cargan con datos
 * ficticios.
 */
trait BuildsManifestImportSchema
{
    protected const COMPANY = 1;
    protected const USER = 1;
    protected const VESSEL = 1;
    protected const AR = 1;
    protected const PY = 2;

    protected function useIsolatedDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
            'audit.enabled' => false,
            'audit.drivers.database.connection' => null,
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        foreach ([
            ManifestImport::class, Voyage::class, Shipment::class, BillOfLading::class,
            BillOfLadingContact::class, ShipmentItem::class, Container::class,
            Client::class, ClientContactData::class,
        ] as $model) {
            $instance = new $model();
            Schema::create($instance->getTable(), function (Blueprint $t) use ($instance) {
                $t->id();
                foreach (array_unique($instance->getFillable()) as $column) {
                    $t->text($column)->nullable();
                }
                $t->timestamps();
                $t->softDeletes();
            });
        }

        Schema::create('container_shipment_item', function (Blueprint $t) {
            $t->id();
            foreach (['container_id', 'shipment_item_id', 'package_quantity', 'gross_weight_kg', 'net_weight_kg',
                'volume_m3', 'verified_gross_mass_kg', 'required_temperature', 'source_seals', 'source_line_numbers',
                'container_condition', 'status', 'webservice_data', 'created_by_user_id', 'created_date'] as $column) {
                $t->text($column)->nullable();
            }
            $t->timestamps();
        });

        $catalogs = [
            'companies' => ['legal_name', 'country'],
            'users' => ['name', 'email', 'password', 'userable_type', 'userable_id'],
            'countries' => ['name', 'alpha2_code'],
            'document_types' => ['country_id', 'code', 'name', 'active'],
            'ports' => ['code', 'name', 'country_id', 'active'],
            'vessels' => ['company_id', 'name', 'imo_number', 'active', 'cargo_capacity_tons', 'container_capacity'],
            'cargo_types' => ['code', 'name', 'active'],
            'packaging_types' => ['code', 'name', 'active'],
            'container_types' => ['code', 'iso_code', 'name', 'max_gross_weight_kg', 'active'],
        ];
        foreach ($catalogs as $table => $columns) {
            Schema::create($table, function (Blueprint $t) use ($columns) {
                $t->id();
                foreach ($columns as $column) {
                    $t->text($column)->nullable();
                }
                $t->timestamps();
            });
        }

        DB::table('companies')->insert(['id' => self::COMPANY, 'legal_name' => 'EMPRESA DE PRUEBA', 'country' => 'AR']);
        DB::table('countries')->insert([
            ['id' => self::AR, 'name' => 'Argentina', 'alpha2_code' => 'AR'],
            ['id' => self::PY, 'name' => 'Paraguay', 'alpha2_code' => 'PY'],
        ]);
        DB::table('document_types')->insert([
            ['country_id' => self::AR, 'code' => 'CUIT', 'name' => 'CUIT', 'active' => 1],
            ['country_id' => self::PY, 'code' => 'RUC', 'name' => 'RUC', 'active' => 1],
        ]);
        DB::table('ports')->insert([
            ['code' => 'ARBUE', 'name' => 'BUENOS AIRES', 'country_id' => self::AR, 'active' => 1],
            ['code' => 'PYASU', 'name' => 'ASUNCION', 'country_id' => self::PY, 'active' => 1],
        ]);
        DB::table('cargo_types')->insert(['code' => 'CON001', 'name' => 'CONTENEDORES', 'active' => 1]);
        DB::table('packaging_types')->insert(['code' => 'CON', 'name' => 'CONTENEDOR', 'active' => 1]);
        DB::table('users')->insert([
            'id' => self::USER, 'name' => 'Usuario de prueba', 'email' => 'usuario@example.test',
            'password' => 'x', 'userable_type' => 'App\Models\Company', 'userable_id' => self::COMPANY,
        ]);

        Auth::login(User::findOrFail(self::USER));
    }

    protected function addVessel(string $name): void
    {
        DB::table('vessels')->insert([
            'id' => self::VESSEL, 'company_id' => self::COMPANY, 'name' => $name,
            'imo_number' => null, 'active' => 1, 'cargo_capacity_tons' => 1000, 'container_capacity' => 100,
        ]);
    }

    /** @param string[] $codes */
    protected function addContainerTypes(array $codes): void
    {
        foreach ($codes as $code) {
            DB::table('container_types')->insert([
                'code' => $code, 'iso_code' => $code, 'name' => "Tipo {$code}",
                'max_gross_weight_kg' => 30480, 'active' => 1,
            ]);
        }
    }

    protected function addClient(string $name, string $taxId, int $countryId): void
    {
        DB::table('clients')->insert([
            'legal_name' => $name, 'commercial_name' => $name, 'tax_id' => $taxId,
            'country_id' => $countryId, 'status' => 'active', 'created_by_company_id' => self::COMPANY,
        ]);
    }

    /** @return array<string, int> */
    protected function operationalCounts(): array
    {
        $counts = [];
        foreach (['voyages', 'shipments', 'bills_of_lading', 'shipment_items', 'containers',
            'container_shipment_item', 'clients'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
