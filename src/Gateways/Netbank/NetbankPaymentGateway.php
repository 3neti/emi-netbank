<?php

namespace LBHurtado\PaymentGateway\Gateways\Netbank;

use Illuminate\Support\Facades\Http;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\PaymentGateway\Gateways\Netbank\Traits\CanCheckBalance;
use LBHurtado\PaymentGateway\Gateways\Netbank\Traits\CanCollect;
use LBHurtado\PaymentGateway\Gateways\Netbank\Traits\CanConfirmDeposit;
use LBHurtado\PaymentGateway\Gateways\Netbank\Traits\CanDisburse;
use LBHurtado\PaymentGateway\Gateways\Netbank\Traits\CanGenerate;

class NetbankPaymentGateway implements PaymentGatewayInterface
{
    use CanCheckBalance;
    use CanCollect;
    use CanConfirmDeposit;
    use CanDisburse;
    use CanGenerate;

    protected function getAccessToken(): string
    {
        $credentials = base64_encode(
            config('disbursement.client.id').':'.config('disbursement.client.secret')
        );

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$credentials,
        ])->asForm()->post(config('disbursement.server.token-end-point'), [
            'grant_type' => 'client_credentials',
        ]);

        return $response->json('access_token');
    }

    /**
     * Get the transaction fee for a specific settlement rail.
     *
     * @param  SettlementRail  $rail  The settlement rail
     * @return int Fee amount in minor units (centavos)
     */
    public function getRailFee(SettlementRail $rail): int
    {
        $railsConfig = config('omnipay.gateways.netbank.options.rails', []);
        $railConfig = $railsConfig[$rail->value] ?? [];

        return $railConfig['fee'] ?? 0;
    }
}
