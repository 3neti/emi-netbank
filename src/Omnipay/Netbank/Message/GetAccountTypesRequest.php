<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\HasOAuth2;
use Omnipay\Common\Message\AbstractRequest;

class GetAccountTypesRequest extends AbstractRequest
{
    use HasOAuth2;

    public function getData()
    {
        return [];
    }

    public function sendData($data)
    {
        $token = $this->getAccessToken();

        try {
            $response = $this->httpClient->request(
                'GET',
                $this->getAccountTypesEndpoint(),
                [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ]
            );

            $body = $response->getBody()->getContents();
            $responseData = json_decode($body, true);

            return new GetAccountTypesResponse($this, $responseData);

        } catch (\Exception $e) {
            return new GetAccountTypesResponse($this, [
                'error' => $e->getMessage(),
                'status_code' => method_exists($e, 'getCode') ? $e->getCode() : 500,
            ]);
        }
    }

    public function getAccountTypesEndpoint(): string
    {
        return $this->getParameter('accountTypesEndpoint');
    }

    public function setAccountTypesEndpoint($value)
    {
        return $this->setParameter('accountTypesEndpoint', $value);
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
