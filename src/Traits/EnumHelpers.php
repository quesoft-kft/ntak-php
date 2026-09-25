<?php

namespace QueSoft\Ntak\Traits;

use InvalidArgumentException;

trait EnumHelpers
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function array(): array
    {
        return array_combine(self::values(), self::names());
    }

    public static function random(): static
    {
        $cases = self::cases();

        return $cases[array_rand($cases)];
    }

    public static function fromName(string $name): static
    {
        foreach (self::cases() as $case) {
            if ($name === $case->name) {
                return $case;
            }
        }

        throw new InvalidArgumentException(
            "$name is not a valid name for enum ".static::class
        );
    }
}
