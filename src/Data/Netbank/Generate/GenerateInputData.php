<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Generate;

use Brick\Money\Money;
use LBHurtado\PaymentGateway\Data\Casts\MoneyCast;
use LBHurtado\PaymentGateway\Data\Transformers\MoneyToFloatTransformer;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

class GenerateInputData extends Data
{
    public function __construct(
        public string $account,
        #[WithTransformer(MoneyToFloatTransformer::class)]
        #[WithCast(MoneyCast::class)]
        public Money $amount
    ) {}

    public static function rules(): array
    {
        return [
            'account' => ['required', 'string', 'starts_with:0', 'max_digits:11'], // TODO: rationalize this
            'amount' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
