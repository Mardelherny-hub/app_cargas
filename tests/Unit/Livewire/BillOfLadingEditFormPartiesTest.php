<?php

namespace Tests\Unit\Livewire;

use App\Livewire\BillOfLadingEditForm;
use App\Models\BillOfLading;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class BillOfLadingEditFormPartiesTest extends TestCase
{
    private const ACTIVE = 10;
    private const ASSIGNED_INACTIVE = 20;
    private const OTHER_INACTIVE = 30;

    protected function setUp(): void
    {
        parent::setUp();

        // Solo la tabla de fichas, en SQLite temporal, sin datos del operador.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');

        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->string('status');
        });
        DB::table('clients')->insert([
            ['id' => self::ACTIVE, 'status' => 'active'],
            ['id' => self::ASSIGNED_INACTIVE, 'status' => 'inactive'],
            ['id' => self::OTHER_INACTIVE, 'status' => 'inactive'],
        ]);
    }

    /**
     * BL guardado con la ficha inactiva como cargador y consignatario, sin
     * notificatario ni propietario. El guardado nunca debe llegar a la base.
     */
    private function form(array $parties): BillOfLadingEditForm
    {
        $bill = Mockery::mock(BillOfLading::class)->makePartial();
        $bill->forceFill([
            'id' => 29,
            'shipper_id' => self::ASSIGNED_INACTIVE,
            'consignee_id' => self::ASSIGNED_INACTIVE,
            'notify_party_id' => null,
            'cargo_owner_id' => null,
        ]);
        $bill->shouldNotReceive('update');

        $form = new BillOfLadingEditForm();
        $form->billOfLading = $bill;
        $form->bill_date = '2026-11-20';
        $form->loading_date = '2026-11-21';

        foreach (array_merge([
            'shipper_id' => self::ASSIGNED_INACTIVE,
            'consignee_id' => self::ASSIGNED_INACTIVE,
            'notify_party_id' => '',
            'cargo_owner_id' => '',
        ], $parties) as $field => $value) {
            $form->{$field} = $value;
        }

        return $form;
    }

    private function submitErrors(BillOfLadingEditForm $form): array
    {
        try {
            $form->submit();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('El guardado debía frenarse con un error de validación en el campo.');
    }

    public function test_empty_required_party_is_a_field_error_not_a_database_error(): void
    {
        $errors = $this->submitErrors($this->form(['shipper_id' => '', 'consignee_id' => '']));

        $this->assertSame(['Seleccione el cargador.'], $errors['shipper_id']);
        $this->assertSame(['Seleccione el consignatario.'], $errors['consignee_id']);
        $this->assertNull(session('error'));
    }

    public function test_new_inactive_client_is_rejected_in_that_field(): void
    {
        $errors = $this->submitErrors($this->form(['consignee_id' => self::OTHER_INACTIVE]));

        $this->assertSame(['consignee_id'], array_keys($errors));
        $this->assertSame(
            ['La ficha elegida para el consignatario no existe o está inactiva. Elija una ficha activa.'],
            $errors['consignee_id']
        );
    }

    public function test_inactive_client_assigned_in_another_role_is_rejected(): void
    {
        $errors = $this->submitErrors($this->form(['notify_party_id' => self::ASSIGNED_INACTIVE]));

        $this->assertSame(['notify_party_id'], array_keys($errors));
    }

    public function test_assigned_inactive_in_same_role_and_active_clients_are_accepted(): void
    {
        $form = $this->form([
            'consignee_id' => self::ACTIVE,
            'notify_party_id' => self::ACTIVE,
        ]);

        (new ReflectionMethod($form, 'validateParties'))->invoke($form);

        $this->assertSame([], $form->getErrorBag()->toArray());
    }
}
