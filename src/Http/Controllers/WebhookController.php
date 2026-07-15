<?php

namespace Paymob\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Paymob\Laravel\Enums\WebhookType;
use Paymob\Laravel\Exceptions\InvalidSignatureException;
use Paymob\Laravel\Models\WebhookCall;
use Paymob\Laravel\Webhooks\SignatureVerifier;
use Paymob\Laravel\Webhooks\WebhookProcessor;

final class WebhookController extends Controller
{
    public function __construct(private readonly SignatureVerifier $signatures, private readonly WebhookProcessor $processor) {}

    public function handle(Request $request): Response
    {
        return $this->receive($request, WebhookType::detect($this->payload($request)));
    }
    public function transaction(Request $request): Response
    {
        return $this->receive($request, WebhookType::TRANSACTION);
    }
    public function token(Request $request): Response
    {
        return $this->receive($request, WebhookType::TOKEN);
    }
    public function subscription(Request $request): Response
    {
        return $this->receive($request, WebhookType::SUBSCRIPTION);
    }
    private function receive(Request $request, WebhookType $type): Response
    {
        $payload = $this->payload($request);
        $signature = $request->query('hmac') ?? $request->header('X-Paymob-Hmac') ?? $payload['hmac'] ?? null;
        $valid = match ($type) {
            WebhookType::TRANSACTION => $this->signatures->transaction($payload, $signature),
            WebhookType::TOKEN => $this->signatures->token($payload, $signature),
            WebhookType::SUBSCRIPTION => $this->signatures->subscription($request->query('secret') ?? $request->header('X-Paymob-Webhook-Secret')),
            default => false
        };
        $hash = hash('sha256', $type->value . '|' . $this->canonical($payload) . '|' . (string) $signature);
        /** @var WebhookCall $call */
        $call = config('paymob.models.webhook_call')::query()->firstOrCreate(['payload_hash' => $hash], [
            'type' => $type->value,
            'external_id' => $this->externalId($payload),
            'event' => $payload['type'] ?? $payload['event'] ?? null,
            'signature' => $signature,
            'valid_signature' => $valid,
            'payload' => $payload,
            'status' => $valid ? 'pending' : 'rejected'
        ]);
        if (!$valid) throw new InvalidSignatureException('Invalid Paymob webhook signature.');
        if ($call->status !== 'processed') $this->processor->process($call);
        return response()->noContent();
    }

    private function payload(Request $request): array
    {
        $payload = $request->json()->all();
        if (!$payload) $payload = $request->request->all();
        if (!$payload) $payload = $request->query();
        unset($payload['hmac'], $payload['secret']);
        return $payload;
    }

    private function externalId(array $payload): ?string
    {
        $object = isset($payload['obj']) && is_array($payload['obj']) ? $payload['obj'] : $payload;
        $id = $object['id'] ?? $object['subscription_id'] ?? null;
        return is_scalar($id) ? (string) $id : null;
    }

    private function canonical(array $payload): string
    {
        $sort = function (&$value) use (&$sort) {
            if (!is_array($value)) return;
            foreach ($value as &$item) $sort($item);
            if (!array_is_list($value)) ksort($value);
        };
        $sort($payload);
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
