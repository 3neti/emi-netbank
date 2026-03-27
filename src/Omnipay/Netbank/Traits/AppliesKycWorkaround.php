<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Traits;

use LBHurtado\PaymentGateway\Support\Address;

/**
 * AppliesKycWorkaround Trait
 *
 * Provides KYC workaround by injecting randomized Philippine addresses
 * into disbursement requests to comply with BSP requirements while
 * maintaining transaction flexibility.
 */
trait AppliesKycWorkaround
{
    /**
     * Generate a random Philippine address
     *
     * @return array Address with address1, city, country, postal_code
     */
    protected function generateRandomAddress(): array
    {
        // Check if randomization is enabled
        if (! config('omnipay.kyc.randomize_address', true)) {
            return [
                'address1' => 'N/A',
                'city' => 'Manila',
                'country' => 'PH',
                'postal_code' => '1000',
            ];
        }

        // Use Address helper to generate random address from zip codes
        return Address::generate();
    }

    /**
     * Apply KYC workaround by injecting address into payload
     *
     * @param  array  $payload  The request payload (passed by reference)
     * @param  string  $recipientKey  The key path where address should be injected
     */
    protected function applyKycWorkaround(array &$payload, string $recipientKey = 'recipient'): void
    {
        // Generate random address
        $address = $this->generateRandomAddress();

        // Inject into payload at the specified key
        if (isset($payload[$recipientKey])) {
            $payload[$recipientKey]['address'] = $address;
        } else {
            $payload[$recipientKey] = [
                'address' => $address,
            ];
        }

        // Log for debugging (in test mode only)
        if ($this->getParameter('testMode')) {
            logger()->info('[KYC Workaround] Generated address', [
                'address' => $address,
                'recipient_key' => $recipientKey,
            ]);
        }
    }

    /**
     * Check if KYC workaround is enabled
     */
    protected function isKycWorkaroundEnabled(): bool
    {
        return config('omnipay.kyc.randomize_address', true);
    }
}
