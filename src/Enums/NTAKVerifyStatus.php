<?php

namespace QueSoft\Ntak\Enums;

use QueSoft\Ntak\Traits\EnumHelpers;

enum NTAKVerifyStatus: string
{
    use EnumHelpers;

    case BEFOGADVA = 'BEFOGADVA';
    case TELJESEN_HIBAS = 'TELJESEN_HIBAS';
    case RESZBEN_SIKERES = 'RESZBEN_SIKERES';
    case TELJESEN_SIKERES = 'TELJESEN_SIKERES';
    case UJRA_KULDENDO = 'UJRA_KULDENDO';
}
