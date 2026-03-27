<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait CanCheckBalance
{
    /**
     * Check account balance.
     *
     * @param  string  $accountNumber  Account number to check
     * @return array{balance: int, available_balance: int, currency: string, as_of: ?string, raw: array}
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
                    'account' => $accountNumber,
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);

                return [
                    'balance' => 0,
                    'available_balance' => 0,
                    'currency' => 'PHP',
                    'as_of' => null,
                    'raw' => [],
                ];
            }

            $data = $response->json();

            // NetBank returns balance as {"cur": "PHP", "num": "135000"}
            $balance = isset($data['balance']['num']) ? (int) $data['balance']['num'] : 0;
            $availableBalance = isset($data['available_balance']['num']) ? (int) $data['available_balance']['num'] : $balance;
            $currency = $data['balance']['cur'] ?? 'PHP';
            $asOf = $data['created_date'] ?? null;

            Log::info('[Netbank] Balance checked', [
                'account' => $accountNumber,
                'balance' => $balance,
            ]);

            return [
                'balance' => $balance,
                'available_balance' => $availableBalance,
                'currency' => $currency,
                'as_of' => $asOf,
                'raw' => $data,
            ];

        } catch (\Throwable $e) {
            Log::error('[Netbank] Balance check error', [
                'account' => $accountNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'balance' => 0,
                'available_balance' => 0,
                'currency' => 'PHP',
                'as_of' => null,
                'raw' => [],
            ];
        }
    }
}
