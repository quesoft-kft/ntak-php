<?php

namespace QueSoft\Ntak;

use Carbon\Carbon;
use QueSoft\Ntak\Enums\NTAKCategory;
use QueSoft\Ntak\Enums\NTAKDayType;
use QueSoft\Ntak\Enums\NTAKOrderType;
use QueSoft\Ntak\Enums\NTAKSubcategory;
use QueSoft\Ntak\Enums\NTAKVerifyStatus;
use QueSoft\Ntak\Models\NTAKOrder;
use QueSoft\Ntak\Responses\NTAKVerifyResponse;

class NTAK
{
    public const int DRS_AMOUNT = 50;

    public function __construct(
        public readonly NTAKClient $client,
        protected Carbon $when,
    ) {
    }

    /**
     * @return list<NTAKCategory>
     */
    public static function categories(): array
    {
        return NTAKCategory::cases();
    }

    /**
     * @return list<NTAKSubcategory>
     */
    public static function subcategories(NTAKCategory $category): array
    {
        return $category->subcategories();
    }

    public static function message(NTAKClient $client, Carbon $when): self
    {
        return new static($client, $when);
    }

    public function fake(array $expectedResponse): self
    {
        $this->client->fakeResponse($expectedResponse);

        return $this;
    }

    public function handleOrder(NTAKOrder ...$ntakOrders): string
    {
        $orders = [];

        foreach ($ntakOrders as $ntakOrder) {
            $orders[] = [
                'rendelesBesorolasa' => $ntakOrder->orderType->name,
                'rmsRendelesAzonosito' => $ntakOrder->orderId,
                'hivatkozottRendelesOsszesito' => $ntakOrder->orderType === NTAKOrderType::NORMAL
                    ? null
                    : $ntakOrder->ntakOrderId,
                // Carbon 3: createFromTimestamp() UTC-ben marad → format() előző napot adhat BP éjfél körül
                'targynap' => $ntakOrder->end->timezone('Europe/Budapest')->format('Y-m-d'),
                'rendelesKezdete' => $ntakOrder->orderType === NTAKOrderType::SZTORNO
                    ? null
                    : $ntakOrder->start->timezone('Europe/Budapest')->toIso8601String(),
                'rendelesVege' => $ntakOrder->orderType === NTAKOrderType::SZTORNO
                    ? null
                    : $ntakOrder->end->timezone('Europe/Budapest')->toIso8601String(),
                'helybenFogyasztott' => $ntakOrder->isAtTheSpot,
                'osszesitett' => $ntakOrder->orderType === NTAKOrderType::NORMAL
                    ? $ntakOrder->aggregated
                    : false,
                'osszesitettIndoklasa' => ($ntakOrder->orderType === NTAKOrderType::NORMAL && $ntakOrder->aggregated === true)
                    ? $ntakOrder->aggregatedCause?->name
                    : null,
                'fizetesiInformaciok' => $ntakOrder->orderType === NTAKOrderType::SZTORNO
                    ? null
                    : [
                        'rendelesVegosszegeHUF' => (int) round($ntakOrder->paymentsTotal()),
                        'fizetesiModok' => $ntakOrder->buildPaymentTypes(),
                    ],
                'rendelesTetelek' => $ntakOrder->orderType === NTAKOrderType::SZTORNO
                    ? null
                    : $ntakOrder->buildOrderItems(),
            ];
        }

        return $this->client->message(
            ['rendelesOsszesitok' => $orders],
            $this->when,
            '/rms/rendeles-osszesito'
        )['feldolgozasAzonosito'];
    }

    /**
     * Resend a previously sent message.
     * Requires decoded array contents from the previous request's lastRequest message.
     * Set $when to exactly the same timestamp as the previous message.
     *
     * @param  array<string, mixed>  $message
     */
    public function resendMessage(array $message = [], bool $order = true): string
    {
        $uri = $order ? '/rms/rendeles-osszesito' : '/rms/napi-zaras';

        return $this->client->message(
            $message,
            $this->when,
            $uri
        )['feldolgozasAzonosito'];
    }

    public function closeDay(
        Carbon $start,
        ?Carbon $end,
        NTAKDayType $dayType,
        int $tips = 0,
    ): string {
        $message = [
            'zarasiInformaciok' => [
                // Carbon 3: createFromTimestamp() UTC-ben marad → format() előző napot adhat BP éjfél körül
                'targynap' => $start->timezone('Europe/Budapest')->format('Y-m-d'),
                'targynapBesorolasa' => $dayType->name,
                'nyitasIdopontja' => $dayType !== NTAKDayType::ADOTT_NAPON_ZARVA
                    ? $start->timezone('Europe/Budapest')->toIso8601String()
                    : null,
                'zarasIdopontja' => $dayType !== NTAKDayType::ADOTT_NAPON_ZARVA
                    ? $end?->timezone('Europe/Budapest')->toIso8601String()
                    : null,
                'osszesBorravalo' => $tips,
            ],
        ];

        return $this->client->message(
            $message,
            $this->when,
            '/rms/napi-zaras'
        )['feldolgozasAzonosito'];
    }

    public function verify(string $processId): NTAKVerifyResponse
    {
        return $this->verifyAll([$processId])[0];
    }

    /**
     * @param  list<string>  $processIds
     * @return list<NTAKVerifyResponse>
     */
    public function verifyAll(array $processIds): array
    {
        $messageContent = [];

        foreach ($processIds as $processId) {
            $messageContent[] = [
                'feldolgozasAzonosito' => $processId,
            ];
        }

        $responses = $this->client->message(
            ['feldolgozasAzonositok' => $messageContent],
            $this->when,
            '/rms/ellenorzes'
        )['uzenetValaszok'];

        $verifyResponses = [];

        foreach ($responses as $response) {
            $verifyResponses[] = new NTAKVerifyResponse(
                successfulMessages: $response['sikeresUzenetek'] ?? [],
                unsuccessfulMessages: $response['sikertelenUzenetek'] ?? [],
                headerErrors: $response['fejlecHibak'] ?? [],
                status: NTAKVerifyStatus::from($response['statusz']),
                processId: $response['feldolgozasAzonosito'],
            );
        }

        return $verifyResponses;
    }
}
