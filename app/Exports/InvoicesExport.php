<?php

namespace App\Exports;

use App\Models\GiftCard;
use App\Models\Invoice;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithProperties;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\BookingService;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoicesExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function headings(): array
    {
        return [
            '#',
            'رقم الفاتورة',
            'اسم العميل',
            'جوال العميل',
            'تاريخ الفاتورة',
            'طريقة الدفع',
            'كود الكوبون',
            'خصم الكوبون',
            'خصم نقاط الولاء',
            'خصم بوابة الدفع',
            'خصم الفاتورة',
            'ضريبة / خدمة',
            'الإجمالي النهائي',
            'نوع الفاتورة',
            'عدد فواتير العميل',
        ];
    }

    public function collection()
    {
        $query = Invoice::query()->with('user');

        // فلتر رقم الفاتورة
        if (!empty($this->filters['invoice_id'])) {
            $query->where('id', $this->filters['invoice_id']);
        }

        // فلتر اسم العميل
        if (!empty($this->filters['customer_name'])) {
            $search = '%' . $this->filters['customer_name'] . '%';
            $query->whereHas('user', fn($q) =>
                $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$search])
            );
        }

        // فلتر الجوال
        if (!empty($this->filters['mobile'])) {
            $mobile = $this->filters['mobile'];
            $mobile = strtr($mobile, [
                '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
                '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
                '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
                '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            ]);
            $query->whereHas('user', fn($q) =>
                $q->where('mobile', 'like', '%' . $mobile . '%')
            );
        }

        // فلتر تاريخ محدد
        if (!empty($this->filters['date'])) {
            $query->whereDate('created_at', $this->filters['date']);
        }

        // فلتر من تاريخ
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }

        // فلتر إلى تاريخ
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        // فلتر طريقة الدفع
        if (!empty($this->filters['payment_method'])) {
            $pm = $this->filters['payment_method'];
            $query->where('payment_method', 'like', '%' . $pm . '%');
        }

        // فلتر كود الكوبون (أي فاتورة لها كوبون)
        if (!empty($this->filters['has_coupon'])) {
            $query->whereNotNull('coupon_code')->where('coupon_code', '!=', '');
        }

        // فلتر الحد الأدنى للإجمالي
        if (!empty($this->filters['min_total'])) {
            $query->where('final_total', '>=', (float) $this->filters['min_total']);
        }

        // فلتر الحد الأقصى للإجمالي
        if (!empty($this->filters['max_total'])) {
            $query->where('final_total', '<=', (float) $this->filters['max_total']);
        }

        // فلتر الحد الأدنى للخصم
        if (!empty($this->filters['min_discount'])) {
            $query->where('discount_amount', '>=', (float) $this->filters['min_discount']);
        }

        // فلتر نوع الفاتورة
        if (!empty($this->filters['invoice_type'])) {
            switch ($this->filters['invoice_type']) {
                case 'booking':
                    $query->whereNotNull('cart_ids')
                          ->where('cart_ids', '!=', '[]')
                          ->where('cart_ids', '!=', 'null');
                    break;
                case 'gift':
                    $query->whereNotNull('gift_ids')
                          ->where('gift_ids', '!=', '[]')
                          ->where('gift_ids', '!=', 'null');
                    break;
                case 'product':
                    $query->whereNotNull('product_ids')
                          ->where('product_ids', '!=', '[]')
                          ->where('product_ids', '!=', 'null');
                    break;
            }
        }

        // فلتر العملاء الذين لديهم أكثر من عدد معين من الفواتير
        if (!empty($this->filters['min_invoices_count'])) {
            $minCount = (int) $this->filters['min_invoices_count'];
            // نحصل على user_ids الذين لديهم >= minCount فواتير
            $userIds = Invoice::select('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) >= ?', [$minCount])
                ->pluck('user_id');
            $query->whereIn('user_id', $userIds);
        }

        // الترتيب
        $sortBy = $this->filters['sort_by'] ?? 'created_at';
        $sortDir = $this->filters['sort_dir'] ?? 'desc';
        $allowedSorts = ['id', 'created_at', 'final_total', 'discount_amount'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        $sortDir = in_array(strtolower($sortDir), ['asc', 'desc']) ? strtolower($sortDir) : 'desc';

        $invoices = $query->orderBy($sortBy, $sortDir)->get();

        // احسب عدد فواتير كل عميل دفعة واحدة
        $userInvoiceCount = Invoice::select('user_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $rows = collect();
        $rowNum = 1;

        foreach ($invoices as $invoice) {
            // نوع الفاتورة
            $types = [];
            $cartIds = is_array($invoice->cart_ids) ? $invoice->cart_ids : (json_decode($invoice->cart_ids, true) ?? []);
            $giftIds = is_array($invoice->gift_ids) ? $invoice->gift_ids : (json_decode($invoice->gift_ids ?? '[]', true) ?? []);
            $productIds = is_array($invoice->product_ids) ? $invoice->product_ids : (json_decode($invoice->product_ids ?? '[]', true) ?? []);

            if (!empty($cartIds)) $types[] = 'حجوزات';
            if (!empty($giftIds)) $types[] = 'بطاقات هدايا';
            if (!empty($productIds)) $types[] = 'منتجات';
            $typeLabel = implode(' + ', $types) ?: 'غير محدد';

            $paymentMethod = $invoice->payment_method;
            if (!$paymentMethod && !empty($cartIds)) {
                // محاولة الحصول من BookingTransaction
                $tx = \Modules\Booking\Models\BookingTransaction::where('invoice_id', $invoice->id)->first();
                $paymentMethod = $tx->transaction_type ?? '-';
            }

            $rows->push([
                $rowNum++,
                'INV-' . $invoice->id,
                optional($invoice->user)->first_name . ' ' . optional($invoice->user)->last_name,
                optional($invoice->user)->mobile ?? '-',
                $invoice->created_at ? $invoice->created_at->format('Y-m-d H:i') : '-',
                $paymentMethod ?? '-',
                $invoice->coupon_code ?? '-',
                number_format((float)($invoice->coupon_discount_amount ?? 0), 2),
                number_format((float)($invoice->loyalty_points_discount ?? 0), 2),
                number_format((float)($invoice->payment_gateway_discount_amount ?? 0), 2),
                number_format((float)($invoice->discount_amount ?? 0), 2),
                number_format((float)($invoice->taxs_service ?? 0), 2),
                number_format((float)($invoice->final_total ?? 0), 2),
                $typeLabel,
                $userInvoiceCount[$invoice->user_id] ?? 1,
            ]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();

        // تنسيق رأس الجدول
        $sheet->getStyle('A1:' . $highestCol . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        // تنسيق الصفوف (تلوين بالتناوب)
        for ($row = 2; $row <= $highestRow; $row++) {
            $bgColor = ($row % 2 === 0) ? 'F0FAFA' : 'FFFFFF';
            $sheet->getStyle("A{$row}:{$highestCol}{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $bgColor],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        // حدود لكل الخلايا
        $sheet->getStyle("A1:{$highestCol}{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1E8E8'],
                ],
            ],
        ]);

        // تثبيت الرأس
        $sheet->freezePane('A2');

        // ارتفاع الصفوف
        for ($row = 1; $row <= $highestRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight($row === 1 ? 30 : 22);
        }

        // إعداد الصفحة للطباعة
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        // الاتجاه RTL (عربي)
        $sheet->setRightToLeft(true);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // #
            'B' => 14,  // رقم الفاتورة
            'C' => 22,  // اسم العميل
            'D' => 16,  // جوال العميل
            'E' => 18,  // تاريخ الفاتورة
            'F' => 14,  // طريقة الدفع
            'G' => 14,  // كود الكوبون
            'H' => 14,  // خصم الكوبون
            'I' => 18,  // خصم نقاط الولاء
            'J' => 18,  // خصم بوابة الدفع
            'K' => 14,  // خصم الفاتورة
            'L' => 14,  // ضريبة/خدمة
            'M' => 16,  // الإجمالي النهائي
            'N' => 18,  // نوع الفاتورة
            'O' => 16,  // عدد فواتير العميل
        ];
    }
}

