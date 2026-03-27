# Omnipay Integration - Updated Implementation Plan
## Integrating EMIs, Settlement Rails, and KYC Considerations

## Overview

This updated plan incorporates:
- **Electronic Money Issuers (EMIs)** - GCash, PayMaya, ICash, etc.
- **Traditional Banks** - BDO, BPI, Metrobank, etc.
- **Settlement Rails** - INSTAPAY (real-time) and PESONET (batch)
- **KYC Workaround** - Address randomization from zip codes
- **Bank Registry** - Centralized bank data from banks.json
- **Money Issuer abstraction** - Merge or integrate with existing lbhurtado/money-issuer

---

## Architecture Considerations

### 1. EMIs vs Traditional Banks

**Key Differences:**
- **EMIs** (GCash, Maya, PayMaya): May have different APIs, limits, and features
- **Traditional Banks** (BDO, BPI): Standard banking APIs
- **Both** support INSTAPAY and/or PESONET rails

**Design Decision:** Omnipay gateway per issuer type (NetBank, ICash, etc.), not per EMI/bank. The **issuer** (NetBank, ICash) handles routing to different EMIs/banks via settlement rails.

### 2. Settlement Rails Integration

```
User Request → Gateway → Issuer API → Settlement Rail → Recipient Bank/EMI
                                    ↓
                               INSTAPAY (real-time, ₱50K limit)
                                    or
                               PESONET (batch, higher limits)
```

**Implementation:**
- Rail selection in request parameters
- Validation: Bank/EMI must support selected rail
- Stored in banks.json per institution

### 3. KYC Address Randomization

**Problem:** BSP requires KYC for higher transaction limits  
**Workaround:** Randomize recipient addresses using valid PH zip codes

**Flow:**
```php
Address::generate() → Random zip code → City + Address1 → Used in API payload
```

---

## Updated Goals

1. ✅ **Standardize** payment gateway integrations using Omnipay
2. ✅ **Support EMIs and banks** via unified interface
3. ✅ **Handle settlement rails** (INSTAPAY/PESONET) with validation
4. ✅ **Integrate bank registry** from banks.json
5. ✅ **Apply KYC workarounds** transparently
6. ✅ **Decide** on money-issuer package integration strategy

---

## Integration Strategy: money-issuer Package

### Option A: Fold into payment-gateway (Recommended)

**Rationale:**
- EMI concept is tightly coupled to payment gateways
- Avoid duplication (BankRegistry, MoneyIssuerManager vs PaymentGatewayManager)
- Simpler dependency graph

**Migration:**
- Move `MoneyIssuerServiceInterface` → `PaymentGatewayInterface` (extend)
- Merge `MoneyIssuerManager` → `PaymentGatewayManager`
- Keep EMI-specific DTOs (`BalanceData`, `TransferData`)

### Option B: Keep Separate with Facade

**Rationale:**
- Separation of concerns (EMI management vs payment processing)
- money-issuer can be used without payment-gateway

**Implementation:**
- `MoneyIssuer` facade delegates to `PaymentGateway` underneath
- Shared interfaces

**Decision Point:** We'll proceed with **Option A** for simplicity.

---

## Pre-Implementation Checklist

- [ ] Review money-issuer package structure
- [ ] Audit all usages of BankRegistry, SettlementRail
- [ ] Confirm zip_codes_list.json is complete
- [ ] Test Address::generate() randomization quality
- [ ] Map NetBank API requirements for INSTAPAY vs PESONET

---

## Phase 1: Foundation Setup (Day 1)

### 1.1 Install Dependencies

Same as original plan.

---

### 1.2 Create Directory Structure

**Updated structure:**
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
│   │   │   ├── ConfirmDisbursementResponse.php
│   │   │   └── CheckBalanceRequest.php (NEW)
│   │   │   └── CheckBalanceResponse.php (NEW)
│   │   └── Traits/
│   │       ├── HasOAuth2.php
│   │       ├── ValidatesSettlementRail.php (NEW)
│   │       └── AppliesKycWorkaround.php (NEW)
│   ├── ICash/
│   │   ├── Gateway.php
│   │   └── Message/ (similar structure)
│   └── Support/
│       ├── OmnipayFactory.php
│       ├── GatewayResolver.php
│       └── RailValidator.php (NEW)
├── Services/
│   ├── OmnipayBridge.php
│   └── MoneyIssuerService.php (MIGRATED from money-issuer)
├── Support/
│   ├── Address.php (existing)
│   └── BankRegistry.php (existing, enhanced)
├── Enums/
│   └── SettlementRail.php (existing)
└── Data/
    ├── BankData.php (existing)
    ├── SettlementBanksData.php (existing)
    └── Wallet/ (MIGRATED from money-issuer)
        ├── BalanceData.php
        └── TransferData.php
