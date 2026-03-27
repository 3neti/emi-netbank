<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank\Traits;

use Brick\Money\Money;
use Illuminate\Support\Facades\Http;
use LBHurtado\Merchant\Contracts\MerchantInterface;
use LBHurtado\PaymentGateway\Data\Netbank\Generate\GeneratePayloadData;

trait CanGenerate
{
    public function generate(string $account, Money $amount, array $merchantData = []): string
    {
        // If merchant data provided (unauthenticated flow), use it
        if (! empty($merchantData) && isset($merchantData['user'])) {
            $user = $merchantData['user'];
        } else {
            // Otherwise, use authenticated user (authenticated flow)
            $user = auth()->user();
        }

        if (! $user instanceof MerchantInterface) {
            throw new \LogicException('User must implement MerchantInterface to use this functionality.');
        }

        // Build a unique cache key
        $amountKey = (string) $amount;
        $currency = $amount->getCurrency()->getCurrencyCode();
        $userKey = $user->getKey();
        $cacheKey = "qr:merchant:{$userKey}:{$account}:{$currency}_{$amountKey}";

        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($user, $account, $amount) {
            // If we missed the cache, generate a new QR
            $token = $this->getAccessToken();
            $payload_data = GeneratePayloadData::fromUserAccountAmount($user, $account, $amount);
            $payload = $payload_data->toArray();

            logger()->info('Generating new QR code for deposit', [
                'merchant' => $user->getKey(),
                'account' => $account,
                'amount' => $amount->getAmount()->toFloat(),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])->post(config('disbursement.server.qr-end-point'), $payload);

            return 'data:image/png;base64,'.$response->json('qr_code');
        });
    }
}
