<?php

namespace QueSoft\Ntak\Tests;

use Carbon\Carbon;
use QueSoft\Ntak\Enums\NTAKAmount;
use QueSoft\Ntak\Enums\NTAKCategory;
use QueSoft\Ntak\Enums\NTAKOrderType;
use QueSoft\Ntak\Enums\NTAKPaymentType;
use QueSoft\Ntak\Enums\NTAKSubcategory;
use QueSoft\Ntak\Enums\NTAKVat;
use QueSoft\Ntak\Models\NTAKOrder;
use QueSoft\Ntak\Models\NTAKOrderItem;
use QueSoft\Ntak\Models\NTAKPayment;
use QueSoft\Ntak\NTAK;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class StoreOrderTest extends TestCase
{
    public function test_store_order_with_fake_client(): void
    {
        $when = Carbon::now()->subMinute();

        $client = new FakeNTAKClient(
            taxNumber: '11223344122',
            regNumber: 'ET23002557',
            softwareRegNumber: 'SKYLON',
            version: '3.2',
            certPath: 'unused',
            testing: true,
        );

        $response = NTAK::message($client, Carbon::now())->handleOrder(
            new NTAKOrder(
                orderType: NTAKOrderType::NORMAL,
                orderId: (string) Uuid::uuid4(),
                orderItems: [
                    new NTAKOrderItem(
                        name: 'Absolut vodka',
                        category: NTAKCategory::ALKOHOLOSITAL,
                        subcategory: NTAKSubcategory::PARLAT,
                        vat: NTAKVat::C_27,
                        price: 1000,
                        amountType: NTAKAmount::LITER,
                        amount: 0.04,
                        quantity: 2,
                        when: $when,
                    ),
                ],
                start: $when->copy()->subMinutes(7),
                end: $when,
                payments: [
                    new NTAKPayment(NTAKPaymentType::BANKKARTYA, 2000),
                ],
            )
        );

        $this->assertIsString($response);
        $this->assertIsArray($client->lastRequest());
        $this->assertArrayHasKey('rendelesOsszesitok', $client->lastRequest());
        $this->assertIsArray($client->lastResponse());
    }
}