```

**Commands:**
```bash
cd packages/payment-gateway
mkdir -p src/Omnipay/Netbank/Message
mkdir -p src/Omnipay/Netbank/Traits
mkdir -p src/Omnipay/ICash/Message
mkdir -p src/Omnipay/Support
mkdir -p src/Data/Wallet
```

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
                'balanceEndpoint' => env('NETBANK_BALANCE_ENDPOINT'),
                'testMode' => env('NETBANK_TEST_MODE', false),
                
                // Rail-specific configuration
                'rails' => [
                    'INSTAPAY' => [
                        'enabled' => env('NETBANK_INSTAPAY_ENABLED', true),
                        'min_amount' => 1, // ₱0.01
                        'max_amount' => 50000 * 100, // ₱50,000 in centavos
                        'fee' => 1000, // ₱10 fee
                    ],
                    'PESONET' => [
                        'enabled' => env('NETBANK_PESONET_ENABLED', true),
                        'min_amount' => 1,
                        'max_amount' => 1000000 * 100, // ₱1M
                        'fee' => 2500, // ₱25 fee
                    ],
                ],
            ],
        ],
        'icash' => [
            'class' => \LBHurtado\PaymentGateway\Omnipay\ICash\Gateway::class,
            'options' => [
                'apiKey' => env('ICASH_API_KEY'),
                'apiSecret' => env('ICASH_API_SECRET'),
                'apiEndpoint' => env('ICASH_API_ENDPOINT'),
                'testMode' => env('ICASH_TEST_MODE', false),
                
                'rails' => [
                    'INSTAPAY' => [
                        'enabled' => env('ICASH_INSTAPAY_ENABLED', true),
                        'min_amount' => 1,
                        'max_amount' => 50000 * 100,
                    ],
                ],
            ],
        ],
    ],
    
    'default' => env('PAYMENT_GATEWAY', 'netbank'),
    
    // Feature flag for gradual rollout
    'use_omnipay' => env('USE_OMNIPAY', false),
    
    // KYC workaround settings
    'kyc' => [
        'randomize_address' => env('GATEWAY_RANDOMIZE_ADDRESS', true),
        'address_provider' => \LBHurtado\PaymentGateway\Support\Address::class,
    ],
    
    // Bank registry
    'banks' => [
        'json_path' => env('BANKS_JSON_PATH', 'banks.json'),
        'cache_enabled' => env('BANKS_CACHE_ENABLED', true),
        'cache_ttl' => env('BANKS_CACHE_TTL', 86400), // 24 hours
    ],
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
NETBANK_BALANCE_ENDPOINT=
NETBANK_TEST_MODE=false

# Settlement Rails
NETBANK_INSTAPAY_ENABLED=true
NETBANK_PESONET_ENABLED=true

# ICash Configuration
ICASH_API_KEY=
ICASH_API_SECRET=
ICASH_API_ENDPOINT=
ICASH_TEST_MODE=false
ICASH_INSTAPAY_ENABLED=true

# KYC Workaround
GATEWAY_RANDOMIZE_ADDRESS=true

# Bank Registry
BANKS_JSON_PATH=banks.json
BANKS_CACHE_ENABLED=true
BANKS_CACHE_TTL=86400
```

---

## Phase 2: NetBank Gateway Implementation (Days 2-3)

### 2.1 Enhanced Gateway with Rail Support

**File:** `src/Omnipay/Netbank/Gateway.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank;

use Omnipay\Common\AbstractGateway;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\{
    GenerateQrRequest,
    DisburseRequest,
    ConfirmDisbursementRequest,
    CheckBalanceRequest
};
use LBHurtado\PaymentGateway\Enums\SettlementRail;

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
            'balanceEndpoint' => '',
            'testMode' => false,
            'rails' => [],
        ];
    }
    
    // Parameter getters/setters
    public function getClientId(): string
    {
        return $this->getParameter('clientId');
    }
    
    public function setClientId(string $value): self
    {
        return $this->setParameter('clientId', $value);
    }
    
    // ... other getters/setters (same as before)
    
    public function getRails(): array
    {
        return $this->getParameter('rails');
    }
    
    public function setRails(array $value): self
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
    
    public function checkBalance(array $options = []): CheckBalanceRequest
    {
        return $this->createRequest(CheckBalanceRequest::class, $options);
    }
}
```

