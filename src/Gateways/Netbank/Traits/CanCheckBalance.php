<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\PaymentGateway\Support\NetbankLivePreflightFailureMapper;
use Throwable;

trait CanCheckBalance
{
    /**
     * Check account balance.
     *
     * @param  string  $accountNumber  Account number to check
     * @return array{balance: int, available_balance: int, currency: string, as_of: ?string, raw: array, failure_code?: string}
     */
    public function checkAccountBalance(string $accountNumber): array
    {
        try {
            $endpoint = config('disbursement.server.balance-endpoint', config('omnipay.gateways.netbank.options.balanceEndpoint'));

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get($endpoint.'/'.$accountNumber);

            if (! $response->successful()) {
                Log::warning('[Netbank] Balance check failed', [
                    'status' => $response->status(),
                    'failure_code' => NetbankLivePreflightFailureMapper::fromHttpStatus(
                        $response->status(),
                    )->value,
                ]);

                return $this->failedBalanceResponse(
                    NetbankLivePreflightFailureMapper::fromHttpStatus(
                        $response->status(),
                    ),
                );
            }

            $data = $response->json();

            if (! is_array($data)) {
                return $this->failedBalanceResponse(
                    ProviderLivePreflightFailureCode::InvalidBalanceResponse,
                );
            }

            // NetBank returns balance as {"cur": "PHP", "num": "135000"}
            $balance = isset($data['balance']['num']) ? (int) $data['balance']['num'] : 0;
            $availableBalance = isset($data['available_balance']['num']) ? (int) $data['available_balance']['num'] : $balance;
            $currency = $data['balance']['cur'] ?? 'PHP';
            $asOf = $data['created_date'] ?? null;

            Log::info('[Netbank] Balance checked', [
                'status' => 'success',
            ]);

            return [
                'balance' => $balance,
                'available_balance' => $availableBalance,
                'currency' => $currency,
                'as_of' => $asOf,
                'raw' => $data,
            ];

        } catch (Throwable $exception) {
            $failureCode = NetbankLivePreflightFailureMapper::fromThrowable(
                $exception,
            );
            Log::error('[Netbank] Balance check error', [
                'failure_code' => $failureCode->value,
            ]);

            return $this->failedBalanceResponse($failureCode);
        }
    }

    /**
     * @return array{balance: int, available_balance: int, currency: string, as_of: null, raw: array, failure_code: string}
     */
    private function failedBalanceResponse(
        ProviderLivePreflightFailureCode $failureCode,
    ): array {
        return [
            'balance' => 0,
            'available_balance' => 0,
            'currency' => 'PHP',
            'as_of' => null,
            'raw' => [],
            'failure_code' => $failureCode->value,
        ];
    }
}
