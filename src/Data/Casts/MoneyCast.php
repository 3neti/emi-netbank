<?php

namespace LBHurtado\PaymentGateway\Data\Casts;

use Brick\Money\Money;
use Illuminate\Support\Number;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class MoneyCast implements Cast
{
    public function cast(
        DataProperty $property,
        mixed $value,
        array $properties,
        CreationContext $context
    ): mixed {
        if ($value instanceof Money) {
            return $value;
        }

        if (is_array($value) && isset($value['amount'], $value['currency'])) {
            return Money::of($value['amount'], $value['currency']);
        }

        if (is_numeric($value)) {
            return Money::of($value, Number::defaultCurrency()); // default currency
        }

        throw new \InvalidArgumentException('Cannot cast value to Money: '.print_r($value, true));
    }
}