**Checklist:**
- [ ] Gateway supports rail configuration
- [ ] `supportsRail()` validates capability
- [ ] `getRailConfig()` provides limits/fees
- [ ] New `checkBalance()` method

---

### 2.2 Create Settlement Rail Validation Trait

**File:** `src/Omnipay/Netbank/Traits/ValidatesSettlementRail.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Traits;

use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\PaymentGateway\Support\BankRegistry;
use Omnipay\Common\Exception\InvalidRequestException;

trait ValidatesSettlementRail
{
    protected function validateSettlementRail(
        string $bankCode,
        SettlementRail $rail,
        int $amount
    ): void {
        // 1. Check if bank supports the rail
        $bankRegistry = app(BankRegistry::class);
        $supportedRails = $bankRegistry->supportedSettlementRails($bankCode);
        
        if (!isset($supportedRails[$rail->value])) {
            throw new InvalidRequestException(
                "Bank {$bankCode} does not support {$rail->value} settlement rail"
            );
        }
        
        // 2. Check if gateway supports the rail
        $railConfig = $this->getParameter('rails')[$rail->value] ?? null;
        
        if (!$railConfig || !($railConfig['enabled'] ?? false)) {
            throw new InvalidRequestException(
                "Gateway does not support {$rail->value} settlement rail"
            );
        }
        
        // 3. Validate amount limits
        $minAmount = $railConfig['min_amount'] ?? 0;
        $maxAmount = $railConfig['max_amount'] ?? PHP_INT_MAX;
        
        if ($amount < $minAmount) {
            throw new InvalidRequestException(
                "Amount too small for {$rail->value}. Minimum: ₱" . ($minAmount / 100)
            );
        }
        
        if ($amount > $maxAmount) {
            throw new InvalidRequestException(
                "Amount exceeds {$rail->value} limit. Maximum: ₱" . ($maxAmount / 100)
            );
        }
    }
    
    protected function getRailFee(SettlementRail $rail): int
    {
        $railConfig = $this->getParameter('rails')[$rail->value] ?? null;
        return $railConfig['fee'] ?? 0;
    }
}
```

**Checklist:**
- [ ] Validates bank supports rail
- [ ] Validates gateway supports rail
- [ ] Checks amount limits per rail
- [ ] Provides rail fee calculation

---

### 2.3 Create KYC Workaround Trait

**File:** `src/Omnipay/Netbank/Traits/AppliesKycWorkaround.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Traits;

use LBHurtado\PaymentGateway\Support\Address;

trait AppliesKycWorkaround
{
    protected function generateRandomAddress(): array
    {
        // Check if randomization is enabled
        if (!config('omnipay.kyc.randomize_address', true)) {
            return [
                'address1' => 'N/A',
                'city' => 'Manila',
                'country' => 'PH',
                'postal_code' => '1000',
            ];
        }
        
        // Use Address helper to generate random address
        return Address::generate();
    }
    
    protected function applyKycWorkaround(array &$payload, string $recipientKey = 'recipient'): void
    {
        // Generate random address
        $address = $this->generateRandomAddress();
        
        // Inject into payload
        if (isset($payload[$recipientKey])) {
            $payload[$recipientKey]['address'] = $address;
        } else {
            $payload['recipient'] = [
                'address' => $address,
            ];
        }
        
        // Log for debugging (in test mode only)
        if ($this->getParameter('testMode')) {
            logger()->info('[KYC Workaround] Generated address', [
                'address' => $address,
            ]);
        }
    }
}
```

**Checklist:**
- [ ] Uses existing Address::generate()
- [ ] Configurable via omnipay.kyc.randomize_address
- [ ] Injects address into request payload
- [ ] Logs in test mode for auditing

---

### 2.4 Enhanced Disburse Request with Rails + KYC

**File:** `src/Omnipay/Netbank/Message/DisburseRequest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\{
    HasOAuth2,
    ValidatesSettlementRail,
    AppliesKycWorkaround
};
use LBHurtado\PaymentGateway\Enums\SettlementRail;

class DisburseRequest extends AbstractRequest
{
    use HasOAuth2;
    use ValidatesSettlementRail;
    use AppliesKycWorkaround;
    
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
        
        // Build NetBank API payload
        $payload = [
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
                'settlement_rail' => $rail->value,
                'fee' => $this->getRailFee($rail),
            ],
        ];
        
        // Apply KYC workaround (inject random address)
        $this->applyKycWorkaround($payload, 'transaction');
        
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
    
    // Parameter getters/setters (same as before)
    
    public function getVia(): string
    {
        return $this->getParameter('via');
    }
    
    public function setVia(string $value): self
    {
        return $this->setParameter('via', $value);
    }
    
    // ... other getters/setters
}
```

