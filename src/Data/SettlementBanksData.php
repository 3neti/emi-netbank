<?php

namespace LBHurtado\PaymentGateway\Data;

use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class SettlementBanksData extends Data
{
    public function __construct(
        /** @var BankData[] */
        public DataCollection $banks,
    ) {}

    public static function fromRegistry(): static
    {
        $banks = app(BankRegistry::class)->all();

        $collection = array_map(
            fn (string $swift, array $data) => BankData::from([
                'code' => $swift,
                'name' => $data['full_name'],
                'settlementRails' => array_map(
                    fn (string $key) => SettlementRail::from($key),
                    array_keys($data['settlement_rail'] ?? [])
                ),
            ]),
            array_keys($banks),
            array_values($banks)
        );

        return new static(
            banks: new DataCollection(BankData::class, $collection)
        );
    }

    public function toSelectOptions(): array
    {
        return $this->banks->toCollection()
            ->mapWithKeys(fn (BankData $bank) => [$bank->code => $bank->name])
            ->all();
    }

    public function filterByRail(SettlementRail $rail): static
    {
        $collection = $this->banks->toCollection()->filter(
            fn (BankData $bank) => in_array($rail, $bank->settlementRails, true)
        );

        return new static(
            new DataCollection(BankData::class, $collection->values()->all())
        );
    }

    public function filterByName(string $substring): static
    {
        $collection = $this->banks->toCollection()->filter(
            fn (BankData $bank) => str_contains(strtolower($bank->name), strtolower($substring))
        );

        return new static(
            new DataCollection(BankData::class, $collection->values()->all())
        );
    }

    public function filterByCodePrefix(string $prefix): static
    {
        $collection = $this->banks->toCollection()->filter(
            fn (BankData $bank) => str_starts_with($bank->code, strtoupper($prefix))
        );

        return new static(
            new DataCollection(BankData::class, $collection->values()->all())
        );
    }

    public static function cached(): static
    {
        return cache()
            ->tags(['banks'])
            ->rememberForever('settlement_banks_data', fn () => static::fromRegistry());
    }

    public static function clearCache(): void
    {
        cache()->tags(['banks'])->forget('settlement_banks_data');
    }

    public static function indices(): array
    {
        return static::cached()
            ->banks
            ->toCollection()
            ->pluck('code')
            ->all();
    }
}
