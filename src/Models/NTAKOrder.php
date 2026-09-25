<?php

namespace QueSoft\Ntak\Models;

use Carbon\Carbon;
use InvalidArgumentException;
use QueSoft\Ntak\Enums\NTAKAggregateCause;
use QueSoft\Ntak\Enums\NTAKOrderType;
use QueSoft\Ntak\Enums\NTAKPaymentType;
use QueSoft\Ntak\Enums\NTAKVat;
use QueSoft\Ntak\NTAK;

class NTAKOrder
{
    /** @var list<array<string, mixed>> */
    protected array $serviceFeeItems = [];

    protected int $drsQuantity = 0;

    /**
     * @param  list<NTAKOrderItem>|null  $orderItems
     * @param  list<NTAKPayment>|null  $payments
     */
    public function __construct(
        public readonly NTAKOrderType $orderType,
        public readonly string $orderId,
        public readonly ?array $orderItems = null,
        public readonly ?string $ntakOrderId = null,
        public readonly ?Carbon $start = null,
        public ?Carbon $end = null,
        public readonly bool $isAtTheSpot = true,
        public readonly ?array $payments = null,
        public readonly int $discount = 0,
        public readonly int $serviceFee = 0,
        public readonly bool $aggregated = false,
        public readonly ?NTAKAggregateCause $aggregatedCause = null,
        public readonly int $tip_amount = 0,
    ) {
        if ($orderType === NTAKOrderType::NORMAL) {
            $this->validateIfNormal();
        }
        if ($orderType !== NTAKOrderType::NORMAL) {
            $this->validateIfNotNormal();
        }
        if ($orderType !== NTAKOrderType::SZTORNO) {
            $this->validateIfNotStorno();
        }

        $this->drsQuantity = $this->drsQuantity();
        $this->end = $end ?: Carbon::now();
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function buildOrderItems(): ?array
    {
        $orderItems = $this->orderItems === null
            ? null
            : array_map(
                fn (NTAKOrderItem $orderItem) => $orderItem->buildRequest($this->isAtTheSpot),
                $this->orderItems
            );

        if ($orderItems !== null && $this->drsQuantity > 0) {
            $orderItems[] = NTAKOrderItem::buildDrsRequest($this->drsQuantity, $this->end);
        }

        if ($orderItems !== null && ($this->discount > 0 || $this->hasItemDiscounts())) {
            $orderItems = $this->buildDiscountRequests($orderItems);
        }

        if ($orderItems !== null && $this->serviceFee > 0) {
            $orderItems = $this->buildServiceFeeRequests($orderItems);
        }

        if ($orderItems !== null && $this->tip_amount > 0) {
            $orderItems[] = NTAKOrderItem::buildTipAmountRequest(
                NTAKVat::C_27,
                $this->tip_amount,
                $this->start ?? $this->end
            );
        }

        return $orderItems;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildPaymentTypes(): array
    {
        $payments = array_map(
            fn (NTAKPayment $payment) => $payment->buildRequest(),
            $this->payments ?? []
        );

        foreach ($this->payments ?? [] as $payment) {
            if ($payment->round() !== 0) {
                $payments[] = [
                    'fizetesiMod' => NTAKPaymentType::KEREKITES->name,
                    'fizetettOsszegHUF' => $payment->round(),
                ];

                break;
            }
        }

        return $payments;
    }

    public function paymentsTotal(): float
    {
        if ($this->orderType === NTAKOrderType::SZTORNO) {
            return 0;
        }

        return array_reduce(
            $this->payments ?? [],
            fn (float $carry, NTAKPayment $payment) => $carry + $payment->total,
            0.0
        );
    }

    protected function validateIfNormal(): void
    {
        if ($this->discount > 100) {
            throw new InvalidArgumentException('discount cannot be greater than 100');
        }

        foreach ($this->orderItems ?? [] as $orderItem) {
            if ($orderItem instanceof NTAKOrderItem
                && $orderItem->discount !== null
                && $orderItem->discount > 100
            ) {
                throw new InvalidArgumentException('order item discount cannot be greater than 100');
            }
        }
    }

    protected function validateIfNotNormal(): void
    {
        if ($this->ntakOrderId === null) {
            throw new InvalidArgumentException('ntakOrderId cannot be null in this case');
        }
    }

    protected function validateIfNotStorno(): void
    {
        if ($this->orderItems === null || count($this->orderItems) === 0) {
            throw new InvalidArgumentException('orderItems cannot be null in this case');
        }

        foreach ($this->orderItems as $orderItem) {
            if (! $orderItem instanceof NTAKOrderItem) {
                throw new InvalidArgumentException('orderItems must be an array of NTAKOrderItem instances');
            }
        }

        if ($this->start === null) {
            throw new InvalidArgumentException('start cannot be null in this case');
        }

        if ($this->end === null) {
            throw new InvalidArgumentException('end cannot be null in this case');
        }

        if ($this->payments === null || count($this->payments) === 0) {
            throw new InvalidArgumentException('paymentType cannot be null in this case');
        }
    }

    protected function calculateDrsQuantity(?NTAKVat $vat = null): int
    {
        if ($this->orderType === NTAKOrderType::SZTORNO) {
            return 0;
        }

        return array_reduce(
            $this->orderItems ?? [],
            function (int $carry, NTAKOrderItem $orderItem) use ($vat) {
                $isTargetVat = $vat === null || $orderItem->vat === $vat;

                if ($orderItem->isDrs && $isTargetVat) {
                    return $carry + (int) $orderItem->quantity;
                }

                return $carry;
            },
            0
        );
    }

    public function drsQuantityByVat(NTAKVat $vat): int
    {
        return $this->calculateDrsQuantity($vat);
    }

    protected function drsQuantity(): int
    {
        return $this->calculateDrsQuantity();
    }

    /**
     * @param  list<array<string, mixed>>  $orderItems
     * @return list<array<string, mixed>>
     */
    protected function buildDiscountRequests(array $orderItems): array
    {
        foreach ($this->uniqueVats() as $vat) {
            $orderItems = $this->addDiscountRequestByVat($orderItems, $vat);
        }

        return $orderItems;
    }

    /**
     * @param  list<array<string, mixed>>  $orderItems
     * @return list<array<string, mixed>>
     */
    protected function buildServiceFeeRequests(array $orderItems): array
    {
        foreach ($this->uniqueVats() as $vat) {
            $orderItems = $this->addServiceFeeRequestByVat(
                $orderItems,
                $vat,
                $this->drsQuantityByVat($vat)
            );
        }

        return $orderItems;
    }

    /**
     * @param  list<array<string, mixed>>  $orderItems
     * @return list<array<string, mixed>>
     */
    protected function addDiscountRequestByVat(array $orderItems, NTAKVat $vat): array
    {
        $totalRoundedDiscount = 0;

        foreach ($this->orderItemsWithVat($vat) as $item) {
            $roundedOriginal = $item->roundedSum();
            $roundedDiscounted = (int) round($item->discountedRawSum($this->discount));

            $totalRoundedDiscount += ($roundedOriginal - $roundedDiscounted);
        }

        if ($vat === NTAKVat::E_0 && $this->drsQuantity > 0) {
            $totalRoundedDiscount += $this->drsRoundedDiscount();
        }

        if ($totalRoundedDiscount > 0) {
            $orderItems[] = NTAKOrderItem::buildDiscountRequest(
                $vat,
                -$totalRoundedDiscount,
                $this->end
            );
        }

        return $orderItems;
    }

    protected function hasItemDiscounts(): bool
    {
        foreach ($this->orderItems ?? [] as $orderItem) {
            if ($orderItem->discount !== null) {
                return true;
            }
        }

        return false;
    }

    protected function drsRoundedDiscount(): int
    {
        if (! $this->hasItemDiscounts()) {
            $drsTotalOriginal = $this->drsQuantity * NTAK::DRS_AMOUNT;
            $drsTotalDiscounted = (int) round($drsTotalOriginal * (1 - $this->discount / 100));

            return $drsTotalOriginal - $drsTotalDiscounted;
        }

        $totalRoundedDiscount = 0;

        foreach ($this->orderItems ?? [] as $orderItem) {
            if (! $orderItem->isDrs) {
                continue;
            }

            $drsTotalOriginal = $orderItem->quantity * NTAK::DRS_AMOUNT;
            $drsTotalDiscounted = (int) round(
                $drsTotalOriginal * (1 - $orderItem->effectiveDiscount($this->discount) / 100)
            );

            $totalRoundedDiscount += ($drsTotalOriginal - $drsTotalDiscounted);
        }

        return $totalRoundedDiscount;
    }

    /**
     * @param  list<array<string, mixed>>  $orderItems
     * @return list<array<string, mixed>>
     */
    protected function addServiceFeeRequestByVat(array $orderItems, NTAKVat $vat, int $drsQuantity): array
    {
        $orderItemsWithVat = $this->orderItemsWithVat($vat);
        $totalOfOrderItemsWithDiscount = $this->totalOfOrderItemsWithDiscount($orderItemsWithVat);
        $serviceFeeAmount = (int) round($totalOfOrderItemsWithDiscount * $this->serviceFee / 100);

        if ($serviceFeeAmount <= 0) {
            return $orderItems;
        }

        $serviceFeeItem = NTAKOrderItem::buildServiceFeeRequest(
            $vat,
            $serviceFeeAmount,
            $this->end
        );

        $orderItems[] = $serviceFeeItem;
        $this->serviceFeeItems[] = $serviceFeeItem;

        return $orderItems;
    }

    /**
     * @return list<NTAKOrderItem>
     */
    protected function orderItemsWithVat(NTAKVat $vat): array
    {
        return array_values(array_filter(
            $this->orderItems ?? [],
            fn (NTAKOrderItem $orderItem) => $orderItem->vat === $vat
        ));
    }

    /**
     * @param  list<NTAKOrderItem>  $orderItems
     */
    protected function totalOfOrderItemsWithDiscount(array $orderItems): float
    {
        return array_reduce(
            $orderItems,
            function (float $carry, NTAKOrderItem $orderItem) {
                if ($orderItem->bypassServiceFee) {
                    return $carry;
                }

                return $carry + $orderItem->discountedRawSum($this->discount);
            },
            0.0
        );
    }

    /**
     * @return list<NTAKVat>
     */
    protected function uniqueVats(): array
    {
        $vats = array_map(
            fn (NTAKOrderItem $orderItem) => $orderItem->vat,
            $this->orderItems ?? []
        );

        if ($this->drsQuantity > 0) {
            $vats[] = NTAKVat::E_0;
        }

        return array_values(array_unique($vats, SORT_REGULAR));
    }
}