**Checklist:**
- [ ] Uses ValidatesSettlementRail trait
- [ ] Uses AppliesKycWorkaround trait
- [ ] Validates rail before request
- [ ] Injects randomized address
- [ ] Includes rail fee in payload

---

### 2.5 Implement CheckBalance Request & Response

**File:** `src/Omnipay/Netbank/Message/CheckBalanceRequest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\HasOAuth2;

class CheckBalanceRequest extends AbstractRequest
{
    use HasOAuth2;
    
    public function getData(): array
    {
        // No payload needed for balance check
        return [];
    }
    
    public function sendData($data): CheckBalanceResponse
    {
        try {
            $token = $this->getAccessToken();
            
            $httpResponse = $this->httpClient->request(
                'GET',
                $this->getBalanceEndpoint(),
                [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ]
            );
            
            $body = $httpResponse->getBody()->getContents();
            $responseData = json_decode($body, true);
            
            return $this->response = new CheckBalanceResponse($this, $responseData);
            
        } catch (\Exception $e) {
            return $this->response = new CheckBalanceResponse($this, [
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    protected function getBalanceEndpoint(): string
    {
        return $this->getParameter('balanceEndpoint');
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

**File:** `src/Omnipay/Netbank/Message/CheckBalanceResponse.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;

class CheckBalanceResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return !isset($this->data['error']) && isset($this->data['balance']);
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
        return null; // No transaction for balance check
    }
    
    /**
     * Get available balance in minor units (centavos)
     */
    public function getBalance(): ?int
    {
        return $this->data['balance'] ?? null;
    }
    
    /**
     * Get currency code
     */
    public function getCurrency(): string
    {
        return $this->data['currency'] ?? 'PHP';
    }
}
```

**Checklist:**
- [ ] Balance check request implemented
- [ ] Response provides balance in minor units
- [ ] Currency returned
- [ ] Error handling

---

## Phase 3: Bridge Adapter with EMI Support (Day 4)

### 3.1 Enhanced OmnipayBridge

**File:** `src/Services/OmnipayBridge.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Services;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\{
    DisburseInputData,
    DisburseResponseData
};
use LBHurtado\PaymentGateway\Data\Wallet\BalanceData;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\PaymentGateway\Support\BankRegistry;
use Omnipay\Common\GatewayInterface;
use Bavix\Wallet\Interfaces\Wallet;
use Brick\Money\Money;
use Illuminate\Support\Facades\{DB, Log};
use Bavix\Wallet\Models\Transaction;
use LBHurtado\Wallet\Events\DisbursementConfirmed;

class OmnipayBridge implements PaymentGatewayInterface
{
    protected GatewayInterface $gateway;
    protected BankRegistry $bankRegistry;
    
