<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressRequestData;
use LBHurtado\PaymentGateway\Enums\NetbankStandingAddressScheme;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingConfigurationException;

final class NetbankStandingAddressProfile
{
    public function scheme(): NetbankStandingAddressScheme
    {
        $scheme = NetbankStandingAddressScheme::tryFrom(strtolower(trim(
            (string) config('payment-gateway.netbank.funding.standing_address.scheme'),
        )));

        if ($scheme === null) {
            throw new NetbankFundingConfigurationException(
                'NetBank Standing Funding Address scheme is unsupported.',
            );
        }

        if (app()->environment('production') && $scheme !== NetbankStandingAddressScheme::AccountHmacV2) {
            throw new NetbankFundingConfigurationException(
                'Production requires the netbank-account-hmac-v2 Standing Funding Address scheme.',
            );
        }

        return $scheme;
    }

    public function referenceLength(): int
    {
        $length = (int) config(
            'payment-gateway.netbank.funding.standing_address.reference_length',
            11,
        );

        if ($length !== 11) {
            throw new NetbankFundingConfigurationException(
                'NetBank alias 91500 requires an 11-digit Standing Funding Address reference.',
            );
        }

        return $length;
    }

    public function totalLength(string $alias): int
    {
        $total = strlen($alias) + $this->referenceLength();

        if ($total !== 16) {
            throw new NetbankFundingConfigurationException(
                'NetBank Standing Funding Addresses must contain exactly 16 digits.',
            );
        }

        return $total;
    }

    public function derive(
        StandingFundingAddressRequestData $request,
    ): NetbankStandingAddressReferenceData {
        return match ($this->scheme()) {
            NetbankStandingAddressScheme::MobileV1 => $this->mobileReference($request),
            NetbankStandingAddressScheme::AccountHmacV2 => $this->hmacReference($request),
        };
    }

    private function mobileReference(
        StandingFundingAddressRequestData $request,
    ): NetbankStandingAddressReferenceData {
        $reference = trim((string) $request->routingReference);

        if (preg_match('/\A09\d{9}\z/', $reference) !== 1) {
            throw new NetbankFundingConfigurationException(
                'The netbank-mobile-v1 scheme requires a verified 11-digit national mobile.',
            );
        }

        return new NetbankStandingAddressReferenceData(
            reference: $reference,
            scheme: NetbankStandingAddressScheme::MobileV1,
            keyId: null,
            counter: 0,
        );
    }

    private function hmacReference(
        StandingFundingAddressRequestData $request,
    ): NetbankStandingAddressReferenceData {
        $keyId = trim((string) config(
            'payment-gateway.netbank.funding.standing_address.hmac_key_id',
        ));
        $key = $this->hmacKey();
        $counter = max(0, $request->derivationCounter);
        $message = implode('|', [
            'v2',
            'netbank',
            $keyId,
            trim($request->accountReference),
            $request->purpose->value,
            (string) $counter,
        ]);
        $digest = hash_hmac('sha256', $message, $key, true);
        $reference = '';

        foreach (unpack('C*', $digest) ?: [] as $byte) {
            if ($byte < 250) {
                $reference .= (string) ($byte % 10);
            }

            if (strlen($reference) === $this->referenceLength()) {
                break;
            }
        }

        if (strlen($reference) !== $this->referenceLength()) {
            throw new NetbankFundingConfigurationException(
                'NetBank Standing Funding Address HMAC output could not be normalized.',
            );
        }

        return new NetbankStandingAddressReferenceData(
            reference: $reference,
            scheme: NetbankStandingAddressScheme::AccountHmacV2,
            keyId: $keyId,
            counter: $counter,
        );
    }

    private function hmacKey(): string
    {
        $keyId = trim((string) config(
            'payment-gateway.netbank.funding.standing_address.hmac_key_id',
        ));
        $configured = trim((string) config(
            'payment-gateway.netbank.funding.standing_address.hmac_key',
        ));

        if ($keyId === '' || $configured === '') {
            throw new NetbankFundingConfigurationException(
                'The netbank-account-hmac-v2 scheme requires a dedicated key and key identifier.',
            );
        }

        $key = $configured;

        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);

            if (! is_string($decoded)) {
                throw new NetbankFundingConfigurationException(
                    'The NetBank Standing Funding Address HMAC key is not valid base64.',
                );
            }

            $key = $decoded;
        }

        if (strlen($key) < 32) {
            throw new NetbankFundingConfigurationException(
                'The NetBank Standing Funding Address HMAC key must contain at least 32 bytes.',
            );
        }

        return $key;
    }
}
