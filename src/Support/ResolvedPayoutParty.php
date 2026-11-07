<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Support;

use InvalidArgumentException;

final class ResolvedPayoutParty
{
    /** @return array{address1: string, city: string, country: string, postal_code: string} */
    public static function address(array $party): array
    {
        $address = $party['address'] ?? null;
        if (! is_array($address)) {
            throw new InvalidArgumentException('The resolved payout address is incomplete.');
        }
        foreach (['address1', 'city', 'country', 'postal_code'] as $field) {
            if (! is_string($address[$field] ?? null) || trim($address[$field]) === '') {
                throw new InvalidArgumentException('The resolved payout address is incomplete.');
            }
        }
        if (mb_strlen($address['address1']) > 128) {
            throw new InvalidArgumentException('The resolved payout address exceeds the NetBank address limit.');
        }

        return array_intersect_key($address, array_flip(['address1', 'city', 'country', 'postal_code']));
    }
}
