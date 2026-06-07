<?php

namespace App\Support;

use App\Models\Setting;

class FrontendPaymentSettings
{
    public static function paymentMethods(): array
    {
        $tapSources = self::tapPaymentSources();

        return [
            'card' => (int) (Setting::get('tap_payment_method', 1) && self::hasEnabledTapSource($tapSources)),
            'urpay' => (int) Setting::get('urpay_payment_method', 0),
            'tabby' => (int) Setting::get('tabby_payment_method', 1),
            'tamara' => (int) Setting::get('tamara_payment_method', 1),
        ];
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
}
