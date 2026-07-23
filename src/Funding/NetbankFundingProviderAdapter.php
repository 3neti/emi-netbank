<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use DateTimeImmutable;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\FundingProviderAdapter;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderEventHintData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Data\Funding\WebhookAuthenticationData;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingAmbiguous;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingConfigurationException;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingNotVerified;

class NetbankFundingProviderAdapter implements FundingProviderAdapter
{
    private const Provider = 'netbank';

    public function __construct(
        private readonly NetbankFundingApiClient $client,
    ) {}

    public function providerCode(): string
    {
        return self::Provider;
    }

    public function createFundingInstructions(FundingInstructionRequestData $request): FundingInstructionsData
    {
        $this->assertProvider($request->provider);
        $this->assertPositiveAmount($request->amountMinor);
        $currency = $this->currency($request->currency);
        $routing = $this->routingProfile($request->destination);
        $alias = $routing['alias'];
        $reference = $this->numericReference($request->fundingReference);
        $vcaNumber = $alias.$reference;
        $issuedAt = DateTimeImmutable::createFromInterface(now());
        $expiresAt = $request->expiresAt
            ?? $issuedAt->modify('+30 minutes');

        if ($expiresAt <= $issuedAt) {
            throw new InvalidArgumentException('Funding instruction expiry must be in the future.');
        }

        if ((bool) config('payment-gateway.netbank.funding.pre_transaction_validation_enabled', true)) {
            $this->client->registerPreTransactionReference($reference, $routing['alias_token']);
        }

        if ((bool) config('payment-gateway.netbank.funding.exact_limits_enabled', true)) {
            $this->client->createExactLimit(
                vcaNumber: $vcaNumber,
                amountMinor: $request->amountMinor,
                currency: $currency,
                validFrom: $issuedAt,
                validTo: $expiresAt,
                accountNumber: $routing['account_number'],
            );
        }

        return new FundingInstructionsData(
            provider: self::Provider,
            providerReference: $vcaNumber,
            amountMinor: $request->amountMinor,
            currency: $currency,
            expiresAt: $expiresAt,
            fundingAddress: $vcaNumber,
            displayData: [
                'institution' => 'NetBank',
                'account_name' => $routing['account_name'],
                'destination_account' => $vcaNumber,
                'amount_minor' => $request->amountMinor,
                'currency' => $currency,
                'one_time' => true,
                'delivery' => 'manual-bank-or-wallet-transfer',
            ],
        );
    }

    public function authenticateWebhook(ProviderWebhookRequestData $request): WebhookAuthenticationData
    {
        $this->assertProvider($request->provider);
        $allowedIps = array_values(array_filter(array_map(
            static fn (mixed $ip): string => trim((string) $ip),
            (array) config('payment-gateway.netbank.funding.webhook.allowed_ips', []),
        )));
        $sourceIp = trim((string) $request->sourceIp);

        if ($allowedIps === [] || $sourceIp === '' || ! in_array($sourceIp, $allowedIps, true)) {
            return new WebhookAuthenticationData(
                authenticated: false,
                method: 'source-ip-allowlist',
                reason: 'source-ip-not-allowed',
            );
        }

        $contentType = strtolower(trim((string) $request->contentType));

        if (preg_match('/\Atext\/plain(?:;|\z)/', $contentType) !== 1) {
            return new WebhookAuthenticationData(
                authenticated: false,
                method: 'source-ip-allowlist',
                reason: 'unexpected-content-type',
            );
        }

        return new WebhookAuthenticationData(
            authenticated: true,
            method: 'source-ip-allowlist',
        );
    }

    public function normalizeWebhook(ProviderWebhookReceiptData $receipt): ProviderEventHintData
    {
        $this->assertProvider($receipt->provider);

        return new ProviderEventHintData(
            eventType: 'netbank.credit-notification',
        );
    }

    public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
    {
        $this->assertProvider($verification->provider);
        $this->assertPositiveAmount($verification->expectedAmountMinor);
        $currency = $this->currency($verification->currency);
        $routing = $this->routingProfile($verification->destination);
        $vcaNumber = trim((string) $verification->fundingAddress);

        if ($vcaNumber === '' || preg_match('/\A\d{12,}\z/', $vcaNumber) !== 1) {
            throw new InvalidArgumentException('A valid NetBank VCA number is required for verification.');
        }

        $incoming = array_values(array_filter(
            $this->client->transactions($vcaNumber, $routing['account_number']),
            fn (array $transaction): bool => $this->isIncomingCredit($transaction, $vcaNumber),
        ));

        if ($incoming === []) {
            throw NetbankFundingNotVerified::noIncomingTransaction();
        }

        $exact = array_values(array_filter(
            $incoming,
            fn (array $transaction): bool => $this->amountMinor($transaction) === $verification->expectedAmountMinor
                && $this->transactionCurrency($transaction) === $currency,
        ));

        $transaction = match (true) {
            count($exact) === 1 => $exact[0],
            count($exact) > 1 => throw NetbankFundingAmbiguous::multipleTransactions(),
            count($incoming) === 1 => $incoming[0],
            default => throw NetbankFundingAmbiguous::multipleTransactions(),
        };

        $grossAmountMinor = $this->amountMinor($transaction);
        $transactionCurrency = $this->transactionCurrency($transaction);
        $feeAmountMinor = $this->feeAmountMinor($transaction, $transactionCurrency);

        if ($feeAmountMinor > $grossAmountMinor) {
            throw new InvalidArgumentException('NetBank transaction fees exceed the incoming amount.');
        }

        $payloadHash = hash('sha256', json_encode(
            $this->canonicalize($transaction),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));

        return new ProviderFundingObservationData(
            provider: self::Provider,
            providerTransactionId: $this->requiredTransactionValue($transaction, 'transaction_id'),
            grossAmountMinor: $grossAmountMinor,
            feeAmountMinor: $feeAmountMinor,
            netAmountMinor: $grossAmountMinor - $feeAmountMinor,
            currency: $transactionCurrency,
            providerStatus: strtolower($this->requiredTransactionValue($transaction, 'status')),
            verificationSource: 'netbank-vca-transaction-history',
            payloadHash: $payloadHash,
            providerOperationId: $this->optionalString(data_get($transaction, 'operation_id')),
            requestId: $this->optionalString(data_get($transaction, 'reference_id')),
            fundingAddress: 'sha256:'.hash('sha256', $vcaNumber),
            providerAccountReference: 'sha256:'.hash('sha256', $routing['account_number']),
            occurredAt: $this->optionalDate(data_get($transaction, 'date')),
            settledAt: $this->settledAt($transaction),
            webhookReceiptId: $verification->webhookReceiptId,
            metadata: [
                'description' => $this->optionalString(data_get($transaction, 'description')),
                'type' => $this->optionalString(data_get($transaction, 'type')),
                'settlement_rail' => $this->optionalString(data_get($transaction, 'settlement_rail')),
                'destination_verified' => true,
                'expected_amount_matches' => $grossAmountMinor === $verification->expectedAmountMinor,
                'expected_currency_matches' => $transactionCurrency === $currency,
            ],
        );
    }

