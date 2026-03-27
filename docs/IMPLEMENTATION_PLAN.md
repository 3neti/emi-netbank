# Omnipay Integration - Implementation Plan

## Overview

This document outlines a comprehensive, phase-based implementation plan for integrating League Omnipay into the payment-gateway package. The plan ensures backward compatibility while progressively migrating to a more maintainable, gateway-agnostic architecture.

---

## Goals

1. **Standardize** payment gateway integrations using Omnipay patterns
2. **Maintain** backward compatibility during migration
3. **Enable** easy addition of new payment gateways (ICash, BDO, GCash, etc.)
4. **Improve** testability and separation of concerns
5. **Keep** existing Laravel integrations working

---

## Pre-Implementation Checklist

- [ ] Review current NetBank implementation
- [ ] Identify all payment operations (generate QR, disburse, confirm)
- [ ] List all NetBank-specific Data classes that need conversion
- [ ] Confirm test coverage for existing functionality
- [ ] Backup current implementation

---

## Phase 1: Foundation Setup (Day 1)

### 1.1 Install Dependencies

**Tasks:**
- Add Omnipay packages to composer.json
- Install HTTP client adapter
- Verify installation

**Commands:**
```bash
cd packages/payment-gateway
composer require league/omnipay:^3.2
composer require omnipay/common:^3.1
composer require php-http/guzzle7-adapter:^1.0
composer require php-http/discovery:^1.14
```

**Verification:**
```bash
composer show league/omnipay
composer show omnipay/common
composer show php-http/guzzle7-adapter
```

**Success Criteria:**
- All packages installed without conflicts
- No breaking changes to existing code
- Tests still pass

---

### 1.2 Create Directory Structure

**Create new directories:**
```
packages/payment-gateway/src/
├── Omnipay/
│   ├── Netbank/
│   │   ├── Gateway.php
│   │   ├── Message/
│   │   │   ├── GenerateQrRequest.php
│   │   │   ├── GenerateQrResponse.php
│   │   │   ├── DisburseRequest.php
│   │   │   ├── DisburseResponse.php
│   │   │   ├── ConfirmDisbursementRequest.php
│   │   │   └── ConfirmDisbursementResponse.php
│   │   └── Traits/
│   │       └── HasOAuth2.php
│   ├── ICash/
│   │   └── Gateway.php (placeholder)
│   └── Support/
│       ├── OmnipayFactory.php
│       └── GatewayResolver.php
└── Services/
    └── OmnipayBridge.php (adapter)
```

**Commands:**
```bash
mkdir -p src/Omnipay/Netbank/Message
mkdir -p src/Omnipay/Netbank/Traits
mkdir -p src/Omnipay/ICash
mkdir -p src/Omnipay/Support
```

**Success Criteria:**
- Directory structure matches Omnipay conventions
- Separates gateway implementations clearly

---

### 1.3 Update Configuration

**Create `config/omnipay.php`:**
```php
<?php

return [
    'gateways' => [
        'netbank' => [
            'class' => \LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway::class,
            'options' => [
                'clientId' => env('NETBANK_CLIENT_ID'),
                'clientSecret' => env('NETBANK_CLIENT_SECRET'),
                'tokenEndpoint' => env('NETBANK_TOKEN_ENDPOINT'),
                'apiEndpoint' => env('NETBANK_DISBURSEMENT_ENDPOINT'),
                'qrEndpoint' => env('NETBANK_QR_ENDPOINT'),
                'statusEndpoint' => env('NETBANK_STATUS_ENDPOINT'),
                'testMode' => env('NETBANK_TEST_MODE', false),
            ],
        ],
        'icash' => [
            'class' => \LBHurtado\PaymentGateway\Omnipay\ICash\Gateway::class,
            'options' => [
                'apiKey' => env('ICASH_API_KEY'),
                'apiSecret' => env('ICASH_API_SECRET'),
                'apiEndpoint' => env('ICASH_API_ENDPOINT'),
                'testMode' => env('ICASH_TEST_MODE', false),
            ],
        ],
    ],
    
    'default' => env('PAYMENT_GATEWAY', 'netbank'),
    
    // Feature flag for gradual rollout
    'use_omnipay' => env('USE_OMNIPAY', false),
];
```

