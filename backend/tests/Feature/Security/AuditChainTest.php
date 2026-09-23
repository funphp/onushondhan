<?php

use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

it('produces a verifiable hash chain and detects tampering', function () {
    $logger = app(AuditLogger::class);
    $logger->log('test.one', 'Thing', null, ['a' => 1, 'nested' => ['z' => 1, 'b' => 2]], null, 1);
    $logger->log('test.two', 'Thing', ['x' => 'y'], null, null, 2);
    $this->artisan('audit:verify')->assertSuccessful();

    DB::table('audit_logs')->where('action', 'test.one')->update(['after_json' => json_encode(['a' => 2])]);
    expect($logger->verifyChain())->not->toBeNull();
    $this->artisan('audit:verify')->assertFailed();
});

it('redacts secrets from audit payloads', function () {
    $log = app(AuditLogger::class)->log('test.secret', 'Thing', null, ['password' => 'hunter2', 'token' => 'abc', 'ok' => 'visible']);
    expect($log->after_json)->toEqual(['password' => '[redacted]', 'token' => '[redacted]', 'ok' => 'visible']);
});

it('refuses updates and deletes through the model', function () {
    $log = app(AuditLogger::class)->log('test.immutable');
    expect(fn () => $log->update(['action' => 'x']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
});
