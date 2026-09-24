<?php

namespace Tests\Unit\Models;

use App\Models\BillOfLading;
use App\Models\Client;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class BillOfLadingReportPartyDataTest extends TestCase
{
    private function client(string $name, string $taxId): Client
    {
        $client = new Client();
        $client->legal_name = $name;
        $client->commercial_name = $name;
        $client->tax_id = $taxId;
        $client->setRelation('contactData', new Collection());

        return $client;
    }

    public function test_consignee_is_not_lost_when_client_has_no_contact_row(): void
    {
        $client = $this->client('MSC BUENOS AIRES', '30-12345678-9');

        $bill = new BillOfLading();
        $bill->consignee_id = 100;
        $bill->setRelation('consignee', $client);
        $bill->setRelation('specificContacts', new Collection());

        $data = $bill->getConsigneeCompleteData();

        $this->assertSame('MSC BUENOS AIRES', $data['company_name']);
        $this->assertSame('30-12345678-9', $data['tax_id']);
        $this->assertSame('', $data['address']);
    }

    public function test_notify_party_is_not_lost_when_client_has_no_contact_row(): void
    {
        $client = $this->client('MSC BUENOS AIRES', '30-12345678-9');

        $bill = new BillOfLading();
        $bill->notify_party_id = 100;
        $bill->setRelation('notifyParty', $client);
        $bill->setRelation('specificContacts', new Collection());

        $data = $bill->getNotifyPartyCompleteData();

        $this->assertSame('MSC BUENOS AIRES', $data['company_name']);
        $this->assertSame('30-12345678-9', $data['tax_id']);
    }

    public function test_literal_notify_party_is_preserved_for_reports(): void
    {
        $bill = new BillOfLading();
        $bill->notify_party_text = 'SAME AS CONSIGNEE';
        $bill->setRelation('notifyParty', null);
        $bill->setRelation('specificContacts', new Collection());

        $data = $bill->getNotifyPartyCompleteData();

        $this->assertSame('SAME AS CONSIGNEE', $data['company_name']);
    }
}
