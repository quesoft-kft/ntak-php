<?php

namespace QueSoft\Ntak\Enums;

use QueSoft\Ntak\Traits\EnumHelpers;

enum NTAKCategory: string
{
    use EnumHelpers;

    case ETEL = 'Étel';
    case ALKMENTESITAL_HELYBEN = 'Helyben készített alkoholmentes ital';
    case ALKMENTESITAL_NEM_HELYBEN = 'Nem helyben készített alkoholmentes ital';
    case ALKOHOLOSITAL = 'Alkoholos Ital';
    case EGYEB = 'Egyéb';

    /**
     * @return list<NTAKSubcategory>
     */
    public function subcategories(): array
    {
        return match ($this) {
            self::ETEL => [
                NTAKSubcategory::REGGELI,
                NTAKSubcategory::SZENDVICS,
                NTAKSubcategory::ELOETEL,
                NTAKSubcategory::LEVES,
                NTAKSubcategory::FOETEL,
                NTAKSubcategory::KORET,
                NTAKSubcategory::SAVANYUSAG_SALATA,
                NTAKSubcategory::KOSTOLO,
                NTAKSubcategory::PEKSUTEMENY,
                NTAKSubcategory::DESSZERT,
                NTAKSubcategory::SNACK,
                NTAKSubcategory::FOETEL_KORETTEL,
                NTAKSubcategory::ETELCSOMAG,
                NTAKSubcategory::EGYEB,
            ],
            self::ALKMENTESITAL_HELYBEN => [
                NTAKSubcategory::VIZ,
                NTAKSubcategory::LIMONADE_SZORP_FACSART,
                NTAKSubcategory::ALKOHOLMENTES_KOKTEL,
                NTAKSubcategory::TEA_FORROCSOKOLADE,
                NTAKSubcategory::ITALCSOMAG,
                NTAKSubcategory::KAVE,
            ],
            self::ALKMENTESITAL_NEM_HELYBEN => [
                NTAKSubcategory::VIZ,
                NTAKSubcategory::ROSTOS_UDITO,
                NTAKSubcategory::SZENSAVAS_UDITO,
                NTAKSubcategory::SZENSAVMENTES_UDITO,
                NTAKSubcategory::ITALCSOMAG,
            ],
            self::ALKOHOLOSITAL => [
                NTAKSubcategory::KOKTEL,
                NTAKSubcategory::LIKOR,
                NTAKSubcategory::PARLAT,
                NTAKSubcategory::SOR,
                NTAKSubcategory::BOR,
                NTAKSubcategory::PEZSGO,
                NTAKSubcategory::ITALCSOMAG,
            ],
            self::EGYEB => [
                NTAKSubcategory::EGYEB,
                NTAKSubcategory::SZERVIZDIJ,
                NTAKSubcategory::BORRAVALO,
                NTAKSubcategory::KISZALLITASI_DIJ,
                NTAKSubcategory::NEM_VENDEGLATAS,
                NTAKSubcategory::KORNYEZETBARAT_CSOMAGOLAS,
                NTAKSubcategory::MUANYAG_CSOMAGOLAS,
                NTAKSubcategory::KEDVEZMENY,
            ],
        };
    }

    public function hasSubcategory(NTAKSubcategory $subcategory): bool
    {
        return in_array($subcategory, $this->subcategories(), true);
    }
}
