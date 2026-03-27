<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\PaymentGateway\Http\Controllers\ConfirmDepositController;
use LBHurtado\PaymentGateway\Http\Controllers\ConfirmDisbursementController;
use LBHurtado\PaymentGateway\Http\Controllers\DisburseController;
use LBHurtado\PaymentGateway\Http\Controllers\GenerateController;

Route::post('confirm-deposit', ConfirmDepositController::class)
    ->name('confirm-deposit');

Route::post('confirm-disbursement', ConfirmDisbursementController::class)
    ->name('confirm-disbursement');

Route::middleware(['auth:sanctum'])
    ->post('generate-qrcode', GenerateController::class)
    ->name('generate-qrcode');

Route::post('disburse-funds', DisburseController::class)
    ->name('disburse-funds');