    private function numericReference(string $fundingReference): string
    {
        $reference = trim($fundingReference);

        if ($reference === '') {
            throw new InvalidArgumentException('Funding reference is required.');
        }

        $digest = hash_hmac('sha256', $reference, $this->requiredConfig('reference_key'), true);
        $numeric = '';

        for ($index = 0; $index < 16; $index++) {
            $numeric .= (string) (ord($digest[$index]) % 10);
        }

        return $numeric;
    }

    /**
     * @return array{account_number: string, account_name: string, alias: string, alias_token: string}
     */
    private function routingProfile(?FundingDestinationData $destination): array
    {
        if ($destination !== null) {
            $this->assertProvider($destination->provider);

            if ($destination->destinationType !== 'bank_account') {
                throw new InvalidArgumentException('The NetBank funding destination must be a bank account.');
            }
        }

        $accountNumber = $destination?->bankAccountNumber ?? $this->requiredConfig('corporate_account_number');
        $accountName = $destination?->bankAccountName ?? $this->requiredConfig('corporate_account_name');
        $alias = $destination?->routingAlias ?? $this->requiredConfig('vca_alias');
        $aliasToken = $destination?->routingCredential ?? $this->requiredConfig('vca_alias_token');

        if (preg_match('/\A\d{8,32}\z/', $accountNumber) !== 1) {
            throw new NetbankFundingConfigurationException('NetBank corporate account number must contain 8 to 32 digits.');
        }

        if (preg_match('/\A\d{5}\z/', $alias) !== 1) {
            throw new NetbankFundingConfigurationException('NetBank VCA alias must contain exactly five digits.');
        }

        if (trim($accountName) === '' || trim($aliasToken) === '') {
            throw new NetbankFundingConfigurationException('NetBank dedicated routing requires an account name and VCA alias token.');
        }

        return [
            'account_number' => $accountNumber,
            'account_name' => trim($accountName),
            'alias' => $alias,
            'alias_token' => trim($aliasToken),
        ];
    }

    private function isIncomingCredit(array $transaction, string $vcaNumber): bool
    {
        $destinationAlias = $this->optionalString(data_get($transaction, 'destination_account.account_alias'));

        return strcasecmp((string) data_get($transaction, 'type'), 'Credit') === 0
            && strcasecmp((string) data_get($transaction, 'description'), 'EXTERNAL_TRANSFER_INCOMING') === 0
            && ($destinationAlias === null || hash_equals($vcaNumber, $destinationAlias));
    }

    private function amountMinor(array $transaction): int
    {
        $amount = data_get($transaction, 'amount.num');

        if (! is_string($amount) && ! is_int($amount)) {
            throw new InvalidArgumentException('NetBank transaction amount is missing.');
        }

        $amount = (string) $amount;

        if (preg_match('/\A\d+\z/', $amount) !== 1) {
            throw new InvalidArgumentException('NetBank transaction amount must use integer minor units.');
        }

        return (int) $amount;
    }

    private function transactionCurrency(array $transaction): string
    {
        return $this->currency((string) data_get($transaction, 'amount.cur'));
    }

    private function feeAmountMinor(array $transaction, string $currency): int
    {
        $total = 0;

        foreach ((array) data_get($transaction, 'fees', []) as $fee) {
            if (! is_array($fee)) {
                throw new InvalidArgumentException('NetBank transaction fee has an invalid shape.');
            }

            $feeCurrency = $this->currency((string) data_get($fee, 'amount.cur', $currency));

            if ($feeCurrency !== $currency) {
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

    private function assertProvider(string $provider): void
    {
        if (strtolower(trim($provider)) !== self::Provider) {
            throw new InvalidArgumentException('The NetBank adapter cannot handle this provider.');
        }
    }

    private function assertPositiveAmount(int $amountMinor): void
    {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Funding amount must be greater than zero.');
        }
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

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
