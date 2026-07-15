<?php

namespace Paymob\Laravel\Exceptions;

use Illuminate\Http\Client\Response;

class ApiException extends PaymobException
{
    public function __construct(public readonly int $status, public readonly array|string|null $responseBody, string $message = 'Paymob API request failed.')
    {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json() ?? $response->body();
        $message = data_get($body, 'message') ?? data_get($body, 'detail') ?? data_get($body, 'error') ?? "Paymob API returned HTTP {$response->status()}.";
        return new self($response->status(), $body, is_string($message) ? $message : json_encode($message));
    }
}
