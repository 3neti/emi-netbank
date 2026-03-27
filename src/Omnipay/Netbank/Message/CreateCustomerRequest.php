<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\HasOAuth2;
use Omnipay\Common\Message\AbstractRequest;

/**
 * Create Customer Request (Account-As-A-Service)
 *
 * Creates a customer record (CIF) in NetBank's core banking system.
 * This is a prerequisite for creating bank accounts.
 */
class CreateCustomerRequest extends AbstractRequest
{
    use HasOAuth2;

    public function getData()
    {
        // Get customer data that was set via setParameter('customerData', $data)
        $data = $this->getParameter('customerData');

        error_log('customerData param: '.json_encode($data));

        if (! $data || ! is_array($data)) {
            error_log('customerData is empty or not array!');

            return [];
        }

        // Apply any transformations needed
        if (isset($data['gender'])) {
            $data['gender'] = strtoupper($data['gender']);
        }
        if (isset($data['birth_place_country'])) {
            $data['birth_place_country'] = strtoupper($data['birth_place_country']);
        }
        if (isset($data['civil_status'])) {
            $data['civil_status'] = strtoupper($data['civil_status']);
        }
        if (isset($data['customer_risk_level'])) {
            $data['customer_risk_level'] = strtoupper($data['customer_risk_level']);
        }

        return $data;
    }

    public function sendData($data)
    {
        $token = $this->getAccessToken();

        // Debug: Log the JSON being sent
        $json = json_encode($data, JSON_PRETTY_PRINT);
        error_log('CreateCustomerRequest JSON: '.$json);

        try {
            $response = $this->httpClient->request(
                'POST',
                $this->getCustomerEndpoint(),
                [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ],
                json_encode($data)
            );

            $body = $response->getBody()->getContents();
            $responseData = json_decode($body, true);

            return new CreateCustomerResponse($this, $responseData);

        } catch (\Exception $e) {
            return new CreateCustomerResponse($this, [
                'error' => $e->getMessage(),
                'status_code' => method_exists($e, 'getCode') ? $e->getCode() : 500,
            ]);
        }
    }

    // Parameter getters/setters

    public function getCustomerEndpoint(): string
    {
        return $this->getParameter('customerEndpoint');
    }

    public function setCustomerEndpoint($value)
    {
        return $this->setParameter('customerEndpoint', $value);
    }

    public function getClientId(): string
    {
        return $this->getParameter('clientId');
    }

    public function setClientId($value)
    {
        return $this->setParameter('clientId', $value);
    }

    public function getClientSecret(): string
    {
        return $this->getParameter('clientSecret');
    }

    public function setClientSecret($value)
    {
        return $this->setParameter('clientSecret', $value);
    }

    public function getTokenEndpoint(): string
    {
        return $this->getParameter('tokenEndpoint');
    }

    public function setTokenEndpoint($value)
    {
        return $this->setParameter('tokenEndpoint', $value);
    }
}
