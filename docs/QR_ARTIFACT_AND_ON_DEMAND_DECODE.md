# NetBank QR Artifact and On-Demand Decode

## Current provider contract

NetBank's documented QR Ph generation response contains `qr_code`, a Base64
string representing a PNG image. The adapter validates this PNG and exposes it
through `FundingQrCodeData::base64Payload`.

NetBank's `qr_string` is supplied to the separate `/v1/qrph/decode` endpoint;
the generation response does not currently document a machine-readable string
field. Do not decode the PNG during generation and do not populate
`FundingQrCodeData::displayContent` from it.

## Capability registration

`NetbankFundingQrDecoder` implements the Core `FundingQrCodeDecoder` contract
and is registered under:

```text
emi.funding-qr-decoders
```

An authorized host may resolve that capability and call it explicitly. The
adapter:

1. validates the artifact as a PNG;
2. decodes the PNG in bounded temporary storage;
3. submits the recovered string to the configured NetBank decode endpoint; and
4. returns the QR string with NetBank's decoded response.

The temporary file is removed in a `finally` block. Invalid MIME types, invalid
PNG data, oversized images, empty strings, and invalid provider responses fail
closed.

## Configuration

```dotenv
NETBANK_FUNDING_QR_DECODE_ENDPOINT=/v1/qrph/decode
NETBANK_FUNDING_QR_DECODE_MAX_BYTES=5242880
```

The endpoint and limit are provider configuration, not Core contract fields.

## Security boundaries

- Do not log or persist raw PNG bytes, QR strings, or decoded destinations.
- Require host-level authorization before invoking the capability.
- Apply host-level rate limiting; the package does not create a public route.
- Do not use the decode capability to alter issuance, funding, payout, or
  accounting state.
- Do not manufacture URLs, EMV payloads, or provider payloads.

## Tests

The package tests cover NetBank request/response handling, invalid artifacts,
non-PNG rejection, and the optional capability's decoded result. Full package
tests should be run after any change to the decoder or provider response
mapping.
