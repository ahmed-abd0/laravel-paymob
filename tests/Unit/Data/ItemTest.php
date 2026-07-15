<?php

use Paymob\Laravel\Data\Item;

it('serializes item with all fields', function () {
    $item = new Item(name: 'Product', amount: 5000, quantity: 2, description: 'A product', image: 'http://img.png');
    $array = $item->toArray();
    expect($array)->toBe([
        'name' => 'Product',
        'amount' => 5000,
        'quantity' => 2,
        'description' => 'A product',
        'image' => 'http://img.png',
    ]);
});

it('omits null optional fields', function () {
    $item = new Item(name: 'Product', amount: 5000);
    $array = $item->toArray();
    expect($array)->not->toHaveKey('description');
    expect($array)->not->toHaveKey('image');
    expect($array['quantity'])->toBe(1);
});
