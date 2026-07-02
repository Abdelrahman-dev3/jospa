<?php

namespace App\Support;

class SaudiPhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (blank($digits)) {
            return null;
        }

        if (str_starts_with($digits, '00966')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }

        if (preg_match('/^5\d{8}$/', $digits)) {
            $digits = '0'.$digits;
        }

        return preg_match('/^05\d{8}$/', $digits) ? $digits : null;
    }

    public static function lookupDigits(?string $phone): array
    {
        $normalized = self::normalize($phone);

        if (! $normalized) {
            return [];
        }

        $withoutLeadingZero = substr($normalized, 1);

        return array_values(array_unique([
            $normalized,
            $withoutLeadingZero,
            '966'.$withoutLeadingZero,
            '00966'.$withoutLeadingZero,
        ]));
    }
}
