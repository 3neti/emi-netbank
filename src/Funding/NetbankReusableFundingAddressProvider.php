<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use DateTimeImmutable;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\StandingFundingAddressProvider;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressRequestData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingObservationRequestData;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingConfigurationException;

final class NetbankReusableFundingAddressProvider implements StandingFundingAddressProvider
{
    private const Provider = 'netbank';

    public function __construct(
        private readonly NetbankFundingApiClient $client,
    ) {}

    public function providerCode(): string
    {
        return self::Provider;
    }

    public function createStandingFundingAddress(
        StandingFundingAddressRequestData $request,
    ): StandingFundingAddressData {
        $address = $this->create(
            ownerReference: $this->standingAddressReference($request),
            currency: $request->currency,
            destination: $request->destination,
        );

        return new StandingFundingAddressData(
            provider: self::Provider,
            providerReference: 'standing:netbank:'.hash(
                'sha256',
                $this->standingAddressReference($request),
            ),
            fundingAddress: $address->fundingAddress,
            accountReference: $request->accountReference,
            purpose: $request->purpose,
            currency: $address->currency,
            qrCode: $address->qrCode,
            reusable: true,
            displayData: [
                'institution' => $address->institution,
                'merchant_name' => $address->merchantName,
            ],
        );
    }

    /**
     * @return list<ProviderFundingObservationData>
     */
    public function observeStandingFundingAddress(
        StandingFundingObservationRequestData $request,
    ): array {
        $routing = $this->routingProfile($request->destination);
        $this->assertFundingAddress($request->fundingAddress, $routing['alias']);

        return array_values(array_map(
            fn (array $transaction): ProviderFundingObservationData => $this->providerObservation(
                transaction: $transaction,
                request: $request,
                providerAccountNumber: $routing['account_number'],
            ),
            $this->incomingTransactions(
                fundingAddress: $request->fundingAddress,
                accountNumber: $routing['account_number'],
            ),
        ));
    }

    public function create(
        string $ownerReference,
        string $currency = 'PHP',
        ?FundingDestinationData $destination = null,
    ): NetbankReusableFundingAddressData {
        $routing = $this->routingProfile($destination);
        $currency = $this->currency($currency);
        $fundingAddress = $this->fundingAddress($ownerReference, $routing['alias']);
        $qrCode = $this->client->generateReusableQrCode($fundingAddress, $currency);

        return new NetbankReusableFundingAddressData(
            provider: self::Provider,
            fundingAddress: $fundingAddress,
            currency: $currency,
            institution: 'NetBank',
            merchantName: $this->requiredConfig('qr_merchant_name'),
            qrCode: new FundingQrCodeData(
                mimeType: 'image/png',
                base64Payload: $qrCode,
                qrMode: 'static',
                transactionType: 'p2m',
                embeddedAmount: false,
                providerGenerated: true,
            ),
        );
    }

    /**
     * @return list<NetbankReusableFundingObservationData>
     */
    public function observationsForOwner(
        string $ownerReference,
        ?FundingDestinationData $destination = null,
    ): array {
        $routing = $this->routingProfile($destination);

        return $this->observations(
            $this->fundingAddress($ownerReference, $routing['alias']),
            $destination,
        );
    }