    public function __construct(GatewayInterface $gateway)
    {
        $this->gateway = $gateway;
        $this->bankRegistry = app(BankRegistry::class);
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
        
        // Validate settlement rail
        $rail = SettlementRail::from($data['via']);
        $this->validateBankSupportsRail($data['bank'], $rail);
        
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
                    'rail' => $rail->value,
                    'bank' => $data['bank'],
                ]);
                DB::rollBack();
                return false;
            }
            
            // Store operation ID with rail info
            $transaction->meta = [
                'operationId' => $response->getOperationId(),
                'user_id' => $wallet->getKey(),
                'payload' => $data,
                'settlement_rail' => $rail->value,
                'bank_code' => $data['bank'],
            ];
            $transaction->save();
            
            DB::commit();
            
            // Return response DTO
            return DisburseResponseData::from([
                'uuid' => $transaction->uuid,
                'transaction_id' => $response->getOperationId(),
                'status' => $response->getStatus(),
                'message' => $response->getMessage(),
                'settlement_rail' => $rail->value,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('[OmnipayBridge] Disbursement error', [
                'error' => $e->getMessage(),
                'rail' => $rail->value ?? null,
            ]);
            DB::rollBack();
            return false;
        }
    }
    
    /**
     * Check gateway balance
     */
    public function checkBalance(): BalanceData
    {
        $response = $this->gateway->checkBalance()->send();
        
        if (!$response->isSuccessful()) {
            throw new \RuntimeException('Failed to check balance: ' . $response->getMessage());
        }
        
        return BalanceData::from([
            'amount' => $response->getBalance(),
            'currency' => $response->getCurrency(),
        ]);
    }
    
    public function confirmDeposit(array $payload): bool
    {
        // Implement based on your existing logic
        return true;
    }
    
    public function confirmDisbursement(string $operationId): bool
    {
        try {
            $transaction = Transaction::whereJsonContains('meta->operationId', $operationId)
                ->firstOrFail();
            
            $transaction->payable->confirm($transaction);
            DisbursementConfirmed::dispatch($transaction);
            
            $rail = $transaction->meta['settlement_rail'] ?? 'unknown';
            Log::info("[OmnipayBridge] Disbursement confirmed for {$operationId}", [
                'rail' => $rail,
            ]);
            return true;
            
        } catch (\Throwable $e) {
            Log::error('[OmnipayBridge] Confirm disbursement failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Validate that bank supports settlement rail
     */
    protected function validateBankSupportsRail(string $bankCode, SettlementRail $rail): void
    {
        $supportedRails = $this->bankRegistry->supportedSettlementRails($bankCode);
        
        if (!isset($supportedRails[$rail->value])) {
            throw new \InvalidArgumentException(
                "Bank {$bankCode} does not support {$rail->value}"
            );
        }
    }
}
```

**Checklist:**
- [ ] Validates rail support before disbursement
- [ ] Stores rail info in transaction meta
- [ ] Implements checkBalance() method
- [ ] Logs rail-specific errors

---

### 3.2 Migrate BalanceData from money-issuer

**File:** `src/Data/Wallet/BalanceData.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Data\Wallet;

use Spatie\LaravelData\Data;

class BalanceData extends Data
{
    public function __construct(
        public int $amount,      // in minor units (centavos)
        public string $currency,
    ) {}
    
    public function toMajor(): float
    {
        return $this->amount / 100;
    }
    
    public function formatted(): string
    {
        return number_format($this->toMajor(), 2);
    }
}
```

---

### 3.3 Enhance BankRegistry

**File:** `src/Support/BankRegistry.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Support;

use LBHurtado\PaymentGateway\Enums\SettlementRail;
use Illuminate\Support\Collection;

class BankRegistry
{
    protected array $banks;
    
    public function __construct()
    {
        $path = documents_path('banks.json');
        
        if (!file_exists($path)) {
            throw new \RuntimeException("Bank directory file not found at: {$path}");
        }
        
        $data = json_decode(file_get_contents($path), true);
        
        if (!isset($data['banks']) || !is_array($data['banks'])) {
            throw new \UnexpectedValueException("Invalid format in banks.json.");
        }
        
        $this->banks = $data['banks'];
    }
    
    public function all(): array
    {
        return $this->banks;
    }
    
    public function find(string $swiftBic): ?array
    {
        return $this->banks[$swiftBic] ?? null;
    }
    
    public function supportedSettlementRails(string $swiftBic): array
    {
        return $this->banks[$swiftBic]['settlement_rail'] ?? [];
    }
    
    /**
     * Check if bank supports a specific rail
     */
    public function supportsRail(string $swiftBic, SettlementRail $rail): bool
    {
        $supportedRails = $this->supportedSettlementRails($swiftBic);
        return isset($supportedRails[$rail->value]);
    }
    
    /**
     * Get all banks supporting a specific rail
     */
    public function byRail(SettlementRail $rail): Collection
    {
        return collect($this->banks)->filter(function ($bank) use ($rail) {
            return isset($bank['settlement_rail'][$rail->value]);
        });
    }
    
    /**
     * Get all EMIs (electronic money issuers)
     * Identified by having "short" codes or specific patterns
     */
    public function getEMIs(): Collection
    {
        return collect($this->banks)->filter(function ($bank, $code) {
            // EMIs often have specific patterns or are in a known list
            $emiPatterns = ['GXCH', 'PAPH', 'DCPH', 'GHPE', 'SHPH', 'TAGC'];
            
            foreach ($emiPatterns as $pattern) {
                if (str_starts_with($code, $pattern)) {
                    return true;
                }
            }
            
            return false;
        });
    }
    
    /**
     * Check if code is an EMI
     */
    public function isEMI(string $swiftBic): bool
    {
        return $this->getEMIs()->has($swiftBic);
    }
    
    public function toCollection(): Collection
    {
        return collect($this->banks);
    }
}
```

**Checklist:**
- [ ] Added `supportsRail()` method
- [ ] Added `byRail()` to filter banks
- [ ] Added `getEMIs()` to identify EMIs
- [ ] Added `isEMI()` checker

---

## Phase 4: Testing (Days 5-6)

### 4.1 Unit Tests for Rails

**File:** `tests/Unit/Omnipay/SettlementRailValidationTest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Omnipay;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\DisburseRequest;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use LBHurtado\PaymentGateway\Tests\TestCase;
use Http\Mock\Client as MockClient;
use Omnipay\Common\Exception\InvalidRequestException;

class SettlementRailValidationTest extends TestCase
{
    public function test_validates_bank_supports_rail()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('does not support INSTAPAY');
        
        $request = new DisburseRequest(
            new MockClient(),
            new \Symfony\Component\HttpFoundation\Request()
        );
        
        $request->initialize([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'CITIPHMXXXX', // Only supports PESONET
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
            'rails' => config('omnipay.gateways.netbank.options.rails'),
        ]);
        
        $request->getData(); // Should throw
    }
    
    public function test_validates_amount_limits_for_instapay()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('exceeds INSTAPAY limit');
        
        $request = new DisburseRequest(
            new MockClient(),
            new \Symfony\Component\HttpFoundation\Request()
        );
        
        $request->initialize([
            'amount' => 60000 * 100, // ₱60,000 exceeds ₱50K limit
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX', // GCash supports INSTAPAY
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
            'rails' => config('omnipay.gateways.netbank.options.rails'),
        ]);
        
        $request->getData(); // Should throw
    }
    
    public function test_allows_valid_pesonet_transaction()
    {
        $request = new DisburseRequest(
            new MockClient(),
            new \Symfony\Component\HttpFoundation\Request()
        );
        
        $request->initialize([
            'amount' => 100000 * 100, // ₱100,000
            'accountNumber' => '1234567890',
            'bankCode' => 'BNORPHMMXXX', // BDO supports PESONET
            'reference' => 'REF123',
            'via' => 'PESONET',
            'rails' => config('omnipay.gateways.netbank.options.rails'),
        ]);
        
        $data = $request->getData();
        
        $this->assertEquals('PESONET', $data['transaction']['settlement_rail']);
        $this->assertEquals(100000 * 100, $data['transaction']['amount']['value']);
    }
}
```

---

### 4.2 Unit Tests for KYC Workaround

**File:** `tests/Unit/Omnipay/KycWorkaroundTest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Tests\Unit\Omnipay;

