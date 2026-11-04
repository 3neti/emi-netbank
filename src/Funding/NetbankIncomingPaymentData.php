<?php

declare(strict_types=1);

namespace LBHurtado\PaymentGateway\Funding;

use LBHurtado\EmiCore\Data\Funding\IncomingPaymentData;

/** @deprecated Use the provider-neutral EMI Core observation type. */
final readonly class NetbankIncomingPaymentData extends IncomingPaymentData {}
