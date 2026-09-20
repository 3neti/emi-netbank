<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use DateTimeImmutable;
use Generator;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use LBHurtado\EmiCore\Data\Funding\FundingQrMerchantData;
use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\PaymentGateway\Exceptions\NetbankBalanceReadException;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingConfigurationException;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;
use LBHurtado\PaymentGateway\Support\NetbankLivePreflightFailureMapper;
use Throwable;

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

    public function registerPreTransactionReference(string $reference, string $aliasToken): void
    {
        $response = $this->api()->post('/v1/vca/pre-transaction/register', [
            'vca_alias_token' => $aliasToken,
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

    public function generateQrCode(
        string $vcaNumber,
        int $amountMinor,
        string $currency,
        ?FundingQrMerchantData $merchant = null,
    ): string {
        $merchantPayload = $this->merchantPayload($merchant);

        return $this->generateQrCodeFromPayload([
            ...$merchantPayload,
            'qr_type' => 'Dynamic',
            'qr_transaction_type' => 'P2M',
            'destination_account' => $vcaNumber,
            'resolution' => (int) config('payment-gateway.netbank.funding.qr_resolution', 480),
            'amount' => [
                'cur' => $currency,
                'num' => (string) $amountMinor,
            ],
        ]);
    }

    public function generateReusableQrCode(
        string $vcaNumber,
        string $currency,
        ?FundingQrMerchantData $merchant = null,
    ): string {
        $merchantPayload = $this->merchantPayload($merchant);

        return $this->generateQrCodeFromPayload([
            ...$merchantPayload,
            'qr_type' => 'Static',
            'qr_transaction_type' => 'P2M',
            'destination_account' => $vcaNumber,
            'resolution' => (int) config('payment-gateway.netbank.funding.qr_resolution', 480),
            'amount' => [
                'cur' => $currency,
                'num' => '',
            ],
        ]);
    }

    /**
     * @return array{balance: int, available_balance: int, currency: string, as_of: ?string, raw: array<string, mixed>}
     */
    public function balance(string $accountNumber): array
    {
        try {
            $response = $this->api()->get(
                rtrim($this->requiredConfig('balance_endpoint'), '/')
                .'/'.rawurlencode($accountNumber),
            );

            if (! $response->successful()) {
                throw new NetbankBalanceReadException(
                    NetbankLivePreflightFailureMapper::fromHttpStatus(
                        $response->status(),
                    ),
                );
            }

            $data = $response->json();

            if (! is_array($data)) {
                throw new NetbankBalanceReadException(
                    ProviderLivePreflightFailureCode::InvalidBalanceResponse,
                );
            }

            $balance = $this->minorAmount($data['balance']['num'] ?? null);
            $availableBalance = $this->minorAmount(
                $data['available_balance']['num']
                    ?? $data['balance']['num']
                    ?? null,
            );
            $currency = $data['balance']['cur'] ?? null;

            if (
                $balance === null
                || $availableBalance === null
                || ! is_string($currency)
                || trim($currency) === ''
            ) {
                throw new NetbankBalanceReadException(
                    ProviderLivePreflightFailureCode::InvalidBalanceResponse,
                );
            }

            $asOf = $data['created_date'] ?? null;

            return [
                'balance' => $balance,
                'available_balance' => $availableBalance,
                'currency' => mb_strtoupper(trim($currency)),
                'as_of' => is_string($asOf) ? $asOf : null,
                'raw' => $data,
            ];
        } catch (NetbankBalanceReadException $exception) {
            throw $exception;
        } catch (NetbankFundingRequestFailed $exception) {
            $failureCode = $exception->operation === 'oauth-token'
                ? $this->authenticationFailureCode($exception)
                : ProviderLivePreflightFailureCode::ProviderUnavailable;

            throw new NetbankBalanceReadException($failureCode);
        } catch (Throwable $exception) {
            throw new NetbankBalanceReadException(
                NetbankLivePreflightFailureMapper::fromThrowable($exception),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function generateQrCodeFromPayload(array $payload): string
    {
        $response = $this->api()->post($this->requiredConfig('qr_endpoint'), $payload);
        $this->assertSuccessful($response, 'generate-qrph');
        $encoded = $response->json('qr_code');

        if (! is_string($encoded) || ! $this->isBase64Png($encoded)) {
            throw NetbankFundingRequestFailed::invalidResponse('generate-qrph');
        }

        return $encoded;
    }

    /**
     * @return array{merchant_name: string, merchant_city: string}
     */
    private function merchantPayload(?FundingQrMerchantData $merchant): array
    {
        $name = $this->normalizedMerchantValue(
            $merchant?->displayName ?? $this->requiredConfig('qr_merchant_name'),
            'name',
            (int) config(
                'payment-gateway.netbank.funding.qr_merchant_name_max_length',
                25,
            ),
        );
        $city = $this->normalizedMerchantValue(
            $merchant?->city ?? $this->requiredConfig('qr_merchant_city'),
            'city',
            (int) config(
                'payment-gateway.netbank.funding.qr_merchant_city_max_length',
                15,
            ),
        );

        return [
            'merchant_name' => $name,
            'merchant_city' => $city,
        ];
    }

    private function normalizedMerchantValue(
        string $value,
        string $field,
        int $maximumLength,
    ): string {
        if (
            ! mb_check_encoding($value, 'UTF-8')
            || preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1
        ) {
            throw new NetbankFundingConfigurationException(
                "NetBank QR merchant {$field} contains unsupported characters.",
            );
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        if (
            $normalized === ''
            || $maximumLength < 1
            || mb_strlen($normalized, 'UTF-8') > $maximumLength
        ) {
            throw new NetbankFundingConfigurationException(
                "NetBank QR merchant {$field} must contain 1 to {$maximumLength} characters.",
            );
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function transactions(string $vcaNumber, ?string $accountNumber = null): array
    {
        $response = $this->api()->get('/v1/vca/'.rawurlencode($vcaNumber).'/transactions', [
            'account_number' => $accountNumber ?? $this->requiredConfig('corporate_account_number'),
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

    /**
     * Stream a bounded corporate-account transaction history without persisting provider data.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function accountTransactions(
        string $accountNumber,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?int $maximumRows = null,
    ): Generator {
        $accountNumber = trim($accountNumber);

        if ($accountNumber === '' || mb_strlen($accountNumber) > 191) {
            throw new \InvalidArgumentException('A valid NetBank account number is required.');
        }

        if ($endDate <= $startDate) {
            throw new \InvalidArgumentException('The NetBank transaction-history end date must be after the start date.');
        }

        $maximumRangeDays = max(
            1,
            (int) config('payment-gateway.netbank.funding.account_history.maximum_range_days', 3660),
        );

        if ($startDate->diff($endDate)->days > $maximumRangeDays) {
            throw new \InvalidArgumentException('The NetBank transaction-history date range exceeds the configured limit.');
        }

        $configuredMaximumRows = max(
            1,
            (int) config('payment-gateway.netbank.funding.account_history.maximum_rows', 10_000),
        );
        $maximumRows ??= $configuredMaximumRows;

        if ($maximumRows < 1 || $maximumRows > $configuredMaximumRows) {
            throw new \InvalidArgumentException('The NetBank transaction-history row limit is invalid.');
        }

        $pageLimit = min(
            100,
            max(1, (int) config('payment-gateway.netbank.funding.account_history.page_limit', 100)),
        );
        $maximumPages = max(
            1,
            (int) config('payment-gateway.netbank.funding.account_history.maximum_pages', 100),
        );
        $endpoint = str_replace(
            '{account_number}',
            rawurlencode($accountNumber),
            $this->requiredConfig('account_transactions_endpoint'),
        );
        $offset = 0;
        $emitted = 0;
        $page = 0;
        $previousPageHash = null;
        $seenTransactionIds = [];

        while ($emitted < $maximumRows && $page < $maximumPages) {
            $requestedLimit = min($pageLimit, $maximumRows - $emitted);
            $response = $this->api()->get($endpoint, [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'limit' => $requestedLimit,
                'offset' => $offset,
            ]);

            $this->assertSuccessful($response, 'retrieve-account-transactions');
            $transactions = $response->json('result');

            if (! is_array($transactions)) {
                $transactions = $response->json('transactions');
            }

            if (! is_array($transactions) || count($transactions) > $requestedLimit) {
                throw NetbankFundingRequestFailed::invalidResponse('retrieve-account-transactions');
            }

            $transactions = array_values($transactions);
            $pageHash = hash('sha256', serialize($transactions));

            if ($transactions !== [] && hash_equals((string) $previousPageHash, $pageHash)) {
                throw NetbankFundingRequestFailed::invalidResponse('retrieve-account-transactions');
            }

            $previousPageHash = $pageHash;
            $page++;

            foreach ($transactions as $transaction) {
                if (! is_array($transaction)) {
                    throw NetbankFundingRequestFailed::invalidResponse('retrieve-account-transactions');
                }

                $transactionId = $transaction['transaction_id'] ?? null;

                if (
                    ! is_string($transactionId)
                    || trim($transactionId) === ''
                    || mb_strlen($transactionId) > 191
                    || isset($seenTransactionIds[$transactionId])
                ) {
                    throw NetbankFundingRequestFailed::invalidResponse('retrieve-account-transactions');
                }

                $seenTransactionIds[$transactionId] = true;

                yield $transaction;
                $emitted++;
            }

            if (count($transactions) < $requestedLimit) {
                break;
            }

            $offset += $requestedLimit;
        }
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

    private function authenticationFailureCode(
        NetbankFundingRequestFailed $exception,
    ): ProviderLivePreflightFailureCode {
        if (
            $exception->invalidResponse
            || in_array($exception->status, [400, 401, 403], true)
        ) {
            return ProviderLivePreflightFailureCode::AuthenticationFailed;
        }

        return $exception->status === null
            ? ProviderLivePreflightFailureCode::ProviderUnavailable
            : NetbankLivePreflightFailureMapper::fromHttpStatus(
                $exception->status,
            );
    }

    private function minorAmount(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (
            ! is_string($value)
            || preg_match('/\A\d+\z/', trim($value)) !== 1
        ) {
            return null;
        }

        $amount = filter_var(trim($value), FILTER_VALIDATE_INT);

        return $amount === false ? null : $amount;
    }

    private function requiredConfig(string $key): string
    {
        $value = config("payment-gateway.netbank.funding.{$key}");

        if (! is_string($value) || trim($value) === '') {
            throw NetbankFundingConfigurationException::missing($key);
        }

        return trim($value);
    }

    private function isBase64Png(string $encoded): bool
    {
        $encoded = trim($encoded);

        if ($encoded === '' || preg_match('/\A[A-Za-z0-9+\/]+={0,2}\z/', $encoded) !== 1) {
            return false;
        }

        $decoded = base64_decode($encoded, true);

        if (! is_string($decoded) || ! str_starts_with($decoded, "\x89PNG\r\n\x1a\n")) {
            return false;
        }

        $image = @getimagesizefromstring($decoded);

        return is_array($image) && ($image['mime'] ?? null) === 'image/png';
    }
}
