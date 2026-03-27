<?php

namespace LBHurtado\PaymentGateway\Http\Controllers;

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;

class DisburseController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    public function __invoke(Request $request)
    {
        $inputData = DisburseInputData::from($request->all());

        $user = auth()->user();

        if (! $user instanceof Wallet) {
            return response()->json(['message' => 'User does not support wallet functionality'], 403);
        }

        try {
            $response = $this->gateway->disburse($user, $inputData->toArray());

        } catch (\Exception $e) {

            return response()->json(['message' => 'Failed to initiate disbursement', 'error' => $e->getMessage()], 500);
        }

        $eventData = [
            'name' => 'disbursement.initiated',
            'data' => $response,
        ];

        // JSON (XHR/API)
        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['event' => $eventData]);
        }

        // Vue (Inertia)
        if ($request->header('X-Inertia')) {
            return back()->with('event', $eventData);
        }

        // Traditional HTTP redirect
        return back();
    }
}
