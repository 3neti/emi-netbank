<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\HasOAuth2;
use Omnipay\Common\Message\AbstractRequest;

class CreateAccountRequest extends AbstractRequest
{
    use HasOAuth2;

    public function getData()
    {
        $params = $this->getParameters();

        // Remove gateway-specific parameters
        unset($params['clientId'], $params['clientSecret'], $params['tokenEndpoint'],
            $params['accountEndpoint'], $params['testMode']);

        return $params;
    }

    public function sendData($data)
    {
        $token = $this->getAccessToken();

        try {
            $response = $this->httpClient->request(
                'POST',
                $this->getAccountEndpoint(),
                [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ],
                json_encode($data)
            );

            $body = $response->getBody()->getContents();
            $responseData = json_decode($body, true);

            return new CreateAccountResponse($this, $responseData);

        } catch (\Exception $e) {
            return new CreateAccountResponse($this, [
                'error' => $e->getMessage(),
                'status_code' => method_exists($e, 'getCode') ? $e->getCode() : 500,
            ]);
        }
    }

    public function getAccountEndpoint(): string
    {
        return $this->getParameter('accountEndpoint');
    }

    public function setAccountEndpoint($value)
    {
        return $this->setParameter('accountEndpoint', $value);
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
