<?php

namespace Tests\Unit\Views;

use PHPUnit\Framework\TestCase;

class LoginSourceUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_login_source_truth_is_visible_in_show_and_edit(): void
    {
        $show = file_get_contents(
            $this->root()
            . '/resources/views/company/bills-of-lading/show.blade.php'
        );

        $edit = file_get_contents(
            $this->root()
            . '/resources/views/livewire/bill-of-lading-edit-form.blade.php'
        );

        foreach ([
            'booking_number',
            'export_references',
            'source_email',
            'type_of_move',
            'container_summary',
            'commodity_codes',
            'dangerous_goods_details',
        ] as $field) {
            $this->assertStringContainsString(
                $field,
                $show,
                "show debe exponer {$field}"
            );

            $this->assertStringContainsString(
                $field,
                $edit,
                "edit debe exponer {$field}"
            );
        }

        $this->assertStringContainsString(
            'source_line_numbers',
            $show
        );

        $this->assertStringContainsString(
            'source_seals',
            $show
        );

        $this->assertStringContainsString(
            'container_condition',
            $show
        );

        $this->assertStringContainsString(
            'Solo lectura',
            $edit
        );

        /*
         * Los datos crudos de proveniencia no se convierten en
         * inputs editables: eso falsearía qué vino del archivo.
         */
        $this->assertStringNotContainsString(
            'wire:model="booking_number"',
            $edit
        );

        $this->assertStringNotContainsString(
            'wire:model="source_email"',
            $edit
        );
    }

    public function test_login_mane_and_fiscal_contract_are_pinned(): void
    {
        $mane = file_get_contents(
            $this->root()
            . '/app/Services/Webservice/ManeFileGeneratorService.php'
        );

        foreach ([
            'isLoginBill',
            'loginCustomsCode',
            'loginShipperValue',
            'loginNcmDescription',
            'BODEGA COMPARTIDA VIAJE ',
            "\$this->field('05', 2)",
            "\$this->field('T', 1)",
            "\$this->field('H', 1)",
            "'S/N',",
            'dischargePort',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $mane
            );
        }

        $catalog = require
            $this->root()
            . '/resources/data/ncm_es_login.php';

        $this->assertCount(27, $catalog);

        $this->assertSame(
            'POLIMEROS DE ETILENO EN FORMAS PRIMARIAS.',
            $catalog['3901']
        );

        $parser = file_get_contents(
            $this->root()
            . '/app/Services/Parsers/LoginXmlParser.php'
        );

        $this->assertStringContainsString(
            'Login corrige maestro fiscal legacy inequívoco',
            $parser
        );

        $this->assertStringContainsString(
            'Los escalares sólo son representativos cuando existe',
            $parser
        );
    }

    public function test_login_view_uses_container_count_for_displayed_quantity(): void
    {
        $show = file_get_contents(
            $this->root()
            . '/resources/views/company/bills-of-lading/show.blade.php'
        );

        $this->assertStringContainsString(
            "\$billOfLading->source_format === 'LOGIN_XML' ? \$billOfLading->container_count",
            $show
        );

        $this->assertStringContainsString(
            "? 'Contenedores' : 'Bultos'",
            $show
        );

        $this->assertStringContainsString(
            ": \$item->package_quantity",
            $show
        );
    }


}
