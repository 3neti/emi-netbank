# Environment Variables - Quick Reference

Quick copy-paste reference for NetBank gateway configuration.

## ✅ Current Configuration Status

Your `.env` file is **complete** with all required variables. This is your current setup:

```bash
# Authentication
NETBANK_CLIENT_ID=provider-issued-client-id
NETBANK_CLIENT_SECRET=provider-issued-client-secret

# API Endpoints
NETBANK_TOKEN_ENDPOINT=https://auth.netbank.ph/oauth2/token
NETBANK_DISBURSEMENT_ENDPOINT=https://api.netbank.ph/v1/transactions
NETBANK_QR_ENDPOINT=https://api.netbank.ph/v1/qrph/generate
NETBANK_STATUS_ENDPOINT=https://api.netbank.ph/v1/transactions
NETBANK_BALANCE_ENDPOINT=https://api.netbank.ph/v1/accounts/balance

# Configuration
NETBANK_TEST_MODE=true  ⚠️ CRITICAL for safety
NETBANK_CLIENT_ALIAS=91500
NETBANK_SOURCE_ACCOUNT_NUMBER=113-001-00001-9
NETBANK_SENDER_CUSTOMER_ID=90627

# Standing Account Funding Address
# Local/development/testing:
NETBANK_FUNDING_STANDING_ADDRESS_SCHEME=netbank-mobile-v1
NETBANK_FUNDING_VCA_REFERENCE_LENGTH=11

# Production (store the key in managed secrets):
# NETBANK_FUNDING_STANDING_ADDRESS_SCHEME=netbank-account-hmac-v2
# NETBANK_FUNDING_STANDING_HMAC_KEY_ID=v2-2026-01
# NETBANK_FUNDING_STANDING_HMAC_KEY=base64:<dedicated-secret-of-at-least-32-bytes>

# Sender Details (KYC)
NETBANK_SENDER_ADDRESS_ADDRESS1="Salcedo Village"
NETBANK_SENDER_ADDRESS_CITY="Makati City"
NETBANK_SENDER_ADDRESS_POSTAL_CODE=1227

# Limits
MINIMUM_DISBURSEMENT=10
MAXIMUM_DISBURSEMENT=50000
```

## 📋 Variable Mapping to Config

| `.env` Variable | Config Path | Used By |
|----------------|-------------|---------|
| `NETBANK_CLIENT_ID` | `omnipay.gateways.netbank.options.clientId` | OAuth2 authentication |
| `NETBANK_CLIENT_SECRET` | `omnipay.gateways.netbank.options.clientSecret` | OAuth2 authentication |
| `NETBANK_TOKEN_ENDPOINT` | `omnipay.gateways.netbank.options.tokenEndpoint` | OAuth2 token requests |
| `NETBANK_DISBURSEMENT_ENDPOINT` | `omnipay.gateways.netbank.options.apiEndpoint` | Disbursement API calls |
| `NETBANK_QR_ENDPOINT` | `omnipay.gateways.netbank.options.qrEndpoint` | QR code generation |
| `NETBANK_STATUS_ENDPOINT` | `omnipay.gateways.netbank.options.statusEndpoint` | Transaction status checks |
| `NETBANK_BALANCE_ENDPOINT` | `omnipay.gateways.netbank.options.balanceEndpoint` | Balance queries |
| `NETBANK_TEST_MODE` | `omnipay.gateways.netbank.options.testMode` | Test/prod mode toggle |

## 🚀 Quick Start Commands

```bash
# 1. Verify config is loaded
php artisan config:clear && php artisan config:cache

# 2. Check balance (read-only, safest test)
php artisan omnipay:balance

# 3. Generate QR code (safe, no money transfer)
php artisan omnipay:qr 09171234567 50

# 4. Test disbursement (⚠️ transfers real money!)
php artisan omnipay:disburse 10 09171234567 GXCHPHM2XXX INSTAPAY
```

## ⚠️ Safety Checklist

Before running any commands:
- [ ] `NETBANK_TEST_MODE=true` is set
- [ ] Config cache is cleared and rebuilt
- [ ] Using your own test account/mobile number
- [ ] Starting with minimum amount (₱10)

## 🔍 Verification Commands

```bash
# Check all NETBANK variables
cat .env | grep NETBANK

# Verify specific config values
php artisan tinker
>>> config('omnipay.gateways.netbank.options.testMode')
>>> config('omnipay.gateways.netbank.options.balanceEndpoint')
>>> exit
```

## 📊 Required Variables Summary

| Category | Count | Status |
|----------|-------|--------|
| **Authentication** | 2 | ✅ Complete |
| **API Endpoints** | 5 | ✅ Complete |
| **Account Config** | 3 | ✅ Complete |
| **Sender Details** | 3 | ✅ Complete |
| **Test Mode** | 1 | ✅ Set to `true` |

**Total:** 14/14 required variables configured ✅

## 🎯 Next Steps

1. **Validate configuration:**
   ```bash
   cd packages/payment-gateway
   php artisan config:clear && php artisan config:cache
   ```

2. **Test balance check (safest):**
   ```bash
   php artisan omnipay:balance
   ```

3. **Follow complete walkthrough:**
   See [LIVE_TESTING_WALKTHROUGH.md](LIVE_TESTING_WALKTHROUGH.md)

## 📚 Full Documentation

- **[ENVIRONMENT_VARIABLES.md](ENVIRONMENT_VARIABLES.md)** - Complete reference with explanations
- **[LIVE_TESTING_WALKTHROUGH.md](LIVE_TESTING_WALKTHROUGH.md)** - Step-by-step testing guide
- **[TESTING_COMMANDS.md](TESTING_COMMANDS.md)** - Command usage and options

---

**Status:** ✅ Ready to test  
**Test Mode:** ✅ Enabled  
**Configuration:** ✅ Complete