use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\DisburseRequest;
use LBHurtado\PaymentGateway\Tests\TestCase;
use Http\Mock\Client as MockClient;

class KycWorkaroundTest extends TestCase
{
    public function test_injects_random_address_when_enabled()
    {
        config(['omnipay.kyc.randomize_address' => true]);
        
        $request = new DisburseRequest(
            new MockClient(),
            new \Symfony\Component\HttpFoundation\Request()
        );
        
        $request->initialize([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
            'rails' => config('omnipay.gateways.netbank.options.rails'),
        ]);
        
        $data = $request->getData();
        
        $this->assertArrayHasKey('address', $data['transaction']);
        $this->assertArrayHasKey('address1', $data['transaction']['address']);
        $this->assertArrayHasKey('city', $data['transaction']['address']);
        $this->assertArrayHasKey('postal_code', $data['transaction']['address']);
        $this->assertEquals('PH', $data['transaction']['address']['country']);
    }
    
    public function test_uses_static_address_when_disabled()
    {
        config(['omnipay.kyc.randomize_address' => false]);
        
        $request = new DisburseRequest(
            new MockClient(),
            new \Symfony\Component\HttpFoundation\Request()
        );
        
        $request->initialize([
            'amount' => 1000,
            'accountNumber' => '1234567890',
            'bankCode' => 'GXCHPHM2XXX',
            'reference' => 'REF123',
            'via' => 'INSTAPAY',
            'rails' => config('omnipay.gateways.netbank.options.rails'),
        ]);
        
        $data = $request->getData();
        
        $this->assertEquals('N/A', $data['transaction']['address']['address1']);
        $this->assertEquals('Manila', $data['transaction']['address']['city']);
    }
}
```

---

### 4.3 Integration Tests with EMIs

**File:** `tests/Feature/EmiDisbursementTest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Tests\Feature;

