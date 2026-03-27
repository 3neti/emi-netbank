<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Traits;

use Omnipay\Common\Exception\InvalidResponseException;

/**
 * HasOAuth2 Trait
 *
 * Provides OAuth2 client credentials authentication with token caching
 * and expiry management for gateway requests.
 */
trait HasOAuth2
{
    protected ?string $accessToken = null;

    protected ?int $tokenExpiry = null;

    /**
     * Get OAuth2 access token (with caching)
     *
     * @return string The access token
     *
     * @throws InvalidResponseException
     */
    protected function getAccessToken(): string
    {
        // Return cached token if still valid
        if ($this->accessToken && $this->tokenExpiry && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }

        // Prepare credentials for Basic Auth
        $credentials = base64_encode(
            $this->getClientId().':'.$this->getClientSecret()
        );

        // Request new token
        try {
            $response = $this->httpClient->request(
                'POST',
                $this->getTokenEndpoint(),
                [
                    'Authorization' => 'Basic '.$credentials,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                http_build_query(['grant_type' => 'client_credentials'])
            );

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (! isset($data['access_token'])) {
                throw new InvalidResponseException('No access token in response');
            }

            // Cache token with expiry
            $this->accessToken = $data['access_token'];
            $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600);

            return $this->accessToken;

        } catch (\Exception $e) {
            throw new InvalidResponseException(
                'Failed to obtain access token: '.$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Clear cached access token (useful for testing)
     */
    protected function clearAccessToken(): void
    {
        $this->accessToken = null;
        $this->tokenExpiry = null;
    }

    /**
     * Check if access token is cached and valid
     */
    protected function hasValidAccessToken(): bool
    {
        return $this->accessToken !== null
            && $this->tokenExpiry !== null
            && $this->tokenExpiry > time();
    }
}