**Update `.env.example`:**
```env
# Payment Gateway
PAYMENT_GATEWAY=netbank
USE_OMNIPAY=false

# NetBank Configuration
NETBANK_CLIENT_ID=
NETBANK_CLIENT_SECRET=
NETBANK_TOKEN_ENDPOINT=
NETBANK_DISBURSEMENT_ENDPOINT=
NETBANK_QR_ENDPOINT=
NETBANK_STATUS_ENDPOINT=
NETBANK_TEST_MODE=false
```

**Success Criteria:**
- Configuration supports both legacy and Omnipay modes
- Environment variables documented
- Feature flag allows safe testing

---

## Phase 2: NetBank Gateway Implementation (Days 2-3)

### 2.1 Create NetBank Gateway Class

**File:** `src/Omnipay/Netbank/Gateway.php`

**Implementation:**
```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank;

use Omnipay\Common\AbstractGateway;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\{
    GenerateQrRequest,
    DisburseRequest,
    ConfirmDisbursementRequest
};

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
            'apiEndpoint' => '',
            'qrEndpoint' => '',
            'statusEndpoint' => '',
            'testMode' => false,
        ];
    }
    
    // Parameter getters/setters (auto-generated via magic methods)
    public function getClientId(): string
    {
        return $this->getParameter('clientId');
    }
    
    public function setClientId(string $value): self
    {
        return $this->setParameter('clientId', $value);
    }
    
    public function getClientSecret(): string
    {
        return $this->getParameter('clientSecret');
    }
    
    public function setClientSecret(string $value): self
    {
        return $this->setParameter('clientSecret', $value);
    }
    
    public function getTokenEndpoint(): string
    {
        return $this->getParameter('tokenEndpoint');
    }
    
    public function setTokenEndpoint(string $value): self
    {
        return $this->setParameter('tokenEndpoint', $value);
    }
    
    public function getApiEndpoint(): string
    {
        return $this->getParameter('apiEndpoint');
    }
    
    public function setApiEndpoint(string $value): self
    {
        return $this->setParameter('apiEndpoint', $value);
    }
    
    public function getQrEndpoint(): string
    {
        return $this->getParameter('qrEndpoint');
    }
    
    public function setQrEndpoint(string $value): self
    {
        return $this->setParameter('qrEndpoint', $value);
    }
    
    public function getStatusEndpoint(): string
    {
        return $this->getParameter('statusEndpoint');
    }
    
    public function setStatusEndpoint(string $value): self
    {
        return $this->setParameter('statusEndpoint', $value);
    }
    
    public function getTestMode(): bool
    {
        return $this->getParameter('testMode');
    }
    
    public function setTestMode(bool $value): self
    {
        return $this->setParameter('testMode', $value);
    }
    
    // Custom gateway operations
    public function generateQr(array $options = []): GenerateQrRequest
    {
        return $this->createRequest(GenerateQrRequest::class, $options);
    }
    
    public function disburse(array $options = []): DisburseRequest
    {
        return $this->createRequest(DisburseRequest::class, $options);
    }
    
    public function confirmDisbursement(array $options = []): ConfirmDisbursementRequest
    {
        return $this->createRequest(ConfirmDisbursementRequest::class, $options);
    }
}
```

**Checklist:**
- [ ] Implements AbstractGateway
- [ ] getName() returns 'Netbank'
- [ ] getDefaultParameters() defines all config options
- [ ] Parameter getters/setters defined
- [ ] Custom methods for each operation

---

### 2.2 Create OAuth2 Trait

**File:** `src/Omnipay/Netbank/Traits/HasOAuth2.php`

**Implementation:**
```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Traits;

use Omnipay\Common\Exception\InvalidResponseException;

trait HasOAuth2
{
    protected ?string $accessToken = null;
    protected ?int $tokenExpiry = null;
    
    protected function getAccessToken(): string
    {
        // Return cached token if still valid
        if ($this->accessToken && $this->tokenExpiry && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }
        
        // Prepare credentials
        $credentials = base64_encode(
            $this->getClientId() . ':' . $this->getClientSecret()
        );
        
        // Request new token
        try {
            $response = $this->httpClient->request(
                'POST',
                $this->getTokenEndpoint(),
                [
                    'Authorization' => 'Basic ' . $credentials,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                http_build_query(['grant_type' => 'client_credentials'])
            );
            
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);
            
            if (!isset($data['access_token'])) {
                throw new InvalidResponseException('No access token in response');
            }
            
            // Cache token
            $this->accessToken = $data['access_token'];
            $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600);
            
            return $this->accessToken;
            
        } catch (\Exception $e) {
            throw new InvalidResponseException(
                'Failed to obtain access token: ' . $e->getMessage()
            );
        }
    }
    
    protected function clearAccessToken(): void
    {
        $this->accessToken = null;
        $this->tokenExpiry = null;
    }
}
```

