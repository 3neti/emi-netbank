<?php

namespace LBHurtado\PaymentGateway\Data;

use LBHurtado\PaymentGateway\Enums\SettlementRail;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class BankData extends Data
{
    public function __construct(
        #[MapInputName('swift_bic')]
        public string $code,

        #[MapInputName('full_name')]
        public string $name,

        /** @var SettlementRail[] */
        public array $settlementRails,
    ) {}

    //    public static function fromRegistry(): array
    //    {
    //        return collect(app(\LBHurtado\PaymentGateway\Support\BankRegistry::class)->all())
    //            ->map(fn (array $data, string $swift) => new self(
    //                code: $swift,
    //                name: $data['full_name'],
    //                settlementRails: array_map(
    //                    fn (string $key) => SettlementRail::from($key),
    //                    array_keys($data['settlement_rail'] ?? [])
    //                )
    //            ))
    //            ->values()
    //            ->all();
    //    }
    //
    //    public static function all(): array
    //    {
    //        return collect(app(\LBHurtado\PaymentGateway\Support\BankRegistry::class)->all())
    //            ->map(fn (array $data, string $swift) => new self(
    //                code: $swift,
    //                name: $data['full_name'],
    //                settlementRails: array_map(
    //                    fn (string $key) => SettlementRail::from($key),
    //                    array_keys($data['settlement_rail'] ?? [])
    //                )
    //            ))
    //            ->values()
    //            ->all();
    //    }
    //
    //    public static function findByCode(string $code): ?self
    //    {
    //        return collect(self::all())
    //            ->firstWhere('code', $code);
    //    }
}
