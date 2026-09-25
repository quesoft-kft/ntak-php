# Changelog

All notable changes to this package are documented in this file.

## 1.4.0

PHP 8.4+ modernization. **This is a breaking release** relative to 1.x builds that still targeted PHP 7 / MyCLabs enums.

### Upgrade notes — mire kell figyelni

Telepítés előtt nézd át a fogyasztó kódot. Az alábbiak **szétesést okoznak**, ha változatlanul hagyod őket.

#### 1. PHP minimum: `^8.4`

- Composer **nem telepíti** 8.3-ra vagy régebbire.
- 8.4 és 8.5 támogatott.

```bash
composer require quesoft/ntak-php:^1.4
```

#### 2. Enum hívások — zárójel nélkül

MyCLabs `Enum::CASE()` helyett natív backed enum:

```php
// ❌ régi (elszáll 1.4-től)
NTAKOrderType::NORMAL()
NTAKCategory::ETEL()
NTAKVat::C_27()
NTAKDayType::NORMAL_NAP()
NTAKPaymentType::BANKKARTYA()
NTAKAggregateCause::UZEMZAVAR_MUSZAKI_HIBA_MIATT()

// ✅ új
NTAKOrderType::NORMAL
NTAKCategory::ETEL
NTAKVat::C_27
NTAKDayType::NORMAL_NAP
NTAKPaymentType::BANKKARTYA
NTAKAggregateCause::UZEMZAVAR_MUSZAKI_HIBA_MIATT
```

Gyors keresés a projektben: `NTAK\w+::[A-Z0-9_]+\(`

#### 3. `getKey()` → `->name`

```php
// ❌ régi
$orderType->getKey()
$category->getKey()

// ✅ új
$orderType->name
$category->name
```

A payload továbbra is a **case name**-et küldi (pl. `NORMAL`, `ETEL`), nem a magyar value-t.

#### 4. Enum példány value-ból

```php
// ❌ régi
new NTAKVerifyStatus($response['statusz'])

// ✅ új
NTAKVerifyStatus::from($response['statusz'])
// vagy név alapján:
NTAKVerifyStatus::fromName('TELJESEN_SIKERES')
```

#### 5. Egyéb API változások, amik érinthetik a hívó kódot

| Terület | Régi | 1.4 |
| --- | --- | --- |
| Ár (`NTAKOrderItem::$price`) | tipikusan `int` | `float` |
| Fizetés összege | tipikusan `int` | `float` |
| Rendelés végösszeg a payloadban | tételből számolt total | `paymentsTotal()` (fizetések összege) |
| `NTAKVerifyResponse` | nincs `processId` | kötelező `processId` property |
| `SZENSAVMENTES_UDITO` value | trailing space volt | `'szénsavmentes üdítő'` (javítva) |
| Függőségek | Guzzle 6 / Carbon 2 / myclabs | Guzzle ^7.10 / Carbon ^3 / natív enum |

#### 6. Ami megmaradt (QueSoft fork)

Ezek továbbra is elérhetők, nem kell átírni a koncepciót:

- `aggregated` / `aggregatedCause`
- `tip_amount` (BORRAVALO sor)
- `resendMessage()`

Új, opcionális mezők (defaulttal nem törik a régi hívásokat, ha named/positional defaultok stimmelnek):

- `isDrs`, `bypassServiceFee`, tételszintű `discount`
- `verifyAll()`

### Added
- Natív PHP backed enumok (`fromName()`, `names()`, `values()`, `array()`, `random()`)
- DRS támogatás (`isDrs`, automatikus DRS sor, `NTAK::DRS_AMOUNT`)
- Tételszintű kedvezmény és `bypassServiceFee`
- `verifyAll()` több process ID-hez
- `NTAKVerifyResponse::$processId`
- Guzzle `timeout` / `connect_timeout`
- Pricing unit tesztek (upstream alapján)
- `NTAKAmount::ADAG`

### Changed
- Minimum PHP: **^8.4** (kompatibilis 8.5-tel)
- Carbon ^3, Guzzle ^7.10, PHPUnit ^11.5
- Készpénz-kerekítés `KESZPENZEUR`-re is
- Kedvezmény / szervizdíj kerekítés upstream szerint
- Tesztek: `tests/` + `phpunit.xml.dist`

### Removed
- `myclabs/php-enum`
- `tightenco/collect`
- PHP 7 támogatás
