<?php

namespace LBHurtado\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;

class ConfirmDisbursementController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    public function __invoke(Request $request): Response
    {
        try {
            $operationId = $request->validate([
                'operationId' => ['required', 'string'],
            ])['operationId'];

            /** @var PaymentGatewayInterface $gateway */
            $gateway = app(PaymentGatewayInterface::class);

            $success = $gateway->confirmDisbursement($operationId);

            return $success
                ? response('Disbursement confirmed!', 200)
                : response('Disbursement confirmation failed.', Response::HTTP_BAD_GATEWAY);
        } catch (\Throwable $e) {
            return response('Disbursement confirmation failed.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        //        return response()->noContent();
    }
}
