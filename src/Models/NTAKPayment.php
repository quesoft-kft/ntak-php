<?php

namespace QueSoft\Ntak\Models;

use QueSoft\Ntak\Enums\NTAKPaymentType;

class NTAKPayment
{
    protected int $round = 0;

    public function __construct(
        public readonly NTAKPaymentType $paymentType,
        public readonly float $total,
    ) {
    }

    /**
     * @return array{fizetesiMod: string, fizetettOsszegHUF: int|float}
     */
    public function buildRequest(): array
    {
        $rounded = 0;
        $isCash = in_array($this->paymentType, [
            NTAKPaymentType::KESZPENZHUF,
            NTAKPaymentType::KESZPENZEUR,
        ], true);

        $request = [
            'fizetesiMod' => $this->paymentType->name,
            'fizetettOsszegHUF' => $isCash
                ? $rounded = (int) (round($this->total / 5) * 5)
                : round($this->total),
        ];

        if ($isCash) {
            $this->round = (int) ($this->total - $rounded);
        }

        return $request;
    }

    public function round(): int
    {
        return $this->round;
    }
}
