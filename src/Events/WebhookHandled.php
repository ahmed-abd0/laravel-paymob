<?php

namespace Paymob\Laravel\Events;

use Paymob\Laravel\Models\WebhookCall;

final class WebhookHandled
{
    public function __construct(public readonly WebhookCall $webhookCall) {}
}
