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
        $event = (string) Arr::get($payload, 'event', '');
        $messageId = $this->firstValue($payload, [
            'messageId',
            'message_id',
            'data.messageId',
            'data.message_id',
            'data.id',
            'data.message.id',
            'metadata.messageId',
        ]);
        $fromNumber = $this->firstValue($payload, [
            'from',
            'from_number',
            'data.from',
            'data.sender',
            'data.customer',
            'metadata.from',
        ]);
        $toNumber = $this->firstValue($payload, [
            'to',
            'to_number',
            'data.to',
            'data.recipient',
            'metadata.to',
        ]);
        $status = $this->firstValue($payload, [
            'status',
            'data.status',
            'data.messageStatus',
            'data.deliveryStatus',
        ]);
        $failureReason = $this->firstValue($payload, [
            'reason',
            'error',
            'errorText',
            'errorMessage',
            'description',
            'message',
            'statusMessage',
            'failureReason',
            'failure_reason',
            'data.reason',
            'data.error',
            'data.errorText',
            'data.errorMessage',
            'data.description',
            'data.message',
            'data.statusMessage',
            'data.failureReason',
            'data.failure_reason',
            'data.messageStatus.reason',
            'data.messageStatus.description',
            'data.messageStatus.message',
            'data.deliveryStatus.reason',
            'data.deliveryStatus.description',
            'data.failureInfo.deliveryFailureDetails.Title',
            'data.failureInfo.deliveryFailureDetails.Message',
            'data.failureInfo.deliveryFailureDetails.error_data.Details',
            'metadata.reason',
        ]);
        $failureCode = $this->firstValue($payload, [
            'code',
            'errorCode',
            'statusCode',
            'failureCode',
            'failure_code',
            'data.code',
            'data.errorCode',
            'data.statusCode',
            'data.failureCode',
            'data.failure_code',
            'data.messageStatus.code',
            'data.deliveryStatus.code',
            'data.failureInfo.deliveryFailureDetails.Code',
            'metadata.code',
        ]);

        if (config('services.javna.log_payloads')) {
            Log::info('Javna WhatsApp webhook received', [
                'event' => $event,
                'event_scope' => Arr::get($payload, 'eventScope'),
                'account_id' => Arr::get($payload, 'accountid'),
                'message_id' => $messageId,
                'from' => $fromNumber,
                'to' => $toNumber,
                'status' => $status,
            ]);
        }

        if (str_contains($event, 'failed')) {
            Log::error('Javna WhatsApp delivery failed.', [
                'event' => $event,
                'message_id' => $messageId,
                'from' => $fromNumber,
                'to' => $toNumber,
                'status' => $status,
                'failure_code' => $failureCode,
                'failure_reason' => $failureReason,
                'payload' => $payload,
            ]);
        }

        if (config('services.javna.enabled')) {
            try {
                JavnaWebhookEvent::create([
                    'event_scope' => Arr::get($payload, 'eventScope'),
                    'event' => $event,
                    'account_id' => Arr::get($payload, 'accountid'),
                    'message_id' => $messageId,
                    'from_number' => $fromNumber,
                    'to_number' => $toNumber,
                    'status' => $status,
                    'payload' => $payload,
                    'headers' => $request->headers->all(),
                    'processed_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                Log::error('Failed to store Javna WhatsApp webhook', [
                    'message' => $exception->getMessage(),
                    'event' => $event,
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
