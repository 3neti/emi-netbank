<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingConfigurationException;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;

class NetbankFundingApiClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
    ) {}

    public function generateAliasToken(string $accountNumber, string $alias): string
    {
        $response = $this->api()->post('/v1/vca/pre-transaction/token', [
            'vca_alias' => $alias,
            'account_number' => $accountNumber,
        ]);

        $this->assertSuccessful($response, 'generate-vca-alias-token');
        $token = $response->json('vca_alias_token');

        if (! is_string($token) || trim($token) === '') {
            throw NetbankFundingRequestFailed::invalidResponse('generate-vca-alias-token');
        }

        return trim($token);
    }

    public function registerPreTransactionReference(string $reference, ?string $aliasToken = null): void
    {
        $response = $this->api()->post('/v1/vca/pre-transaction/register', [
            'vca_alias_token' => $aliasToken ?? $this->requiredConfig('vca_alias_token'),
            'vca_reference_number' => $reference,
        ]);

        $this->assertSuccessful($response, 'register-vca');
    }

    public function createExactLimit(
        string $vcaNumber,
        int $amountMinor,
        string $currency,
        DateTimeImmutable $validFrom,
        DateTimeImmutable $validTo,
        ?string $accountNumber = null,
    ): void {
        $response = $this->api()->post('/v1/vca/create', [
            'vca_number' => $vcaNumber,
            'account_number' => $accountNumber ?? $this->requiredConfig('corporate_account_number'),
            'limits' => [
                'is_one_time_usage' => true,
                'maximum_amount' => [
                    'cur' => $currency,
                    'num' => (string) $amountMinor,
                ],
                'minimum_amount' => [
                    'cur' => $currency,
                    'num' => (string) $amountMinor,
                ],
                'valid_from' => $validFrom->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
                'valid_to' => $validTo->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
            ],
        ]);

        $this->assertSuccessful($response, 'create-vca-limit');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function transactions(string $vcaNumber, ?string $accountNumber = null): array
    {
        $response = $this->api()->get('/v1/vca/'.rawurlencode($vcaNumber).'/transactions', [
            'account_number' => $accountNumber ?? $this->requiredConfig('corporate_account_number'),
            'start_date' => now()->subDays((int) config('payment-gateway.netbank.funding.verification_lookback_days', 7))->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
            'limit' => 100,
            'offset' => 0,
        ]);

        $this->assertSuccessful($response, 'retrieve-vca-transactions');
        $transactions = $response->json('transactions');

        if (! is_array($transactions)) {
            throw NetbankFundingRequestFailed::invalidResponse('retrieve-vca-transactions');
        }

        return array_values(array_filter($transactions, 'is_array'));
    }

    private function api(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim($this->requiredConfig('api_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withToken($this->accessToken())
            ->connectTimeout((int) config('payment-gateway.netbank.funding.connect_timeout_seconds', 5))
            ->timeout((int) config('payment-gateway.netbank.funding.timeout_seconds', 15));
    }

    private function accessToken(): string
    {
        $clientId = $this->requiredConfig('client_id');
        $cacheKey = 'netbank:funding:access-token:'.hash('sha256', $clientId);
        $cached = $this->cache->get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->http
            ->asForm()
            ->acceptJson()
            ->withBasicAuth($clientId, $this->requiredConfig('client_secret'))
            ->connectTimeout((int) config('payment-gateway.netbank.funding.connect_timeout_seconds', 5))
            ->timeout((int) config('payment-gateway.netbank.funding.timeout_seconds', 15))
            ->post($this->requiredConfig('token_url'), [
                'grant_type' => 'client_credentials',
            ]);

        $this->assertSuccessful($response, 'oauth-token');
        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw NetbankFundingRequestFailed::invalidResponse('oauth-token');
        }

        $expiresIn = max(60, (int) $response->json('expires_in', 3600));
        $this->cache->put($cacheKey, $token, max(1, $expiresIn - 60));

        return $token;
    }

    private function assertSuccessful(Response $response, string $operation): void
    {
        if (! $response->successful()) {
            throw NetbankFundingRequestFailed::forOperation($operation, $response->status());
        }
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