**Checklist:**
- [ ] Token caching implemented
- [ ] Token expiry checking
- [ ] Error handling for auth failures
- [ ] Clear token method for testing

---

### 2.3 Implement Disburse Request

**File:** `src/Omnipay/Netbank/Message/DisburseRequest.php`

**Implementation:**
```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\HasOAuth2;

class DisburseRequest extends AbstractRequest
{
    use HasOAuth2;
    
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
        
        // Build NetBank API payload
        return [
            'transaction' => [
                'reference' => $this->getReference(),
                'amount' => [
                    'value' => $this->getAmount(),
                    'currency' => $this->getCurrency() ?? 'PHP',
                ],
                'destination' => [
                    'account_number' => $this->getAccountNumber(),
                    'bank_code' => $this->getBankCode(),
                ],
                'settlement_rail' => $this->getVia(),
            ],
        ];
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
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                json_encode($data)
            );
            
            // Parse response
            $body = $httpResponse->getBody()->getContents();
            $responseData = json_decode($body, true);
            
            return $this->response = new DisburseResponse($this, $responseData);
            
        } catch (\Exception $e) {
            return $this->response = new DisburseResponse($this, [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => true,
            ]);
        }
    }
    
    protected function getEndpoint(): string
    {
        return $this->getApiEndpoint();
    }
    
    // Parameter getters
    public function getAmount(): int
    {
        return $this->getParameter('amount');
    }
    
    public function setAmount(int $value): self
    {
        return $this->setParameter('amount', $value);
    }
    
    public function getAccountNumber(): string
    {
        return $this->getParameter('accountNumber');
    }
    
    public function setAccountNumber(string $value): self
    {
        return $this->setParameter('accountNumber', $value);
    }
    
    public function getBankCode(): string
    {
        return $this->getParameter('bankCode');
    }
    
    public function setBankCode(string $value): self
    {
        return $this->setParameter('bankCode', $value);
    }
    
    public function getReference(): string
    {
        return $this->getParameter('reference');
    }
    
    public function setReference(string $value): self
    {
        return $this->setParameter('reference', $value);
    }
    
    public function getVia(): string
    {
        return $this->getParameter('via');
    }
    
    public function setVia(string $value): self
    {
        return $this->setParameter('via', $value);
    }
    
    public function getCurrency(): ?string
    {
        return $this->getParameter('currency');
    }
    
    public function setCurrency(string $value): self
    {
        return $this->setParameter('currency', $value);
    }
    
    // Gateway parameter access
    protected function getApiEndpoint(): string
    {
        return $this->getParameter('apiEndpoint');
    }
    
    protected function getClientId(): string
    {
        return $this->getParameter('clientId');
    }
    
    protected function getClientSecret(): string
    {
        return $this->getParameter('clientSecret');
    }
    
    protected function getTokenEndpoint(): string
    {
        return $this->getParameter('tokenEndpoint');
    }
}
```

**Checklist:**
- [ ] Extends AbstractRequest
- [ ] Uses HasOAuth2 trait
- [ ] getData() validates and builds payload
- [ ] sendData() handles HTTP and errors
- [ ] All parameter getters/setters defined

---

### 2.4 Implement Disburse Response

**File:** `src/Omnipay/Netbank/Message/DisburseResponse.php`

**Implementation:**
```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;

class DisburseResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return !isset($this->data['error']) 
            && isset($this->data['transaction_id']);
    }
    
    public function getMessage(): ?string
    {
        return $this->data['message'] ?? $this->data['error_message'] ?? null;
    }
    
    public function getCode(): ?string
    {
        return $this->data['status_code'] ?? $this->data['error_code'] ?? null;
    }
    
    public function getTransactionReference(): ?string
    {
        return $this->getOperationId();
    }
    
    // Custom methods for NetBank-specific data
    public function getOperationId(): ?string
    {
        return $this->data['transaction_id'] ?? null;
    }
    
    public function getStatus(): ?string
    {
        return $this->data['status'] ?? null;
    }
    
    public function getTransactionUuid(): ?string
    {
        // For mapping back to your Transaction model
        return $this->data['uuid'] ?? null;
    }
}
```

