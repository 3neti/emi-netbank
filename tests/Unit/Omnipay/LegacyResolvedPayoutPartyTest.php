<?php

use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisbursePayloadData;

it('preserves supplied party addresses in the legacy payload path', function (): void {
    config()->set('disbursement.source.account_number', '123');
    config()->set('disbursement.source.sender', ['name' => 'Default issuer']);
    $sender = ['name' => 'Issuer', 'address' => ['address1' => 'Issuer locality', 'city' => 'Manila', 'country' => 'PH', 'postal_code' => '1000']];
    $recipient = ['name' => 'Recipient', 'address' => ['address1' => 'Recipient locality', 'city' => 'Cebu City', 'country' => 'PH', 'postal_code' => '6000']];
    $payload = DisbursePayloadData::fromValidated(['reference' => 'legacy-ref', 'via' => 'INSTAPAY', 'amount' => 100, 'bank' => 'GXCHPHM2XXX', 'account_number' => '09170000000', 'sender' => $sender, 'recipient' => $recipient])->toArray();

    expect($payload['sender'])->toMatchArray($sender)->and($payload['recipient'])->toMatchArray($recipient);
});
