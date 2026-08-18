<?php

namespace Tests\Unit\Models;

use App\Models\Client;
use App\Models\ClientContactData;
use App\Models\Country;
use Carbon\Carbon;
use Tests\TestCase;

class TestableClientContactData extends ClientContactData
{
    public function fireCreatingForTest(): void
    {
        $this->fireModelEvent('creating');
    }
}

class ClientContactDataTimezoneTest extends TestCase
{
    public function test_creating_contact_uses_country_timezone_when_available(): void
    {
        $country = new Country();
        $country->setAttribute(
            'timezone',
            'America/Argentina/Buenos_Aires'
        );

        $client = new Client();
        $client->setRelation('country', $country);

        $contact = new TestableClientContactData();
        $contact->setRelation('client', $client);

        $contact->fireCreatingForTest();

        $this->assertSame(
            'America/Argentina/Buenos_Aires',
            $contact->timezone
        );
    }

    public function test_creating_contact_does_not_invent_timezone_when_country_has_none(): void
    {
        $country = new Country();
        $country->setAttribute('timezone', null);

        $client = new Client();
        $client->setRelation('country', $country);

        $contact = new TestableClientContactData();
        $contact->setRelation('client', $client);

        $contact->fireCreatingForTest();

        $this->assertNull($contact->timezone);
    }

    public function test_business_hours_use_application_timezone_when_contact_and_country_are_unknown(): void
    {
        config()->set('app.timezone', 'UTC');

        $country = new Country();
        $country->setAttribute('timezone', null);

        $client = new Client();
        $client->setRelation('country', $country);

        $contact = new ClientContactData();
        $contact->setAttribute('timezone', null);
        $contact->setAttribute('business_hours', [
            'monday' => [
                'open' => '09:00',
                'close' => '17:00',
            ],
        ]);
        $contact->setRelation('client', $client);

        $when = Carbon::parse(
            '2026-08-17 12:00:00',
            'UTC'
        );

        $this->assertTrue($contact->isOpenAt($when));
    }

    public function test_explicit_contact_timezone_has_priority_over_country_timezone(): void
    {
        config()->set('app.timezone', 'UTC');

        $country = new Country();
        $country->setAttribute(
            'timezone',
            'America/Argentina/Buenos_Aires'
        );

        $client = new Client();
        $client->setRelation('country', $country);

        $contact = new ClientContactData();

        // En este instante:
        // Tokio ya está en lunes 00:30.
        // Argentina todavía está en domingo.
        $contact->setAttribute('timezone', 'Asia/Tokyo');
        $contact->setAttribute('business_hours', [
            'monday' => [
                'open' => '00:00',
                'close' => '01:00',
            ],
        ]);
        $contact->setRelation('client', $client);

        $when = Carbon::parse(
            '2026-08-16 15:30:00',
            'UTC'
        );

        $this->assertTrue($contact->isOpenAt($when));
    }
}
