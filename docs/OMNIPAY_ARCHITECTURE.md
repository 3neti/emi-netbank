# Omnipay Architecture Documentation

## Overview

Omnipay is a framework-agnostic, multi-gateway payment processing library for PHP. It provides a consistent interface for interacting with dozens of different payment gateways through a unified API.

**Core Philosophy:** "Write your application code once, and switch between payment gateways with minimal changes."

## Key Concepts

### 1. Gateway
The main entry point for interacting with a payment provider. Each gateway represents a specific payment service (e.g., NetBank, PayPal, Stripe).

### 2. Request
An object that encapsulates all parameters needed to make an API call to the payment gateway. Each operation (purchase, refund, etc.) has its own Request class.

### 3. Response
An object returned after sending a Request, containing the result of the API call and helper methods to check success/failure.

### 4. HTTP Client
Omnipay uses PSR-18 HTTP clients (via php-http/discovery) to make actual HTTP requests, making it testable and framework-agnostic.

---

## Architecture Layers

```
┌─────────────────────────────────────────────────────────────────┐
│                      Application Layer                          │
│         (Your Laravel controllers, services, etc.)              │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ Calls methods like:
                         │ $gateway->purchase([...])
                         │ $gateway->completePurchase([...])
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                    Gateway Instance                             │
│              (extends AbstractGateway)                          │
│                                                                 │
│  - getName(): string                                            │
│  - getDefaultParameters(): array                                │
│  - initialize(array $parameters)                                │
│  - purchase(array $options): RequestInterface                   │
│  - completePurchase(array $options): RequestInterface           │
│  - [custom methods for your operations]                         │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ Creates and returns
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                    Request Object                               │
│              (extends AbstractRequest)                          │
│                                                                 │
│  - initialize(array $parameters)                                │
│  - getData(): mixed (prepares request payload)                  │
│  - sendData($data): ResponseInterface (executes HTTP call)      │
│  - getEndpoint(): string                                        │
│  - getHttpMethod(): string                                      │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ Makes HTTP request via
                         │ $this->httpClient->request(...)
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                    HTTP Client Layer                            │
│              (PSR-18 compatible client)                         │
│                                                                 │
│  - Guzzle (most common)                                         │
│  - Symfony HttpClient                                           │
│  - Any PSR-18 client                                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ Actual HTTP request to
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                 Payment Gateway API                             │
│            (NetBank, ICash, BDO, etc.)                          │
└─────────────────────────────────────────────────────────────────┘
                         │
                         │ Returns HTTP response
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                    Response Object                              │
│              (extends AbstractResponse)                         │
│                                                                 │
│  - __construct(RequestInterface $request, mixed $data)          │
│  - isSuccessful(): bool                                         │
│  - isRedirect(): bool                                           │
│  - getMessage(): ?string                                        │
│  - getCode(): ?string                                           │
│  - getTransactionReference(): ?string                           │
│  - [custom getters for gateway-specific data]                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Core Classes and Interfaces

### 1. GatewayInterface

The contract that all gateways must implement.

```php
interface GatewayInterface
{
    public function getName(): string;
    public function getDefaultParameters(): array;
    public function initialize(array $parameters = []): GatewayInterface;
    public function getParameters(): array;
    public function supportsAuthorize(): bool;
    public function supportsCompleteAuthorize(): bool;
    public function supportsPurchase(): bool;
    // ... more support methods
}
```

**Purpose:** Define what operations a gateway can perform.

---

### 2. AbstractGateway

Base implementation that handles common gateway functionality.

```php
abstract class AbstractGateway implements GatewayInterface
{
    use ParametersTrait;
    
    protected $httpClient;
    protected $httpRequest;
    
    abstract public function getName(): string;
    
    public function getDefaultParameters(): array
    {
        return [];
    }
    
    public function initialize(array $parameters = []): GatewayInterface
    {
        $this->parameters = new ParameterBag;
        Helper::initialize($this, $parameters);
        return $this;
    }
    
