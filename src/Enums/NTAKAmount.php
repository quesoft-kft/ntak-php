<?php

namespace QueSoft\Ntak\Enums;

use QueSoft\Ntak\Traits\EnumHelpers;

enum NTAKAmount: string
{
    use EnumHelpers;

    case DARAB = 'darab';
    case LITER = 'liter';
    case KILOGRAMM = 'kilogramm';
    case EGYSEG = 'egyseg';
    case ADAG = 'adag';
}
