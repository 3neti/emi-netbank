# Changelog

All notable changes to `3neti/emi-netbank` are documented here.

## v2.1.0 - 2026-07-31

### Added
- Provider-neutral funding, VCA, reusable-address, and QR Ph capabilities
- Sanitized live balance preflight and authoritative balance observations
- Payout status evidence with normalized NetBank rejection reasons
- Laravel 12/13, EMI Core 2, Wallet 2, and Pest 3/4 CI matrix

### Changed
- Treasury attribution now uses NetBank's ledger balance while preserving
  available balance as separate liquidity evidence
- Funding verification supports bounded transaction-history windows and
  purpose-bound standing addresses

### Security
- Provider payloads, credentials, account identifiers, QR bytes, and raw
  evidence remain outside public read models and operational logs

## v2.0.3 - 2026-04-13

### Changed
- Expanded Laravel 13 dependency compatibility