    // Helper method to create requests
    protected function createRequest($class, array $parameters): RequestInterface
    {
        $obj = new $class($this->httpClient, $this->httpRequest);
        return $obj->initialize(array_replace($this->getParameters(), $parameters));
    }
}
```

**Key Features:**
- Parameter management via ParameterBag
- Request factory via `createRequest()`
- HTTP client injection
- Getters/setters for common parameters

**Your Implementation:**
```php
class NetbankGateway extends AbstractGateway
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
        ];
    }
    
    // Custom method for your use case
    public function generateQr(array $options = []): GenerateQrRequest
    {
        return $this->createRequest(GenerateQrRequest::class, $options);
    }
    
    public function disburse(array $options = []): DisburseRequest
    {
        return $this->createRequest(DisburseRequest::class, $options);
    }
}
```

---

### 3. RequestInterface

The contract for all requests.

```php
interface RequestInterface
{
    public function initialize(array $parameters = []): RequestInterface;
    public function getParameters(): array;
    public function getResponse(): ResponseInterface;
    public function send(): ResponseInterface;
    public function sendData($data): ResponseInterface;
    public function getData(): mixed;
}
```

---

### 4. AbstractRequest

Base implementation providing common request functionality.

```php
abstract class AbstractRequest implements RequestInterface
{
    use ParametersTrait;
    
    protected $httpClient;
    protected $httpRequest;
    protected $response;
    
    public function __construct(ClientInterface $httpClient, RequestInterface $httpRequest)
    {
        $this->httpClient = $httpClient;
        $this->httpRequest = $httpRequest;
        $this->initialize();
    }
    
    abstract public function getData(): mixed;
    abstract public function sendData($data): ResponseInterface;
    
    public function send(): ResponseInterface
    {
        $data = $this->getData();
        return $this->response = $this->sendData($data);
    }
    
    // Helper for making HTTP requests
    protected function sendRequest(
        string $method,
        string $endpoint,
        array $headers = [],
        $body = null
    ): ResponseInterface {
        // Uses $this->httpClient to make actual HTTP call
    }
}
```

**Workflow:**
1. `getData()` - Validate parameters and build request payload
2. `sendData($data)` - Execute HTTP call and return Response
3. `send()` - Convenience method that calls both

**Your Implementation:**
```php
class DisburseRequest extends AbstractRequest
{
    public function getData(): array
    {
        // Validate required parameters
        $this->validate('amount', 'accountNumber', 'bankCode');
        
        // Build API payload
        return [
            'amount' => $this->getAmount(),
            'destination' => [
                'account_number' => $this->getAccountNumber(),
                'bank_code' => $this->getBankCode(),
            ],
            'reference' => $this->getReference(),
        ];
    }
    
    public function sendData($data): ResponseInterface
    {
        // Get OAuth token
        $token = $this->getAccessToken();
        
        // Make HTTP call
        $httpResponse = $this->httpClient->request(
            'POST',
            $this->getEndpoint(),
            [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            json_encode($data)
        );
        
        // Parse response
        $json = json_decode($httpResponse->getBody()->getContents(), true);
        
        // Return Response object
        return $this->response = new DisburseResponse($this, $json);
    }
    
    protected function getEndpoint(): string
    {
        return $this->getParameter('apiEndpoint') . '/disbursements';
    }
    
    // Parameter getters/setters
    public function getAmount(): int
    {
        return $this->getParameter('amount');
    }
    
    public function setAmount(int $value): self
    {
        return $this->setParameter('amount', $value);
    }
}
```

---

### 5. ResponseInterface

The contract for all responses.

```php
interface ResponseInterface
{
    public function getRequest(): RequestInterface;
    public function isSuccessful(): bool;
    public function isRedirect(): bool;
    public function isCancelled(): bool;
    public function getMessage(): ?string;
    public function getCode(): ?string;
    public function getTransactionReference(): ?string;
}
```

---

### 6. AbstractResponse

Base implementation for responses.

```php
abstract class AbstractResponse implements ResponseInterface
{
    protected $request;
    protected $data;
    
