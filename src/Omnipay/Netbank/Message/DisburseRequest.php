<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\AppliesKycWorkaround;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\HasOAuth2;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\ValidatesSettlementRail;
use Omnipay\Common\Http\Exception\NetworkException;
use Omnipay\Common\Http\Exception\RequestException;
use Omnipay\Common\Message\AbstractRequest;

/**
 * NetBank Disburse Request
 *
 * Handles disbursement requests to NetBank with settlement rail validation
 * and KYC workarounds.
 */
class DisburseRequest extends AbstractRequest
{
    use AppliesKycWorkaround;
    use HasOAuth2;
    use ValidatesSettlementRail;

    public function getData(): array
    {
        // Validate required parameters
        $this->validate(
            'amount',
            'accountNumber',
            'bankCode',
            'reference',
            'via'
        );

        // Parse rail enum
        $rail = SettlementRail::from($this->getVia());

        // Validate settlement rail
        $this->validateSettlementRail(
            $this->getBankCode(),
            $rail,
            $this->getAmount()
        );

        // Build NetBank API payload matching x-change package structure
        $payload = [
            'reference_id' => $this->getReference(),
            'amount' => [
                'cur' => $this->getCurrency() ?? 'PHP',
                'num' => (string) $this->getAmount(), // MUST be string, not integer
            ],
            'settlement_rail' => $rail->value,
            'source_account_number' => $this->getSourceAccountNumber() ?? config('omnipay.gateways.netbank.options.sourceAccountNumber'),
            'destination_account' => [
                'bank_code' => $this->getBankCode(),
                'account_number' => $this->getAccountNumber(),
            ],
            'recipient' => [
                'name' => $this->getAccountNumber(), // Use account as name for simplicity
            ],
            'sender' => [
                'name' => config('app.name', 'System'),
                'customer_id' => $this->getSenderCustomerId() ?? config('omnipay.gateways.netbank.options.senderCustomerId'),
            ],
        ];

        // Apply KYC workaround (inject random address to both sender and recipient)
        // NetBank requires address for both parties
        $this->applyKycWorkaround($payload, 'sender');
        $this->applyKycWorkaround($payload, 'recipient');

        // Add optional remarks field (for GCash memo smoke test)
        if ($remarks = $this->getRemarks()) {
            $payload['remarks'] = $remarks;
        }

        // Add optional additional sender info (camelCase per NetBank API docs)
        if ($additionalSenderInfo = $this->getAdditionalSenderInfo()) {
            $payload['additionalSenderInfo'] = $additionalSenderInfo;
        }

        return $payload;
    }

