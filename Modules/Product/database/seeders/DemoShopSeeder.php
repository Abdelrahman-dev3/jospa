<?php

namespace Modules\Product\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;

/**
 * Demo categories + products for previewing the shop page.
 * Safe to re-run (skips records that already exist).
 *
 * php artisan db:seed --class="Modules\Product\database\seeders\DemoShopSeeder"
 */
class DemoShopSeeder extends Seeder
{
    public function run()
    {
        $catalog = [
            ['name' => 'العناية بالشعر', 'color' => [191, 148, 86], 'products' => [
                ['شامبو الأرجان المغذي', 'شامبو طبيعي بزيت الأرجان لشعر لامع وصحي.', 65],
                ['ماسك الكيراتين المرمم', 'ماسك عميق لإصلاح الشعر التالف وتنعيمه.', 95],
                ['زيت الشعر الملكي', 'زيت مغذي يمنع التقصف ويمنح لمعانًا طبيعيًا.', 80],
                ['سيروم تقوية الجذور', 'سيروم خفيف يقلل التساقط ويعزز النمو.', 120],
            ]],
            ['name' => 'العناية بالبشرة', 'color' => [214, 140, 150], 'products' => [
                ['كريم ترطيب مكثف', 'كريم يومي يمنح البشرة ترطيبًا يدوم 24 ساعة.', 110],
                ['سيروم فيتامين سي', 'يوحّد لون البشرة ويمنحها إشراقة صحية.', 150],
                ['غسول الوجه اللطيف', 'غسول خالٍ من الكبريتات مناسب لجميع أنواع البشرة.', 55],
                ['قناع الطين المنقّي', 'قناع أسبوعي لتنقية المسام وتنظيف البشرة.', 70],
            ]],
            ['name' => 'العناية بالأظافر', 'color' => [176, 106, 179], 'products' => [
                ['طلاء أظافر جل - وردي', 'طلاء يدوم طويلًا بلمعة جل دون الحاجة لمصباح.', 35],
                ['مقوي الأظافر', 'يقوي الأظافر الضعيفة ويقلل التقشر.', 45],
                ['زيت الجلد والأظافر', 'زيت مرطب للأظافر والجلد المحيط بها.', 40],
            ]],
            ['name' => 'المكياج', 'color' => [200, 70, 90], 'products' => [
                ['أحمر شفاه مخملي', 'لون غني بملمس مخملي مريح يدوم طوال اليوم.', 60],
                ['كونسيلر عالي التغطية', 'تغطية كاملة بتشطيب طبيعي خفيف.', 75],
                ['باليت ظلال العيون', 'درجات دافئة بتركيبة ناعمة وسهلة المزج.', 130],
                ['ماسكارا كثافة وطول', 'ترفع الرموش وتمنحها كثافة دون تكتل.', 58],
            ]],
            ['name' => 'العطور', 'color' => [70, 110, 150], 'products' => [
                ['عطر العود الفاخر', 'عطر شرقي دافئ بنفحات العود والعنبر.', 280],
                ['بخاخ الجسم المنعش', 'رذاذ منعش بنفحات الزهور والحمضيات.', 85],
                ['مبخرة معطرة', 'مجموعة بخور فاخرة لأجواء مميزة.', 140],
            ]],
            ['name' => 'أطقم الهدايا', 'color' => [60, 140, 110], 'products' => [
                ['طقم سبا منزلي', 'مجموعة متكاملة للاسترخاء والعناية في المنزل.', 220],
                ['صندوق هدية فاخر', 'تشكيلة منتجات مختارة بتغليف أنيق.', 310],
            ]],
        ];

        foreach ($catalog as $c => $row) {
            $category = ProductCategory::firstOrCreate(
                ['name' => $row['name']],
                ['status' => 1]
            );
            $category->forceFill(['status' => 1])->save();

            if (! $category->getFirstMedia('feature_image')) {
                $this->attachImage($category, $row['name'], $row['color'], true);
            }

            foreach ($row['products'] as $i => [$name, $desc, $price]) {
                $existing = Product::where('name', $name)->first();
                if ($existing) {
                    if (! $existing->getFirstMedia('feature_image')) {
                        $this->attachImage($existing, $name, $row['color'], false);
                    }
                    continue;
                }

                $product = Product::create([
                    'name' => $name,
                    'slug' => Str::slug($name) ?: 'demo-' . Str::random(8),
                    'short_description' => $desc,
                    'description' => '<p>' . $desc . '</p>',
                    'min_price' => $price,
                    'max_price' => $price,
                    'discount_value' => 0,
                    'stock_qty' => 100,
                    'status' => 1,
                    'is_featured' => $i === 0 ? 1 : 0,
                    'min_purchase_qty' => 1,
                    'max_purchase_qty' => 10,
                    'has_variation' => 0,
                    'has_warranty' => 0,
                    'total_sale_count' => random_int(0, 60),
                    'reward_points' => 0,
                ]);

                $product->categories()->sync([$category->id]);
                $this->attachImage($product, $name, $row['color'], false);
            }
        }
    }

    /**
     * Generate a simple gradient placeholder image (GD) and attach it as feature_image.
     */
    private function attachImage($model, string $seed, array $rgb, bool $round): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->command?->error('PHP GD extension is not enabled - images skipped.');

            return;
        }

        $size = 600;
        $img = imagecreatetruecolor($size, $size);
        $shift = (crc32($seed) % 50) - 25;

        for ($y = 0; $y < $size; $y++) {
            $t = $y / $size;
            $r = (int) max(0, min(255, $rgb[0] + $shift + 70 * (1 - $t)));
            $g = (int) max(0, min(255, $rgb[1] + $shift + 70 * (1 - $t)));
            $b = (int) max(0, min(255, $rgb[2] + $shift + 70 * (1 - $t)));
            imageline($img, 0, $y, $size, $y, imagecolorallocate($img, $r, $g, $b));
        }

        // soft decorative circles
        imagealphablending($img, true);
        $white = imagecolorallocatealpha($img, 255, 255, 255, 100);
        imagefilledellipse($img, (int) ($size * 0.5), (int) ($size * 0.5), (int) ($size * 0.55), (int) ($size * 0.55), $white);
        imagefilledellipse($img, (int) ($size * 0.5), (int) ($size * 0.5), (int) ($size * 0.3), (int) ($size * 0.3), imagecolorallocatealpha($img, 255, 255, 255, 80));

        $path = tempnam(sys_get_temp_dir(), 'demo') . '.png';
        imagepng($img, $path);
        imagedestroy($img);

        try {
            $model->addMedia($path)->toMediaCollection('feature_image');
        } catch (\Throwable $e) {
            @unlink($path);
            $this->command?->error("Image failed for [{$seed}]: " . $e->getMessage());
        }
    }
}
