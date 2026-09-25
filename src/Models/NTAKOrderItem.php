<?php

namespace QueSoft\Ntak\Models;

use Carbon\Carbon;
use QueSoft\Ntak\Enums\NTAKAmount;
use QueSoft\Ntak\Enums\NTAKCategory;
use QueSoft\Ntak\Enums\NTAKSubcategory;
use QueSoft\Ntak\Enums\NTAKVat;
use QueSoft\Ntak\NTAK;

class NTAKOrderItem
{
    protected int $drsSum;

    public function __construct(
        public readonly string $name,
        public readonly NTAKCategory $category,
        public readonly NTAKSubcategory $subcategory,
        public NTAKVat $vat,
        public readonly float $price,
        public readonly NTAKAmount $amountType,
        public readonly float $amount,
        public readonly int $quantity,
        public readonly Carbon $when,
        public readonly bool $isDrs = false,
        public readonly bool $bypassServiceFee = false,
        public readonly ?int $discount = null,
    ) {
        $this->drsSum = $isDrs
            ? $this->quantity * NTAK::DRS_AMOUNT
            : 0;
    }

    /**
     * Item-level discount overrides the order-level one.
     * Null means the item inherits the order discount.
     */
    public function effectiveDiscount(int $orderDiscount = 0): int
    {
        return $this->discount ?? $orderDiscount;
    }

    public function discountedRawSum(int $orderDiscount = 0): float
    {
        return $this->rawSum() * (1 - $this->effectiveDiscount($orderDiscount) / 100);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildRequest(bool $isAtTheSpot = true): array
    {
        $this->vat = ! $isAtTheSpot && $this->category === NTAKCategory::ALKMENTESITAL_HELYBEN
            ? NTAKVat::C_27
            : $this->vat;

        $price = $this->subcategory === NTAKSubcategory::SZERVIZDIJ
            ? $this->price
            : $this->price - ($this->quantity > 0 ? $this->drsSum / $this->quantity : 0);

        return [
            'megnevezes' => $this->name,
            'fokategoria' => $this->category->name,
            'alkategoria' => $this->subcategory->name,
            'afaKategoria' => $this->vat->name,
            'bruttoEgysegar' => $price,
            'mennyisegiEgyseg' => $this->amountType->name,
            'mennyiseg' => round($this->amount, 2),
            'tetelszam' => $this->quantity,
            'rendelesIdopontja' => $this->when->timezone('Europe/Budapest')->toIso8601String(),
            'tetelOsszesito' => $this->roundedSum(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildDiscountRequest(NTAKVat $vat, int $price, Carbon $when): array
    {
        return (new static(
            name: 'Kedvezmény',
            category: NTAKCategory::EGYEB,
            subcategory: NTAKSubcategory::KEDVEZMENY,
            vat: $vat,
            price: $price,
            amountType: NTAKAmount::DARAB,
            amount: 1,
            quantity: 1,
            when: $when,
        ))->buildRequest();
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildServiceFeeRequest(NTAKVat $vat, int $price, Carbon $when): array
    {
        return (new static(
            name: 'Szervízdíj',
            category: NTAKCategory::EGYEB,
            subcategory: NTAKSubcategory::SZERVIZDIJ,
            vat: $vat,
            price: $price,
            amountType: NTAKAmount::DARAB,
            amount: 1,
            quantity: 1,
            when: $when,
        ))->buildRequest();
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildTipAmountRequest(NTAKVat $vat, int $price, Carbon $when): array
    {
        return (new static(
            name: 'Borravaló',
            category: NTAKCategory::EGYEB,
            subcategory: NTAKSubcategory::BORRAVALO,
            vat: $vat,
            price: $price,
            amountType: NTAKAmount::DARAB,
            amount: 1,
            quantity: 1,
            when: $when,
        ))->buildRequest();
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildDrsRequest(int $quantity, Carbon $when): array
    {
        return (new static(
            name: 'DRS',
            category: NTAKCategory::EGYEB,
            subcategory: NTAKSubcategory::KORNYEZETBARAT_CSOMAGOLAS,
            vat: NTAKVat::E_0,
            price: NTAK::DRS_AMOUNT,
            amountType: NTAKAmount::DARAB,
            amount: 1,
            quantity: $quantity,
            when: $when,
        ))->buildRequest();
    }

    public function roundedSum(): int
    {
        return (int) round($this->quantity * $this->price - $this->drsSum);
    }

    public function rawSum(): float
    {
        return ($this->quantity * $this->price) - ($this->isDrs ? $this->quantity * NTAK::DRS_AMOUNT : 0);
    }
}