**Checklist:**
- [ ] Extends AbstractResponse
- [ ] isSuccessful() checks NetBank response format
- [ ] getMessage() returns error/success message
- [ ] getCode() returns status code
- [ ] Custom getters for NetBank-specific fields

---

### 2.5 Implement GenerateQr Request & Response

**File:** `src/Omnipay/Netbank/Message/GenerateQrRequest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\HasOAuth2;

class GenerateQrRequest extends AbstractRequest
{
    use HasOAuth2;
    
    public function getData(): array
    {
        $this->validate('account', 'amount', 'merchantId');
        
        return [
            'merchant_id' => $this->getMerchantId(),
            'account' => $this->getAccount(),
            'amount' => [
                'value' => $this->getAmount(),
                'currency' => $this->getCurrency() ?? 'PHP',
            ],
        ];
    }
    
    public function sendData($data): GenerateQrResponse
    {
        try {
            $token = $this->getAccessToken();
            
            $httpResponse = $this->httpClient->request(
                'POST',
                $this->getQrEndpoint(),
                [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                json_encode($data)
            );
            
            $body = $httpResponse->getBody()->getContents();
            $responseData = json_decode($body, true);
            
            return $this->response = new GenerateQrResponse($this, $responseData);
            
        } catch (\Exception $e) {
            return $this->response = new GenerateQrResponse($this, [
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    // Getters/setters
    public function getAccount(): string
    {
        return $this->getParameter('account');
    }
    
    public function setAccount(string $value): self
    {
        return $this->setParameter('account', $value);
    }
    
    public function getAmount(): int
    {
        return $this->getParameter('amount');
    }
    
    public function setAmount(int $value): self
    {
        return $this->setParameter('amount', $value);
    }
    
    public function getMerchantId(): string
    {
        return $this->getParameter('merchantId');
    }
    
    public function setMerchantId(string $value): self
    {
        return $this->setParameter('merchantId', $value);
    }
    
    public function getCurrency(): ?string
    {
        return $this->getParameter('currency');
    }
    
    public function setCurrency(string $value): self
    {
        return $this->setParameter('currency', $value);
    }
    
    protected function getQrEndpoint(): string
    {
        return $this->getParameter('qrEndpoint');
    }
    
    protected function getClientId(): string
    {
        return $this->getParameter('clientId');
    }
    
    protected function getClientSecret(): string
    {
        return $this->getParameter('clientSecret');
    }
    
    protected function getTokenEndpoint(): string
    {
        return $this->getParameter('tokenEndpoint');
    }
}
```

**File:** `src/Omnipay/Netbank/Message/GenerateQrResponse.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;

class GenerateQrResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return isset($this->data['qr_code']) && !isset($this->data['error']);
    }
    
    public function getMessage(): ?string
    {
        return $this->data['message'] ?? $this->data['error'] ?? null;
    }
    
    public function getCode(): ?string
    {
        return $this->data['code'] ?? null;
    }
    
    public function getTransactionReference(): ?string
    {
        return $this->data['reference'] ?? null;
    }
    
    public function getQrCode(): ?string
    {
        if (!isset($this->data['qr_code'])) {
            return null;
        }
        
        // Return as data URI
        return 'data:image/png;base64,' . $this->data['qr_code'];
    }
}
```

**Checklist:**
- [ ] GenerateQrRequest implements OAuth2
- [ ] Request validates merchant and amount
- [ ] Response provides getQrCode() method
- [ ] QR code returned as data URI

---

### 2.6 Implement ConfirmDisbursement Request & Response

**File:** `src/Omnipay/Netbank/Message/ConfirmDisbursementRequest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractRequest;

class ConfirmDisbursementRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('operationId');
        
        return [
            'operation_id' => $this->getOperationId(),
        ];
    }
    
    public function sendData($data): ConfirmDisbursementResponse
    {
        // This is typically called by webhook
        // No HTTP request needed, just mark transaction as confirmed
        
        return $this->response = new ConfirmDisbursementResponse($this, [
            'success' => true,
            'operation_id' => $data['operation_id'],
        ]);
    }
    
    public function getOperationId(): string
    {
        return $this->getParameter('operationId');
    }
    
    public function setOperationId(string $value): self
    {
        return $this->setParameter('operationId', $value);
    }
}
```

