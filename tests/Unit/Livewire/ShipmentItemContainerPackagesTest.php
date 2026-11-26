<?php

namespace Tests\Unit\Livewire;

use App\Livewire\ShipmentItemCreateForm;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Alta de ítem con contenedores: el total de bultos va en el ítem y cada
 * contenedor puede quedar en 0 cuando no se sabe. La suma solo se valida si
 * algún contenedor informa bultos. Datos ficticios.
 */
class ShipmentItemContainerPackagesTest extends TestCase
{
    private function form(array $quantities): ShipmentItemCreateForm
    {
        $form = new ShipmentItemCreateForm();
        $form->showContainerFields = true;
        $form->package_quantity = 80;
        $form->gross_weight_kg = 2000;
        $form->containers = array_map(fn ($quantity, $index) => [
            'container_number' => sprintf('TSTU%07d', $index + 1),
            'container_type_id' => 1,
            'package_quantity' => $quantity,
            'gross_weight_kg' => 1000,
            'net_weight_kg' => 0,
            'volume_m3' => 0,
        ], $quantities, array_keys($quantities));

        return $form;
    }

    private function validate(ShipmentItemCreateForm $form): bool
    {
        return (new ReflectionMethod($form, 'validateContainers'))->invoke($form);
    }

    public function test_all_containers_in_zero_are_accepted(): void
    {
        $form = $this->form([0, 0]);

        $this->assertTrue($this->validate($form));
        $this->assertSame([], $form->getErrorBag()->all());
    }

    public function test_sum_is_checked_when_a_container_has_packages(): void
    {
        $form = $this->form([50, 0]);

        $this->assertFalse($this->validate($form));
        $this->assertSame(
            ['La suma de bultos en contenedores (50) debe coincidir con el total del ítem (80)'],
            $form->getErrorBag()->get('containers_total')
        );
    }

    public function test_matching_sum_is_accepted(): void
    {
        $this->assertTrue($this->validate($this->form([80, 0])));
        $this->assertTrue($this->validate($this->form([40, 40])));
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $form = $this->form([-1, 0]);

        $this->assertFalse($this->validate($form));
        $this->assertSame(['Cantidad de bultos no puede ser negativa'], $form->getErrorBag()->get('container_0'));
    }
}
