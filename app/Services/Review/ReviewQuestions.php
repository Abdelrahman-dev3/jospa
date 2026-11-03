<?php

namespace App\Services\Review;

/**
 * The three review questions. Rating values: 4 = excellent ... 1 = unsatisfied.
 * Four options per question means a WhatsApp list message (reply buttons max out at 3).
 */
class ReviewQuestions
{
    public const SOURCE_WEBSITE = 'website';
    public const SOURCE_WHATSAPP = 'whatsapp';

    public static function all(): array
    {
        return [
            1 => [
                'field' => 'overall_rating',
                'text' => 'كيف تصفين تجربتكِ بشكل عام في عالم Jo spa؟',
                'options' => [4 => 'استثنائية', 3 => 'رائعة', 2 => 'جيدة', 1 => 'تحتاج إلى تحسين'],
            ],
            2 => [
                'field' => 'specialist_rating',
                'text' => 'كيف تقيّمين أداء الأخصائية واحترافيتها؟',
                'options' => [4 => 'متميزة واحترافية', 3 => 'جيدة جدًا', 2 => 'مقبولة', 1 => 'تحتاج إلى تحسين'],
            ],
            3 => [
                'field' => 'service_rating',
                'text' => 'كيف تقيّمين تجربتكِ مع فريق خدمة العملاء؟',
                'options' => [4 => 'ممتازة', 3 => 'جيدة جدًا', 2 => 'مقبولة', 1 => 'غير مرضية'],
            ],
        ];
    }

    public static function question(int $step): ?array
    {
        return self::all()[$step] ?? null;
    }

    public static function totalSteps(): int
    {
        return count(self::all());
    }

    public static function label(string $field, ?int $value): string
    {
        foreach (self::all() as $question) {
            if ($question['field'] === $field) {
                return $question['options'][$value] ?? '-';
            }
        }

        return '-';
    }
}
