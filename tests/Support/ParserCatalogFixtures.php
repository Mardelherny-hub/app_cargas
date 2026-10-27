<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ParserCatalogFixtures
{
    protected function setUpParserCatalogFixtures(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('container_types', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('iso_code');
            $table->string('length_feet');
            $table->decimal('max_gross_weight_kg')->default(30480);
            $table->boolean('active')->default(true);
        });
        foreach ([
            ['20GP', '22G1'], ['20OT', '22U1'], ['20RF', '22R1'], ['20TN', '22T1'],
            ['40GP', '42G1'], ['40HC', '45G1'], ['40RH', '45R1'], ['40OT', '42U1'],
        ] as [$code, $iso]) {
            DB::table('container_types')->insert([
                'code' => $code, 'iso_code' => $iso, 'length_feet' => substr($code, 0, 2),
            ]);
        }
        Schema::create('packaging_types', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->boolean('active')->default(true);
        });
        DB::table('packaging_types')->insert(['code' => 'N', 'name' => 'NO RETORNABLE']);
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('alpha2_code');
            $table->string('iso_code');
            $table->string('name');
            $table->boolean('active')->default(true);
        });
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->unsignedBigInteger('country_id');
            $table->boolean('active')->default(true);
        });
        foreach ([
            ['AR', 'ARG', 'Argentina', 'CUIT'], ['PY', 'PRY', 'Paraguay', 'RUC'],
            ['CO', 'COL', 'Colombia', 'NIT'], ['BR', 'BRA', 'Brasil', 'CNPJ'],
        ] as [$alpha2, $iso, $name, $document]) {
            $id = DB::table('countries')->insertGetId([
                'alpha2_code' => $alpha2, 'iso_code' => $iso, 'name' => $name,
            ]);
            DB::table('document_types')->insert(['code' => $document, 'country_id' => $id]);
        }
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
        });
        DB::table('companies')->insert(['id' => 1, 'legal_name' => 'Parser fixture']);
        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->string('code');
        });
        DB::table('ports')->insert(['country_id' => 1, 'code' => 'ARBUE']);
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('tax_id')->nullable();
            $table->string('legal_name');
            $table->string('commercial_name')->nullable();
            $table->unsignedBigInteger('country_id');
            $table->unsignedBigInteger('document_type_id')->nullable();
            $table->unsignedBigInteger('created_by_company_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('verified_at')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
}
