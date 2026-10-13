<?php

namespace Tests\Unit\Services\Webservice;

use App\Livewire\BillOfLadingEditForm;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\ShipmentItem;
use ReflectionMethod;
use Tests\TestCase;

class BillOfLadingEmptyNavigationContractTest extends TestCase
{
    public function test_empty_container_is_recognized_without_database(): void
    {
        $form = new BillOfLadingEditForm();
        $bill = new BillOfLading();
        $item = new ShipmentItem();
        $container = (new Container())->forceFill([
            'condition' => 'V',
            'container_condition' => 'V',
        ]);
        $item->setRelation('containers', collect([$container]));
        $bill->setRelation('shipmentItems', collect([$item]));
        $form->billOfLading = $bill;

        $method = new ReflectionMethod($form, 'billHasEmptyContainer');
        $this->assertTrue($method->invoke($form));

        $container->condition = 'L';
        $container->container_condition = 'H';
        $this->assertFalse($method->invoke($form));
    }

    public function test_empty_save_redirect_contract_opens_existing_items(): void
    {
        $source = file_get_contents(app_path('Livewire/BillOfLadingEditForm.php'));
        $this->assertIsString($source);

        $commit = strpos($source, 'DB::commit();', strpos($source, 'public function submit()'));
        $emptyRedirect = strpos($source, 'if ($this->billHasEmptyContainer()) {', $commit);
        $normalRedirect = strpos($source, "return redirect()->route('company.bills-of-lading.show', \$this->billOfLading);", $emptyRedirect);

        $this->assertNotFalse($commit);
        $this->assertNotFalse($emptyRedirect);
        $this->assertNotFalse($normalRedirect);
        $this->assertGreaterThan($commit, $emptyRedirect);
        $this->assertGreaterThan($emptyRedirect, $normalRedirect);

        $emptyBranch = substr($source, $emptyRedirect, $normalRedirect - $emptyRedirect);
        $this->assertStringContainsString("'show_all_items' => '1'", $emptyBranch);
        $this->assertStringContainsString("'#itemsTable'", $emptyBranch);
        $this->assertStringNotContainsString('company.shipment-items.create', $emptyBranch);

        $view = file_get_contents(resource_path('views/company/bills-of-lading/show.blade.php'));
        $this->assertStringContainsString('id="itemsTable"', $view);
    }
}
