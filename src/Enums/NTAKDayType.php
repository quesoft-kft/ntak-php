<?php

namespace QueSoft\Ntak\Enums;

use QueSoft\Ntak\Traits\EnumHelpers;

enum NTAKDayType: string
{
    use EnumHelpers;

    case ADOTT_NAPON_ZARVA = 'Adott napon zárva';
    case FORGALOM_NELKULI_NAP = 'Forgalom nélküli nap';
    case NORMAL_NAP = 'Normál nap';
}
