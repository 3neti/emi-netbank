<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\PaymentGateway\Support\NetbankLivePreflightFailureMapper;

it('maps sanitized NetBank transport failures', function (
    string $message,
    ProviderLivePreflightFailureCode $expected,
) {
    expect(NetbankLivePreflightFailureMapper::fromThrowable(
        new RuntimeException($message),
    ))->toBe($expected);
})->with([
    'dns' => [
        'cURL error 6: Could not resolve host: sensitive.example',
        ProviderLivePreflightFailureCode::DnsResolutionFailed,
    ],
    'timeout' => [
        'cURL error 28: Operation timed out after 10001 milliseconds',
        ProviderLivePreflightFailureCode::ConnectionTimeout,
    ],
    'tls' => [
        'cURL error 60: SSL certificate problem',
        ProviderLivePreflightFailureCode::TlsFailure,
    ],
    'unknown' => [
        'opaque provider transport failure',
        ProviderLivePreflightFailureCode::ProviderUnavailable,
    ],
]);

it('maps sanitized NetBank HTTP failures', function (
    int $status,
    ProviderLivePreflightFailureCode $expected,
) {
    expect(NetbankLivePreflightFailureMapper::fromHttpStatus($status))
        ->toBe($expected);
})->with([
    'authentication' => [401, ProviderLivePreflightFailureCode::AuthenticationFailed],
    'rejected' => [422, ProviderLivePreflightFailureCode::BalanceEndpointRejected],
    'unavailable' => [503, ProviderLivePreflightFailureCode::ProviderUnavailable],
]);
