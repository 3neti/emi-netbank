<?php

namespace LBHurtado\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;

class ConfirmDepositController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    public function __invoke(Request $request): Response
    {
        Log::debug('[ConfirmDeposit] Incoming raw payload:', ['body' => $request->all()]);

        $validated = $request->validate([
            'alias' => ['required', 'string'],
            'amount' => ['required', 'int', 'min:1'],
            'channel' => ['required', 'string'],
            'commandId' => ['required', 'int'],
            'externalTransferStatus' => ['required', 'string'],
            'operationId' => ['required', 'int'],
            'productBranchCode' => ['required', 'string'],
            'recipientAccountNumber' => ['required', 'string'],
            'recipientAccountNumberBankFormat' => ['required', 'string'],
            'referenceCode' => ['required', 'string'],
            'referenceNumber' => ['required', 'string'],
            'registrationTime' => ['required', 'string'],
            'remarks' => ['required', 'string'],
            'sender' => ['required', 'array'],
            'sender.accountNumber' => ['required', 'string'],
            'sender.institutionCode' => ['required', 'string'],
            'sender.name' => ['required', 'string'],
            'transferType' => ['required', 'string'],
            'merchant_details' => ['nullable', 'array'],
            'merchant_details.merchant_code' => ['nullable', 'string'],
            'merchant_details.merchant_account' => ['nullable', 'string'],
        ]);

        Log::info('[ConfirmDeposit] Payload validated, dispatching to gateway', ['validated' => $validated]);

        try {
            $this->gateway->confirmDeposit($validated);
            Log::debug('[ConfirmDeposit] Gateway confirmDeposit() returned successfully');
        } catch (\Throwable $e) {
            Log::error('[ConfirmDeposit] Gateway threw exception', [
                'exception' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return response()->noContent();
    }
}
