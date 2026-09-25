<?php

namespace QueSoft\Ntak\Enums;

use QueSoft\Ntak\Traits\EnumHelpers;

enum NTAKOrderType: string
{
    use EnumHelpers;

    case NORMAL = 'Normál';
    case SZTORNO = 'Storno';
    case HELYESBITO = 'Helyesbítő';
}