    public function __construct(RequestInterface $request, $data)
    {
        $this->request = $request;
        $this->data = $data;
    }
    
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
    
    public function isRedirect(): bool
    {
        return false;
    }
    
    public function isCancelled(): bool
    {
        return false;
    }
    
    public function getData()
    {
        return $this->data;
    }
    
    // Subclasses must implement
    abstract public function isSuccessful(): bool;
    abstract public function getMessage(): ?string;
}
```

**Your Implementation:**
```php
class DisburseResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return isset($this->data['status']) && $this->data['status'] === 'success';
    }
    
    public function getMessage(): ?string
    {
        return $this->data['message'] ?? null;
    }
    
    public function getCode(): ?string
    {
        return $this->data['status_code'] ?? null;
    }
    
    public function getTransactionReference(): ?string
    {
        return $this->data['transaction_id'] ?? null;
    }
    
    // Custom getter for your use case
    public function getOperationId(): ?string
    {
        return $this->data['transaction_id'] ?? null;
    }
}
```

---

## Parameter Management

Omnipay uses **ParameterBag** for type-safe parameter storage.

### ParametersTrait

Provides getter/setter magic:

```php
trait ParametersTrait
{
    protected $parameters;
    
    public function getParameters(): array
    {
        return $this->parameters->all();
    }
    
    public function getParameter(string $key)
    {
        return $this->parameters->get($key);
    }
    
    public function setParameter(string $key, $value): self
    {
        $this->parameters->set($key, $value);
        return $this;
    }
}
```

### Magic Methods

Omnipay auto-generates getters/setters:

```php
// Instead of defining every getter/setter...
public function getAmount() { return $this->getParameter('amount'); }
public function setAmount($value) { return $this->setParameter('amount', $value); }

// You can use magic methods
$request->getAmount();  // Calls getParameter('amount')
$request->setAmount(100);  // Calls setParameter('amount', 100)
```

### Usage in Requests

```php
// In your request class
public function getData(): array
{
    // Get parameters using magic methods or explicit getters
    $amount = $this->getAmount();
    $account = $this->getParameter('accountNumber');
    
    return ['amount' => $amount, 'account' => $account];
}
```

---

## HTTP Client Abstraction

Omnipay uses **php-http/discovery** to auto-discover available PSR-18 HTTP clients.

### Installation

```bash
composer require php-http/guzzle7-adapter
```

This provides a Guzzle 7 adapter that implements PSR-18.

### Making HTTP Requests

```php
// In AbstractRequest, you have access to:
protected $httpClient;  // PSR-18 ClientInterface

// Make requests like this:
$httpResponse = $this->httpClient->request(
    'POST',
    'https://api.example.com/endpoint',
    [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer token',
    ],
    json_encode(['data' => 'value'])
);

// Get response body
$body = $httpResponse->getBody()->getContents();
$data = json_decode($body, true);
```

### Benefits

- **Testable**: Inject mock HTTP clients in tests
- **Swappable**: Change HTTP client without code changes
- **Standard**: PSR-18 compatible

---

## Common Patterns

### Pattern 1: OAuth2 Authentication

Many gateways use OAuth2. Here's the pattern:

```php
trait HasOAuth2
{
    protected $accessToken;
    protected $tokenExpiry;
    
