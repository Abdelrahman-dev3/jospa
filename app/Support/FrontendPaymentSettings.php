<?php

namespace App\Support;

use App\Models\Setting;

class FrontendPaymentSettings
{
    public static function paymentMethods(): array
    {
        return [
            'card' => (int) Setting::get('tap_payment_method', 1),
            'urpay' => (int) Setting::get('urpay_payment_method', 0),
            'tabby' => (int) Setting::get('tabby_payment_method', 1),
            'tamara' => (int) Setting::get('tamara_payment_method', 1),
        ];
    }

    public static function gatewayDiscounts(): array
    {
        return [
            'card' => self::gatewayDiscountConfig('card'),
            'urpay' => self::gatewayDiscountConfig('urpay'),
            'tabby' => self::gatewayDiscountConfig('tabby'),
            'tamara' => self::gatewayDiscountConfig('tamara'),
        ];
    }

    public static function paymentGatewayDiscountAmount(?string $method, float $baseAmount): float
    {
        $normalizedMethod = self::normalizePaymentMethod($method);

        if (! $normalizedMethod || $baseAmount <= 0) {
            return 0.0;
        }

        $config = self::gatewayDiscounts()[$normalizedMethod] ?? null;
        if (! is_array($config)) {
            return 0.0;
        }

        $value = max((float) ($config['value'] ?? 0), 0);
        $type = (string) ($config['type'] ?? 'fixed');

        if ($type === 'percent') {
            $value = min($value, 100);

            return max(($baseAmount * $value) / 100, 0);
        }

        return min($value, $baseAmount);
    }

    public static function paymentGatewayDiscountLabel(?string $method): ?string
    {
        return match (self::normalizePaymentMethod($method)) {
            'card' => 'Hyperpay',
            'urpay' => 'UrPay',
            'tabby' => 'Tabby',
            'tamara' => 'Tamara',
            default => null,
        };
    }

    public static function tapPaymentSources(): array
    {
        return [
            'src_card' => (int) Setting::get('tap_card_payment_method', 1),
            'src_apple_pay' => (int) Setting::get('tap_apple_pay_payment_method', 1),
            'src_sa.mada' => (int) Setting::get('tap_mada_payment_method', 1),
        ];
    }

    public static function defaultPaymentMethod(?array $paymentMethods = null): ?string
    {
        $paymentMethods ??= self::paymentMethods();

        foreach (['card', 'urpay', 'tabby', 'tamara'] as $method) {
            if (($paymentMethods[$method] ?? 0) === 1) {
                return $method;
            }
        }

        return null;
    }

    public static function defaultTapPaymentSource(?array $tapPaymentSources = null): ?string
    {
        $tapPaymentSources ??= self::tapPaymentSources();

        foreach (['src_card', 'src_apple_pay', 'src_sa.mada'] as $source) {
            if (($tapPaymentSources[$source] ?? 0) === 1) {
                return $source;
            }
        }

        return null;
    }

    public static function isEnabledTapSource(?string $source): bool
    {
        if (! $source) {
            return false;
        }

        return (self::tapPaymentSources()[$source] ?? 0) === 1;
    }

    private static function hasEnabledTapSource(array $tapSources): bool
    {
        foreach ($tapSources as $enabled) {
            if ((int) $enabled === 1) {
                return true;
            }
        }

        return false;
    }

    private static function normalizePaymentMethod(?string $method): ?string
    {
        return match ($method) {
            'tap', 'card' => 'card',
            'urpay', 'stcpay' => 'urpay',
            'tabby' => 'tabby',
            'tamara' => 'tamara',
            default => null,
        };
    }

    private static function gatewayDiscountConfig(string $method): array
    {
        $config = match ($method) {
            'card' => [
                'type' => self::normalizeDiscountType(Setting::get('tap_payment_discount_type', 'fixed')),
                'value' => self::normalizeDiscountValue(Setting::get('tap_payment_discount_amount', 0)),
                'label' => 'Hyperpay',
            ],
            'urpay' => [
                'type' => self::normalizeDiscountType(Setting::get('urpay_payment_discount_type', 'fixed')),
                'value' => self::normalizeDiscountValue(Setting::get('urpay_payment_discount_amount', 0)),
                'label' => 'UrPay',
            ],
            'tabby' => [
                'type' => self::normalizeDiscountType(Setting::get('tabby_payment_discount_type', 'fixed')),
                'value' => self::normalizeDiscountValue(Setting::get('tabby_payment_discount_amount', 0)),
                'label' => 'Tabby',
            ],
            'tamara' => [
                'type' => self::normalizeDiscountType(Setting::get('tamara_payment_discount_type', 'fixed')),
                'value' => self::normalizeDiscountValue(Setting::get('tamara_payment_discount_amount', 0)),
                'label' => 'Tamara',
            ],
        };

        if (($config['type'] ?? 'fixed') === 'percent') {
            $config['value'] = min((float) ($config['value'] ?? 0), 100);
        }

        return $config;
    }

    private static function normalizeDiscountType(?string $type): string
    {
        return $type === 'percent' ? 'percent' : 'fixed';
    }

    private static function normalizeDiscountValue($value): float
    {
        return max((float) $value, 0);
    }
}
