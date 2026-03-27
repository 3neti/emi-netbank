<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank;

use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\CheckBalanceRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\CheckDisbursementStatusRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\ConfirmDisbursementRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\CreateAccountRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\CreateCustomerRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\DisburseRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\GenerateQrRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\GetAccountTypesRequest;
use Omnipay\Common\AbstractGateway;

/**
 * NetBank Gateway
 *
 * Provides access to NetBank payment gateway operations including
 * QR code generation, disbursements, and balance checking with
 * support for INSTAPAY and PESONET settlement rails.
 */
class Gateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'Netbank';
    }

    public function getDefaultParameters(): array
    {
        return [
            'clientId' => '',
            'clientSecret' => '',
            'tokenEndpoint' => '',
            'apiUrl' => '',  // Simplified API base URL
            'apiEndpoint' => '',
            'qrEndpoint' => '',
            'statusEndpoint' => '',
            'balanceEndpoint' => '',
            'customerEndpoint' => '',  // Account-As-A-Service
            'accountEndpoint' => '',    // Account-As-A-Service
            'accountTypesEndpoint' => '',  // Account-As-A-Service
            'testMode' => false,
            'rails' => [],
        ];
    }

    // Parameter getters/setters

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

    public function getApiUrl(): string
    {
        return $this->getParameter('apiUrl');
    }

    public function setApiUrl($value)
    {
        return $this->setParameter('apiUrl', $value);
    }

    public function getApiEndpoint(): string
    {
        return $this->getParameter('apiEndpoint');
    }

    public function setApiEndpoint($value)
    {
        return $this->setParameter('apiEndpoint', $value);
    }

    public function getQrEndpoint(): string
    {
        return $this->getParameter('qrEndpoint');
    }

    public function setQrEndpoint($value)
    {
        return $this->setParameter('qrEndpoint', $value);
    }

    public function getStatusEndpoint(): string
    {
        return $this->getParameter('statusEndpoint');
    }

    public function setStatusEndpoint($value)
    {
        return $this->setParameter('statusEndpoint', $value);
    }

    public function getBalanceEndpoint(): string
    {
        return $this->getParameter('balanceEndpoint');
    }

    public function setBalanceEndpoint($value)
    {
        return $this->setParameter('balanceEndpoint', $value);
    }

    public function getCustomerEndpoint(): string
    {
        return $this->getParameter('customerEndpoint');
    }

    public function setCustomerEndpoint($value)
    {
        return $this->setParameter('customerEndpoint', $value);
    }

    public function getAccountEndpoint(): string
    {
        return $this->getParameter('accountEndpoint');
    }

    public function setAccountEndpoint($value)
    {
        return $this->setParameter('accountEndpoint', $value);
    }

    public function getAccountTypesEndpoint(): string
    {
        return $this->getParameter('accountTypesEndpoint');
    }

    public function setAccountTypesEndpoint($value)
    {
        return $this->setParameter('accountTypesEndpoint', $value);
    }

    public function getTestMode()
    {
        return $this->getParameter('testMode');
    }

    public function setTestMode($value)
    {
        return $this->setParameter('testMode', $value);
    }

    public function getRails(): array
    {
        return $this->getParameter('rails');
    }

    public function setRails($value)
    {
        return $this->setParameter('rails', $value);
    }

    /**
     * Check if gateway supports a specific settlement rail
     */
    public function supportsRail(SettlementRail $rail): bool
    {
        $rails = $this->getRails();

        return isset($rails[$rail->value]) && ($rails[$rail->value]['enabled'] ?? false);
    }

    /**
     * Get rail configuration
     */
    public function getRailConfig(SettlementRail $rail): ?array
    {
        $rails = $this->getRails();

        return $rails[$rail->value] ?? null;
    }

    // Custom gateway operations

    /**
     * Generate QR code for payment
     */
    public function generateQr(array $options = []): GenerateQrRequest
    {
        return $this->createRequest(GenerateQrRequest::class, $options);
    }

    /**
     * Disburse funds to a recipient
     */
    public function disburse(array $options = []): DisburseRequest
    {
        return $this->createRequest(DisburseRequest::class, $options);
    }

    /**
     * Confirm a disbursement operation
     */
    public function confirmDisbursement(array $options = []): ConfirmDisbursementRequest
    {
        return $this->createRequest(ConfirmDisbursementRequest::class, $options);
    }

    /**
     * Check account balance
     */
    public function checkBalance(array $options = []): CheckBalanceRequest
    {
        return $this->createRequest(CheckBalanceRequest::class, $options);
    }

    /**
     * Check disbursement transaction status
     */
    public function checkDisbursementStatus(array $options = []): CheckDisbursementStatusRequest
    {
        return $this->createRequest(CheckDisbursementStatusRequest::class, $options);
    }

    /**
     * Create a customer record (Account-As-A-Service)
     */
    public function createCustomer(array $options = []): CreateCustomerRequest
    {
        return $this->createRequest(CreateCustomerRequest::class, $options);
    }

    /**
     * Create a bank account for a customer (Account-As-A-Service)
     */
    public function createAccount(array $options = []): CreateAccountRequest
    {
        return $this->createRequest(CreateAccountRequest::class, $options);
    }

    /**
     * Get available account types (Account-As-A-Service)
     */
    public function getAccountTypes(array $options = []): GetAccountTypesRequest
    {
        return $this->createRequest(GetAccountTypesRequest::class, $options);
    }
}
