<?php

namespace Paymob\Laravel\Exceptions;

use Illuminate\Http\JsonResponse;

class InvalidSignatureException extends PaymobException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 401);
    }
}
