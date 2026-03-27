<?php

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Support\Facades\Event;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositResponseData;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;
use LBHurtado\PaymentGateway\Gateways\Netbank\Traits\CanConfirmDeposit;
use LBHurtado\PaymentGateway\Services\ResolvePayable;
use LBHurtado\Wallet\Actions\TopupWalletAction;
use LBHurtado\Wallet\Events\DepositConfirmed;

// uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // 1) stub out DepositResponseData::from()
    $this->fakePayload = [
        'recipientAccountNumber' => '91500:09171234567',
        'amount' => 123.45,
        // …plus whatever else your DepositResponseData needs…
    ];

    // 2) bind ResolvePayable to return a dummy wallet
    resolve(ResolvePayable::class);
    $this->dummyWallet = Mockery::mock(Wallet::class);
    $this->app->instance(ResolvePayable::class, new class
    {
        public function execute(RecipientAccountNumberData $dto)
        {
            return Mockery::mock(Wallet::class)->shouldIgnoreMissing();
        }
    });
});

// it('returns false if pipeline throws', function(){
//    // make pipeline throw
//    $this->app->instance(ResolvePayable::class, new class {
//        public function execute($_){ throw new \RuntimeException("boom"); }
//    });
//
//    // make a dummy class using the trait
//    $t = new class { use CanConfirmDeposit; };
//
//    expect($t->confirmDeposit($this->fakePayload))
//        ->toBeFalse();
// });
//
// it('returns false if pipeline returns non-wallet', function(){
//    $this->app->instance(ResolvePayable::class, new class {
//        public function execute($_){ return null; }
//    });
//    $t = new class { use CanConfirmDeposit; };
//
//    expect($t->confirmDeposit($this->fakePayload))
//        ->toBeFalse();
// });
//
// it('tops up and dispatches the DepositConfirmed event on success', function(){
//    // fake event & action
//    Event::fake([DepositConfirmed::class]);
//    // stub TopupWalletAction
//    $fakeTransfer = (object)[
//        'deposit' => (object)[
//            'meta' => [],
//            'save' => fn()=>null,
//        ],
//    ];
//    $this->mock(TopupWalletAction::class, function($m) use($fakeTransfer){
//        $m->shouldReceive('run')->andReturn($fakeTransfer);
//    });
//
//    // pipeline returns a real Wallet mock
//    $wallet = Mockery::mock(Wallet::class, ['getKey'=>1]);
//    $this->app->instance(ResolvePayable::class, new class($wallet) {
//        protected $w; public function __construct($w){ $this->w=$w; }
//        public function execute($_){ return $this->w; }
//    });
//
//    $t = new class { use CanConfirmDeposit; };
//
//    $ok = $t->confirmDeposit($this->fakePayload);
//    expect($ok)->toBeTrue();
//
//    Event::assertDispatched(DepositConfirmed::class);
// });