    protected function getAccessToken(): string
    {
        // Check if token is cached and valid
        if ($this->accessToken && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }
        
        // Request new token
        $credentials = base64_encode(
            $this->getClientId() . ':' . $this->getClientSecret()
        );
        
        $response = $this->httpClient->request(
            'POST',
            $this->getTokenEndpoint(),
            [
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            http_build_query(['grant_type' => 'client_credentials'])
        );
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600);
        
        return $this->accessToken;
    }
}
```

### Pattern 2: Validation

```php
public function getData(): array
{
    // Validate required fields
    $this->validate('amount', 'accountNumber', 'bankCode');
    
    // Validate format
    if ($this->getAmount() < 100) {
        throw new InvalidRequestException('Amount must be at least 100');
    }
    
    return $this->buildPayload();
}
```

### Pattern 3: Error Handling

```php
public function sendData($data): ResponseInterface
{
    try {
        $httpResponse = $this->httpClient->request(...);
        $json = json_decode($httpResponse->getBody()->getContents(), true);
        
        return new DisburseResponse($this, $json);
        
    } catch (\Exception $e) {
        // Return error response
        return new DisburseResponse($this, [
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
}
```

---

## Testing with Omnipay

### Mock HTTP Responses

```php
use Http\Mock\Client as MockClient;

$mock = new MockClient();
$gateway = new NetbankGateway($mock, $httpRequest);

// Queue mock responses
$mock->addResponse(new Response(200, [], json_encode([
    'status' => 'success',
    'transaction_id' => 'TXN123',
])));

// Make request
$response = $gateway->disburse(['amount' => 1000])->send();

// Assert
$this->assertTrue($response->isSuccessful());
$this->assertEquals('TXN123', $response->getTransactionReference());
```

### Integration Tests

```php
public function test_real_disburse()
{
    $gateway = Omnipay::create('Netbank');
    $gateway->initialize([
        'clientId' => env('NETBANK_CLIENT_ID'),
        'clientSecret' => env('NETBANK_CLIENT_SECRET'),
    ]);
    
    $response = $gateway->disburse([
        'amount' => 100,
        'accountNumber' => '1234567890',
    ])->send();
    
    $this->assertTrue($response->isSuccessful());
}
```

---

## Benefits of Omnipay Architecture

### 1. Separation of Concerns
- **Gateway**: Configuration and request factory
- **Request**: Payload building and HTTP execution
- **Response**: Result parsing and access

### 2. Testability
- Mock HTTP clients
- Mock requests/responses
- No network calls in unit tests

### 3. Extensibility
- Add new gateways without changing existing code
- Add custom methods per gateway
- Share common code via traits

### 4. Maintainability
- Clear boundaries between layers
- Single Responsibility Principle
- Easy to debug (clear request → response flow)

### 5. Framework Agnostic
- No Laravel dependencies
- Works with any PHP framework
- Can be used in plain PHP

---

## Comparison with Your Current Implementation

### Current (NetBank-specific)

```php
// Tightly coupled to NetBank
class NetbankPaymentGateway implements PaymentGatewayInterface
{
    use CanGenerate, CanDisburse;
    
    protected function getAccessToken() { /* OAuth logic */ }
}

// Traits mix HTTP, auth, and business logic
trait CanDisburse
{
    public function disburse(Wallet $wallet, array $data) {
        // HTTP call inline
        // Transaction logic inline
        // Error handling inline
    }
}
```

### With Omnipay

```php
// Gateway is just configuration
class NetbankGateway extends AbstractGateway
{
    public function getName() { return 'Netbank'; }
    public function disburse(array $options) {
        return $this->createRequest(DisburseRequest::class, $options);
    }
}

// Request handles HTTP
class DisburseRequest extends AbstractRequest
{
    public function getData() { /* build payload */ }
    public function sendData($data) { /* execute HTTP */ }
}

// Response handles parsing
class DisburseResponse extends AbstractResponse
{
    public function isSuccessful() { /* check status */ }
}

// Your app layer handles business logic
class PaymentService
{
    public function disburse(Wallet $wallet, array $data) {
        DB::beginTransaction();
        $transaction = $wallet->withdraw(...);
        
        $response = $this->gateway->disburse($data)->send();
        
        if ($response->isSuccessful()) {
            $transaction->confirm();
            DB::commit();
        } else {
            DB::rollBack();
        }
    }
}
```

**Clear separation**: Gateway config → HTTP execution → Business logic

---

## Summary

Omnipay's architecture provides:
- **Consistent interface** across all payment gateways
- **Request/Response pattern** for clean separation
- **Parameter management** via ParameterBag
- **HTTP abstraction** via PSR-18
- **Testability** through dependency injection
- **Extensibility** through inheritance and traits

This makes it ideal for applications that need to support multiple payment gateways without rewriting integration code.
