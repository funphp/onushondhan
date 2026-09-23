<?php

declare(strict_types=1);

namespace App\Contracts;

interface SmsSender
{
    public function send(string $phone, string $message): void;
}
