<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

class RecipientAccountNumberData extends Data
{
    public function __construct(
        public string $alias,
        public string $referenceCode,
    ) {}

    /**
     * Parse a recipient account number by removing the configured alias prefix
     * and returning the remaining part as a reference code.
     *
     * @param  string  $recipientAccountNumber  Full account string including alias prefix
     *
     * @throws \InvalidArgumentException if alias not found at start
     */
    public static function fromRecipientAccountNumber(string $recipientAccountNumber): self
    {
        $alias = config('disbursement.client.alias');
        if (empty($alias)) {
            throw new InvalidArgumentException("Configuration key 'disbursement.client.alias' is not set or empty.");
        }

        // Ensure alias is a prefix of the provided account number
        if (str_starts_with($recipientAccountNumber, $alias) === false) {
            throw new InvalidArgumentException(
                "Recipient account number must start with the alias '{$alias}'. Given: {$recipientAccountNumber}"
            );
        }

        // Strip the alias prefix; the remainder is the reference code
        $referenceCode = substr($recipientAccountNumber, strlen($alias));

        //        if ($referenceCode === '' || !ctype_digit($referenceCode)) {
        if (empty($referenceCode)) {
            throw new InvalidArgumentException(
                "Invalid reference code extracted from account number: '{$referenceCode}'"
            );
        }

        return new self(alias: $alias, referenceCode: $referenceCode);
    }
}
