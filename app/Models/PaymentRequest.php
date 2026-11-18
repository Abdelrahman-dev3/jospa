<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A short payment request ("فاتورة مختصرة") sent to a customer as a link.
 * This is a payment request, not a ZATCA tax invoice.
 */
class PaymentRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_CANCELLED];

    protected $table = 'payment_requests';

    protected $fillable = [
        'number',
        'token',
        'customer_name',
        'customer_mobile',
        'description',
        'amount',
        'currency',
        'status',
        'paid_attempt_id',
        'gateway_transaction_id',
        'paid_at',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'odoo_sync_status',
        'odoo_invoice_number',
        'odoo_invoice_pdf',
        'odoo_sync_attempts',
        'odoo_last_error',
        'odoo_last_attempt_at',
        'odoo_synced_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'odoo_sync_attempts' => 'integer',
        'odoo_last_attempt_at' => 'datetime',
        'odoo_synced_at' => 'datetime',
    ];

    public const ODOO_PENDING = 'pending';

    public const ODOO_SENDING = 'sending';

    public const ODOO_SYNCED = 'synced';

    public const ODOO_FAILED = 'failed';

    public function hasOdooInvoicePdf(): bool
    {
        return filled($this->odoo_invoice_pdf);
    }

    protected $hidden = ['token'];

    public function attempts()
    {
        return $this->hasMany(PaymentRequestAttempt::class);
    }

    public function paidAttempt()
    {
        return $this->belongsTo(PaymentRequestAttempt::class, 'paid_attempt_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function publicUrl(): string
    {
        return route('payment-request.show', ['token' => $this->token]);
    }

    /** wa.me link with a prefilled message; the admin sends it manually. */
    public function whatsappUrl(): string
    {
        $text = __('payment_requests.whatsapp_message', [
            'name' => $this->customer_name,
            'number' => $this->number,
            'amount' => number_format((float) $this->amount, 2),
            'url' => $this->publicUrl(),
        ]);

        // Stored as +9665XXXXXXXX; wa.me wants digits only. Without a number WhatsApp asks for the contact.
        $phone = ltrim((string) $this->customer_mobile, '+');

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }
}
