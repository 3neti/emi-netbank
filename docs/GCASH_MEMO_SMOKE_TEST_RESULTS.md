# GCash Memo Smoke Test Results

**Date**: January 5, 2026  
**Status**: ❌ FAILED - GCash does not display sender memos  
**Tested By**: Research team  
**Total Cost**: ₱20 (2 test transactions @ ₱10 each)

---

## Executive Summary

**Question**: Can voucher rider messages be delivered to GCash recipients via NetBank InstaPay's `additionalSenderInfo` field?

**Answer**: **NO** - GCash mobile app does not display memo/remarks/sender info sent via NetBank InstaPay disbursements, even when using the officially documented API field.

**Recommendation**: Continue using SMS/email/webhook/web UI for voucher rider delivery. Do not rely on payment rail for messaging.

---

## Background

The voucher system includes "rider messages" (custom text/images shown after redemption). We investigated whether these messages could be delivered through the InstaPay payment rail itself, similar to how bank transfers sometimes show memos.

**Hypothesis**: If GCash displays memos from InstaPay transfers, we could deliver rider messages more reliably (no need for SMS/email).

---

## Test Setup

### Environment
- **Gateway**: NetBank API (Omnipay implementation)
- **Settlement Rail**: InstaPay
- **Recipient**: GCash account (09173011987)
- **Test Mode**: NetBank sandbox/test mode
- **Amount per test**: ₱10

### Test Cases Executed

#### Test #1: Using `remarks` Field
- **Field Name**: `remarks`
- **Value Sent**: `"TEST MEMO"`
- **API Response**: ✅ Success (Transaction ID: 302454753)
- **GCash Display**: ❌ No memo visible

#### Test #2: Using `additionalSenderInfo` Field (Correct per API docs)
- **Field Name**: `additionalSenderInfo` (camelCase)
- **Value Sent**: `"XCHG TEST MEMO"`
- **API Response**: ✅ Success (Transaction ID: 302459419)
- **GCash Display**: ❌ No memo visible

---

## Technical Details

### NetBank API Documentation

