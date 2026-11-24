<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AfipConfigController;
use App\Models\AfipOperativeLocation;
use App\Models\Country;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Vincular Puerto: un lugar operativo del exterior se vincula a un puerto de
 * su país. Datos ficticios.
 */
class AfipConfigLinkForeignPortTest extends TestCase
{
    private const AR = 1;
    private const PY = 2;
    private const BR = 3;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
            'audit.enabled' => false,
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('countries', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('alpha2_code');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('ports', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('name');
            $t->string('city')->nullable();
            $t->unsignedBigInteger('country_id');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('afip_operative_locations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('country_id');
            $t->unsignedBigInteger('port_id')->nullable();
            $t->string('customs_code');
            $t->string('location_code');
            $t->string('description');
            $t->boolean('is_foreign')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('afip_customs_offices', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('port_afip_customs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('port_id');
            $t->unsignedBigInteger('afip_customs_office_id');
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        DB::table('countries')->insert([
            ['id' => self::AR, 'name' => 'Argentina', 'alpha2_code' => 'AR'],
            ['id' => self::PY, 'name' => 'Paraguay', 'alpha2_code' => 'PY'],
            ['id' => self::BR, 'name' => 'Brasil', 'alpha2_code' => 'BR'],
        ]);
        DB::table('ports')->insert([
            ['id' => 10, 'code' => 'ARQA1', 'name' => 'PUERTO QA AR', 'city' => 'QA', 'country_id' => self::AR, 'active' => true],
            ['id' => 20, 'code' => 'PYQA1', 'name' => 'PUERTO QA PY', 'city' => 'QA', 'country_id' => self::PY, 'active' => true],
            ['id' => 21, 'code' => 'PYQA2', 'name' => 'PUERTO QA PY INACTIVO', 'city' => 'QA', 'country_id' => self::PY, 'active' => false],
            ['id' => 30, 'code' => 'BRQA1', 'name' => 'PUERTO QA BR', 'city' => 'QA', 'country_id' => self::BR, 'active' => true],
        ]);
        DB::table('afip_operative_locations')->insert([
            ['id' => 1, 'country_id' => self::AR, 'customs_code' => '001', 'location_code' => 'QA001', 'description' => 'LUGAR QA AR', 'is_foreign' => false],
            ['id' => 2, 'country_id' => self::PY, 'customs_code' => '001', 'location_code' => 'QA002', 'description' => 'LUGAR QA PY', 'is_foreign' => true],
        ]);
    }

    public function test_link_selector_lists_active_ports_of_the_location_countries(): void
    {
        $data = (new AfipConfigController())->index(Request::create('/admin/afip-config', 'GET', ['tab' => 'locations']))->getData();

        $this->assertSame([10, 20], $data['locationPorts']->pluck('id')->sort()->values()->all());
        $this->assertSame([10], $data['ports']->pluck('id')->all());

        $html = view('admin.afip-config.partials.locations', $data)->render();
        $this->assertStringContainsString('value="20" data-country="2">PY · PYQA1 - PUERTO QA PY', $html);
        $this->assertStringContainsString("'QA002', 'LUGAR QA PY', '', '2')", $html);
        $this->assertStringNotContainsString('PYQA2', $html);
        $this->assertStringNotContainsString('BRQA1', $html);
    }

    public function test_foreign_location_is_saved_with_a_port_of_its_country(): void
    {
        $location = AfipOperativeLocation::findOrFail(2);

        (new AfipConfigController())->vincularPuerto(
            Request::create('/admin/afip-config/locations/2/vincular-puerto', 'PATCH', ['port_id' => 20]),
            $location
        );

        $this->assertSame(20, (int) $location->fresh()->port_id);
    }

    public function test_new_location_form_offers_active_ports_of_the_chosen_country(): void
    {
        $response = (new AfipConfigController())->portsByCountry(Country::findOrFail(self::PY));

        $this->assertSame([['id' => 20, 'label' => 'PY · PYQA1 - PUERTO QA PY (QA)']], $response->getData(true));

        $route = app('router')->getRoutes()->getByName('admin.afip-config.ports.by-country');
        $this->assertNotNull($route);
        $this->assertContains('role:super-admin', $route->gatherMiddleware());

        $data = (new AfipConfigController())->index(Request::create('/admin/afip-config', 'GET', ['tab' => 'locations']))->getData();
        $html = view('admin.afip-config.partials.locations', $data)->render();
        $this->assertStringContainsString('onchange="loadCountryPorts(this.value)"', $html);
        $this->assertMatchesRegularExpression(
            '#<select name="port_id" id="port_id" data-url="[^"]*/admin/afip-config/ports/by-country/__COUNTRY__"[^>]*>\s*<option value="">Sin asignar</option>\s*</select>#',
            $html
        );
    }
}
