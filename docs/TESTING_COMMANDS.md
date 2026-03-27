# Omnipay Testing Commands

Artisan commands for testing Omnipay gateway operations with real API credentials.

⚠️ **WARNING**: These commands make REAL API calls and can process REAL transactions. Use with caution!

## Table of Contents

1. [Quick Start](#quick-start)
2. [Commands Reference](#commands-reference)
3. [Configuration](#configuration)
4. [Examples](#examples)
5. [Safety Guidelines](#safety-guidelines)
6. [Troubleshooting](#troubleshooting)

---

## Quick Start

### Prerequisites

1. Valid API credentials configured in `.env`
2. Omnipay gateway enabled in `config/omnipay.php`
3. (Optional) Test account number set

```bash
# .env
NETBANK_CLIENT_ID=your_client_id
NETBANK_CLIENT_SECRET=your_client_secret
NETBANK_API_URL=https://api.netbank.example.com
NETBANK_TEST_MODE=true
OMNIPAY_TEST_ACCOUNT=1234567890
```

### Quick Test Sequence

```bash
# 1. Check balance
php artisan omnipay:balance

# 2. Generate QR code
php artisan omnipay:qr 1234567890 100

# 3. Test disbursement (requires confirmation)
php artisan omnipay:disburse 10 09171234567 GXCHPHM2XXX INSTAPAY
```

---

## Commands Reference

### 1. Check Balance

Check account balance from the payment gateway.

```bash
php artisan omnipay:balance [options]
```

**Options:**
- `--gateway=netbank` - Gateway to use (default: netbank)
- `--account=NUMBER` - Account number (uses config if not provided)

**Examples:**
```bash
# Using default account from config
php artisan omnipay:balance

# Specify account
php artisan omnipay:balance --account=1234567890

# Use different gateway
php artisan omnipay:balance --gateway=icash
```

**Expected Output:**
```
Checking Account Balance
==================================================

⚠️  Running in TEST MODE
Gateway: netbank

Checking balance for account: 1234567890...

✓ Balance retrieved successfully!

+--------------------+----------------------+
| Field              | Value                |
+--------------------+----------------------+
| Account            | 1234567890           |
| Balance            | ₱12,500.00 PHP       |
| Available Balance  | ₱12,000.00 PHP       |
| Currency           | PHP                  |
| As Of              | 2024-11-13 23:00:00  |
+--------------------+----------------------+
```

---

### 2. Generate QR Code

Generate a QR code for payment collection.

```bash
php artisan omnipay:qr {account} [amount] [options]
```

**Arguments:**
- `account` - Account number for QR code (required)
- `amount` - Amount in pesos (optional - creates fixed-amount QR if provided)

**Options:**
- `--gateway=netbank` - Gateway to use
- `--save=PATH` - File path to save QR code data

**Examples:**
```bash
# Dynamic amount QR (user enters amount during payment)
php artisan omnipay:qr 1234567890

# Fixed amount QR (₱100)
php artisan omnipay:qr 1234567890 100

# Save QR code data to file
php artisan omnipay:qr 1234567890 100 --save=qr_code.txt
```

**Expected Output:**
```
Generate QR Code
==================================================

⚠️  Running in TEST MODE
Gateway: netbank

Generating FIXED-AMOUNT QR code for ₱100.00 PHP...
Account: 1234567890

✓ QR Code generated successfully!

+-------------+--------------------------------------+
| Field       | Value                                |
+-------------+--------------------------------------+
| QR ID       | qr_abc123xyz                         |
| Account     | 1234567890                           |
| Reference   | QR-673B4F12                          |
| Amount      | ₱100.00 PHP                          |
| QR URL      | https://api.netbank.../qr/abc123     |
| Expires At  | 2024-12-31 23:59:59                  |
+-------------+--------------------------------------+

QR Code Data:
00020101021226370011com.netbank01090123456780211QR123456780303PHP5204000053030...

Note: Use this QR code for testing payments. Share via QR URL or encode the data.
```

---

### 3. Test Disbursement

⚠️ **DANGER**: This command processes REAL transactions and transfers REAL money!

```bash
php artisan omnipay:disburse {amount} {account} {bank} {rail} [options]
```

**Arguments:**
- `amount` - Amount in pesos (e.g., 100 for ₱100.00)
- `account` - Account number or mobile number
- `bank` - Bank SWIFT/BIC code (e.g., GXCHPHM2XXX for GCash)
- `rail` - Settlement rail (INSTAPAY or PESONET)

**Options:**
- `--reference=REF` - Custom reference (auto-generated if not provided)
- `--gateway=netbank` - Gateway to use
- `--no-confirm` - Skip confirmation prompt (**DANGEROUS!**)

**Examples:**
```bash
# Disburse to GCash via INSTAPAY (requires confirmation)
php artisan omnipay:disburse 100 09171234567 GXCHPHM2XXX INSTAPAY

# With custom reference
php artisan omnipay:disburse 100 09171234567 GXCHPHM2XXX INSTAPAY --reference=PAY-001

# Disburse to BDO via PESONET
php artisan omnipay:disburse 5000 1234567890 BNORPHMMXXX PESONET
```

**Expected Output:**
```
⚠️  DISBURSEMENT TEST - REAL MONEY WILL BE TRANSFERRED
==================================================

⚠️  Running in PRODUCTION MODE - Real transactions will be processed!
Gateway: netbank

Disbursement Details:
+------------------+--------------------------------------------+
| Field            | Value                                      |
+------------------+--------------------------------------------+
| Amount           | ₱100.00 PHP                                |
| Account          | 09171234567                                |
| Bank             | G-Xchange / GCash (GXCHPHM2XXX) [EMI]      |
| Settlement Rail  | INSTAPAY                                   |
| Fee              | ₱10.00 PHP                                 |
| Total Debit      | ₱110.00 PHP                                |
| Reference        | TEST-673B4F12A                             |
+------------------+--------------------------------------------+

⚠️  This will initiate a REAL DISBURSEMENT!

  Amount:  ₱100.00 PHP
  Account:  09171234567
  Bank:  G-Xchange / GCash (GXCHPHM2XXX) [EMI]
  Settlement Rail:  INSTAPAY
  Fee:  ₱10.00 PHP
  Total Debit:  ₱110.00 PHP
  Reference:  TEST-673B4F12A

 Do you want to continue? (yes/no) [no]:
 > yes

Processing disbursement...

✓ Disbursement initiated successfully!

+------------------+---------------------------+
| Field            | Value                     |
+------------------+---------------------------+
| Transaction ID   | TXN-9876543210            |
| Status           | pending                   |
| Settlement Rail  | INSTAPAY                  |
| Reference        | TEST-673B4F12A            |
+------------------+---------------------------+

Note: Transaction may take time to process. Check your dashboard for status updates.
```

---

## Configuration

### Required Environment Variables

```bash
# NetBank Gateway - Authentication
NETBANK_CLIENT_ID=your_client_id
NETBANK_CLIENT_SECRET=your_client_secret

# NetBank Gateway - API Endpoints
NETBANK_TOKEN_ENDPOINT=https://auth.netbank.ph/oauth2/token
NETBANK_DISBURSEMENT_ENDPOINT=https://api.netbank.ph/v1/transactions
NETBANK_QR_ENDPOINT=https://api.netbank.ph/v1/qrph/generate
NETBANK_STATUS_ENDPOINT=https://api.netbank.ph/v1/transactions/:operationId
NETBANK_BALANCE_ENDPOINT=https://api.netbank.ph/v1/accounts/balance

# NetBank Gateway - Configuration
NETBANK_TEST_MODE=true  # CRITICAL: Set to true for testing!
NETBANK_CLIENT_ALIAS=91500  # Your branch/wallet user ID
NETBANK_SOURCE_ACCOUNT_NUMBER=113-001-00001-9  # Source account for disbursements

# NetBank Gateway - Sender Details (for KYC)
NETBANK_SENDER_CUSTOMER_ID=90627
NETBANK_SENDER_ADDRESS_ADDRESS1="Salcedo Village"
NETBANK_SENDER_ADDRESS_CITY="Makati City"
NETBANK_SENDER_ADDRESS_POSTAL_CODE=1227

# Feature flags
USE_OMNIPAY=true  # Enable Omnipay integration

# Optional: Override default test account
OMNIPAY_TEST_ACCOUNT=1234567890
```

### Configuration Files

**`config/omnipay.php`** - Gateway configuration with rails and limits

---

## Examples

### Complete Testing Workflow

```bash
# 1. Check current balance
php artisan omnipay:balance
# Expected: ₱10,000.00

# 2. Generate QR for testing
php artisan omnipay:qr 1234567890 50

# 3. Test small disbursement to GCash
php artisan omnipay:disburse 10 09171234567 GXCHPHM2XXX INSTAPAY
# Confirm with 'yes'

# 4. Check balance again
php artisan omnipay:balance
# Expected: ₱9,990.00 (₱10 + ₱10 fee deducted)
```

### Testing Different Rails

```bash
# INSTAPAY (fast, ₱50K limit, ₱10 fee)
php artisan omnipay:disburse 100 09171234567 GXCHPHM2XXX INSTAPAY

# PESONET (batch, ₱1M limit, ₱25 fee)
php artisan omnipay:disburse 60000 1234567890 BNORPHMMXXX PESONET
```

### Testing EMIs vs Banks

```bash
# EMI: GCash
php artisan omnipay:disburse 50 09171234567 GXCHPHM2XXX INSTAPAY

# EMI: PayMaya
php artisan omnipay:disburse 50 09181234567 PAPHPHM1XXX INSTAPAY

# Traditional Bank: BDO
php artisan omnipay:disburse 100 1234567890 BNORPHMMXXX INSTAPAY
```

---

## Safety Guidelines

### ⚠️ CRITICAL SAFETY RULES

1. **ALWAYS use test mode** when testing
   - Set `NETBANK_TEST_MODE=true` in `.env`
   - Verify "Running in TEST MODE" appears in output

2. **Start with small amounts**
   - Test with ₱1 - ₱10 initially
   - Gradually increase after confirming success

3. **Use test accounts only**
   - Use your own test mobile number for EMIs
   - Use dedicated test bank accounts

4. **Never use `--no-confirm` in production**
   - This flag is for automation only
   - Always require confirmation for real money

5. **Monitor your gateway dashboard**
   - Check transaction status after each test
   - Verify amounts match expectations

6. **Keep logs of all operations**
   - Commands automatically log to `storage/logs/laravel.log`
   - Review logs for audit trail

### Production Use

When moving to production:

1. Change `NETBANK_TEST_MODE=false`
2. Update API credentials to production values
3. Test with smallest possible amount first (₱1)
4. Set up monitoring and alerts
5. Have rollback plan ready

---

## Troubleshooting

### Common Issues

#### 1. "Failed to initialize gateway"

**Problem**: Gateway configuration not found or invalid credentials

**Solution**:
```bash
# Check if config exists
php artisan config:clear
php artisan config:cache

# Verify credentials in .env
cat .env | grep NETBANK
```

#### 2. "Bank code 'XXX' not found in registry"

**Problem**: Invalid or unsupported bank code

**Solution**:
- Check `resources/documents/banks.json` for valid codes
- Use correct SWIFT/BIC format (e.g., GXCHPHM2XXX)
- Common codes:
  - GCash: `GXCHPHM2XXX`
  - PayMaya: `PAPHPHM1XXX`
  - BDO: `BNORPHMMXXX`

#### 3. "Bank does not support RAIL settlement rail"

**Problem**: Selected bank doesn't support the chosen rail

**Solution**:
- Check supported rails in error message
- Most EMIs support INSTAPAY
- Traditional banks often support both INSTAPAY and PESONET

#### 4. "Amount exceeds RAIL limit"

**Problem**: Amount exceeds rail limit (INSTAPAY: ₱50K, PESONET: ₱1M)

**Solution**:
- Use PESONET for amounts over ₱50,000
- Split large amounts into multiple transactions if needed

#### 5. "Gateway does not support RAIL settlement rail"

**Problem**: Rail not enabled in gateway configuration

**Solution**:
- Check `config/omnipay.php` rails configuration
- Ensure `enabled => true` for the rail
- Clear config cache: `php artisan config:clear`

### Debugging

Enable verbose output:
```bash
php artisan omnipay:disburse 10 09171234567 GXCHPHM2XXX INSTAPAY -v
```

Check logs:
```bash
tail -f storage/logs/laravel.log | grep "Omnipay Test"
```

### Getting Help

1. Review this documentation
2. Check `docs/OMNIPAY_ARCHITECTURE.md` for technical details
3. Review `docs/IMPLEMENTATION_PLAN_UPDATED.md` for implementation notes
4. Check gateway API documentation for error codes

---

## Bank Codes Quick Reference

| Institution | Code | Type | Rails |
|------------|------|------|-------|
| GCash | GXCHPHM2XXX | EMI | INSTAPAY, PESONET |
| PayMaya | PAPHPHM1XXX | EMI | INSTAPAY, PESONET |
| BDO | BNORPHMMXXX | Bank | INSTAPAY, PESONET |
| BPI | BOPIPHMXXXX | Bank | INSTAPAY, PESONET |
| Metrobank | MBTCPHMXXXX | Bank | INSTAPAY, PESONET |

Full list available in `resources/documents/banks.json`

---

## Settlement Rails Comparison

| Feature | INSTAPAY | PESONET |
|---------|----------|---------|
| **Speed** | Real-time (seconds) | Batch (hours) |
| **Limit** | ₱50,000 | ₱1,000,000+ |
| **Fee** | ₱10 (typical) | ₱25 (typical) |
| **Best For** | Small amounts, urgent | Large amounts, non-urgent |
| **Processing** | 24/7 | Business days only |

---

## Changelog

### Phase 4.5 (2024-11-13)
- Initial release of testing commands
- Added `omnipay:balance` command
- Added `omnipay:qr` command
- Added `omnipay:disburse` command
- Implemented safety confirmations
- Added comprehensive logging

---

**Last Updated**: 2024-11-13  
**Version**: 1.0.0  
**Status**: Production Ready