use LBHurtado\PaymentGateway\Tests\TestCase;
use LBHurtado\PaymentGateway\Facades\PaymentGateway;
use LBHurtado\PaymentGateway\Tests\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmiDisbursementTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_disburse_to_gcash_via_instapay()
    {
        config(['omnipay.use_omnipay' => true]);
        
        $user = User::factory()->create();
        $user->deposit(100000);
        
        $this->actingAs($user);
        
        $response = PaymentGateway::driver('netbank')->disburse($user, [
            'amount' => 1000,
            'account_number' => '09171234567', // GCash mobile number
            'bank' => 'GXCHPHM2XXX', // GCash
            'reference' => 'GCASH-' . uniqid(),
            'via' => 'INSTAPAY',
        ]);
        
        $this->assertNotFalse($response);
        $this->assertEquals('INSTAPAY', $response->settlement_rail);
    }
    
    public function test_disburse_to_paymaya_via_instapay()
    {
        config(['omnipay.use_omnipay' => true]);
        
        $user = User::factory()->create();
        $user->deposit(100000);
        
        $this->actingAs($user);
        
        $response = PaymentGateway::driver('netbank')->disburse($user, [
            'amount' => 5000,
            'account_number' => '09181234567',
            'bank' => 'PAPHPHM1XXX', // PayMaya
            'reference' => 'MAYA-' . uniqid(),
            'via' => 'INSTAPAY',
        ]);
        
        $this->assertNotFalse($response);
    }
    
    public function test_disburse_to_bdo_via_pesonet()
    {
        config(['omnipay.use_omnipay' => true]);
        
        $user = User::factory()->create();
        $user->deposit(500000);
        
        $this->actingAs($user);
        
        $response = PaymentGateway::driver('netbank')->disburse($user, [
            'amount' => 100000,
            'account_number' => '1234567890',
            'bank' => 'BNORPHMMXXX', // BDO
            'reference' => 'BDO-' . uniqid(),
            'via' => 'PESONET',
        ]);
        
        $this->assertNotFalse($response);
        $this->assertEquals('PESONET', $response->settlement_rail);
    }
}
```

---

## Phase 5: Migration & Documentation (Day 7)

### 5.1 Migration Guide for money-issuer Users

**File:** `docs/MONEY_ISSUER_MIGRATION.md`

```markdown
# Migrating from money-issuer Package

## Overview

The `money-issuer` package has been merged into `payment-gateway` to provide a unified interface for EMIs, banks, and settlement rails.

## What Changed

### Namespaces

| Old (money-issuer) | New (payment-gateway) |
|--------------------|----------------------|
| `LBHurtado\MoneyIssuer\Services\MoneyIssuerManager` | `LBHurtado\PaymentGateway\Services\PaymentGatewayManager` |
| `LBHurtado\MoneyIssuer\Data\Wallet\BalanceData` | `LBHurtado\PaymentGateway\Data\Wallet\BalanceData` |
| `LBHurtado\MoneyIssuer\Facades\MoneyIssuer` | `LBHurtado\PaymentGateway\Facades\PaymentGateway` |

### Code Changes

**Before:**
```php
use LBHurtado\MoneyIssuer\Facades\MoneyIssuer;

$balance = MoneyIssuer::driver('netbank')->checkBalance();
```

**After:**
```php
use LBHurtado\PaymentGateway\Facades\PaymentGateway;

$balance = PaymentGateway::driver('netbank')->checkBalance();
```

## New Features

1. **Settlement Rail Support** - Explicit INSTAPAY/PESONET selection
2. **Bank Validation** - Automatic rail compatibility checking
3. **KYC Workarounds** - Built-in address randomization
4. **EMI Detection** - BankRegistry can identify EMIs

## Migration Steps

1. Remove money-issuer from composer.json
2. Update use statements
3. Update facade calls
4. Test thoroughly
```

---

### 5.2 Updated README

**File:** `packages/payment-gateway/README.md`

````markdown
# Payment Gateway Package

Multi-gateway payment processing with support for EMIs, traditional banks, and Philippine settlement rails.

## Features

- ✅ **Multiple Gateways** - NetBank, ICash, and more
- ✅ **EMI Support** - GCash, PayMaya, ShopeePay, etc.
- ✅ **Settlement Rails** - INSTAPAY (real-time) and PESONET (batch)
- ✅ **Bank Registry** - 180+ Philippine banks and EMIs
- ✅ **KYC Workarounds** - Address randomization for compliance
- ✅ **Omnipay Integration** - Industry-standard patterns

## Installation

```bash
composer require lbhurtado/payment-gateway
```

## Quick Start

### Disbursement

```php
use LBHurtado\PaymentGateway\Facades\PaymentGateway;

