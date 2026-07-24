# Environment Variables Guide

This document provides a comprehensive reference for all environment variables used in the payment gateway package.

## Table of Contents

1. [NetBank Gateway](#netbank-gateway)
2. [ICash Gateway](#icash-gateway)
3. [General Configuration](#general-configuration)
4. [KYC Settings](#kyc-settings)
5. [Testing Settings](#testing-settings)
6. [Quick Start](#quick-start)

---

## NetBank Gateway

### Funding and Standing QR Ph Addresses

The funding adapter uses a 16-digit VCA profile: a five-digit NetBank alias plus an eleven-digit reference.

```bash
NETBANK_FUNDING_API_URL=https://api.netbank.ph
NETBANK_FUNDING_TOKEN_URL=https://auth.netbank.ph/oauth2/token
NETBANK_FUNDING_CLIENT_ID=provider-issued-client-id
NETBANK_FUNDING_CLIENT_SECRET=provider-issued-client-secret
NETBANK_FUNDING_CORPORATE_ACCOUNT_NUMBER=provider-issued-account-number
NETBANK_FUNDING_CORPORATE_ACCOUNT_NAME="Registered account name"
NETBANK_FUNDING_VCA_ALIAS=91500
NETBANK_FUNDING_REFERENCE_KEY=dedicated-provider-request-reference-key
NETBANK_FUNDING_QR_ENDPOINT=https://api.netbank.ph/v1/qrph/generate
NETBANK_FUNDING_QR_MERCHANT_NAME="X Change"
NETBANK_FUNDING_QR_MERCHANT_CITY=Manila
```

Standing-address derivation:

```bash
# Local/development/testing default:
NETBANK_FUNDING_STANDING_ADDRESS_SCHEME=netbank-mobile-v1

# Production requirement:
NETBANK_FUNDING_STANDING_ADDRESS_SCHEME=netbank-account-hmac-v2
NETBANK_FUNDING_VCA_REFERENCE_LENGTH=11
NETBANK_FUNDING_STANDING_HMAC_KEY_ID=v2-2026-01
NETBANK_FUNDING_STANDING_HMAC_KEY=base64:<dedicated-secret-of-at-least-32-bytes>
```

`netbank-mobile-v1` uses a verified `09XXXXXXXXX` mobile as the readable eleven-digit suffix. It is convenient for local seeded users, but exposes a correlatable identifier and cannot safely represent multiple Accounts that share one mobile. Production rejects it.

`netbank-account-hmac-v2` uses a purpose-separated HMAC over the immutable Account reference and a persisted collision counter. Its key is dedicated to this derivation domain and never falls back to `APP_KEY`. Keep the key stable in managed secret storage. A new key affects only new bindings because consumers must persist and reopen the exact issued address.

`NETBANK_FUNDING_VCA_ALIAS_TOKEN` is required by registered one-time VCA/Funding Intent operations. It is not required to render a shared reusable static QR for an already-routable 16-digit funding address.

For VCA history rows classified as incoming credits (`Credit` and `EXTERNAL_TRANSFER_INCOMING`), NetBank's `amount.num` is normalized as the credited amount received. The provider `fees` collection is not subtracted from it a second time. These observations carry normalization version `netbank-standing-credit-v2`, with normalized fee `0` and net equal to the credited amount. A version change creates new immutable normalized evidence in `emi-core`; it never rewrites the raw provider payload or an earlier observation.

### Authentication (Required)

These credentials are provided by NetBank when you register for API access.

```bash
NETBANK_CLIENT_ID=your_client_id_here
NETBANK_CLIENT_SECRET=your_client_secret_here
```

**How to obtain:**
1. Register for NetBank API access at https://developer.netbank.ph
2. Create an application in the developer portal
3. Copy the Client ID and Client Secret

---

### API Endpoints (Required)

The complete set of NetBank API endpoints. These should match your environment (sandbox vs production).

```bash
# OAuth2 token endpoint for authentication
NETBANK_TOKEN_ENDPOINT=https://auth.netbank.ph/oauth2/token

# Disbursement API endpoint
NETBANK_DISBURSEMENT_ENDPOINT=https://api.netbank.ph/v1/transactions

# QR code generation endpoint
NETBANK_QR_ENDPOINT=https://api.netbank.ph/v1/qrph/generate

# Transaction status checking endpoint (use :operationId as placeholder)
NETBANK_STATUS_ENDPOINT=https://api.netbank.ph/v1/transactions/:operationId

# Account balance checking endpoint
NETBANK_BALANCE_ENDPOINT=https://api.netbank.ph/v1/accounts/balance
```

**Sandbox vs Production:**

For **sandbox/testing**:
```bash
NETBANK_TOKEN_ENDPOINT=https://sandbox-auth.netbank.ph/oauth2/token
NETBANK_DISBURSEMENT_ENDPOINT=https://sandbox-api.netbank.ph/v1/transactions
NETBANK_QR_ENDPOINT=https://sandbox-api.netbank.ph/v1/qrph/generate
NETBANK_STATUS_ENDPOINT=https://sandbox-api.netbank.ph/v1/transactions/:operationId
NETBANK_BALANCE_ENDPOINT=https://sandbox-api.netbank.ph/v1/accounts/balance
```

For **production** (use the endpoints shown in the first code block above).

---

### Account Configuration (Required)

Your NetBank account details for processing transactions.

```bash
# Your branch or wallet user identifier
NETBANK_CLIENT_ALIAS=91500

# Source account number for disbursements (format varies by bank)
NETBANK_SOURCE_ACCOUNT_NUMBER=113-001-00001-9

# Your customer ID in NetBank's system
NETBANK_SENDER_CUSTOMER_ID=90627
```

**Where to find these:**
- Log into your NetBank dashboard
- Navigate to Settings > API Configuration
- Copy the values from your account profile

---

### Sender Details (Required for KYC)

Required sender information for compliance with BSP (Bangko Sentral ng Pilipinas) regulations.

```bash
NETBANK_SENDER_ADDRESS_ADDRESS1="Salcedo Village"
NETBANK_SENDER_ADDRESS_CITY="Makati City"
NETBANK_SENDER_ADDRESS_POSTAL_CODE=1227
```

**Note:** When `GATEWAY_RANDOMIZE_ADDRESS=true` (see KYC Settings), these values are used as fallbacks if the randomization service fails.

---

### Test Mode (Critical for Safety)

Controls whether the gateway operates in test or production mode.

```bash
NETBANK_TEST_MODE=true  # Set to false for production
```

**⚠️ IMPORTANT:**
- **ALWAYS** set this to `true` during development and testing
- Commands will display "Running in TEST MODE" warnings when enabled
- Set to `false` ONLY when ready for production transactions
- Double-check this value before any live disbursement

---

### Settlement Rail Configuration (Optional)

Override default limits and fees for INSTAPAY and PESONET rails.

```bash
# Enable/disable specific rails
NETBANK_INSTAPAY_ENABLED=true
NETBANK_PESONET_ENABLED=true
```

**Default values** (configured in `config/omnipay.php`):
- **INSTAPAY**: ₱0.01 - ₱50,000, ₱10 fee
- **PESONET**: ₱0.01 - ₱1,000,000, ₱25 fee

---

## ICash Gateway

### Authentication (Required)

```bash
ICASH_API_KEY=your_api_key
ICASH_API_SECRET=your_api_secret
```

### API Configuration (Required)

```bash
ICASH_API_ENDPOINT=https://api.icash.ph/v1
ICASH_TEST_MODE=true
```

### Settlement Rails (Optional)

```bash
ICASH_INSTAPAY_ENABLED=true
```

---

## General Configuration

### Default Gateway Selection

```bash
# Which gateway to use by default (netbank or icash)
PAYMENT_GATEWAY=netbank
```

### Feature Flags

```bash
# Enable Omnipay integration (set to true to use new gateway system)
USE_OMNIPAY=true
```

---

## KYC Settings

### Address Randomization

The gateway can randomize sender addresses to work around strict KYC validation during testing.

```bash
# Enable address randomization for testing
GATEWAY_RANDOMIZE_ADDRESS=true
```

**How it works:**
- When `true`, the gateway generates random but valid Philippine addresses
- Falls back to `NETBANK_SENDER_ADDRESS_*` values if randomization fails
- Uses `LBHurtado\PaymentGateway\Support\Address` service
- Recommended for **testing only**

---

## Testing Settings

### Test Account

```bash
# Default account number for testing commands
OMNIPAY_TEST_ACCOUNT=1234567890
```

Used by `php artisan omnipay:balance` when no `--account` option is provided.

### Disbursement Limits

```bash
# Minimum disbursement amount (in pesos)
MINIMUM_DISBURSEMENT=10

# Maximum disbursement amount (in pesos)
MAXIMUM_DISBURSEMENT=50000
```

These override the rail-specific limits configured in `config/omnipay.php`.

---

## Quick Start

### Minimal Configuration for Testing

Copy this to your `.env` to get started quickly:

```bash
# Required: NetBank credentials (replace with your values)
NETBANK_CLIENT_ID=your_client_id_here
NETBANK_CLIENT_SECRET=your_client_secret_here

# Required: NetBank endpoints (sandbox)
NETBANK_TOKEN_ENDPOINT=https://sandbox-auth.netbank.ph/oauth2/token
NETBANK_DISBURSEMENT_ENDPOINT=https://sandbox-api.netbank.ph/v1/transactions
NETBANK_QR_ENDPOINT=https://sandbox-api.netbank.ph/v1/qrph/generate
NETBANK_STATUS_ENDPOINT=https://sandbox-api.netbank.ph/v1/transactions/:operationId
NETBANK_BALANCE_ENDPOINT=https://sandbox-api.netbank.ph/v1/accounts/balance

# Required: Account configuration (replace with your values)
NETBANK_CLIENT_ALIAS=your_alias
NETBANK_SOURCE_ACCOUNT_NUMBER=your_account_number
NETBANK_SENDER_CUSTOMER_ID=your_customer_id

# Required: Sender address (use your actual address)
NETBANK_SENDER_ADDRESS_ADDRESS1="Your Street Address"
NETBANK_SENDER_ADDRESS_CITY="Your City"
NETBANK_SENDER_ADDRESS_POSTAL_CODE=1234

# Safety: Enable test mode
NETBANK_TEST_MODE=true

# Feature flags: Enable Omnipay
USE_OMNIPAY=true

# Testing helpers
GATEWAY_RANDOMIZE_ADDRESS=true
OMNIPAY_TEST_ACCOUNT=your_test_account
```

### Complete Configuration (All Variables)

For a complete `.env.example` with all variables, see: `packages/payment-gateway/.env.example`

---

## Validation Checklist

Before running live transactions, verify:

- [ ] `NETBANK_CLIENT_ID` and `NETBANK_CLIENT_SECRET` are set correctly
- [ ] All 5 `NETBANK_*_ENDPOINT` variables are set
- [ ] `NETBANK_TEST_MODE=true` for testing, `false` for production
- [ ] `NETBANK_CLIENT_ALIAS`, `NETBANK_SOURCE_ACCOUNT_NUMBER`, and `NETBANK_SENDER_CUSTOMER_ID` are configured
- [ ] Sender address fields are filled in
- [ ] `USE_OMNIPAY=true` to enable the new gateway system
- [ ] Run `php artisan config:clear && php artisan config:cache` after changes

---

## Troubleshooting

### "Failed to initialize gateway"

**Cause:** Missing or invalid credentials

**Solution:**
```bash
# Check current values
cat .env | grep NETBANK

# Clear and rebuild config cache
php artisan config:clear
php artisan config:cache

# Verify config loads correctly
php artisan tinker
>>> config('omnipay.gateways.netbank.options.clientId')
```

### "OAuth2 authentication failed"

**Cause:** Invalid `NETBANK_CLIENT_ID` or `NETBANK_CLIENT_SECRET`

**Solution:**
1. Verify credentials in NetBank developer portal
2. Check that you're using sandbox credentials for sandbox endpoints
3. Regenerate credentials if necessary

### "Endpoint not found" or 404 errors

**Cause:** Incorrect endpoint URLs

**Solution:**
1. Verify you're using the correct environment (sandbox vs production)
2. Check for typos in endpoint URLs
3. Ensure endpoints match your NetBank API version

### Balance check returns error

**Cause:** Missing `NETBANK_BALANCE_ENDPOINT`

**Solution:**
```bash
# Add to .env
echo "NETBANK_BALANCE_ENDPOINT=https://api.netbank.ph/v1/accounts/balance" >> .env

# Clear config
php artisan config:clear
php artisan config:cache
```

---

## Security Best Practices

1. **Never commit `.env` to version control**
   - Use `.env.example` as a template
   - Keep actual credentials private

2. **Use different credentials for each environment**
   - Separate credentials for local, staging, production
   - Rotate credentials regularly

3. **Restrict API key permissions**
   - Use least-privilege principle
   - Enable only necessary operations in NetBank portal

4. **Monitor for suspicious activity**
   - Review transaction logs regularly
   - Set up alerts for unusual patterns

5. **Keep test mode enabled until production-ready**
   - Test thoroughly in sandbox environment
   - Perform dry runs with small amounts

---

## Related Documentation

- [Testing Commands Guide](TESTING_COMMANDS.md) - How to use the testing commands
- [Omnipay Integration Guide](../README.md) - Architecture and implementation details
- [NetBank API Documentation](https://developer.netbank.ph/docs) - Official API reference

---

## Support

For issues related to:

- **Environment variable configuration**: Check this document first
- **NetBank API access**: Contact NetBank support or check their developer portal
- **Package bugs or features**: Open an issue on the GitHub repository
- **Local development setup**: See `TESTING_COMMANDS.md` for walkthrough

---

**Last Updated:** Phase 4.5 (Live Testing Commands)  
**Package Version:** 1.0.0