**File:** `src/Omnipay/Netbank/Message/ConfirmDisbursementResponse.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;

class ConfirmDisbursementResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->data['success'] ?? false;
    }
    
    public function getMessage(): ?string
    {
        return $this->data['message'] ?? null;
    }
    
    public function getCode(): ?string
    {
        return $this->data['code'] ?? null;
    }
    
    public function getTransactionReference(): ?string
    {
        return $this->data['operation_id'] ?? null;
    }
}
```

**Checklist:**
- [ ] ConfirmDisbursementRequest validates operationId
- [ ] Response indicates success/failure
- [ ] Transaction reference accessible

---

## Phase 3: Bridge Adapter (Day 4)

### 3.1 Create OmnipayBridge

**File:** `src/Services/OmnipayBridge.php`

This adapter translates between your existing `PaymentGatewayInterface` and Omnipay gateways.

**Implementation:**
```php
<?php

namespace LBHurtado\PaymentGateway\Services;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\{
    DisburseInputData,
    DisburseResponseData
};
use Omnipay\Common\GatewayInterface;
use Bavix\Wallet\Interfaces\Wallet;
use Brick\Money\Money;
use Illuminate\Support\Facades\{DB, Log};
use Bavix\Wallet\Models\Transaction;
use LBHurtado\Wallet\Events\DisbursementConfirmed;

class OmnipayBridge implements PaymentGatewayInterface
{
    protected GatewayInterface $gateway;
    
    public function __construct(GatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }
    
    public function generate(string $account, Money $amount): string
    {
        $user = auth()->user();
        
        if (!$user instanceof \LBHurtado\PaymentGateway\Contracts\MerchantInterface) {
            throw new \LogicException('User must implement MerchantInterface');
        }
        
        // Build cache key
        $amountKey = (string) $amount;
        $currency = $amount->getCurrency()->getCurrencyCode();
        $userKey = $user->getKey();
        $cacheKey = "qr:merchant:{$userKey}:{$account}:{$currency}_{$amountKey}";
        
        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($user, $account, $amount) {
            $response = $this->gateway->generateQr([
                'account' => $account,
                'amount' => $amount->getMinorAmount()->toInt(),
                'merchantId' => $user->getMerchant()->id,
                'currency' => $amount->getCurrency()->getCurrencyCode(),
            ])->send();
            
            if (!$response->isSuccessful()) {
                Log::error('[OmnipayBridge] QR generation failed', [
                    'message' => $response->getMessage(),
                ]);
                throw new \RuntimeException('Failed to generate QR code');
            }
            
            return $response->getQrCode();
        });
    }
    
    public function disburse(Wallet $wallet, DisburseInputData|array $validated): DisburseResponseData|bool
    {
        $data = $validated instanceof DisburseInputData
            ? $validated->toArray()
            : $validated;
        
        $amount = $data['amount'];
        $currency = config('disbursement.currency', 'PHP');
        $credits = Money::of($amount, $currency);
        
        DB::beginTransaction();
        
        try {
            // Reserve funds
            $transaction = $wallet->withdraw(
                $credits->getMinorAmount()->toInt(),
                [],
                false
            );
            
            // Call Omnipay gateway
            $response = $this->gateway->disburse([
                'amount' => $amount,
                'accountNumber' => $data['account_number'],
                'bankCode' => $data['bank'],
                'reference' => $data['reference'],
                'via' => $data['via'],
                'currency' => $currency,
            ])->send();
            
            if (!$response->isSuccessful()) {
                Log::warning('[OmnipayBridge] Disbursement failed', [
                    'message' => $response->getMessage(),
                    'code' => $response->getCode(),
                ]);
                DB::rollBack();
                return false;
            }
            
            // Store operation ID
            $transaction->meta = [
                'operationId' => $response->getOperationId(),
                'user_id' => $wallet->getKey(),
                'payload' => $data,
            ];
            $transaction->save();
            
            DB::commit();
            
            // Return response DTO
            return DisburseResponseData::from([
                'uuid' => $transaction->uuid,
                'transaction_id' => $response->getOperationId(),
                'status' => $response->getStatus(),
                'message' => $response->getMessage(),
            ]);
            
        } catch (\Throwable $e) {
            Log::error('[OmnipayBridge] Disbursement error', [
                'error' => $e->getMessage(),
            ]);
            DB::rollBack();
            return false;
        }
    }
    
    public function confirmDeposit(array $payload): bool
    {
        // Implement based on your existing logic
        // This might call gateway methods or handle webhook data directly
        return true;
    }
    
    public function confirmDisbursement(string $operationId): bool
    {
        try {
            $transaction = Transaction::whereJsonContains('meta->operationId', $operationId)
                ->firstOrFail();
            
            $transaction->payable->confirm($transaction);
            DisbursementConfirmed::dispatch($transaction);
            
            Log::info("[OmnipayBridge] Disbursement confirmed for {$operationId}");
            return true;
            
        } catch (\Throwable $e) {
            Log::error('[OmnipayBridge] Confirm disbursement failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
```

