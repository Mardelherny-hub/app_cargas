<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $countries = DB::table('countries')
            ->whereIn('alpha2_code', ['CO', 'BR'])
            ->pluck('id', 'alpha2_code');

        foreach (['CO', 'BR'] as $alpha2) {
            if (!isset($countries[$alpha2])) {
                throw new RuntimeException(
                    "No existe el país {$alpha2}; no se puede crear su tipo fiscal."
                );
            }
        }

        $now = now();

        $types = [
            'NIT' => [
                'code' => 'NIT',
                'name' => 'NIT - Número de Identificación Tributaria',
                'short_name' => 'NIT',
                'country_id' => (int) $countries['CO'],
                'validation_pattern' => '^\\d{9,10}$',
                'min_length' => 9,
                'max_length' => 10,
                'has_check_digit' => true,
                'check_digit_algorithm' => null,
                'display_format' => null,
                'input_mask' => null,
                'format_examples' => null,
                'for_individuals' => true,
                'for_companies' => true,
                'for_tax_purposes' => true,
                'for_customs' => true,
                'is_primary' => false,
                'required_for_clients' => false,
                'display_order' => 10,
                'active' => true,
                'webservice_field' => null,
                'webservice_config' => null,
                'updated_at' => $now,
            ],

            'CNPJ' => [
                'code' => 'CNPJ',
                'name' => 'CNPJ - Cadastro Nacional da Pessoa Jurídica',
                'short_name' => 'CNPJ',
                'country_id' => (int) $countries['BR'],
                'validation_pattern' => '^\\d{14}$',
                'min_length' => 14,
                'max_length' => 14,
                'has_check_digit' => true,
                'check_digit_algorithm' => null,
                'display_format' => '99.999.999/9999-99',
                'input_mask' => '99.999.999/9999-99',
                'format_examples' => null,
                'for_individuals' => false,
                'for_companies' => true,
                'for_tax_purposes' => true,
                'for_customs' => true,
                'is_primary' => false,
                'required_for_clients' => false,
                'display_order' => 10,
                'active' => true,
                'webservice_field' => null,
                'webservice_config' => null,
                'updated_at' => $now,
            ],
        ];

        foreach ($types as $code => $attributes) {
            $existing = DB::table('document_types')
                ->where('code', $code)
                ->first();

            if ($existing && (int) $existing->country_id !== $attributes['country_id']) {
                throw new RuntimeException(
                    "El tipo documental {$code} ya existe asociado a otro país."
                );
            }

            if ($existing) {
                DB::table('document_types')
                    ->where('id', $existing->id)
                    ->update($attributes);
            } else {
                DB::table('document_types')->insert(array_merge(
                    $attributes,
                    [
                        'created_date' => $now,
                        'created_at' => $now,
                    ]
                ));
            }
        }

        DB::statement(
            'ALTER TABLE clients
             MODIFY document_type_id BIGINT UNSIGNED NULL'
        );
    }

    public function down(): void
    {
        if (DB::table('clients')->whereNull('document_type_id')->exists()) {
            throw new RuntimeException(
                'No se puede revertir: existen clientes sin tipo documental.'
            );
        }

        $documentTypeIds = DB::table('document_types')
            ->whereIn('code', ['NIT', 'CNPJ'])
            ->pluck('id');

        if (
            $documentTypeIds->isNotEmpty()
            && DB::table('clients')
                ->whereIn('document_type_id', $documentTypeIds)
                ->exists()
        ) {
            throw new RuntimeException(
                'No se puede revertir: existen clientes que usan NIT o CNPJ.'
            );
        }

        DB::table('document_types')
            ->whereIn('code', ['NIT', 'CNPJ'])
            ->delete();

        DB::statement(
            'ALTER TABLE clients
             MODIFY document_type_id BIGINT UNSIGNED NOT NULL'
        );
    }
};
