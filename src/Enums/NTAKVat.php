<?php

namespace QueSoft\Ntak\Enums;

use QueSoft\Ntak\Traits\EnumHelpers;

enum NTAKVat: string
{
    use EnumHelpers;

    case A_5 = '5%';
    case B_18 = '18%';
    case C_27 = '27%';
    case D_AJT = 'Ajt';
    case E_0 = '0%';
}