**Checklist:**
- [ ] Implements PaymentGatewayInterface
- [ ] Wraps Omnipay gateway
- [ ] Maintains existing method signatures
- [ ] Handles DB transactions
- [ ] Converts between DTOs and Omnipay parameters

---

### 3.2 Create OmnipayFactory

**File:** `src/Omnipay/Support/OmnipayFactory.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Support;

use Omnipay\Common\GatewayInterface;
use Omnipay\Common\Http\Client;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class OmnipayFactory
{
    public static function create(string $name, array $parameters = []): GatewayInterface
    {
        $config = config("omnipay.gateways.{$name}");
        
        if (!$config) {
            throw new \InvalidArgumentException("Gateway '{$name}' not configured");
        }
        
        $class = $config['class'];
        
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Gateway class '{$class}' not found");
        }
        
        // Create HTTP client
        $httpClient = new Client(
            new GuzzleAdapter(),
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory()
        );
        
        // Create gateway instance
        $gateway = new $class($httpClient, HttpRequest::createFromGlobals());
        
        // Merge config options with runtime parameters
        $options = array_merge($config['options'] ?? [], $parameters);
        
        // Initialize gateway
        $gateway->initialize($options);
        
        return $gateway;
    }
}
```

**Checklist:**
- [ ] Creates gateway from config
- [ ] Sets up HTTP client
- [ ] Initializes with parameters
- [ ] Throws clear errors for missing config

---

### 3.3 Update PaymentGatewayManager

**File:** `src/Services/PaymentGatewayManager.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Services;

use LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Omnipay\Support\OmnipayFactory;
use Illuminate\Support\Manager;

class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('omnipay.default', 'netbank');
    }
    
    public function createNetbankDriver(): PaymentGatewayInterface
    {
        // Feature flag: use Omnipay or legacy implementation
        if (config('omnipay.use_omnipay', false)) {
            $gateway = OmnipayFactory::create('netbank');
            return new OmnipayBridge($gateway);
        }
        
        // Legacy implementation
        return new NetbankPaymentGateway();
    }
    
    public function createIcashDriver(): PaymentGatewayInterface
    {
        if (!config('omnipay.use_omnipay', false)) {
            throw new \RuntimeException('iCash driver requires Omnipay to be enabled');
        }
        
        $gateway = OmnipayFactory::create('icash');
        return new OmnipayBridge($gateway);
    }
    
    // Generic method for any Omnipay gateway
    public function createOmnipayDriver(string $name): PaymentGatewayInterface
    {
        $gateway = OmnipayFactory::create($name);
        return new OmnipayBridge($gateway);
    }
}
```

**Checklist:**
- [ ] Supports both legacy and Omnipay drivers
- [ ] Uses feature flag for gradual rollout
- [ ] Generic method for any gateway
- [ ] Backward compatible

---

## Phase 4: Testing (Days 5-6)

### 4.1 Unit Tests for Gateway

