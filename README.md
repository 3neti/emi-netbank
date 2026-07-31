# NetBank EMI Adapter

`3neti/emi-netbank` connects the provider-neutral contracts in `3neti/emi-core`
to NetBank payout, funding, QR Ph, transaction-history, and balance APIs.

## Capabilities

- InstaPay and PESONet payout submission and status checks
- Sanitized provider rejection evidence
- Authoritative corporate-account ledger-balance observations
- VCA registration, transaction history, and incoming-funding verification
- Exact-amount and reusable QR Ph funding instructions
- Live-readiness checks that distinguish configuration from provider access

## Installation

```bash
composer require 3neti/emi-netbank
```

Publish and configure the package through the consuming application's normal
installation workflow. Credentials, tokens, account numbers, raw provider
responses, and QR payload bytes must not be logged or exposed in read models.

## Treasury Balance Semantics

Treasury attribution uses NetBank's authoritative ledger `balance`. The
provider's `available_balance` remains liquidity evidence and may differ due to
holds or provider-side availability rules. Consumers must not silently replace
one meaning with the other.

## Verification

```bash
composer test
composer pint -- --test
composer validate --strict
composer audit
```

## Compatibility

- PHP 8.2 or newer
- Laravel 12 or 13
- EMI Core 2.0 beta or newer compatible 2.x release
- Wallet 1.x or 2.0 beta

## License

Proprietary.
