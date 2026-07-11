<?php

namespace App\Support;

class SaudiPhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        $phone = self::normalizeDigits(trim((string) $phone));

        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (blank($digits)) {
            return null;
        }

        if (str_starts_with($phone, '+')) {
            return self::normalizeInternational($digits);
        }

        if (str_starts_with($digits, '00')) {
            return self::normalizeInternational(substr($digits, 2));
        }

        if (preg_match('/^05\d{8}$/', $digits)) {
            return '+966'.substr($digits, 1);
        }

        if (preg_match('/^5\d{8}$/', $digits)) {
            return '+966'.$digits;
        }

        if (preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            return '+'.$digits;
        }

        return null;
    }

    public static function lookupDigits(?string $phone): array
    {
        $normalized = self::normalize($phone);

        if (! $normalized) {
            return [];
        }

        $digits = ltrim($normalized, '+');

        $candidates = [
            $normalized,
            $digits,
            '00'.$digits,
        ];

        if (str_starts_with($digits, '9665') && strlen($digits) === 12) {
            $localDigits = '0'.substr($digits, 3);
            $candidates[] = $localDigits;
            $candidates[] = substr($localDigits, 1);
        }

        return array_values(array_unique($candidates));
    }

    private static function normalizeInternational(string $digits): ?string
    {
        return preg_match('/^[1-9]\d{7,14}$/', $digits) ? '+'.$digits : null;
    }

    private static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);
    }
}
