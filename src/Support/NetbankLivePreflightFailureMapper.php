<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use Throwable;

final class NetbankLivePreflightFailureMapper
{
    public static function fromHttpStatus(int $status): ProviderLivePreflightFailureCode
    {
        return match (true) {
            in_array($status, [401, 403], true) => ProviderLivePreflightFailureCode::AuthenticationFailed,
            in_array($status, [408, 504], true) => ProviderLivePreflightFailureCode::ConnectionTimeout,
            $status === 429 || $status >= 500 => ProviderLivePreflightFailureCode::ProviderUnavailable,
            default => ProviderLivePreflightFailureCode::BalanceEndpointRejected,
        };
    }

    public static function fromThrowable(Throwable $exception): ProviderLivePreflightFailureCode
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $status = self::responseStatus($current);

            if ($status !== null) {
                return self::fromHttpStatus($status);
            }

            $message = mb_strtolower($current->getMessage());

            if (
                preg_match('/curl error 6(?:\D|$)/', $message) === 1
                || str_contains($message, 'could not resolve host')
                || str_contains($message, 'name or service not known')
                || str_contains($message, 'getaddrinfo')
            ) {
                return ProviderLivePreflightFailureCode::DnsResolutionFailed;
            }

            if (
                preg_match('/curl error 28(?:\D|$)/', $message) === 1
                || str_contains($message, 'timed out')
                || str_contains($message, 'timeout')
            ) {
                return ProviderLivePreflightFailureCode::ConnectionTimeout;
            }

            if (
                preg_match('/curl error (?:35|51|58|60)(?:\D|$)/', $message) === 1
                || str_contains($message, 'ssl certificate')
                || str_contains($message, 'tls')
            ) {
                return ProviderLivePreflightFailureCode::TlsFailure;
            }
        }

        return ProviderLivePreflightFailureCode::ProviderUnavailable;
    }

    private static function responseStatus(Throwable $exception): ?int
    {
        if (! method_exists($exception, 'getResponse')) {
            return null;
        }

        $response = $exception->getResponse();

        if (! is_object($response) || ! method_exists($response, 'getStatusCode')) {
            return null;
        }

        $status = $response->getStatusCode();

        return is_int($status) ? $status : null;
    }
}
