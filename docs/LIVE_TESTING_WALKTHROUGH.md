# Live NetBank Testing Walkthrough

This walkthrough guides you through testing the NetBank gateway integration with real API calls in a safe, controlled manner.

## Prerequisites

✅ Your `.env` file is configured with all required variables (see [ENVIRONMENT_VARIABLES.md](ENVIRONMENT_VARIABLES.md))  
✅ `NETBANK_TEST_MODE=true` is set (critical for safety)  
✅ You have valid NetBank API credentials  
✅ Your NetBank account has sufficient balance for testing

---

## Environment Validation

### Step 1: Verify Configuration

Check that all required environment variables are set:

```bash
cd packages/payment-gateway
cat .env | grep NETBANK
```

**Expected output should include:**
```bash
NETBANK_DISBURSEMENT_ENDPOINT=https://api.netbank.ph/v1/transactions
NETBANK_TOKEN_ENDPOINT=https://auth.netbank.ph/oauth2/token
NETBANK_QR_ENDPOINT=https://api.netbank.ph/v1/qrph/generate
NETBANK_STATUS_ENDPOINT=https://api.netbank.ph/v1/transactions/:operationId
NETBANK_BALANCE_ENDPOINT=https://api.netbank.ph/v1/accounts/balance
NETBANK_TEST_MODE=true
NETBANK_CLIENT_ID=6mh9Pu6JHVQgj0PsotH6Zyob
NETBANK_CLIENT_SECRET=6oL5wM07lCKzQo0HRl3NJRMS1YdOCPnzhbdBUq38u9rfrtOu
NETBANK_CLIENT_ALIAS=91500
NETBANK_SOURCE_ACCOUNT_NUMBER=113-001-00001-9
NETBANK_SENDER_CUSTOMER_ID=90627
NETBANK_SENDER_ADDRESS_ADDRESS1="Salcedo Village"
NETBANK_SENDER_ADDRESS_CITY="Makati City"
NETBANK_SENDER_ADDRESS_POSTAL_CODE=1227
```

✅ **Checklist:**
- [ ] All 5 endpoint variables present
- [ ] `NETBANK_TEST_MODE=true` is set
- [ ] Client ID and secret are present
- [ ] Account configuration is complete
- [ ] Sender address details are filled

---

### Step 2: Clear Configuration Cache

Ensure Laravel picks up the latest `.env` changes:

```bash
php artisan config:clear
php artisan config:cache
```

**Expected output:**
```
Configuration cache cleared successfully.
Configuration cached successfully.
```

---

### Step 3: Verify Omnipay Config Loads

Check that the gateway configuration is properly loaded:

```bash
php artisan tinker
```

Then run these checks:

```php
// Check gateway is registered
config('omnipay.gateways.netbank.class')
// Expected: "LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway"

// Check credentials
config('omnipay.gateways.netbank.options.clientId')
// Expected: "6mh9Pu6JHVQgj0PsotH6Zyob"

// Check test mode
config('omnipay.gateways.netbank.options.testMode')
// Expected: true

// Check balance endpoint
config('omnipay.gateways.netbank.options.balanceEndpoint')
// Expected: "https://api.netbank.ph/v1/accounts/balance"

exit
```

If any of these return `null`, double-check your `.env` and re-run `php artisan config:clear`.

---

## Testing Sequence

We'll test the three main operations in order of safety: balance check (read-only) → QR generation (safe) → disbursement (transfers money).

---

### Test 1: Check Balance (Read-Only)

This is the safest test—it only reads your account balance without making changes.

```bash
php artisan omnipay:balance
```

**Expected output:**

```
Checking Account Balance
==================================================

⚠️  Running in TEST MODE
Gateway: netbank

Checking balance for account: 113-001-00001-9...

✓ Balance retrieved successfully!

+--------------------+----------------------+
| Field              | Value                |
+--------------------+----------------------+
| Account            | 113-001-00001-9      |
| Balance            | ₱12,500.00 PHP       |
| Available Balance  | ₱12,000.00 PHP       |
| Currency           | PHP                  |
| As Of              | 2024-11-20 10:30:00  |
+--------------------+----------------------+
```

