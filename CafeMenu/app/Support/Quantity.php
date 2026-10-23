<?php

namespace App\Support;

use App\Models\InventoryUnit;

/**
 * Arithmetic on inventory quantities, which are stored as decimal(14,4)
 * strings (Eloquent's decimal cast returns them as strings specifically to
 * avoid float imprecision). Every operation goes through bcmath at a fixed
 * scale so repeated additions/conversions never drift — mirrors Money's
 * role for currency, but for stock.
 */
class Quantity
{
    private const SCALE = 4;

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function subtract(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function multiply(string $a, string $b): string
    {
        return bcmul($a, $b, self::SCALE);
    }

    public static function divide(string $a, string $b): string
    {
        return bcdiv($a, $b, self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isNegative(string $a): bool
    {
        return self::compare($a, '0') < 0;
    }

    /**
     * Converts a quantity from one unit to another of the SAME type (mass,
     * volume, or count) via each unit's base_factor. Throws if the types
     * differ — there is no safe automatic conversion between e.g. grams
     * and milliliters without a density, so that must never be silently
     * attempted.
     */
    public static function convert(string $quantity, InventoryUnit $from, InventoryUnit $to): string
    {
        if ($from->type !== $to->type) {
            throw new \InvalidArgumentException(
                "Cannot convert between incompatible unit types \"{$from->type}\" and \"{$to->type}\"."
            );
        }

        if ($from->is($to)) {
            return $quantity;
        }

        $inBaseUnits = self::multiply($quantity, (string) $from->base_factor);

        return self::divide($inBaseUnits, (string) $to->base_factor);
    }
}
