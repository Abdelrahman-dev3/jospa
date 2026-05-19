<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JavnaWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class JavnaWebhookController extends Controller
{
    public function whatsapp(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (config('services.javna.log_payloads')) {
            Log::info('Javna WhatsApp webhook received', [
                'event' => Arr::get($payload, 'event'),
                'event_scope' => Arr::get($payload, 'eventScope'),
                'account_id' => Arr::get($payload, 'accountid'),
            ]);
        }

        if (config('services.javna.enabled')) {
            try {
                JavnaWebhookEvent::create([
                    'event_scope' => Arr::get($payload, 'eventScope'),
                    'event' => Arr::get($payload, 'event'),
                    'account_id' => Arr::get($payload, 'accountid'),
                    'message_id' => $this->firstValue($payload, [
                        'messageId',
                        'message_id',
                        'data.messageId',
                        'data.message_id',
                        'data.id',
                        'data.message.id',
                        'metadata.messageId',
                    ]),
                    'from_number' => $this->firstValue($payload, [
                        'from',
                        'from_number',
                        'data.from',
                        'data.sender',
                        'data.customer',
                        'metadata.from',
                    ]),
                    'to_number' => $this->firstValue($payload, [
                        'to',
                        'to_number',
                        'data.to',
                        'data.recipient',
                        'metadata.to',
                    ]),
                    'status' => $this->firstValue($payload, [
                        'status',
                        'data.status',
                        'data.messageStatus',
                        'data.deliveryStatus',
                    ]),
                    'payload' => $payload,
                    'headers' => $request->headers->all(),
                ]);
            } catch (\Throwable $exception) {
                Log::error('Failed to store Javna WhatsApp webhook', [
                    'message' => $exception->getMessage(),
                    'event' => Arr::get($payload, 'event'),
                ]);
            }
        }

        return response()->json(['status' => 'received']);
    }

    private function firstValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);

            if ($value !== null && $value !== '') {
                return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return null;
    }
}