**✅ Success indicators:**
- "Running in TEST MODE" warning appears
- Balance is retrieved without errors
- Account number matches your `NETBANK_SOURCE_ACCOUNT_NUMBER`

**❌ Troubleshooting:**

| Error | Cause | Solution |
|-------|-------|----------|
| "Failed to initialize gateway" | Config not loaded | Run `php artisan config:clear && php artisan config:cache` |
| "OAuth2 authentication failed" | Invalid credentials | Verify `NETBANK_CLIENT_ID` and `NETBANK_CLIENT_SECRET` |
| "Endpoint not found" (404) | Wrong URL | Check `NETBANK_BALANCE_ENDPOINT` and `NETBANK_TOKEN_ENDPOINT` |
| Connection timeout | Network/firewall issue | Check internet connection and firewall rules |

---

### Test 2: Generate QR Code (Safe)

Generate a QR code for receiving payments. This doesn't transfer money—it creates a payment request.

**Test 2a: Dynamic Amount QR (user enters amount at payment time)**

```bash
php artisan omnipay:qr 09171234567
```

**Test 2b: Fixed Amount QR (₱50)**

```bash
php artisan omnipay:qr 09171234567 50
```

**Expected output:**

```
Generate QR Code
==================================================

⚠️  Running in TEST MODE
Gateway: netbank

Generating FIXED-AMOUNT QR code for ₱50.00 PHP...
Account: 09171234567

✓ QR Code generated successfully!

+-------------+--------------------------------------+
| Field       | Value                                |
+-------------+--------------------------------------+
| QR ID       | qr_abc123xyz                         |
| Account     | 09171234567                          |
| Reference   | QR-673B4F12                          |
| Amount      | ₱50.00 PHP                           |
| QR URL      | https://api.netbank.../qr/abc123     |
| Expires At  | 2024-12-31 23:59:59                  |
+-------------+--------------------------------------+

QR Code Data:
00020101021226370011com.netbank01090123456780211QR123456780303PHP5204000053030...

Note: Use this QR code for testing payments. Share via QR URL or encode the data.
```

**✅ Success indicators:**
- "Running in TEST MODE" warning appears
- QR code data is generated
- Account number matches what you provided
- You receive a QR URL (can be shared/scanned)

