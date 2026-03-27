<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\HasOAuth2;
use Omnipay\Common\Message\AbstractRequest;

/**
 * NetBank Check Disbursement Status Request
 *
 * Retrieves the status of a disbursement transaction.
 *
 * NetBank API Documentation:
 * https://virtual.netbank.ph/docs#operation/Disburse-To-Account_RetrieveAccount-To-AccountTransactionDetails
 *
 * Example:
 * <code>
 * $request = $gateway->checkDisbursementStatus([
 *     'transactionId' => '260741510',
 * ]);
 *
 * $response = $request->send();
 * if ($response->isSuccessful()) {
 *     $status = $response->getStatus();       // Pending, ForSettlement, Settled, Rejected
 *     $rawData = $response->getRawData();
 * }
 * </code>
 */
class CheckDisbursementStatusRequest extends AbstractRequest
{
    use HasOAuth2;

    /**
     * Get the transaction ID
     */
    public function getTransactionId()
    {
        return $this->getParameter('transactionId');
    }

    /**
     * Set the transaction ID
     */
    public function setTransactionId($value)
    {
        return $this->setParameter('transactionId', $value);
    }

    /**
     * Get the status endpoint
     */
    public function getStatusEndpoint()
    {
        return $this->getParameter('statusEndpoint');
    }

    /**
     * Set the status endpoint
     */
    public function setStatusEndpoint($value)
    {
        return $this->setParameter('statusEndpoint', $value);
    }

    /**
     * Validate the request
     *
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    public function getData()
    {
        // Validate transaction ID is provided
        $this->validate('transactionId');

        return [];
    }

    /**
     * Send the request with authentication
     *
     * @param  mixed  $data
     * @return CheckDisbursementStatusResponse
     */
    public function sendData($data)
    {
        try {
            $token = $this->getAccessToken();

            // GET request to status endpoint
            $httpResponse = $this->httpClient->request(
                'GET',
                $this->getEndpoint(),
                [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            );

            $responseData = json_decode($httpResponse->getBody()->getContents(), true);

            return $this->response = new CheckDisbursementStatusResponse($this, $responseData);
        } catch (\Exception $e) {
            // Return error response
            return $this->response = new CheckDisbursementStatusResponse(
                $this,
                [
                    'error' => $e->getMessage(),
                    'status' => 'Pending',  // Default to pending on error
                ]
            );
        }
    }

    /**
     * Get the API endpoint for status check
     *
     * Format: GET https://api.netbank.ph/v1/transactions/{transaction_id}
     */
    public function getEndpoint(): string
    {
        $baseUrl = $this->getStatusEndpoint();
        $transactionId = $this->getTransactionId();

        return rtrim($baseUrl, '/').'/'.$transactionId;
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