    public function sendData($data): DisburseResponse
    {
        try {
            // Get OAuth token
            $token = $this->getAccessToken();

            // Make HTTP request
            $httpResponse = $this->httpClient->request(
                'POST',
                $this->getEndpoint(),
                [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                json_encode($data)
            );

            // Parse response
            $body = $httpResponse->getBody()->getContents();
            $responseData = json_decode($body, true);

            return $this->response = new DisburseResponse($this, $responseData);

        } catch (NetworkException $e) {
            // Network errors: timeouts, connection failures, DNS issues
            Log::error('[DisburseRequest] Network error during disbursement', [
                'error' => $e->getMessage(),
                'reference' => $data['reference_id'] ?? null,
                'amount' => $data['amount']['num'] ?? null,
                'type' => 'network_timeout',
            ]);

            return $this->response = new DisburseResponse($this, [
                'success' => false,
                'message' => 'Network timeout or connection failure',
                'error' => true,
                'error_type' => 'network_timeout',
                'error_details' => $e->getMessage(),
            ]);

        } catch (RequestException $e) {
            // Request errors: malformed requests, client/server errors
            Log::error('[DisburseRequest] Request error during disbursement', [
                'error' => $e->getMessage(),
                'reference' => $data['reference_id'] ?? null,
                'amount' => $data['amount']['num'] ?? null,
                'type' => 'request_error',
            ]);

            return $this->response = new DisburseResponse($this, [
                'success' => false,
                'message' => 'Invalid request or server error',
                'error' => true,
                'error_type' => 'request_error',
                'error_details' => $e->getMessage(),
            ]);

        } catch (\Exception $e) {
            // Catch-all for unexpected errors
            Log::error('[DisburseRequest] Unexpected error during disbursement', [
                'error' => $e->getMessage(),
                'reference' => $data['reference_id'] ?? null,
                'amount' => $data['amount']['num'] ?? null,
                'type' => 'unknown_error',
            ]);

            return $this->response = new DisburseResponse($this, [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => true,
                'error_type' => 'unknown_error',
            ]);
        }
    }

    protected function getEndpoint(): string
    {
        return $this->getApiEndpoint();
    }

    // Parameter getters/setters

    public function getAmount()
    {
        return $this->getParameter('amount');
    }

    public function setAmount($value)
    {
        return $this->setParameter('amount', $value);
    }

    public function getAccountNumber()
    {
        return $this->getParameter('accountNumber');
    }

    public function setAccountNumber($value)
    {
        return $this->setParameter('accountNumber', $value);
    }

    public function getBankCode()
    {
        return $this->getParameter('bankCode');
    }

    public function setBankCode($value)
    {
        return $this->setParameter('bankCode', $value);
    }

    public function getReference()
    {
        return $this->getParameter('reference');
    }

    public function setReference($value)
    {
        return $this->setParameter('reference', $value);
    }

    public function getVia()
    {
        return $this->getParameter('via');
    }

    public function setVia($value)
    {
        return $this->setParameter('via', $value);
    }

    public function getCurrency()
    {
        return $this->getParameter('currency');
    }

    public function setCurrency($value)
    {
        return $this->setParameter('currency', $value);
    }

    // Additional parameters from gateway/env
    public function getSourceAccountNumber()
    {
        return $this->getParameter('sourceAccountNumber');
    }

    public function setSourceAccountNumber($value)
    {
        return $this->setParameter('sourceAccountNumber', $value);
    }

    public function getSenderCustomerId()
    {
        return $this->getParameter('senderCustomerId');
    }

    public function setSenderCustomerId($value)
    {
        return $this->setParameter('senderCustomerId', $value);
    }

    public function getRecipientName()
    {
        return $this->getParameter('recipientName');
    }

    public function setRecipientName($value)
    {
        return $this->setParameter('recipientName', $value);
    }

    public function getSenderName()
    {
        return $this->getParameter('senderName');
    }

    public function setSenderName($value)
    {
        return $this->setParameter('senderName', $value);
    }

    // Gateway parameter access (for traits)

    protected function getApiEndpoint(): string
    {
        return $this->getParameter('apiEndpoint');
    }

    public function setApiEndpoint($value)
    {
        return $this->setParameter('apiEndpoint', $value);
    }

    protected function getClientId(): string
    {
        return $this->getParameter('clientId');
    }

    public function setClientId($value)
    {
        return $this->setParameter('clientId', $value);
    }

    protected function getClientSecret(): string
    {
        return $this->getParameter('clientSecret');
    }

    public function setClientSecret($value)
    {
        return $this->setParameter('clientSecret', $value);
    }

    protected function getTokenEndpoint(): string
    {
        return $this->getParameter('tokenEndpoint');
    }

    public function setTokenEndpoint($value)
    {
        return $this->setParameter('tokenEndpoint', $value);
    }

    public function getRails()
    {
        return $this->getParameter('rails');
    }

    public function setRails($value)
    {
        return $this->setParameter('rails', $value);
    }

    // Remarks field (for GCash memo smoke test)
    public function getRemarks()
    {
        return $this->getParameter('remarks');
    }

    public function setRemarks($value)
    {
        return $this->setParameter('remarks', $value);
    }

    public function getAdditionalSenderInfo()
    {
        return $this->getParameter('additionalSenderInfo');
    }

    public function setAdditionalSenderInfo($value)
    {
        return $this->setParameter('additionalSenderInfo', $value);
    }
}