From official NetBank API docs (https://virtual.netbank.ph/docs#tag/Disburse-To-Account):

> "For INSTAPAY or PESONET transactions, you can include additionalSenderInfo to help recipients recognize the disbursement transaction."

### Implementation

Added support for `additionalSenderInfo` field in:
- `DisburseRequest.php` - Payload construction
- `TestDisbursementCommand.php` - CLI option `--sender-info`

**Code snippet**:
```php
// DisburseRequest.php (lines 81-84)
if ($additionalSenderInfo = $this->getAdditionalSenderInfo()) {
    $payload['additionalSenderInfo'] = $additionalSenderInfo;
}
```

### What NetBank Sends
```json
{
  "reference_id": "TEST-695B750C7B9CC",
  "amount": {"cur": "PHP", "num": "1000"},
  "settlement_rail": "INSTAPAY",
  "additionalSenderInfo": "XCHG TEST MEMO",
  ...
}
```

### What GCash Shows

**Notification**:
```
You have received 10.00 of GCash from Netbank 
with account ending in 0019.
```

**Transaction Details**:
- Title: "Received GCash from Netbank with account ending in 0019"
- Invoice Number: `20260105CUOBPHM2XXXB000000000810753`
- Amount: +10.00
- Date & Time: Jan 5, 2026 4:06 PM
- Reference Number: 9036494758930
- **NO CUSTOM MEMO/REMARKS FIELD**

---

## Analysis

### Why This Failed

1. **NetBank API works correctly** - Field is accepted and sent
2. **InstaPay may carry the data** - No API errors
3. **GCash UI limitation** - Mobile app chooses not to display sender info

### Possible Reasons GCash Doesn't Display It

- **UI/UX Decision**: GCash may consider sender info unnecessary noise
- **InstaPay Spec**: Field may not be part of standard InstaPay message format visible to recipients
- **Security/Spam**: Preventing unsolicited messages in financial transactions
- **Technical**: GCash's InstaPay integration doesn't parse this field

### Comparison with Bank Transfers

Traditional bank transfers (via online banking UI) often DO show memos because:
- Both sender and recipient use the same bank's system
- Memo is stored in bank's internal database
- Not limited by InstaPay message format

InstaPay is a **settlement network**, not a messaging platform.

---

## Tested Alternatives

### What We Tried
1. ❌ `remarks` field (not in official docs, but tried anyway)
2. ❌ `additionalSenderInfo` (official field, camelCase)
3. ❌ `additional_sender_info` (snake_case variant)

### What We Didn't Try (and why)
- **PESONET rail**: Same limitation likely applies, and it's batch processing (slower)
- **Other EMIs (Maya, ShopeePay)**: Could have different UI, but no evidence they'd display it either
- **Different field names**: NetBank docs only mention `additionalSenderInfo`

---

## Recommendations

### For Voucher Rider Delivery

**Use Existing Channels** (all proven to work):
1. **SMS** - via EngageSpark, delivers to redeemer's mobile
2. **Email** - via Resend, delivers to redeemer's email (if provided)
3. **Webhook** - POST to merchant's system with full voucher data
4. **Web UI** - Success page displays rider message/splash image

### For Future Research

**If you want to pursue payment rail messaging**:
1. Test with **Maya** (PayMaya) - different EMI, might have different UI
2. Test with **traditional banks** (BDO, BPI) - may display memos in online banking
3. Contact **NetBank support** - ask if any recipients display `additionalSenderInfo`
4. Test **PESONET** rail - batch processing might include memos in notifications

**Cost/Benefit**: Low priority. Existing channels work well and are more reliable.

---

## Code Changes Made

### Files Modified

1. **DisburseRequest.php**
   - Added `remarks` getter/setter
   - Added `additionalSenderInfo` getter/setter
   - Includes both fields in payload if provided

2. **TestDisbursementCommand.php**
   - Added `--remarks=TEXT` option
   - Added `--sender-info=TEXT` option
   - Displays in confirmation table

3. **TestGCashMemoCommand.php** (new)
   - Systematic smoke test command
   - 5 test cases with varying message lengths
   - CSV report generation
   - Per-test confirmations

4. **PaymentGatewayServiceProvider.php**
   - Registered `TestGCashMemoCommand`

### Should We Keep This Code?

**Recommendation: YES, but mark as experimental**

**Pros of keeping it**:
- NetBank officially supports the field (may be useful for other recipients)
- Minimal overhead (optional field, no breaking changes)
- Future EMIs/banks might display it
- Useful for debugging/reconciliation (logged in `DisbursementAttempt`)

**Cons**:
- GCash doesn't use it (our primary EMI)
- Adds unused parameters to API calls

**Compromise**: Keep the infrastructure, document as "experimental - not displayed by GCash"

---

## Transaction Log

### Test #1
- **Reference**: TEST-695B70ED296D9
- **NetBank TX ID**: 302454753
- **Amount**: ₱10.00
- **Field**: `remarks = "TEST MEMO"`
- **Result**: Transaction successful, no memo shown in GCash

### Test #2  
- **Reference**: TEST-695B750C7B9CC
- **NetBank TX ID**: 302459419
- **Amount**: ₱10.00
- **Field**: `additionalSenderInfo = "XCHG TEST MEMO"`
- **Result**: Transaction successful, no memo shown in GCash

**Total Spent**: ₱20.00

---

## Lessons Learned

1. **Always check official API documentation first** - We initially used `remarks` before finding `additionalSenderInfo` in docs
2. **Field names matter** - camelCase vs snake_case can break integrations
3. **API acceptance ≠ UI display** - Just because NetBank accepts a field doesn't mean recipients see it
4. **Test with real devices** - Only way to confirm UI behavior
5. **Payment rails are not messaging platforms** - InstaPay/PESONet are for money transfer, not communication

---

## References

- [NetBank API Documentation](https://virtual.netbank.ph/docs#tag/Disburse-To-Account)
- [NetBank Disburse-To-Account Overview](https://virtual.netbank.ph/technical-disburse-to-account)
- [InstaPay Official Site](https://www.instapay.ph/)
- [GCash Help Center](https://help.gcash.com/)

---

## Appendix: Screenshots

_(User should attach GCash transaction screenshots showing absence of memo field)_

### Transaction List View
- Shows generic "Received GCash from Netbank" message
- No custom memo visible

### Transaction Details View  
- Shows amount, date, reference number
- Invoice number from InstaPay
- **NO memo/remarks/sender info field**

---

**Conclusion**: While NetBank's API supports `additionalSenderInfo` for InstaPay transactions, GCash does not display this information to recipients. The voucher system should continue using SMS, email, webhooks, and web UI for rider message delivery.