**File:** `tests/Unit/Omnipay/NetbankGatewayTest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Omnipay;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway;
use LBHurtado\PaymentGateway\Tests\TestCase;

class NetbankGatewayTest extends TestCase
{
    protected Gateway $gateway;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->gateway = new Gateway();
    }
    
    public function test_gateway_name()
    {
        $this->assertEquals('Netbank', $this->gateway->getName());
    }
    
    public function test_default_parameters()
    {
        $params = $this->gateway->getDefaultParameters();
        
        $this->assertArrayHasKey('clientId', $params);
        $this->assertArrayHasKey('clientSecret', $params);
        $this->assertArrayHasKey('tokenEndpoint', $params);
        $this->assertArrayHasKey('apiEndpoint', $params);
    }
    
    public function test_initialize_parameters()
    {
        $this->gateway->initialize([
            'clientId' => 'test-client',
            'clientSecret' => 'test-secret',
        ]);
        
        $this->assertEquals('test-client', $this->gateway->getClientId());
        $this->assertEquals('test-secret', $this->gateway->getClientSecret());
    }
    
    public function test_creates_disburse_request()
    {
        $request = $this->gateway->disburse([
            'amount' => 1000,
            'accountNumber' => '1234567890',
        ]);
        
        $this->assertInstanceOf(
            \LBHurtado\PaymentGateway\Omnipay\Netbank\Message\DisburseRequest::class,
            $request
        );
    }
}
```

---

### 4.2 Unit Tests for Requests

**File:** `tests/Unit/Omnipay/DisburseRequestTest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Omnipay;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\DisburseRequest;
use LBHurtado\PaymentGateway\Tests\TestCase;
use Http\Mock\Client as MockClient;
use GuzzleHttp\Psr7\Response;

class DisburseRequestTest extends TestCase
{
    public function test_get_data_builds_payload()
    {
        $request = new DisburseRequest(new MockClient(), new \Symfony\Component\HttpFoundation\Request());
        
        $request->initialize([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'BDO',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
        ]);
        
        $data = $request->getData();
        
        $this->assertEquals(1000, $data['transaction']['amount']['value']);
        $this->assertEquals('1234567890', $data['transaction']['destination']['account_number']);
        $this->assertEquals('REF123', $data['transaction']['reference']);
    }
    
    public function test_send_data_handles_success()
    {
        $mockClient = new MockClient();
        
        $mockClient->addResponse(new Response(200, [], json_encode([
            'transaction_id' => 'TXN123',
            'status' => 'pending',
        ])));
        
        // Mock token endpoint
        $mockClient->addResponse(new Response(200, [], json_encode([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ])));
        
        $request = new DisburseRequest($mockClient, new \Symfony\Component\HttpFoundation\Request());
        
        $request->initialize([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'BDO',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
            'apiEndpoint' => 'https://api.test.com/disburse',
            'clientId' => 'test',
            'clientSecret' => 'secret',
            'tokenEndpoint' => 'https://api.test.com/token',
        ]);
        
        $response = $request->send();
        
        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('TXN123', $response->getOperationId());
    }
}
```

---

### 4.3 Integration Tests

**File:** `tests/Feature/OmnipayDisburseTest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Tests\Feature;

use LBHurtado\PaymentGateway\Tests\TestCase;
use LBHurtado\PaymentGateway\Facades\PaymentGateway;
use LBHurtado\PaymentGateway\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OmnipayDisburseTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_disburse_via_omnipay()
    {
        config(['omnipay.use_omnipay' => true]);
        
        $user = User::factory()->create();
        $user->deposit(10000);
        
        $this->actingAs($user);
        
        $response = PaymentGateway::driver('netbank')->disburse($user, [
            'amount' => 1000,
            'account_number' => '1234567890',
            'bank' => 'BDO',
            'reference' => 'TEST-' . uniqid(),
            'via' => 'INSTAPAY',
        ]);
        
        $this->assertNotFalse($response);
        $this->assertInstanceOf(
            \LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseResponseData::class,
            $response
        );
    }
}
```

---

### 4.4 Test Checklist

- [ ] Unit tests for Gateway class
- [ ] Unit tests for all Request classes
- [ ] Unit tests for all Response classes
- [ ] Unit tests for OmnipayBridge
- [ ] Mock HTTP responses for requests
- [ ] Integration tests with actual gateway (optional)
- [ ] Test error scenarios
- [ ] Test OAuth token caching
- [ ] Test parameter validation

---

## Phase 5: Migration & Rollout (Day 7)

### 5.1 Gradual Rollout Strategy

**Step 1: Deploy with feature flag OFF**
```env
USE_OMNIPAY=false
```

**Step 2: Enable for testing environment**
```env
APP_ENV=testing
USE_OMNIPAY=true
```

**Step 3: Enable for staging**
```env
APP_ENV=staging
USE_OMNIPAY=true
```

