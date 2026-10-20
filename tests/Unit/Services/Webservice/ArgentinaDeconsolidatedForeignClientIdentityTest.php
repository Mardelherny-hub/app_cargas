<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Client;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\ShipmentItem;
use App\Services\Webservice\Argentina\SimpleXmlGeneratorDesconsolidado;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ArgentinaDeconsolidatedForeignClientIdentityTest extends TestCase
{
    #[Test]
    public function foreign_alphanumeric_identifier_is_not_converted_into_argentine_document(): void
    {
        $bill = $this->bill('US', 'CUIT', 'P11000103603');

        $this->assertNull($this->field($bill, 'consigneeDocumentType'));
        $this->assertNull($this->field($bill, 'consigneeTaxId'));
    }

    #[Test]
    public function foreign_legacy_cuit_with_only_digits_is_not_sent_as_argentine_cuit(): void
    {
        $bill = $this->bill('US', 'CUIT', '11000103603');

        $this->assertNull($this->field($bill, 'consigneeDocumentType'));
        $this->assertNull($this->field($bill, 'consigneeTaxId'));
    }

    #[Test]
    public function brazilian_cnpj_is_not_mapped_to_argentine_recipient_identifier(): void
    {
        $bill = $this->bill('BR', 'CNPJ', '12345678000199');

        $this->assertNull($this->field($bill, 'consigneeDocumentType'));
        $this->assertNull($this->field($bill, 'consigneeTaxId'));
    }

    #[Test]
    public function argentine_cuit_is_kept_with_existing_numeric_normalization(): void
    {
        $bill = $this->bill('AR', 'CUIT', '20-12345678-9');

        $this->assertSame('CUIT', $this->field($bill, 'consigneeDocumentType'));
        $this->assertSame('20123456789', $this->field($bill, 'consigneeTaxId'));
    }

    #[Test]
    public function explicit_title_document_fields_override_foreign_client_metadata(): void
    {
        $bill = $this->bill('US', 'CUIT', 'P11000103603', 'CUIT', '20123456789');

        $this->assertSame('CUIT', $this->field($bill, 'consigneeDocumentType'));
        $this->assertSame('20123456789', $this->field($bill, 'consigneeTaxId'));
    }

    #[Test]
    public function explicit_alphanumeric_title_identifier_is_rejected_instead_of_truncated(): void
    {
        $bill = $this->bill('US', 'CUIT', 'P11000103603', 'CUIT', '1234AB');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('IdentificadorDestinatarioMercaderia debe ser numérico');
        $this->field($bill, 'consigneeTaxId');
    }

    #[Test]
    public function alphanumeric_argentine_identifier_is_rejected_instead_of_truncated(): void
    {
        $bill = $this->bill('AR', 'CUIT', 'P11000103603');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('IdentificadorDestinatarioMercaderia debe ser numérico');
        $this->field($bill, 'consigneeTaxId');
    }

    private function bill(
        string $countryCode,
        string $documentType,
        ?string $taxId,
        ?string $explicitDocument = null,
        ?string $explicitTaxId = null
    ): BillOfLading {
        $item = new ShipmentItem();
        $item->consignee_document_type = $explicitDocument;
        $item->consignee_tax_id = $explicitTaxId;

        $country = new Country();
        $country->alpha2_code = $countryCode;

        $document = new DocumentType();
        $document->code = $documentType;

        $consignee = new Client();
        $consignee->tax_id = $taxId;
        $consignee->setRelation('country', $country);
        $consignee->setRelation('documentType', $document);

        $bill = new BillOfLading();
        $bill->id = 91;
        $bill->setRelation('consignee', $consignee);
        $bill->setRelation('shipmentItems', collect([$item]));

        return $bill;
    }

    private function field(BillOfLading $bill, string $method): ?string
    {
        $generator = (new ReflectionClass(SimpleXmlGeneratorDesconsolidado::class))
            ->newInstanceWithoutConstructor();

        return (new ReflectionMethod($generator, $method))->invoke($generator, $bill);
    }
}
