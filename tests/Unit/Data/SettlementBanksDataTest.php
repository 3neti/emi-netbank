<?php

use LBHurtado\PaymentGateway\Data\SettlementBanksData;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use Spatie\LaravelData\DataCollection;

beforeEach(function () {
    SettlementBanksData::clearCache();
});

it('loads banks from the registry', function () {
    $data = SettlementBanksData::cached();

    expect($data->banks)->not->toBeEmpty();
    expect($data->banks->toCollection()->first())->toHaveProperties(['code', 'name', 'settlementRails']);
});

it('returns select options', function () {
    $data = SettlementBanksData::cached();

    $options = $data->toSelectOptions();

    expect($options)->toBeArray();
    expect(array_key_first($options))->toMatch('/^[A-Z0-9]{8,11}$/'); // SWIFT code format
    expect(array_values($options))->each()->toBeString();
});

it('filters banks by settlement rail', function () {
    $data = SettlementBanksData::cached();

    $filtered = $data->filterByRail(SettlementRail::PESONET);

    expect($filtered)->toBeInstanceOf(SettlementBanksData::class);
    expect($filtered->banks)->toBeInstanceOf(DataCollection::class);

    $filtered->banks->toCollection()->each(function ($bank) {
        expect($bank->settlementRails)->toContain(SettlementRail::PESONET);
    });
});

it('filters banks by name substring', function () {
    $data = SettlementBanksData::cached();

    $filtered = $data->filterByName('rural');

    expect($filtered)->toBeInstanceOf(SettlementBanksData::class);

    $filtered->banks->toCollection()->each(function ($bank) {
        expect(strtolower($bank->name))->toContain('rural');
    });
});

it('filters banks by swift code prefix', function () {
    $data = SettlementBanksData::cached();

    $filtered = $data->filterByCodePrefix('AGB');

    expect($filtered)->toBeInstanceOf(SettlementBanksData::class);

    $filtered->banks->toCollection()->each(function ($bank) {
        expect($bank->code)->toStartWith('AGB');
    });
});

it('can chain multiple filters', function () {
    $data = SettlementBanksData::cached()
        ->filterByRail(SettlementRail::PESONET)
        ->filterByName('bank')
        ->filterByCodePrefix('AL');

    expect($data)->toBeInstanceOf(SettlementBanksData::class);

    $data->banks->toCollection()->each(function ($bank) {
        expect($bank->settlementRails)->toContain(SettlementRail::PESONET);
        expect(strtolower($bank->name))->toContain('bank');
        expect($bank->code)->toStartWith('AL');
    });
});
