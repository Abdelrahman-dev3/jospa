<?php

namespace App\View\Components\frontend;

use Closure;
use App\Support\FrontendPaymentSettings;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

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
        public ?array $gatewayDiscounts = null,
        public ?array $tapPaymentSources = null,
        public ?string $defaultPaymentMethod = null,
        public ?string $defaultPaymentSource = null
    )
    {
        if ($this->paymentMethods === null) {
            $this->paymentMethods = FrontendPaymentSettings::paymentMethods();
        }

        if ($this->gatewayDiscounts === null) {
            $this->gatewayDiscounts = FrontendPaymentSettings::gatewayDiscounts();
        }

        if ($this->tapPaymentSources === null) {
            $this->tapPaymentSources = FrontendPaymentSettings::tapPaymentSources();
        }

        if ($this->defaultPaymentMethod === null) {
            $this->defaultPaymentMethod = FrontendPaymentSettings::defaultPaymentMethod($this->paymentMethods);
        }

        if ($this->defaultPaymentSource === null) {
            $this->defaultPaymentSource = FrontendPaymentSettings::defaultTapPaymentSource($this->tapPaymentSources);
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
