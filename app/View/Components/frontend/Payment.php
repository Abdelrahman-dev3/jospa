<?php

namespace App\View\Components\frontend;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Models\Setting;

class Payment extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public int $itemsCount = 0,
        public float $totalPrice = 0,
        public string $pageName = 'cart',
        public float $productsAmount = 0,
        public float $wallet = 0,
        public float $loyaltyBalance = 0,
        public $branches = [],
        public ?array $paymentMethods = null,
        public ?string $defaultPaymentMethod = null
    )
    {
        if ($this->paymentMethods === null) {
            $this->paymentMethods = [
                'card' => (int) Setting::get('tap_payment_method', 1),
                'tabby' => (int) Setting::get('tabby_payment_method', 1),
                'tamara' => (int) Setting::get('tamara_payment_method', 1),
            ];
        }

        if ($this->defaultPaymentMethod === null) {
            $this->defaultPaymentMethod = 'card';
            if (($this->paymentMethods['card'] ?? 0) !== 1) {
                $this->defaultPaymentMethod = null;
                foreach (['tabby', 'tamara'] as $method) {
                    if (($this->paymentMethods[$method] ?? 0) === 1) {
                        $this->defaultPaymentMethod = $method;
                        break;
                    }
                }
                $this->defaultPaymentMethod = $this->defaultPaymentMethod ?? 'card';
            }
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.frontend.payment');
    }
}
