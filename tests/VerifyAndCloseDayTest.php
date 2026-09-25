<?php

namespace QueSoft\Ntak\Tests;

use Carbon\Carbon;
use QueSoft\Ntak\Enums\NTAKDayType;
use QueSoft\Ntak\Enums\NTAKVerifyStatus;
use QueSoft\Ntak\NTAK;
use QueSoft\Ntak\Responses\NTAKVerifyResponse;
use PHPUnit\Framework\TestCase;

class VerifyAndCloseDayTest extends TestCase
{
    public function test_close_day_with_fake_client(): void
    {
        $client = new FakeNTAKClient(
            taxNumber: '11223344122',
            regNumber: 'ET23002557',
            softwareRegNumber: 'SKYLON',
            version: '3.2',
            certPath: 'unused',
            testing: true,
        );

        $response = NTAK::message($client, Carbon::now())->closeDay(
            start: Carbon::now()->subHours(8),
            end: Carbon::now()->subMinutes(2),
            dayType: NTAKDayType::NORMAL_NAP,
        );

        $this->assertIsString($response);
        $this->assertArrayHasKey('zarasiInformaciok', $client->lastRequest());
    }

    public function test_verify_with_fake_client(): void
    {
        $client = new FakeNTAKClient(
            taxNumber: '11223344122',
            regNumber: 'ET23002557',
            softwareRegNumber: 'SKYLON',
            version: '3.2',
            certPath: 'unused',
            testing: true,
        );

        $client->fakeResponse([
            'uzenetValaszok' => [
                [
                    'sikeresUzenetek' => [],
                    'sikertelenUzenetek' => [],
                    'fejlecHibak' => [],
                    'statusz' => NTAKVerifyStatus::TELJESEN_SIKERES->value,
                    'feldolgozasAzonosito' => 'process-1',
                ],
            ],
        ]);

        $response = NTAK::message($client, Carbon::now())->verify('process-1');

        $this->assertInstanceOf(NTAKVerifyResponse::class, $response);
        $this->assertTrue($response->successful());
        $this->assertSame('process-1', $response->processId);
    }

    public function test_verify_all_with_fake_client(): void
    {
        $client = new FakeNTAKClient(
            taxNumber: '11223344122',
            regNumber: 'ET23002557',
            softwareRegNumber: 'SKYLON',
            version: '3.2',
            certPath: 'unused',
            testing: true,
        );

        $client->fakeResponse([
            'uzenetValaszok' => [
                [
                    'sikeresUzenetek' => [],
                    'sikertelenUzenetek' => [],
                    'fejlecHibak' => [],
                    'statusz' => NTAKVerifyStatus::TELJESEN_SIKERES->value,
                    'feldolgozasAzonosito' => 'process-1',
                ],
                [
                    'sikeresUzenetek' => [],
                    'sikertelenUzenetek' => [],
                    'fejlecHibak' => [],
                    'statusz' => NTAKVerifyStatus::BEFOGADVA->value,
                    'feldolgozasAzonosito' => 'process-2',
                ],
            ],
        ]);

        $responses = NTAK::message($client, Carbon::now())->verifyAll(['process-1', 'process-2']);

        $this->assertCount(2, $responses);
        $this->assertTrue($responses[0]->successful());
        $this->assertTrue($responses[1]->unsuccessful());
        $this->assertSame('process-2', $responses[1]->processId);
    }
}