// Disburse to GCash via INSTAPAY
$response = PaymentGateway::driver('netbank')->disburse($wallet, [
    'amount' => 1000, // ₱10.00
    'account_number' => '09171234567',
    'bank' => 'GXCHPHM2XXX', // GCash
    'reference' => 'TXN-' . uniqid(),
    'via' => 'INSTAPAY', // or 'PESONET'
]);
```

### Check Balance

```php
$balance = PaymentGateway::driver('netbank')->checkBalance();

echo "Available: ₱" . $balance->formatted();
```

## Settlement Rails

### INSTAPAY
- **Speed**: Real-time (within seconds)
- **Limit**: ₱50,000 per transaction
- **Fee**: ₱10 (typical)
- **Use for**: Small amounts, urgent transfers

### PESONET
- **Speed**: Batch processing (same-day or next-day)
- **Limit**: ₱1,000,000+ per transaction
- **Fee**: ₱25 (typical)
- **Use for**: Large amounts, non-urgent transfers

## Supported EMIs

| EMI | Code | Rails |
|-----|------|-------|
| GCash | GXCHPHM2XXX | INSTAPAY, PESONET |
| PayMaya | PAPHPHM1XXX | INSTAPAY, PESONET |
| GrabPay | GHPESGSGXXX | INSTAPAY, PESONET |
| ShopeePay | SHPHPHM2XXX | INSTAPAY |
| Coins.ph | DCPHPHM1XXX | INSTAPAY, PESONET |

## Configuration

See `config/omnipay.php` for full configuration options.

## Testing

```bash
composer test
```

## License

Proprietary
````

---

## Phase 6: Cleanup & Optimization (Day 8)

### 6.1 Deprecate money-issuer Package

**File:** `packages/money-issuer/README.md`

```markdown
# ⚠️ DEPRECATED

This package has been merged into `lbhurtado/payment-gateway`.

Please migrate to the new package:

```bash
composer remove lbhurtado/money-issuer
composer require lbhurtado/payment-gateway
```

See migration guide: `packages/payment-gateway/docs/MONEY_ISSUER_MIGRATION.md`
```

---

### 6.2 Create Rail Selection Helper

**File:** `src/Support/RailSelector.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Support;

use LBHurtado\PaymentGateway\Enums\SettlementRail;

class RailSelector
{
    /**
     * Suggest optimal rail based on amount
     */
    public static function suggest(int $amount): SettlementRail
    {
        // Under ₱50K → INSTAPAY (faster)
        if ($amount <= 50000 * 100) {
            return SettlementRail::INSTAPAY;
        }
        
        // Over ₱50K → PESONET (higher limit)
        return SettlementRail::PESONET;
    }
    
    /**
     * Get all supported rails for a bank
     */
    public static function forBank(string $bankCode): array
    {
        $registry = app(BankRegistry::class);
        $supportedRails = $registry->supportedSettlementRails($bankCode);
        
        return array_map(
            fn($key) => SettlementRail::from($key),
            array_keys($supportedRails)
        );
    }
}
```

---

## Summary of Changes

### New Features
1. ✅ **Settlement Rail Validation** - ValidatesSettlementRail trait
2. ✅ **KYC Address Randomization** - AppliesKycWorkaround trait
3. ✅ **Bank Registry Enhancement** - EMI detection, rail filtering
4. ✅ **Balance Check** - CheckBalanceRequest/Response
5. ✅ **Rail-aware Bridge** - OmnipayBridge validates rails
6. ✅ **money-issuer Migration** - BalanceData moved to payment-gateway

### Configuration Updates
- Rail-specific limits and fees in `omnipay.php`
- KYC workaround toggle
- Bank registry caching

### Testing Additions
- Settlement rail validation tests
- KYC workaround tests
- EMI disbursement integration tests

---

## Timeline Summary

| Phase | Duration | Key Deliverables |
|-------|----------|------------------|
| Phase 1 | 1 day | Dependencies, structure, config with rail support |
| Phase 2 | 2 days | NetBank with rails, KYC, balance check |
| Phase 3 | 1 day | Bridge with EMI support, BankRegistry enhancements |
| Phase 4 | 2 days | Comprehensive tests (rails, KYC, EMIs) |
| Phase 5 | 1 day | Migration guide, documentation |
| Phase 6 | 1 day | Cleanup, rail selector helper |
| **Total** | **8 days** | Production-ready with EMI/rail support |

---

## Next Steps

1. ✅ Review updated plan
2. ✅ Approve money-issuer migration strategy
3. ✅ Begin Phase 1 implementation
4. ✅ Test with real NetBank API (INSTAPAY vs PESONET)
5. ✅ Validate Address::generate() quality

**Ready to begin implementation?**
