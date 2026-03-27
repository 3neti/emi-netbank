<?php

namespace LBHurtado\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Netbank\Generate\GenerateInputData;

class GenerateController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    public function __invoke(Request $request)
    {
        $inputData = GenerateInputData::from($request->all());
        $imageBytes = $this->gateway->generate($inputData->account, $inputData->amount);
        $eventData = [
            'name' => 'qrcode.generated',
            'data' => $imageBytes,
        ];

        // Check if the request expects JSON (Accept: application/json or X-Requested-With: XMLHttpRequest)
        if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['event' => $eventData]);
        }

        // Handle requests coming from Vue (e.g., based on a specific header or identifier)
        if ($request->header('X-Inertia')) {
            return back()->with('event', $eventData);
        }

        // Default: Redirect response for traditional requests
        return back();
    }
}
