<?php

namespace Paymob\Laravel\Data;

use Illuminate\Contracts\Support\Arrayable;

final readonly class Item implements Arrayable
{
    public function __construct(public string $name, public int $amount, public int $quantity = 1, public ?string $description = null, public ?string $image = null) {}

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'amount' => $this->amount,
            'quantity' => $this->quantity,
            'description' => $this->description,
            'image' => $this->image
        ], fn($value) => $value !== null);
    }
}
