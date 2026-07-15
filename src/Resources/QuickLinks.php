<?php

namespace Paymob\Laravel\Resources;

use Paymob\Laravel\Http\PaymobHttpClient;
use Paymob\Laravel\Support\PaymobResponse;

final class QuickLinks
{
    public function __construct(private readonly PaymobHttpClient $http) {}

    public function create(array $data): PaymobResponse
    {
        return $this->http->bearer('POST', '/api/ecommerce/payment-links', options: ['multipart' => $this->multipart($data)]);
    }

    public function cancel(int|string $linkId): PaymobResponse
    {
        return $this->http->bearer('POST', '/api/ecommerce/payment-links/cancel', options: [
            'multipart' => $this->multipart(['payment_link_id' => $linkId]),
        ]);
    }

    private function multipart(array $data): array
    {
        $parts = [];
        foreach ($data as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                $part = ['name' => $name];
                if ($item instanceof \SplFileInfo) {
                    $part['contents'] = fopen($item->getRealPath(), 'r');
                    $part['filename'] = $item->getFilename();
                } elseif ($name === 'payment_link_image' && is_string($item) && is_file($item)) {
                    $part['contents'] = fopen($item, 'r');
                    $part['filename'] = basename($item);
                } else {
                    $part['contents'] = is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;
                }
                $parts[] = $part;
            }
        }

        return $parts;
    }
}
