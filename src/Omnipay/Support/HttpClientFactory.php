<?php

namespace LBHurtado\PaymentGateway\Omnipay\Support;

use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use Omnipay\Common\Http\Client;
use Omnipay\Common\Http\ClientInterface;

/**
 * Factory for creating configured HTTP clients for Omnipay gateways
 */
class HttpClientFactory
{
    /**
     * Create a configured HTTP client with timeout settings
     *
     * @param  array  $config  Configuration options (timeout, connect_timeout, etc.)
     */
    public static function create(array $config = []): ClientInterface
    {
        $guzzleConfig = [
            'timeout' => $config['timeout'] ?? 30,
            'connect_timeout' => $config['connect_timeout'] ?? 10,
            'http_errors' => false, // Don't throw on 4xx/5xx responses
            'verify' => true, // Verify SSL certificates
        ];

        // Create Guzzle client with configuration
        $guzzleClient = new GuzzleClient($guzzleConfig);

        // Wrap in php-http adapter (HttpClient interface)
        $httpClient = new GuzzleAdapter($guzzleClient);

        // Wrap in Omnipay's Client (ClientInterface)
        return new Client($httpClient);
    }
}
