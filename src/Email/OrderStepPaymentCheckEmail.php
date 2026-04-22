<?php

declare(strict_types=1);

namespace Sunnysideup\EcommercePaymentCheck\Email;

use Sunnysideup\Ecommerce\Email\OrderEmail;

class OrderStepPaymentCheckEmail extends OrderEmail
{
    protected $ss_template = OrderStepPaymentCheck_Email::class;
}
