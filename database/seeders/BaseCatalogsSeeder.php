<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Models\CustomOffice;

/**
 * FASE 0 - CATÁLOGOS BASE DEL SISTEMA
 *
 * Seeder para crear catálogos esenciales de Argentina y Paraguay
 * Requerido antes de ejecutar ClientsSeeder (Fase 1)
 *
 * Usa EXACTAMENTE las columnas definidas en las migraciones reales
 */
class BaseCatalogsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌎 Creando catálogos base para Argentina y Paraguay...');

        // 1. Crear países
        $this->createCountries();

        // 2. Crear tipos de documento por país
        $this->createDocumentTypes();

        // 3. Crear puertos principales
        $this->createPorts();

        // 4. Crear aduanas principales
        $this->createCustomOffices();

        $this->command->info('✅ Catálogos base creados exitosamente!');
        $this->displaySummary();
    }

    /**
     * Crear países (Argentina y Paraguay)
     * Usando estructura exacta de create_countries_table.php
     */
    private function createCountries(): void
    {
        $this->command->info('🇦🇷🇵🇾 Creando países...');

        $countries = [
            [
                'iso_code'        => 'BRA',
                'alpha2_code'     => 'BR',
                'numeric_code'    => '076',
                'name'            => 'Brasil',
                'official_name'   => 'República Federativa del Brasil',
                'nationality'     => 'brasileño',
                'customs_code'    => 'BR',
                'senasa_code'     => 'BR',
                'document_format' => '99.999.999/9999-99', // CNPJ aprox.
                'currency_code'   => 'BRL',
                'timezone'        => 'America/Sao_Paulo',
                'primary_language'=> 'pt',
                'allows_import'   => true,
                'allows_export'   => true,
                'allows_transit'  => true,
                'requires_visa'   => false,
                'active'          => true,
                'display_order'   => 3,
                'is_primary'      => true,
            ],
            [
                'iso_code'        => 'URY',
                'alpha2_code'     => 'UY',
                'numeric_code'    => '858',
                'name'            => 'Uruguay',
                'official_name'   => 'República Oriental del Uruguay',
                'nationality'     => 'uruguayo',
                'customs_code'    => 'UY',
                'senasa_code'     => 'UY',
                'document_format' => '9.999.999-9', // CI aprox.
                'currency_code'   => 'UYU',
                'timezone'        => 'America/Montevideo',
                'primary_language'=> 'es',
                'allows_import'   => true,
                'allows_export'   => true,
                'allows_transit'  => true,
                'requires_visa'   => false,
                'active'          => true,
                'display_order'   => 4,
                'is_primary'      => true,
            ],

            [
                'iso_code' => 'ARG',
                'alpha2_code' => 'AR',
                'numeric_code' => '032',
                'name' => 'Argentina',
                'official_name' => 'República Argentina',
                'nationality' => 'argentino',
                'customs_code' => 'AR',
                'senasa_code' => 'AR',
                'document_format' => '99-99999999-9',
                'currency_code' => 'ARS',
                'timezone' => 'America/Argentina/Buenos_Aires',
                'primary_language' => 'es',
                'allows_import' => true,
                'allows_export' => true,
                'allows_transit' => true,
                'requires_visa' => false,
                'active' => true,
                'display_order' => 1,
                'is_primary' => true,
            ],
            [
                'iso_code' => 'PRY',
                'alpha2_code' => 'PY',
                'numeric_code' => '600',
                'name' => 'Paraguay',
                'official_name' => 'República del Paraguay',
                'nationality' => 'paraguayo',
                'customs_code' => 'PY',
                'senasa_code' => 'PY',
                'document_format' => '99999999-9',
                'currency_code' => 'PYG',
                'timezone' => 'America/Asuncion',
                'primary_language' => 'es',
                'allows_import' => true,
                'allows_export' => true,
                'allows_transit' => true,
                'requires_visa' => false,
                'active' => true,
                'display_order' => 2,
                'is_primary' => true,
            ]
        ];

        foreach ($countries as $countryData) {
            $country = Country::updateOrCreate(
                ['alpha2_code' => $countryData['alpha2_code']],
                $countryData
            );

            $this->command->line("  ✓ {$country->name} ({$country->alpha2_code})");
        }
    }

    /**
     * Crear tipos de documento por país
     * Usando estructura exacta de create_document_types_table.php
     */
    private function createDocumentTypes(): void
    {
        $this->command->info('📄 Creando tipos de documento...');

        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();

        if (!$argentina || !$paraguay) {
            $this->command->error('❌ Países no encontrados. Error en creación previa.');
            return;
        }

        // Tipos de documento Argentina
        $argentineDocTypes = [
            [
                'code' => 'CUIT',
                'name' => 'CUIT - Clave Única de Identificación Tributaria',
                'short_name' => 'CUIT',
                'country_id' => $argentina->id,
                'validation_pattern' => '^\\d{2}-\\d{8}-\\d{1}$',
                'min_length' => 13,
                'max_length' => 13,
                'has_check_digit' => true,
                'check_digit_algorithm' => 'mod11',
                'display_format' => '99-99999999-9',
                'input_mask' => '99-99999999-9',
                'format_examples' => ['20-12345678-9', '27-34567890-4'],
                'for_individuals' => true,
                'for_companies' => true,
                'for_tax_purposes' => true,
                'for_customs' => true,
                'is_primary' => true,
                'required_for_clients' => true,
                'display_order' => 1,
                'active' => true,
                'webservice_field' => 'cuit',
            ],
            [
                'code' => 'DNI',
                'name' => 'DNI - Documento Nacional de Identidad',
                'short_name' => 'DNI',
                'country_id' => $argentina->id,
                'validation_pattern' => '^\\d{7,8}$',
                'min_length' => 7,
                'max_length' => 8,
                'has_check_digit' => false,
                'check_digit_algorithm' => null,
                'display_format' => '99999999',
                'input_mask' => '99999999',
                'format_examples' => ['12345678', '98765432'],
                'for_individuals' => true,
                'for_companies' => false,
                'for_tax_purposes' => false,
                'for_customs' => false,
                'is_primary' => false,
                'required_for_clients' => false,
                'display_order' => 2,
                'active' => true,
                'webservice_field' => 'dni',
            ]
        ];

        foreach ($argentineDocTypes as $docTypeData) {
            $docType = DocumentType::updateOrCreate(
                [
                    'code' => $docTypeData['code'],
                    'country_id' => $argentina->id
                ],
                $docTypeData
            );

            $this->command->line("  ✓ AR: {$docType->name}");
        }

        // Tipos de documento Paraguay
        $paraguayanDocTypes = [
            [
                'code' => 'RUC',
                'name' => 'RUC - Registro Único del Contribuyente',
                'short_name' => 'RUC',
                'country_id' => $paraguay->id,
                'validation_pattern' => '^\\d{8}-\\d{1}$',
                'min_length' => 10,
                'max_length' => 10,
                'has_check_digit' => true,
                'check_digit_algorithm' => 'mod11',
                'display_format' => '99999999-9',
                'input_mask' => '99999999-9',
                'format_examples' => ['12345678-9', '80012345-6'],
                'for_individuals' => true,
                'for_companies' => true,
                'for_tax_purposes' => true,
                'for_customs' => true,
                'is_primary' => true,
                'required_for_clients' => true,
                'display_order' => 1,
                'active' => true,
                'webservice_field' => 'ruc',
            ],
            [
                'code' => 'CI',
                'name' => 'CI - Cédula de Identidad',
                'short_name' => 'CI',
                'country_id' => $paraguay->id,
                'validation_pattern' => '^\\d{6,8}$',
                'min_length' => 6,
                'max_length' => 8,
                'has_check_digit' => false,
                'check_digit_algorithm' => null,
                'display_format' => '9999999',
                'input_mask' => '9999999',
                'format_examples' => ['1234567', '7654321'],
                'for_individuals' => true,
                'for_companies' => false,
                'for_tax_purposes' => false,
                'for_customs' => false,
                'is_primary' => false,
                'required_for_clients' => false,
                'display_order' => 2,
                'active' => true,
                'webservice_field' => 'ci',
            ]
        ];

        foreach ($paraguayanDocTypes as $docTypeData) {
            $docType = DocumentType::updateOrCreate(
                [
                    'code' => $docTypeData['code'],
                    'country_id' => $paraguay->id
                ],
                $docTypeData
            );

            $this->command->line("  ✓ PY: {$docType->name}");
        }
    }

    /**
     * Crear puertos principales
     * Usando estructura exacta de create_ports_table.php
     */
    private function createPorts(): void
    {
        $this->command->info('🚢 Creando puertos principales...');

        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();

        if (!$argentina || !$paraguay) {
            $this->command->error('❌ Países no encontrados. Error en creación previa.');
            return;
        }

        // Puertos Argentina
        $argentinePorts = [
            [
                'code' => 'ARBUE',
                'name' => 'Puerto de Buenos Aires',
                'short_name' => 'Buenos Aires',
                'local_name' => 'Puerto de Buenos Aires',
                'country_id' => $argentina->id,
                'city' => 'Buenos Aires',
                'province_state' => 'Buenos Aires',
                'address' => 'Av. Antártida Argentina 355',
                'postal_code' => 'C1104AAH',
                'latitude' => -34.6037,
                'longitude' => -58.3816,
                'water_depth' => 10.50,
                'port_type' => 'mixed',
                'port_category' => 'major',
                'handles_containers' => true,
                'handles_bulk_cargo' => true,
                'handles_general_cargo' => true,
                'handles_passengers' => false,
                'handles_dangerous_goods' => true,
                'has_customs_office' => true,
                'max_vessel_length' => 300,
                'max_draft' => 10.00,
                'phone' => '+54-11-4317-8000',
                'email' => 'info@puertobuenosaires.gob.ar',
                'website' => 'www.puertobuenosaires.gob.ar',
                'currency_code' => 'ARS',
                'active' => true,
                'accepts_new_vessels' => true,
                'display_order' => 1,
            ],
            [
                'code' => 'ARROS',
                'name' => 'Puerto de Rosario',
                'short_name' => 'Rosario',
                'local_name' => 'Puerto de Rosario',
                'country_id' => $argentina->id,
                'city' => 'Rosario',
                'province_state' => 'Santa Fe',
                'address' => 'Av. Belgrano 1210',
                'postal_code' => 'S2000AWF',
                'latitude' => -32.9442,
                'longitude' => -60.6505,
                'water_depth' => 8.50,
                'port_type' => 'river',
                'port_category' => 'major',
                'handles_containers' => true,
                'handles_bulk_cargo' => true,
                'handles_general_cargo' => true,
                'handles_passengers' => false,
                'handles_dangerous_goods' => false,
                'has_customs_office' => true,
                'max_vessel_length' => 250,
                'max_draft' => 8.00,
                'phone' => '+54-341-480-2500',
                'email' => 'info@puertorosario.com.ar',
                'currency_code' => 'ARS',
                'active' => true,
                'accepts_new_vessels' => true,
                'display_order' => 2,
            ]
        ];

        foreach ($argentinePorts as $portData) {
            $port = Port::updateOrCreate(
                [
                    'code' => $portData['code'],
                    'country_id' => $argentina->id
                ],
                $portData
            );

            $this->command->line("  ✓ AR: {$port->name} ({$port->code})");
        }

        // Puertos Paraguay
        $paraguayPorts = [
            [
                'code' => 'PYASU',
                'name' => 'Puerto de Asunción',
                'short_name' => 'Asunción',
                'local_name' => 'Puerto de Asunción',
                'country_id' => $paraguay->id,
                'city' => 'Asunción',
                'province_state' => 'Central',
                'address' => 'Av. Costanera c/ Colón',
                'postal_code' => '1209',
                'latitude' => -25.2637,
                'longitude' => -57.5759,
                'water_depth' => 3.50,
                'port_type' => 'river',
                'port_category' => 'major',
                'handles_containers' => true,
                'handles_bulk_cargo' => true,
                'handles_general_cargo' => true,
                'handles_passengers' => true,
                'handles_dangerous_goods' => false,
                'has_customs_office' => true,
                'max_vessel_length' => 180,
                'max_draft' => 3.00,
                'phone' => '+595-21-414-3000',
                'email' => 'info@puertosparaguay.gov.py',
                'currency_code' => 'PYG',
                'active' => true,
                'accepts_new_vessels' => true,
                'display_order' => 1,
            ],
            [
                'code' => 'PYCDE',
                'name' => 'Puerto Ciudad del Este',
                'short_name' => 'Ciudad del Este',
                'local_name' => 'Puerto Ciudad del Este',
                'country_id' => $paraguay->id,
                'city' => 'Ciudad del Este',
                'province_state' => 'Alto Paraná',
                'address' => 'Av. Monseñor Rodríguez 475',
                'postal_code' => '7000',
                'latitude' => -25.5095,
                'longitude' => -54.6110,
                'water_depth' => 2.50,
                'port_type' => 'river',
                'port_category' => 'minor',
                'handles_containers' => true,
                'handles_bulk_cargo' => false,
                'handles_general_cargo' => true,
                'handles_passengers' => false,
                'handles_dangerous_goods' => false,
                'has_customs_office' => true,
                'max_vessel_length' => 120,
                'max_draft' => 2.00,
                'phone' => '+595-61-500-217',
                'currency_code' => 'PYG',
                'active' => true,
                'accepts_new_vessels' => true,
                'display_order' => 2,
            ]
        ];

        foreach ($paraguayPorts as $portData) {
            $port = Port::updateOrCreate(
                [
                    'code' => $portData['code'],
                    'country_id' => $paraguay->id
                ],
                $portData
            );

            $this->command->line("  ✓ PY: {$port->name} ({$port->code})");
        }
    }

    /**
     * Crear aduanas principales
     * Usando estructura exacta de create_custom_offices_table.php
     */
    private function createCustomOffices(): void
    {
        $this->command->info('🏛️ Creando aduanas principales...');

        $argentina = Country::where('alpha2_code', 'AR')->first();
        $paraguay = Country::where('alpha2_code', 'PY')->first();

        if (!$argentina || !$paraguay) {
            $this->command->error('❌ Países no encontrados. Error en creación previa.');
            return;
        }

        // Aduanas Argentina
        $argentineCustoms = [
            [
                'code' => '001',
                'name' => 'Aduana Buenos Aires',
                'short_name' => 'Aduana BA',
                'country_id' => $argentina->id,
                'city' => 'Buenos Aires',
                'province_state' => 'Buenos Aires',
                'address' => 'Av. Antártida Argentina 355',
                'postal_code' => 'C1104AAH',
                'latitude' => -34.6037,
                'longitude' => -58.3816,
                'office_type' => 'port',
                'handles_maritime' => true,
                'handles_fluvial' => true,
                'handles_containers' => true,
                'handles_bulk_cargo' => true,
                'handles_passengers' => false,
                'webservice_code' => '001',
                'supports_anticipada' => true,
                'supports_micdta' => true,
                'supports_desconsolidado' => false,
                'supports_transbordo' => false,
                'operates_24h' => true,
                'phone' => '+54-11-4317-8000',
                'email' => 'consultas@afip.gob.ar',
                'website' => 'www.afip.gob.ar',
                'supervisor_name' => 'Supervisor Buenos Aires',
                'region_code' => 'AR-BA',
                'active' => true,
                'accepts_new_operations' => true,
                'display_order' => 1,
            ],
            [
                'code' => '002',
                'name' => 'Aduana Rosario',
                'short_name' => 'Aduana ROS',
                'country_id' => $argentina->id,
                'city' => 'Rosario',
                'province_state' => 'Santa Fe',
                'address' => 'Av. Belgrano 1210',
                'postal_code' => 'S2000AWF',
                'latitude' => -32.9442,
                'longitude' => -60.6505,
                'office_type' => 'port',
                'handles_maritime' => false,
                'handles_fluvial' => true,
                'handles_containers' => true,
                'handles_bulk_cargo' => true,
                'handles_passengers' => false,
                'webservice_code' => '002',
                'supports_anticipada' => true,
                'supports_micdta' => true,
                'supports_desconsolidado' => false,
                'supports_transbordo' => false,
                'operates_24h' => false,
                'phone' => '+54-341-480-2500',
                'email' => 'rosario@afip.gob.ar',
                'supervisor_name' => 'Supervisor Rosario',
                'region_code' => 'AR-SF',
                'active' => true,
                'accepts_new_operations' => true,
                'display_order' => 2,
            ]
        ];

        foreach ($argentineCustoms as $customData) {
            $custom = CustomOffice::updateOrCreate(
                [
                    'code' => $customData['code'],
                    'country_id' => $argentina->id
                ],
                $customData
            );

            $this->command->line("  ✓ AR: {$custom->name} ({$custom->code})");
        }

        // Aduanas Paraguay
        $paraguayCustoms = [
            [
                'code' => '003',
                'name' => 'Aduana Asunción',
                'short_name' => 'Aduana ASU',
                'country_id' => $paraguay->id,
                'city' => 'Asunción',
                'province_state' => 'Central',
                'address' => 'Av. Pettirossi c/ Alberdi',
                'postal_code' => '1209',
                'latitude' => -25.2637,
                'longitude' => -57.5759,
                'office_type' => 'port',
                'handles_maritime' => false,
                'handles_fluvial' => true,
                'handles_containers' => true,
                'handles_bulk_cargo' => true,
                'handles_passengers' => true,
                'webservice_code' => '001',
                'supports_anticipada' => true,
                'supports_micdta' => true,
                'supports_desconsolidado' => false,
                'supports_transbordo' => false,
                'operates_24h' => false,
                'phone' => '+595-21-414-3000',
                'email' => 'info@aduana.gov.py',
                'website' => 'www.aduana.gov.py',
                'supervisor_name' => 'Supervisor Asunción',
                'region_code' => 'PY-AS',
                'active' => true,
                'accepts_new_operations' => true,
                'display_order' => 1,
            ],
            [
                'code' => '004',
                'name' => 'Aduana Ciudad del Este',
                'short_name' => 'Aduana CDE',
                'country_id' => $paraguay->id,
                'city' => 'Ciudad del Este',
                'province_state' => 'Alto Paraná',
                'address' => 'Av. Monseñor Rodríguez 475',
                'postal_code' => '7000',
                'latitude' => -25.5095,
                'longitude' => -54.6110,
                'office_type' => 'border',
                'handles_maritime' => false,
                'handles_fluvial' => true,
                'handles_containers' => true,
                'handles_bulk_cargo' => false,
                'handles_passengers' => false,
                'webservice_code' => '002',
                'supports_anticipada' => true,
                'supports_micdta' => true,
                'supports_desconsolidado' => false,
                'supports_transbordo' => false,
                'operates_24h' => false,
                'phone' => '+595-61-500-217',
                'supervisor_name' => 'Supervisor Ciudad del Este',
                'region_code' => 'PY-AP',
                'active' => true,
                'accepts_new_operations' => true,
                'display_order' => 2,
            ]
        ];

        foreach ($paraguayCustoms as $customData) {
            $custom = CustomOffice::updateOrCreate(
                [
                    'code' => $customData['code'],
                    'country_id' => $paraguay->id
                ],
                $customData
            );

            $this->command->line("  ✓ PY: {$custom->name} ({$custom->code})");
        }
    }

    /**
     * Mostrar resumen de catálogos creados
     */
    private function displaySummary(): void
    {
        $this->command->info('');
        $this->command->info('=== 📊 RESUMEN DE CATÁLOGOS BASE ===');

        try {
            $countries = Country::count();
            $docTypes = DocumentType::count();
            $ports = Port::count();
            $customs = CustomOffice::count();

            $this->command->info("Países: {$countries}");
            $this->command->info("Tipos de documento: {$docTypes}");
            $this->command->info("Puertos: {$ports}");
            $this->command->info("Aduanas: {$customs}");

            $this->command->info('');

            // Mostrar detalles por país
            $argentina = Country::where('alpha2_code', 'AR')->first();
            $paraguay = Country::where('alpha2_code', 'PY')->first();

            if ($argentina) {
                $arDocTypes = DocumentType::where('country_id', $argentina->id)->count();
                $arPorts = Port::where('country_id', $argentina->id)->count();
                $arCustoms = CustomOffice::where('country_id', $argentina->id)->count();

                $this->command->info("🇦🇷 Argentina:");
                $this->command->info("  - Tipos documento: {$arDocTypes}");
                $this->command->info("  - Puertos: {$arPorts}");
                $this->command->info("  - Aduanas: {$arCustoms}");
            }

            if ($paraguay) {
                $pyDocTypes = DocumentType::where('country_id', $paraguay->id)->count();
                $pyPorts = Port::where('country_id', $paraguay->id)->count();
                $pyCustoms = CustomOffice::where('country_id', $paraguay->id)->count();

                $this->command->info("🇵🇾 Paraguay:");
                $this->command->info("  - Tipos documento: {$pyDocTypes}");
                $this->command->info("  - Puertos: {$pyPorts}");
                $this->command->info("  - Aduanas: {$pyCustoms}");
            }

            $this->command->info('');
            $this->command->info('🎯 FASE 0 COMPLETADA - Listo para Fase 1 (Clientes)');
            $this->command->info('');
            $this->command->info('📋 Próximos pasos:');
            $this->command->info('  php artisan clients:verify --run-seeders');

        } catch (\Exception $e) {
            $this->command->error('❌ Error generando resumen: ' . $e->getMessage());
        }
    }
}
