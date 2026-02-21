<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TapPaymentService
{
    protected $baseUrl = "https://api.tap.company/v2/";
    protected $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.tap.secret_key');
    }

    public function createCharge($amount, $customerData, $redirectUrl)
    {
        $payload = [
            "amount" => $amount,
            "currency" => "SAR",
            "statement_descriptor" => "JOSPA Store",
            "customer" => [
                "first_name" => $customerData['name'],
                "phone"      => [
                    "country_code" => $customerData['country_code'],
                    "number"       => $customerData['phone']
                ]
            ],
            "source" => [
                "id" => $customerData['method']
            ],
            
            "redirect" => [
                "url" => $redirectUrl
            ]
        ];

        $response = Http::withToken($this->secretKey)
            ->acceptJson()
            ->post($this->baseUrl . "charges", $payload);

        return $response->json();
    }
    
    public function getCharge($chargeId)
    {
        $response = Http::withToken($this->secretKey)
            ->acceptJson()
            ->get($this->baseUrl . "charges/" . $chargeId);
    
        return $response->json();
    }
}