**💡 What to do with the QR code:**
1. Save the QR code data to a file: `php artisan omnipay:qr 09171234567 50 --save=test_qr.txt`
2. Use a QR code generator to create an image from the data
3. Test scanning with a payment app (in test mode, won't charge real money)

---

### Test 3: Disbursement (⚠️ Transfers Real Money)

**CRITICAL:** This command will transfer **real money** to the recipient. Start with the smallest possible amount.

#### Pre-flight Checks

Before running disbursement, verify:

- [ ] `NETBANK_TEST_MODE=true` is set
- [ ] You have sufficient balance (checked in Test 1)
- [ ] You're using your own test account/mobile number
- [ ] You understand the fees (₱10 for INSTAPAY, ₱25 for PESONET)

---

#### Test 3a: Minimal Disbursement to GCash (₱10)

Use your own GCash mobile number for safety:

```bash
php artisan omnipay:disburse 10 09171234567 GXCHPHM2XXX INSTAPAY
```

**Replace `09171234567` with YOUR mobile number!**

**Expected output:**

```
⚠️  DISBURSEMENT TEST - REAL MONEY WILL BE TRANSFERRED
==================================================

⚠️  Running in TEST MODE
Gateway: netbank

Disbursement Details:
+------------------+--------------------------------------------+
| Field            | Value                                      |
+------------------+--------------------------------------------+
| Amount           | ₱10.00 PHP                                 |
| Account          | 09171234567                                |
| Bank             | G-Xchange / GCash (GXCHPHM2XXX) [EMI]      |
| Settlement Rail  | INSTAPAY                                   |
| Fee              | ₱10.00 PHP                                 |
| Total Debit      | ₱20.00 PHP                                 |
| Reference        | TEST-673B4F12A                             |
+------------------+--------------------------------------------+

⚠️  This will initiate a REAL DISBURSEMENT!

  Amount:  ₱10.00 PHP
  Account:  09171234567
  Bank:  G-Xchange / GCash (GXCHPHM2XXX) [EMI]
  Settlement Rail:  INSTAPAY
  Fee:  ₱10.00 PHP
  Total Debit:  ₱20.00 PHP
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

**After running:**
1. Check your GCash app/wallet for the incoming ₱10
2. Note the Transaction ID for tracking
3. Check NetBank dashboard to confirm transaction status
4. Run `php artisan omnipay:balance` again to verify balance decreased by ₱20 (₱10 + ₱10 fee)

---

#### Test 3b: Larger Amount with PayMaya

Once comfortable, test with larger amounts and different EMIs:

```bash
php artisan omnipay:disburse 100 09181234567 PAPHPHM1XXX INSTAPAY
```

---

#### Test 3c: Bank Transfer via PESONET

Test traditional bank transfer (higher limit, higher fee):

```bash
php artisan omnipay:disburse 5000 1234567890 BNORPHMMXXX PESONET
```

**Note:** PESONET is batch-processed, so funds may take longer to arrive compared to INSTAPAY.

---

## Validation Workflow

Complete end-to-end test to verify everything works:

```bash
# 1. Check initial balance
php artisan omnipay:balance
# Note the balance (e.g., ₱10,000.00)

# 2. Generate QR for payment collection
php artisan omnipay:qr 09171234567 50 --save=qr_test.txt

# 3. Test small disbursement
php artisan omnipay:disburse 10 09171234567 GXCHPHM2XXX INSTAPAY
# Type 'yes' to confirm

# 4. Check balance again
php artisan omnipay:balance
# Should be decreased by ₱20 (₱10 amount + ₱10 fee)

# 5. Verify in logs
tail -n 50 ../../storage/logs/laravel.log
```

**Expected result:** Balance decreases by exactly ₱20, GCash receives ₱10, logs show successful transaction.

---

## Common Testing Scenarios

### Test EMI Detection

```bash
# EMI: GCash
php artisan omnipay:disburse 50 09171234567 GXCHPHM2XXX INSTAPAY

# EMI: PayMaya
php artisan omnipay:disburse 50 09181234567 PAPHPHM1XXX INSTAPAY

# Traditional Bank: BDO
php artisan omnipay:disburse 100 1234567890 BNORPHMMXXX INSTAPAY
```

Command should correctly label "[EMI]" for electronic money issuers.

---

### Test Rail Validations

```bash
# Valid: GCash supports INSTAPAY
php artisan omnipay:disburse 100 09171234567 GXCHPHM2XXX INSTAPAY
# ✅ Should succeed

# Invalid: Try to use EMI with PESONET (not supported)
php artisan omnipay:disburse 100 09171234567 GXCHPHM2XXX PESONET
# ❌ Should fail with validation error
```

---

### Test Amount Limits

```bash
# Valid: Within INSTAPAY limit (₱50,000)
php artisan omnipay:disburse 10000 09171234567 GXCHPHM2XXX INSTAPAY
# ✅ Should succeed

# Invalid: Exceeds INSTAPAY limit
php artisan omnipay:disburse 60000 09171234567 GXCHPHM2XXX INSTAPAY
# ❌ Should fail with validation error

# Valid: Within PESONET limit (₱1M)
php artisan omnipay:disburse 100000 1234567890 BNORPHMMXXX PESONET
# ✅ Should succeed (if bank supports PESONET)
```

---

## Safety Checklist

Before each disbursement test:

- [ ] Verify "Running in TEST MODE" appears
- [ ] Start with the minimum amount (₱10)
- [ ] Use your own account/mobile number
- [ ] Check balance before and after
- [ ] Review confirmation prompt carefully
- [ ] Monitor transaction in NetBank dashboard
- [ ] Check logs for audit trail

---

## Transaction Monitoring

### Check Logs

```bash
# View recent log entries
tail -n 100 ../../storage/logs/laravel.log | grep -i omnipay

# Follow logs in real-time
tail -f ../../storage/logs/laravel.log
```

### NetBank Dashboard

1. Log into https://dashboard.netbank.ph
2. Navigate to "Transactions" or "Recent Activity"
3. Verify transaction details match command output
4. Check transaction status (pending → processing → completed)

---

## Transitioning to Production

When ready to move from test to production:

### 1. Update Environment Variables

```bash
# In .env, change:
NETBANK_TEST_MODE=false

# Update endpoints to production URLs if different
NETBANK_TOKEN_ENDPOINT=https://auth.netbank.ph/oauth2/token
NETBANK_DISBURSEMENT_ENDPOINT=https://api.netbank.ph/v1/transactions
# ... etc.

# Use production credentials
NETBANK_CLIENT_ID=prod_client_id
NETBANK_CLIENT_SECRET=prod_client_secret
```

### 2. Clear Config

```bash
php artisan config:clear
php artisan config:cache
```

### 3. Test with Minimal Amount

```bash
# First production test should use ₱1
php artisan omnipay:disburse 1 09171234567 GXCHPHM2XXX INSTAPAY
```

### 4. Monitor Closely

- Watch for "Running in PRODUCTION MODE" warning
- Verify transaction completes successfully
- Check recipient confirms receipt
- Monitor logs and dashboard

### 5. Gradual Rollout

- Test with small amounts for 24 hours
- Gradually increase transaction volume
- Set up monitoring and alerts
- Have rollback plan ready

---

## Troubleshooting Guide

### Issue: Balance Check Fails

**Symptoms:**
```
✗ Failed to retrieve balance
Error: OAuth2 authentication failed
```

**Solution:**
1. Verify credentials: `cat .env | grep NETBANK_CLIENT`
2. Check token endpoint: `cat .env | grep NETBANK_TOKEN_ENDPOINT`
3. Test credentials directly in NetBank portal
4. Regenerate credentials if necessary

---

### Issue: Disbursement Validation Fails

**Symptoms:**
```
✗ Validation failed
Error: Bank does not support INSTAPAY
```

**Solution:**
1. Check if bank/EMI actually supports the rail
2. Verify SWIFT/BIC code is correct
3. Try alternative rail (INSTAPAY vs PESONET)
4. Check `storage/banks.json` for bank details

---

### Issue: Transaction Pending Forever

**Symptoms:** Status remains "pending" for hours

**Solution:**
1. Check NetBank dashboard for status
2. Contact NetBank support with Transaction ID
3. Verify recipient account is valid
4. Check for bank holidays or maintenance windows

---

## Next Steps

After successful testing:

1. ✅ **Document your findings** - Note any issues or observations
2. ✅ **Update configuration** - Fine-tune limits and settings
3. ✅ **Implement monitoring** - Set up alerts for failed transactions
4. ✅ **Plan rollout** - Define production cutover strategy
5. ✅ **Train team** - Share testing results and procedures

---

## Related Documentation

- [ENVIRONMENT_VARIABLES.md](ENVIRONMENT_VARIABLES.md) - Complete variable reference
- [TESTING_COMMANDS.md](TESTING_COMMANDS.md) - Command usage and options
- [NetBank API Docs](https://developer.netbank.ph/docs) - Official API documentation

---

## Support

If you encounter issues:

1. Check this walkthrough first
2. Review [ENVIRONMENT_VARIABLES.md](ENVIRONMENT_VARIABLES.md)
3. Check logs: `storage/logs/laravel.log`
4. Verify NetBank dashboard
5. Contact NetBank API support if needed

---

**Remember:** Always keep `NETBANK_TEST_MODE=true` until you're completely ready for production!

**Last Updated:** Phase 4.5 (Live Testing Commands)  
**Status:** ✅ Ready for testing
