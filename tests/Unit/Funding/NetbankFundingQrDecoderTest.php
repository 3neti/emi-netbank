<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;
use LBHurtado\PaymentGateway\Exceptions\NetbankFundingRequestFailed;

beforeEach(function () {
    config()->set('payment-gateway.netbank.funding', [
        'api_url' => 'https://api.netbank.test',
        'token_url' => 'https://auth.netbank.test/oauth2/token',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'qr_decode_endpoint' => 'https://api.netbank.test/v1/qrph/decode',
    ]);
});
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\PaymentGateway\Funding\NetbankFundingQrDecoder;
use LBHurtado\PaymentGateway\Funding\NetbankQrImageDecoder;

it('decodes a stored png on demand and validates the qr string with NetBank', function () {
    Http::fake([
        'https://auth.netbank.test/oauth2/token' => Http::response([
            'access_token' => 'access-token',
            'expires_in' => 3600,
        ]),
        'https://api.netbank.test/v1/qrph/decode*' => Http::response([
            'amount' => ['cur' => 'PHP', 'num' => '500'],
            'qr_type' => 'Dynamic',
            'qr_transaction_type' => 'P2M',
        ]),
    ]);

    $imageDecoder = Mockery::mock(NetbankQrImageDecoder::class);
    $imageDecoder->shouldReceive('decode')
        ->once()
        ->with('stored-png')
        ->andReturn('000201010212...');
    app()->instance(NetbankQrImageDecoder::class, $imageDecoder);

    $result = app(NetbankFundingQrDecoder::class)->decode(new FundingQrCodeData(
        mimeType: 'image/png',
        base64Payload: 'stored-png',
        qrMode: 'dynamic',
        transactionType: 'p2m',
        embeddedAmount: true,
        providerGenerated: true,
    ));

    expect($result->qrString)->toBe('000201010212...')
        ->and($result->providerData)->toMatchArray([
            'qr_type' => 'Dynamic',
            'qr_transaction_type' => 'P2M',
        ]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), '/v1/qrph/decode')
        && ($request->data()['qr_string'] ?? null) === '000201010212...');
});

it('rejects an invalid stored png before contacting NetBank', function () {
    $decoder = app(NetbankQrImageDecoder::class);

    expect(fn () => $decoder->decode(base64_encode('not-a-png')))
        ->toThrow(NetbankFundingRequestFailed::class, 'decode-qrph-image');
});

it('rejects a non-png qr artifact before decoding', function () {
    expect(fn () => app(NetbankFundingQrDecoder::class)->decode(new FundingQrCodeData(
        mimeType: 'image/jpeg',
        base64Payload: 'stored-png',
        qrMode: 'dynamic',
        transactionType: 'p2m',
        embeddedAmount: true,
        providerGenerated: true,
    )))->toThrow(NetbankFundingRequestFailed::class, 'decode-qrph-image');
});

it('rejects an empty qr string before contacting NetBank', function () {
    Http::fake();

    expect(fn () => app(NetbankFundingApiClient::class)
        ->decodeQrString('   '))
        ->toThrow(NetbankFundingRequestFailed::class, 'decode-qrph');

    Http::assertNothingSent();
});
