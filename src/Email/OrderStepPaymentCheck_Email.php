<?php

namespace Sunnysideup\EcommercePaymentCheck\Email;

use Order_Email;
use Sunnysideup\EcommercePaymentCheck\Email\OrderStepPaymentCheck_Email;



class OrderStepPaymentCheck_Email extends Order_Email
{
    protected $ss_template = OrderStepPaymentCheck_Email::class;
}


