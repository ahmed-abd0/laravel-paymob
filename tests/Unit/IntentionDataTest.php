<?php

use Paymob\Laravel\Data\BillingData;
use Paymob\Laravel\Data\IntentionData;
use Paymob\Laravel\Data\Item;

it('rejects mismatched intention item totals', function () {
    $data = new IntentionData(1000, new BillingData('A', 'B', 'a@example.com', '+201000000000'), [1]);
    $data->items(new Item('Plan', 900));
    $data->toArray();
})->throws(InvalidArgumentException::class);
