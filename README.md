# NTAK RMS PHP API / SDK — v1.4.1 (PHP 8.4+)

Fork of [kiralyta/ntak-php](https://github.com/kiralyta/ntak-php) with QueSoft extensions (aggregated orders, tip handling, message resend) and PHP 8.4 / 8.5 support.

Requires **PHP 8.4 or newer** (compatible with PHP 8.5).

## Installation

```bash
composer require quesoft/ntak-php:^1.4
```

## Upgrade to 1.4

**Breaking release (1.4.0).** A fogyasztó kódban tipikusan át kell írni az enum hívásokat (`::CASE()` → `::CASE`), a `getKey()` hívásokat (`->name`), és a `new Enum($value)` konstruktorokat (`Enum::from()`).

Részletes checklist: **[CHANGELOG.md — Upgrade notes](CHANGELOG.md#upgrade-notes--mire-kell-figyelni)**.

**1.4.1:** a `targynap` mezőt a lib `Europe/Budapest` szerint formázza (Carbon 3 UTC elcsúszás ellen). Fogyasztói oldalon is ajánlott: `Carbon::createFromTimestamp($ts, 'Europe/Budapest')`.

## Usage

### Create an API client

```php
use QueSoft\Ntak\NTAKClient;

$client = new NTAKClient(
    taxNumber: 'NTAK client tax nr',         // without `-` chars
    regNumber: 'NTAK client registration nr',
    softwareRegNumber: 'NTAK RMS registration id',
    version: 'NTAK RMS version',
    certPath: '/path/to/your.pem',
    testing: false                           // hit the test NTAK API when true
);
```

> Your `.pem` file is a concatenation of your `.cer` and `.key` files.
> Prefer a singleton `NTAKClient` per request cycle.

```php
$client->lastRequest();     // array|null
$client->lastResponse();    // array|null
$client->lastRequestTime(); // int|null (ms)
```

### Order item

```php
use Carbon\Carbon;
use QueSoft\Ntak\Enums\NTAKAmount;
use QueSoft\Ntak\Enums\NTAKCategory;
use QueSoft\Ntak\Enums\NTAKSubcategory;
use QueSoft\Ntak\Enums\NTAKVat;
use QueSoft\Ntak\Models\NTAKOrderItem;

$orderItem = new NTAKOrderItem(
    name: 'Absolut Vodka',
    category: NTAKCategory::ALKOHOLOSITAL,
    subcategory: NTAKSubcategory::PARLAT,
    vat: NTAKVat::C_27,
    price: 1000,
    amountType: NTAKAmount::LITER,
    amount: 0.04,
    quantity: 2,
    when: Carbon::now(),
    isDrs: false,            // DRS deposit (50 Ft) when true
    bypassServiceFee: false, // exclude from service fee base
    discount: null,          // item-level %; null → inherit order discount
);
```

### Payment

```php
use QueSoft\Ntak\Enums\NTAKPaymentType;
use QueSoft\Ntak\Models\NTAKPayment;

$payment = new NTAKPayment(
    paymentType: NTAKPaymentType::BANKKARTYA,
    total: 2000
);
```

Cash (`KESZPENZHUF` / `KESZPENZEUR`) is rounded to 5 Ft; the difference is sent as `KEREKITES`.

### Order

```php
use Carbon\Carbon;
use QueSoft\Ntak\Enums\NTAKAggregateCause;
use QueSoft\Ntak\Enums\NTAKOrderType;
use QueSoft\Ntak\Models\NTAKOrder;

$order = new NTAKOrder(
    orderType: NTAKOrderType::NORMAL,
    orderId: 'your-rms-order-id',
    orderItems: [$orderItem],
    start: Carbon::now()->subMinutes(7),
    end: Carbon::now(),
    payments: [$payment],
    isAtTheSpot: true,
    discount: 20,      // order-level %
    serviceFee: 10,    // % of discounted total (respects bypassServiceFee)
    ntakOrderId: null, // required for update / storno
    aggregated: false,
    aggregatedCause: NTAKAggregateCause::UZEMSZUNET_ARAMSZOLGALTATAS_TERVEZETT_KIMARADASA_MIATT,
    tip_amount: 0,     // added as BORRAVALO line
);
```

Pricing notes (aligned with upstream):

- Line totals, discounts and service fees are rounded per VAT group.
- DRS is emitted as a separate `E_0` / `KORNYEZETBARAT_CSOMAGOLAS` line.
- Item-level `discount` overrides the order-level rate for that item (including its DRS).
- `rendelesVegosszegeHUF` is the sum of payments (`paymentsTotal()`).

### Store / update / destroy order

```php
use Carbon\Carbon;
use QueSoft\Ntak\NTAK;

$processId = NTAK::message($client, Carbon::now())
    ->handleOrder($order);
```

### Resend message

```php
$processId = NTAK::message(
    $client,
    Carbon::parse($lastSentRequestData['uzenetAdatok']['uzenetKuldesIdeje'])
)->resendMessage(
    ['zarasiInformaciok' => $lastSentRequestData['zarasiInformaciok']],
    order: false // true = rendeles-osszesito, false = napi-zaras
);
```

### Close day

```php
use QueSoft\Ntak\Enums\NTAKDayType;

$processId = NTAK::message($client, Carbon::now())->closeDay(
    start: Carbon::now()->subHours(10),
    end: Carbon::now(),
    dayType: NTAKDayType::NORMAL_NAP,
    tips: 1000
);
```

### Verify

```php
$response = NTAK::message($client, Carbon::now())
    ->verify(processId: 'NTAK Process ID');

$responses = NTAK::message($client, Carbon::now())
    ->verifyAll(['id-1', 'id-2']);

$response->successful();
$response->unsuccessful();
$response->status;
$response->processId;
$response->successfulMessages;
$response->unsuccessfulMessages;
$response->headerErrors;
```

## Enums

Namespace: `QueSoft\Ntak\Enums`

Helpers on each enum: `cases()`, `names()`, `values()`, `array()`, `random()`, `fromName()`, `from()`.

### NTAKAmount

| name | value |
| --- | --- |
| DARAB | darab |
| LITER | liter |
| KILOGRAMM | kilogramm |
| EGYSEG | egyseg |
| ADAG | adag |

### NTAKCategory / NTAKSubcategory / NTAKDayType / NTAKOrderType / NTAKPaymentType / NTAKVat / NTAKVerifyStatus / NTAKAggregateCause

See source under `src/Enums/` for the full case lists (including aggregated-order causes kept from this fork).

## Development

```bash
composer install
composer test
```

Unit / pricing tests do not need certificates. Integration against the live test API still needs `auth/pem.pem`.

## License

MIT
