<?php

namespace QueSoft\Ntak\Enums;

use QueSoft\Ntak\Traits\EnumHelpers;

enum NTAKPaymentType: string
{
    use EnumHelpers;

    case KESZPENZHUF = 'Készpénz huf';
    case KESZPENZEUR = 'Készpénz eur';
    case SZEPKARTYA = 'Szépkártya';
    case BANKKARTYA = 'Bankkártya';
    case ATUTALAS = 'Átutalás';
    case EGYEB = 'Egyéb';
    case VOUCHER = 'Voucher';
    case SZOBAHITEL = 'Szobahitel';
    case KEREKITES = 'Kerekítés';
}
