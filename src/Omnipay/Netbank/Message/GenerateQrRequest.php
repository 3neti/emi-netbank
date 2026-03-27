<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\HasOAuth2;
use Omnipay\Common\Message\AbstractRequest;

/**
 * NetBank Generate QR Request
 *
 * Generates a QR code for a given account. This QR code can be used
 * by customers to initiate payments to the account.
 *
 * Example:
 * <code>
 * $request = $gateway->generateQr([
 *     'accountNumber' => '1234567890',
 *     'reference' => 'QR-' . uniqid(),
 *     'amount' => 50000, // Optional - fixed amount QR
 * ]);
 *
 * $response = $request->send();
 * if ($response->isSuccessful()) {
 *     $qrCode = $response->getQrCode();
 *     $qrUrl = $response->getQrUrl();
 * }
 * </code>
 */
class GenerateQrRequest extends AbstractRequest
{
    use HasOAuth2;

    /**
     * Get the account number
     */
    public function getAccountNumber()
    {
        return $this->getParameter('accountNumber');
    }

    /**
     * Set the account number
     */
    public function setAccountNumber($value)
    {
        return $this->setParameter('accountNumber', $value);
    }

    /**
     * Get the reference
     */
    public function getReference()
    {
        return $this->getParameter('reference');
    }

    /**
     * Set the reference
     */
    public function setReference($value)
    {
        return $this->setParameter('reference', $value);
    }

    /**
     * Get the amount (optional - for fixed amount QR codes)
     */
    public function getAmount()
    {
        return $this->getParameter('amount');
    }

    /**
     * Set the amount (optional - for fixed amount QR codes)
     */
    public function setAmount($value)
    {
        return $this->setParameter('amount', $value);
    }

    /**
     * Get the currency
     */
    public function getCurrency()
    {
        return $this->getParameter('currency');
    }

    /**
     * Set the currency
     */
    public function setCurrency($value)
    {
        return $this->setParameter('currency', $value);
    }

    /**
     * Get the QR endpoint
     */
    public function getQrEndpoint()
    {
        return $this->getParameter('qrEndpoint');
    }

    /**
     * Set the QR endpoint
     */
    public function setQrEndpoint($value)
    {
        return $this->setParameter('qrEndpoint', $value);
    }

    /**
     * Validate the request
     *
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    public function getData()
    {
        $this->validate('accountNumber');

        $amount = $this->getAmount();
        $clientAlias = $this->getClientAlias() ?? config('omnipay.gateways.netbank.options.clientAlias');

        // Build payload matching x-change structure
        $data = [
            'merchant_name' => $this->getMerchantName() ?? config('app.name', 'Merchant'),
            'merchant_city' => $this->getMerchantCity() ?? 'Manila',
            'qr_type' => $amount ? 'Dynamic' : 'Static', // Dynamic if amount specified, Static otherwise
            'qr_transaction_type' => 'P2M', // Person-to-Merchant
            'destination_account' => $clientAlias.$this->getAccountNumber(), // Format: alias + account
            'resolution' => 480,
            'amount' => [
                'cur' => $this->getCurrency() ?? 'PHP',
                'num' => $amount ? (string) $amount : '', // Empty string for static QR
            ],
        ];

        return $data;
    }

    /**
     * Send the request with authentication
     *
     * @param  mixed  $data
     * @return GenerateQrResponse
     */
    public function sendData($data)
    {
        try {
            $token = $this->getAccessToken();

            logger()->info('[GenerateQR] Sending request', [
                'endpoint' => $this->getEndpoint(),
                'payload' => $data,
            ]);

            $httpResponse = $this->httpClient->request(
                'POST',
                $this->getEndpoint(),
                [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                json_encode($data)
            );

            $body = $httpResponse->getBody()->getContents();
            logger()->info('[GenerateQR] Response received', ['body' => $body]);

            $responseData = json_decode($body, true);

            return $this->response = new GenerateQrResponse($this, $responseData);

        } catch (\Exception $e) {
            logger()->error('[GenerateQR] Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->response = new GenerateQrResponse($this, [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => true,
            ]);
        }
    }

    /**
     * Get the API endpoint for QR generation
     */
    public function getEndpoint(): string
    {
        // Use the qrEndpoint directly from config
        return $this->getQrEndpoint();
    }

    /**
     * Get Client ID for OAuth2 (required by HasOAuth2 trait)
     */
    protected function getClientId(): string
    {
        return $this->getParameter('clientId');
    }

    /**
     * Set Client ID
     */
    public function setClientId($value)
    {
        return $this->setParameter('clientId', $value);
    }

    /**
     * Get Client Secret for OAuth2 (required by HasOAuth2 trait)
     */
    protected function getClientSecret(): string
    {
        return $this->getParameter('clientSecret');
    }

    /**
     * Set Client Secret
     */
    public function setClientSecret($value)
    {
        return $this->setParameter('clientSecret', $value);
    }

    /**
     * Get Token Endpoint for OAuth2 (required by HasOAuth2 trait)
     */
    protected function getTokenEndpoint(): string
    {
        return $this->getParameter('tokenEndpoint');
    }

    /**
     * Set Token Endpoint
     */
    public function setTokenEndpoint($value)
    {
        return $this->setParameter('tokenEndpoint', $value);
    }

    /**
     * Get Client Alias
     */
    public function getClientAlias()
    {
        return $this->getParameter('clientAlias');
    }

    /**
     * Set Client Alias
     */
    public function setClientAlias($value)
    {
        return $this->setParameter('clientAlias', $value);
    }

    /**
     * Get Merchant Name
     */
    public function getMerchantName()
    {
        return $this->getParameter('merchantName');
    }

    /**
     * Set Merchant Name
     */
    public function setMerchantName($value)
    {
        return $this->setParameter('merchantName', $value);
    }

    /**
     * Get Merchant City
     */
    public function getMerchantCity()
    {
        return $this->getParameter('merchantCity');
    }

    /**
     * Set Merchant City
     */
    public function setMerchantCity($value)
    {
        return $this->setParameter('merchantCity', $value);
    }
}
