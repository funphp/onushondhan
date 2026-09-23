<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/** Development driver: writes SMS to the log. Bind a real provider in production. */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('sms.dispatch', ['to' => substr($phone, 0, 4).'****'.substr($phone, -2), 'message' => $message]);
    }
}
