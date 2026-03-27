<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\HasOAuth2;
use Omnipay\Common\Message\AbstractRequest;

/**
 * NetBank Check Balance Request
 *
 * Retrieves the current balance for a given account.
 *
 * Example:
 * <code>
 * $request = $gateway->checkBalance([
 *     'accountNumber' => '1234567890',
 * ]);
 *
 * $response = $request->send();
 * if ($response->isSuccessful()) {
 *     $balance = $response->getBalance();
 *     $currency = $response->getCurrency();
 * }
 * </code>
 */
class CheckBalanceRequest extends AbstractRequest
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
     * Get the balance endpoint
     */
    public function getBalanceEndpoint()
    {
        return $this->getParameter('balanceEndpoint');
    }

    /**
     * Set the balance endpoint
     */
    public function setBalanceEndpoint($value)
    {
        return $this->setParameter('balanceEndpoint', $value);
    }

    /**
     * Validate the request
     *
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    public function getData()
    {
        // Validate account number is provided
        $this->validate('accountNumber');

        return [];
    }

    /**
     * Send the request with authentication
     *
     * @param  mixed  $data
     * @return CheckBalanceResponse
     */
    public function sendData($data)
    {
        try {
            $endpoint = $this->getEndpoint();
            $token = $this->getAccessToken();

            // Simple GET request - balance is for the authenticated account
            $httpResponse = $this->httpClient->request(
                'GET',
                $endpoint,
                [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            );

            $responseBody = $httpResponse->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            return $this->response = new CheckBalanceResponse($this, $responseData);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[CheckBalanceRequest] Request failed', [
                'endpoint' => $this->getEndpoint(),
                'error' => $e->getMessage(),
            ]);

            return $this->response = new CheckBalanceResponse($this, [
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the API endpoint for balance check
     * Uses Account Details API: GET /accounts/{account_number}
     */
    public function getEndpoint(): string
    {
        $baseUrl = $this->getBalanceEndpoint();
        $accountNumber = $this->getAccountNumber();

        return rtrim($baseUrl, '/').'/'.$accountNumber;
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
}