**Step 4: Enable for production (canary)**
```php
// In AppServiceProvider or feature flag service
'use_omnipay' => env('USE_OMNIPAY', false) || auth()->user()?->isTestUser()
```

**Step 5: Full rollout**
```env
USE_OMNIPAY=true
```

---

### 5.2 Monitoring & Rollback

**Add logging:**
```php
Log::channel('payment-gateway')->info('[Omnipay] Disbursement initiated', [
    'driver' => config('omnipay.use_omnipay') ? 'omnipay' : 'legacy',
    'gateway' => $gatewayName,
    'amount' => $amount,
]);
```

**Rollback plan:**
1. Set `USE_OMNIPAY=false` in `.env`
2. Run `php artisan config:clear`
3. Monitor for errors
4. Investigate and fix issues
5. Re-enable when ready

---

### 5.3 Documentation

**Update README.md:**
```markdown
## Payment Gateway

This package supports multiple payment gateways via League Omnipay.

### Supported Gateways
- NetBank (production)
- ICash (coming soon)
- BDO (coming soon)

### Adding a New Gateway

1. Create gateway class extending AbstractGateway
2. Implement Request/Response classes
3. Add configuration to config/omnipay.php
4. Write tests

See docs/OMNIPAY_ARCHITECTURE.md for details.
```

**Create migration guide:**
```markdown
## Migration from Legacy to Omnipay

1. Set USE_OMNIPAY=true in .env
2. Clear config cache: php artisan config:clear
3. Test thoroughly
4. Monitor logs
```

---

## Phase 6: Cleanup (Day 8)

### 6.1 Deprecation Notices

Add deprecation notices to legacy code:

```php
// In NetbankPaymentGateway
trigger_error(
    'NetbankPaymentGateway is deprecated. Use Omnipay implementation instead.',
    E_USER_DEPRECATED
);
```

---

### 6.2 Remove Feature Flag

After confidence is high:

**Remove from config:**
```php
// config/omnipay.php
- 'use_omnipay' => env('USE_OMNIPAY', false),
+ // Omnipay is now the default
```

**Simplify manager:**
```php
public function createNetbankDriver(): PaymentGatewayInterface
{
    $gateway = OmnipayFactory::create('netbank');
    return new OmnipayBridge($gateway);
}
```

---

### 6.3 Delete Legacy Code

After full migration:
```bash
rm -rf src/Gateways/Netbank/NetbankPaymentGateway.php
rm -rf src/Gateways/Netbank/Traits/
```

---

## Post-Implementation: Adding New Gateways

### Example: Adding ICash Gateway

**1. Create gateway class (5 minutes):**
```php
class ICashGateway extends AbstractGateway
{
    public function getName() { return 'ICash'; }
    // ... parameters
}
```

**2. Create request classes (30 minutes):**
```php
class ICashDisburseRequest extends AbstractRequest { }
class ICashDisburseResponse extends AbstractResponse { }
```

**3. Configure (2 minutes):**
```php
'icash' => [
    'class' => ICashGateway::class,
    'options' => [...],
]
```

**4. Use it:**
```php
PaymentGateway::driver('icash')->disburse(...);
```

---

## Success Metrics

- [ ] All existing tests pass
- [ ] New Omnipay tests at 80%+ coverage
- [ ] No performance degradation
- [ ] Zero production errors after rollout
- [ ] Can add new gateway in < 1 day

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Breaking changes | Feature flag + gradual rollout |
| Performance issues | Load testing before production |
| Missing features | Comprehensive test suite |
| Integration bugs | Staging environment testing |
| Rollback complexity | Clear rollback procedure |

---

## Timeline Summary

| Phase | Duration | Key Deliverables |
|-------|----------|------------------|
| Phase 1 | 1 day | Dependencies, structure, config |
| Phase 2 | 2 days | NetBank gateway implementation |
| Phase 3 | 1 day | Bridge adapter, manager updates |
| Phase 4 | 2 days | Comprehensive test suite |
| Phase 5 | 1 day | Migration, rollout, monitoring |
| Phase 6 | 1 day | Cleanup, documentation |
| **Total** | **8 days** | Production-ready Omnipay integration |

---

## Next Steps

1. Review this plan with team
2. Get approval for implementation
3. Set up tracking (Jira/Trello tickets)
4. Begin Phase 1 implementation
5. Daily standups to review progress

Would you like me to begin implementation?
