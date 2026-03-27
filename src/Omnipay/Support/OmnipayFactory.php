<?php

namespace LBHurtado\PaymentGateway\Omnipay\Support;

use Illuminate\Support\Facades\Config;
use Omnipay\Common\GatewayInterface;

/**
 * Factory for creating Omnipay gateway instances from configuration
 */
class OmnipayFactory
{
    /**
     * Create a gateway instance by name
     *
     * @param  string  $name  Gateway name (e.g., 'netbank', 'icash')
     *
     * @throws \RuntimeException
     */
    public static function create(string $name): GatewayInterface
    {
        $config = Config::get("omnipay.gateways.{$name}");

        if (! $config) {
            throw new \RuntimeException("Gateway '{$name}' not found in omnipay config");
        }

        if (! isset($config['class'])) {
            throw new \RuntimeException("Gateway '{$name}' config missing 'class' key");
        }

        $gatewayClass = $config['class'];

        if (! class_exists($gatewayClass)) {
            throw new \RuntimeException("Gateway class '{$gatewayClass}' does not exist");
        }

        // Create HTTP client with timeout configuration
        $httpConfig = Config::get('omnipay.http', []);
        $httpClient = HttpClientFactory::create($httpConfig);

        $gateway = new $gatewayClass($httpClient);

        if (! $gateway instanceof GatewayInterface) {
            throw new \RuntimeException("Gateway class '{$gatewayClass}' must implement GatewayInterface");
        }

        // Initialize gateway with options from config
        if (isset($config['options'])) {
            $gateway->initialize($config['options']);
        }

        return $gateway;
    }

    /**
     * Create the default gateway
     */
    public static function createDefault(): GatewayInterface
    {
        $defaultGateway = Config::get('omnipay.default', 'netbank');

        return static::create($defaultGateway);
    }

    /**
     * Get all available gateway names
     */
    public static function available(): array
    {
        return array_keys(Config::get('omnipay.gateways', []));
    }

    /**
     * Check if a gateway is available
     */
    public static function has(string $name): bool
    {
        return Config::has("omnipay.gateways.{$name}");
    }
}
