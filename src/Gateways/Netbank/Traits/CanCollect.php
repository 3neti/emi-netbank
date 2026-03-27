<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Data\Netbank\DirectCheckout\CollectionRequestData;
use LBHurtado\PaymentGateway\Data\Netbank\DirectCheckout\CollectionResponseData;
use LBHurtado\PaymentGateway\Data\Netbank\DirectCheckout\CollectionTransactionData;
use LBHurtado\PaymentGateway\Data\Netbank\DirectCheckout\FinancialInstitutionData;

trait CanCollect
{
    /**
     * Initiate a collection via Direct Checkout.
     */
    public function initiateCollection(CollectionRequestData $request): ?CollectionResponseData
    {
        try {
            // Check if in fake/mock mode
            $useFake = config('payment-gateway.netbank.direct_checkout.use_fake', false);

            if ($useFake) {
                Log::info('[Netbank DirectCheckout FAKE] Initiating collection (mock mode)', [
                    'reference_no' => $request->reference_no,
                    'amount' => $request->amount,
                    'institution' => $request->institution_code,
                ]);

                // Return fake response
                return CollectionResponseData::from([
                    'redirect_url' => url('/topup/callback?reference_no='.$request->reference_no.'&mock=1'),
                    'reference_no' => $request->reference_no,
                ]);
            }

            $endpoint = config('payment-gateway.netbank.direct_checkout.endpoint');
            $payload = $request->toPayload();

            Log::info('[Netbank DirectCheckout] Initiating collection', [
                'reference_no' => $request->reference_no,
                'amount' => $request->amount,
                'institution' => $request->institution_code,
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::warning('[Netbank DirectCheckout] Collection initiation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            Log::info('[Netbank DirectCheckout] Collection initiated successfully', [
                'reference_no' => $data['reference_no'] ?? null,
                'redirect_url' => $data['redirect_url'] ?? null,
            ]);

            return CollectionResponseData::from([
                'redirect_url' => $data['redirect_url'],
                'reference_no' => $data['reference_no'],
            ]);
        } catch (\Throwable $e) {
            Log::error('[Netbank DirectCheckout] Initiation error', [
                'error' => $e->getMessage(),
                'reference_no' => $request->reference_no,
            ]);

            return null;
        }
    }

    /**
     * Get collection transaction status.
     */
    public function getCollectionTransaction(string $referenceNo): ?CollectionTransactionData
    {
        try {
            $endpoint = config('payment-gateway.netbank.direct_checkout.transaction_endpoint');
            $accessKey = config('payment-gateway.netbank.direct_checkout.access_key');

            $response = Http::withHeaders([
                'reference-no' => $referenceNo,
                'x-access-key' => $accessKey,
            ])->get($endpoint);

            if (! $response->successful()) {
                Log::warning('[Netbank DirectCheckout] Transaction retrieval failed', [
                    'reference_no' => $referenceNo,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            return CollectionTransactionData::from([
                'payment_id' => $data['payment_id'],
                'payment_status' => $data['payment_status'],
                'reference_no' => $data['reference_no'],
                'amount_value' => $data['amount']['value'],
                'amount_currency' => $data['amount']['currency'],
                'institution_code' => $data['institution_code'] ?? null,
                'created_at' => $data['created_at'] ?? null,
                'updated_at' => $data['updated_at'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Netbank DirectCheckout] Transaction retrieval error', [
                'error' => $e->getMessage(),
                'reference_no' => $referenceNo,
            ]);

            return null;
        }
    }

    /**
     * List available financial institutions.
     *
     * @return Collection<FinancialInstitutionData>
     */
    public function listFinancialInstitutions(): Collection
    {
        try {
            $endpoint = config('payment-gateway.netbank.direct_checkout.institutions_endpoint');
            $accessKey = config('payment-gateway.netbank.direct_checkout.access_key');

            $response = Http::withHeaders([
                'x-access-key' => $accessKey,
            ])->get($endpoint);

            if (! $response->successful()) {
                Log::warning('[Netbank DirectCheckout] Institutions list failed', [
                    'status' => $response->status(),
                ]);

                return collect();
            }

            $data = $response->json();

            return collect($data['financial_institutions'] ?? [])
                ->map(fn ($institution) => FinancialInstitutionData::from([
                    'institution_code' => $institution['institution_code'],
                    'name' => $institution['name'],
                    'logo_url' => $institution['logo_url'] ?? null,
                ]));
        } catch (\Throwable $e) {
            Log::error('[Netbank DirectCheckout] Institutions list error', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }
}
