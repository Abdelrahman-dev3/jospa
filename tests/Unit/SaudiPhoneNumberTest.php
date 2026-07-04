<?php

namespace Tests\Unit;

use App\Support\SaudiPhoneNumber;
use PHPUnit\Framework\TestCase;

class SaudiPhoneNumberTest extends TestCase
{
    public function test_it_normalizes_legacy_saudi_mobile_numbers_to_international_format(): void
    {
        $this->assertSame('+966555123456', SaudiPhoneNumber::normalize('0555123456'));
        $this->assertSame('+966555123456', SaudiPhoneNumber::normalize('966555123456'));
        $this->assertSame('+966555123456', SaudiPhoneNumber::normalize('+966 55 512 3456'));
    }

    public function test_it_normalizes_generic_international_numbers(): void
    {
        $this->assertSame('+201234567890', SaudiPhoneNumber::normalize('+20 123 456 7890'));
        $this->assertSame('+201234567890', SaudiPhoneNumber::normalize('00201234567890'));
        $this->assertSame('+12025550143', SaudiPhoneNumber::normalize('12025550143'));
    }

    public function test_it_builds_lookup_candidates_for_legacy_and_international_formats(): void
    {
        $this->assertSame([
            '+966555123456',
            '966555123456',
            '00966555123456',
            '0555123456',
            '555123456',
        ], SaudiPhoneNumber::lookupDigits('0555123456'));

        $this->assertSame([
            '+201234567890',
            '201234567890',
            '00201234567890',
        ], SaudiPhoneNumber::lookupDigits('+20 123 456 7890'));
    }
}
