<?php

namespace Sunnysideup\EcommercePaymentCheck\Email;

use Sunnysideup\Ecommerce\Email\OrderEmail;
use Sunnysideup\EcommercePaymentCheck\Email\OrderStepPaymentCheck_Email;



class OrderStepPaymentCheck_Email extends OrderEmail
{
    protected $ss_template = OrderStepPaymentCheck_Email::class;
}


