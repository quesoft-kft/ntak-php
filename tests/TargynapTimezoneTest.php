<?php

namespace QueSoft\Ntak\Tests;

use Carbon\Carbon;
use QueSoft\Ntak\Enums\NTAKAmount;
use QueSoft\Ntak\Enums\NTAKCategory;
use QueSoft\Ntak\Enums\NTAKDayType;
use QueSoft\Ntak\Enums\NTAKOrderType;
use QueSoft\Ntak\Enums\NTAKPaymentType;
use QueSoft\Ntak\Enums\NTAKSubcategory;
use QueSoft\Ntak\Enums\NTAKVat;
use QueSoft\Ntak\Models\NTAKOrder;
use QueSoft\Ntak\Models\NTAKOrderItem;
use QueSoft\Ntak\Models\NTAKPayment;
use QueSoft\Ntak\NTAK;
use PHPUnit\Framework\TestCase;

class TargynapTimezoneTest extends TestCase
{
    private string $previousTimezone;

    protected function setUp(): void
    {
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Budapest');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
    }

    /**
     * Carbon 3: createFromTimestamp() without tz stays UTC — format('Y-m-d') would be 2026-10-22.
     * NTAK targynap must still be the Budapest calendar day matching nyitasIdopontja.
     */
    public function test_close_day_targynap_uses_budapest_date_for_utc_midnight_instance(): void
    {
        $bpMidnightTs = Carbon::parse('2026-10-23 00:00:00', 'Europe/Budapest')->getTimestamp();

        // Intentionally no timezone — simulates consumer Carbon 3 bug pattern
        $start = Carbon::createFromTimestamp($bpMidnightTs);
        $end = Carbon::createFromTimestamp($bpMidnightTs + 3600);

        // Carbon 3: UTC instance (name may be "UTC" or "+00:00")
        $this->assertSame(0, $start->utcOffset());
        $this->assertSame('2026-10-22', $start->format('Y-m-d'));

        $client = new FakeNTAKClient(
            taxNumber: '11223344122',
            regNumber: 'ET23002557',
            softwareRegNumber: 'SKYLON',
            version: '3.2',
            certPath: 'unused',
            testing: true,
        );

        NTAK::message($client, Carbon::now())->closeDay(
            start: $start,
            end: $end,
            dayType: NTAKDayType::FORGALOM_NELKULI_NAP,
        );

        $info = $client->lastRequest()['zarasiInformaciok'];

        $this->assertSame('2026-10-23', $info['targynap']);
        $this->assertSame('2026-10-23T00:00:00+02:00', $info['nyitasIdopontja']);
    }

    public function test_handle_order_targynap_uses_budapest_date_for_utc_end_instance(): void
    {
        $bpMidnightTs = Carbon::parse('2026-10-23 00:00:00', 'Europe/Budapest')->getTimestamp();

        $end = Carbon::createFromTimestamp($bpMidnightTs);
        $start = Carbon::createFromTimestamp($bpMidnightTs - 600);

        $this->assertSame('2026-10-22', $end->format('Y-m-d'));

        $client = new FakeNTAKClient(
            taxNumber: '11223344122',
            regNumber: 'ET23002557',
            softwareRegNumber: 'SKYLON',
            version: '3.2',
            certPath: 'unused',
            testing: true,
        );

        NTAK::message($client, Carbon::now())->handleOrder(
            new NTAKOrder(
                orderType: NTAKOrderType::NORMAL,
                orderId: 'order-targynap-test',
                orderItems: [
                    new NTAKOrderItem(
                        name: 'Teszt',
                        category: NTAKCategory::ETEL,
                        subcategory: NTAKSubcategory::SNACK,
                        vat: NTAKVat::C_27,
                        price: 1000,
                        amountType: NTAKAmount::DARAB,
                        amount: 1,
                        quantity: 1,
                        when: $start->copy(),
                    ),
                ],
                start: $start,
                end: $end,
                payments: [
                    new NTAKPayment(NTAKPaymentType::BANKKARTYA, 1000),
                ],
            )
        );

        $order = $client->lastRequest()['rendelesOsszesitok'][0];

        $this->assertSame('2026-10-23', $order['targynap']);
        $this->assertStringContainsString('2026-10-23T00:00:00+02:00', $order['rendelesVege']);
    }
}
