<?php

namespace Paymob\Laravel\Support;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class PaymobResponse implements Arrayable, ArrayAccess, JsonSerializable
{
    public function __construct(public readonly int $status, private readonly array $data, public readonly array $headers = []) {}

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }
    public function toArray(): array
    {
        return $this->data;
    }
    public function jsonSerialize(): array
    {
        return $this->data;
    }
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('PaymobResponse is immutable.');
    }
    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('PaymobResponse is immutable.');
    }
}
