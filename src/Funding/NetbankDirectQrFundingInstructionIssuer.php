<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use DateTimeImmutable;
use InvalidArgumentException;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingConfigurationException;

final readonly class NetbankDirectQrFundingInstructionIssuer
{
    public function __construct(
        private NetbankFundingApiClient $client,
    ) {}

    public function create(FundingInstructionRequestData $request): FundingInstructionsData
    {
        $this->assertProvider($request->provider);

        if ($request->amountMinor <= 0) {
            throw new InvalidArgumentException('Funding amount must be greater than zero.');
        }

        $currency = $this->currency($request->currency);
        $routing = $this->routingProfile($request->destination);
        $reference = $this->numericReference($request->fundingReference);
        $vcaNumber = $routing['alias'].$reference;
        $issuedAt = DateTimeImmutable::createFromInterface(now());
        $expiresAt = $request->expiresAt ?? $issuedAt->modify('+30 minutes');

        if ($expiresAt <= $issuedAt) {
            throw new InvalidArgumentException('Funding instruction expiry must be in the future.');
        }

        $qrCode = $this->client->generateQrCode(
            vcaNumber: $vcaNumber,
            amountMinor: $request->amountMinor,
            currency: $currency,
            merchant: $request->merchant,
        );

        return new FundingInstructionsData(
            provider: 'netbank',
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
                'delivery' => 'scan-to-pay',
                'amount_control' => 'qr-embedded',
                'address_registration' => 'not-requested',
            ],
            qrCode: new FundingQrCodeData(
                mimeType: 'image/png',
                base64Payload: $qrCode,
                qrMode: 'dynamic',
                transactionType: 'p2m',
                embeddedAmount: true,
                providerGenerated: true,
            ),
        );
    }

    /**
     * @return array{account_name: string, alias: string}
     */
    private function routingProfile(?FundingDestinationData $destination): array
    {
        if ($destination !== null) {
            $this->assertProvider($destination->provider);

            if ($destination->destinationType !== 'bank_account') {
                throw new InvalidArgumentException('The NetBank funding destination must be a bank account.');
            }
        }

        $accountNumber = $destination?->bankAccountNumber
            ?? $this->requiredConfig('corporate_account_number');
        $accountName = $destination?->bankAccountName
            ?? $this->requiredConfig('corporate_account_name');
        $alias = $destination?->routingAlias
            ?? $this->requiredConfig('vca_alias');

        if (preg_match('/\A[0-9-]{8,32}\z/', $accountNumber) !== 1) {
            throw new NetbankFundingConfigurationException(
                'NetBank corporate account number must contain 8 to 32 digits or hyphens.',
            );
        }

        if (preg_match('/\A\d{5}\z/', $alias) !== 1) {
            throw new NetbankFundingConfigurationException(
                'NetBank VCA alias must contain exactly five digits.',
            );
        }

        if (trim($accountName) === '') {
            throw new NetbankFundingConfigurationException(
                'NetBank dedicated routing requires an account name.',
            );
        }

        return [
            'account_name' => trim($accountName),
            'alias' => $alias,
        ];
    }

    private function numericReference(string $fundingReference): string
    {
        $reference = trim($fundingReference);

        if ($reference === '') {
            throw new InvalidArgumentException('Funding reference is required.');
        }

        $digest = hash_hmac(
            'sha256',
            $reference,
            $this->requiredConfig('reference_key'),
            true,
        );
        $numeric = '';

        for ($index = 0; $index < 16; $index++) {
            $numeric .= (string) (ord($digest[$index]) % 10);
        }

        return $numeric;
    }

    private function assertProvider(string $provider): void
    {
        if (strtolower(trim($provider)) !== 'netbank') {
            throw new InvalidArgumentException('The NetBank direct QR issuer cannot handle this provider.');
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
}