    /**
     * @return list<NetbankReusableFundingObservationData>
     */
    public function observations(
        string $fundingAddress,
        ?FundingDestinationData $destination = null,
    ): array {
        $routing = $this->routingProfile($destination);
        $this->assertFundingAddress($fundingAddress, $routing['alias']);

        return array_values(array_map(
            fn (array $transaction): NetbankReusableFundingObservationData => $this->observation($transaction),
            $this->incomingTransactions($fundingAddress, $routing['account_number']),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function incomingTransactions(string $fundingAddress, string $accountNumber): array
    {
        return array_values(array_filter(
            $this->client->transactions($fundingAddress, $accountNumber),
            fn (array $transaction): bool => $this->isIncomingCredit($transaction, $fundingAddress),
        ));
    }

    private function observation(array $transaction): NetbankReusableFundingObservationData
    {
        $transactionId = $this->requiredTransactionValue($transaction, 'transaction_id');
        $grossAmountMinor = $this->amountMinor($transaction);
        $currency = $this->currency((string) data_get($transaction, 'amount.cur'));
        $feeAmountMinor = $this->feeAmountMinor($transaction, $currency);

        if ($feeAmountMinor > $grossAmountMinor) {
            throw new InvalidArgumentException('NetBank transaction fees exceed the incoming amount.');
        }

        return new NetbankReusableFundingObservationData(
            transactionHash: hash('sha256', $transactionId),
            payloadHash: hash('sha256', json_encode(
                $this->canonicalize($transaction),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            )),
            grossAmountMinor: $grossAmountMinor,
            feeAmountMinor: $feeAmountMinor,
            netAmountMinor: $grossAmountMinor - $feeAmountMinor,
            currency: $currency,
            providerStatus: strtolower($this->requiredTransactionValue($transaction, 'status')),
            occurredAt: $this->optionalDate(data_get($transaction, 'date')),
            settledAt: $this->settledAt($transaction),
        );
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function providerObservation(
        array $transaction,
        StandingFundingObservationRequestData $request,
        string $providerAccountNumber,
    ): ProviderFundingObservationData {
        $transactionId = $this->requiredTransactionValue($transaction, 'transaction_id');
        $grossAmountMinor = $this->amountMinor($transaction);
        $currency = $this->currency((string) data_get($transaction, 'amount.cur'));
        $feeAmountMinor = $this->feeAmountMinor($transaction, $currency);

        if ($feeAmountMinor > $grossAmountMinor) {
            throw new InvalidArgumentException('NetBank transaction fees exceed the incoming amount.');
        }

        return new ProviderFundingObservationData(
            provider: self::Provider,
            providerTransactionId: $transactionId,
            grossAmountMinor: $grossAmountMinor,
            feeAmountMinor: $feeAmountMinor,
            netAmountMinor: $grossAmountMinor - $feeAmountMinor,
            currency: $currency,
            providerStatus: strtolower($this->requiredTransactionValue($transaction, 'status')),
            verificationSource: 'netbank-vca-transaction-history',
            payloadHash: hash('sha256', json_encode(
                $this->canonicalize($transaction),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            )),
            providerOperationId: $this->optionalString(data_get($transaction, 'operation_id')),
            requestId: $this->optionalString(data_get($transaction, 'reference_id')),
            fundingAddress: 'sha256:'.hash('sha256', $request->fundingAddress),
            providerAccountReference: 'sha256:'.hash('sha256', $providerAccountNumber),
            occurredAt: $this->optionalDate(data_get($transaction, 'date')),
            settledAt: $this->settledAt($transaction),
            webhookReceiptId: $request->webhookReceiptId,
            metadata: [
                'description' => $this->optionalString(data_get($transaction, 'description')),
                'type' => $this->optionalString(data_get($transaction, 'type')),
                'settlement_rail' => $this->optionalString(data_get($transaction, 'settlement_rail')),
                'destination_verified' => true,
                'address_purpose' => $request->purpose->value,
                'expected_currency_matches' => $currency === $this->currency($request->currency),
                'trigger' => strtolower(trim($request->verificationSource)),
            ],
        );
    }

    /**
     * @return array{account_number: string, alias: string}
     */
    private function routingProfile(?FundingDestinationData $destination): array
    {
        if ($destination !== null) {
            if (strtolower(trim($destination->provider)) !== self::Provider) {
                throw new InvalidArgumentException('The reusable NetBank address cannot use another provider.');
            }

            if ($destination->destinationType !== 'bank_account') {
                throw new InvalidArgumentException('The reusable NetBank funding destination must be a bank account.');
            }
        }

        foreach ([
            'api_url',
            'token_url',
            'client_id',
            'client_secret',
            'reference_key',
            'qr_endpoint',
            'qr_merchant_name',
            'qr_merchant_city',
        ] as $key) {
            $this->requiredConfig($key);
        }

        $accountNumber = $destination?->bankAccountNumber ?? $this->requiredConfig('corporate_account_number');
        $alias = $destination?->routingAlias ?? $this->requiredConfig('vca_alias');

        if (preg_match('/\A[0-9-]{8,32}\z/', $accountNumber) !== 1) {
            throw new NetbankFundingConfigurationException('NetBank corporate account number must contain 8 to 32 digits or hyphens.');
        }

        if (preg_match('/\A\d{5}\z/', $alias) !== 1) {
            throw new NetbankFundingConfigurationException('NetBank VCA alias must contain exactly five digits.');
        }

        return [
            'account_number' => $accountNumber,
            'alias' => $alias,
        ];
    }

    private function numericReference(string $ownerReference): string
    {
        $ownerReference = trim($ownerReference);

        if ($ownerReference === '') {
            throw new InvalidArgumentException('A reusable funding address owner reference is required.');
        }

        $digest = hash_hmac(
            'sha256',
            'reusable-funding-address|'.$ownerReference,
            $this->requiredConfig('reference_key'),
            true,
        );
        $numeric = '';

        for ($index = 0; $index < 16; $index++) {
            $numeric .= (string) (ord($digest[$index]) % 10);
        }

        return $numeric;
    }

    private function fundingAddress(string $ownerReference, string $alias): string
    {
        return $alias.$this->numericReference($ownerReference);
    }

    private function standingAddressReference(StandingFundingAddressRequestData $request): string
    {
        return implode('|', [
            'standing-funding-address',
            trim($request->ownerReference),
            trim($request->accountReference),
            $request->purpose->value,
            $this->currency($request->currency),
        ]);
    }

    private function assertFundingAddress(string $fundingAddress, string $alias): void
    {
        if (
            preg_match('/\A\d{21}\z/', $fundingAddress) !== 1
            || ! str_starts_with($fundingAddress, $alias)
        ) {
            throw new InvalidArgumentException('A valid reusable NetBank VCA is required.');
        }
    }

    private function isIncomingCredit(array $transaction, string $fundingAddress): bool
    {
        $destinationAlias = $this->optionalString(data_get($transaction, 'destination_account.account_alias'));

        return strcasecmp((string) data_get($transaction, 'type'), 'Credit') === 0
            && strcasecmp((string) data_get($transaction, 'description'), 'EXTERNAL_TRANSFER_INCOMING') === 0
            && $destinationAlias !== null
            && hash_equals($fundingAddress, $destinationAlias);
    }

    private function amountMinor(array $transaction): int
    {
        $amount = data_get($transaction, 'amount.num');

        if ((! is_string($amount) && ! is_int($amount)) || preg_match('/\A\d+\z/', (string) $amount) !== 1) {
            throw new InvalidArgumentException('NetBank transaction amount must use integer minor units.');
        }

        return (int) $amount;
    }

    private function feeAmountMinor(array $transaction, string $currency): int
    {
        $total = 0;

        foreach ((array) data_get($transaction, 'fees', []) as $fee) {
            if (! is_array($fee)) {
                throw new InvalidArgumentException('NetBank transaction fee has an invalid shape.');
            }

            if ($this->currency((string) data_get($fee, 'amount.cur', $currency)) !== $currency) {
                throw new InvalidArgumentException('NetBank transaction fee currency does not match the incoming amount.');
            }

            $value = (string) data_get($fee, 'amount.num', '0');

            if (preg_match('/\A\d+\z/', $value) !== 1) {
                throw new InvalidArgumentException('NetBank transaction fee must use integer minor units.');
            }

            $total += (int) $value;
        }

        return $total;
    }

    private function settledAt(array $transaction): ?DateTimeImmutable
    {
        foreach (array_reverse((array) data_get($transaction, 'status_details', [])) as $detail) {
            if (is_array($detail) && strcasecmp((string) data_get($detail, 'status'), 'Settled') === 0) {
                return $this->optionalDate(data_get($detail, 'updated'));
            }
        }

        return strcasecmp((string) data_get($transaction, 'status'), 'Settled') === 0
            ? $this->optionalDate(data_get($transaction, 'updated'))
            : null;
    }

    private function optionalDate(mixed $value): ?DateTimeImmutable
    {
        $value = $this->optionalString($value);

        return $value === null ? null : new DateTimeImmutable($value);
    }

    private function requiredTransactionValue(array $transaction, string $key): string
    {
        $value = $this->optionalString(data_get($transaction, $key));

        if ($value === null) {
            throw new InvalidArgumentException("NetBank transaction [{$key}] is required.");
        }

        return $value;
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalize(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(fn (mixed $entry): mixed => is_array($entry) ? $this->canonicalize($entry) : $entry, $item)
                    : $this->canonicalize($item);
            }
        }

        return $value;
    }

    private function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (preg_match('/\A[A-Z]{3}\z/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be a three-letter code.');
        }

        return $currency;
    }

    private function requiredConfig(string $key): string
    {
        $value = config("payment-gateway.netbank.funding.{$key}");

        if (! is_string($value) || trim($value) === '') {
            throw NetbankFundingConfigurationException::missing($key);
        }

        return trim($value);
    }
}
