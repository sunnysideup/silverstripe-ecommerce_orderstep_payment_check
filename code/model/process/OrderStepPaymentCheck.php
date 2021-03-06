<?php
class OrderStepPaymentCheck extends OrderStep implements OrderStepInterface
{
    private static $verbose = false;

    /**
     * @var String
     */
    protected $emailClassName = "OrderStepPaymentCheck_Email";

    private static $db = array(
        'SendPaymentCheckEmail' => 'Boolean',
        'MinDays' => 'Int',
        'MaxDays' => 'Int',
        'LinkText' => 'Varchar'
    );

    private static $defaults = array(
        'CustomerCanEdit' => 0,
        'CustomerCanCancel' => 0,
        'CustomerCanPay' => 0,
        'Name' => 'Send Payment Reminder',
        'Code' => 'PAYMENTCHECK',
        "ShowAsInProcessOrder" => true,
        "HideStepFromCustomer" => true,
        'SendPaymentCheckEmail' => true,
        'MinDays' => 10,
        'MaxDays' => 20
    );


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.CustomerMessage',
            array(
                CheckboxField::create('SendPaymentCheckEmail', 'Send payment reminder email to customer?'),
                $minDaysField = NumericField::create('MinDays', "<strong>Min Days</strong> before sending e-mail"),
                $maxDaysField = NumericField::create('MaxDays', "<strong>Max Days</strong> before cancelling order")
            ),
            "EmailSubject"
        );
        $minDaysField->setRightTitle('What is the <strong>mininum number of days to wait after the order has been placed</strong> before this email should be sent?');
        $maxDaysField->setRightTitle('What is the <strong>maxinum number of days to wait after the order has been placed </strong> before the order should be cancelled.');
        $fields->addFieldsToTab(
            'Root.CustomerMessage',
            array(
                TextField::create(
                    'LinkText',
                    _t('OrderStepPaymentCheck.BUTTONTEXT', 'Link Text')
                )->setRightTitle('This is the text displayed on the "complete your order" link/button')
            )
        );
        return $fields;
    }

    public function initStep(Order $order)
    {
        //make sure we can send emails at all.
        if ($this->SendPaymentCheckEmail) {
            return Config::inst()->update("OrderStep", "number_of_days_to_send_update_email", $this->MaxDays);
        }

        return true;
    }

    public function doStep(Order $order)
    {
        //if the order has been paid then do not worry about it at all!
        if ($order->IsPaid()) {
            return true;
        }
        //if the order has expired then cancel it ...
        if ($this->isExpiredPaymentCheckStep($order)) {
            //cancel order ....
            if ($this->Config()->get("verbose")) {
                DB::alteration_message(" - Time to send payment reminder is expired ... archive email");
            }
            // cancel as admin ...
            $member = EcommerceRole::get_default_shop_admin_user();
            if (! ($member && $member->exists())) {
                $member = $order->Member();
            }
            $order->Cancel(
                $member,
                _t('OrderStep.CANCELLED_DUE_TO_NON_PAYMENT', 'Cancelled due to non-payment')
            );

            return true;
        }
        //do we send at all?
        elseif ($this->SendPaymentCheckEmail) {
            //we can not send emails for pending payments, because pending payments can not be paid for ...
            if ($order->PaymentIsPending()) {
                return false;
            }
            //is now the right time to send?
            if ($this->isReadyToGo($order)) {
                $subject = $this->EmailSubject;
                $message = $this->CustomerMessage;
                if ($this->hasBeenSent($order, false)) {
                    if ($this->Config()->get("verbose")) {
                        DB::alteration_message(" - already sent!");
                    }

                    return true; //do nothing
                } else {
                    if ($this->Config()->get("verbose")) {
                        DB::alteration_message(" - Sending it now!");
                    }
                    return $order->sendEmail(
                        $this->getEmailClassName(),
                        $subject,
                        $message,
                        $resend = false,
                        $adminOnlyOrToEmail = false
                    );
                }
            }
            //wait until later....
            else {
                if ($this->Config()->get("verbose")) {
                    DB::alteration_message(" - We need to wait until minimum number of days.");
                }

                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * can only continue if order has been paid ...
     *
     * @param DataObject $order Order
     *
     * @return DataObject | Null - DataObject = next OrderStep
     **/
    public function nextStep(Order $order)
    {
        if (
             $order->IsPaid()
        ) {
            if ($this->Config()->get("verbose")) {
                DB::alteration_message(" - Moving to next step");
            }
            return parent::nextStep($order);
        } else {
            if ($this->Config()->get("verbose")) {
                DB::alteration_message(" - no next step: has not been sent");
            }
            return null;
        }
    }

    /**
     * For some ordersteps this returns true...
     * @return Boolean
     **/
    protected function hasCustomerMessage()
    {
        return true;
    }

    /**
     * Explains the current order step.
     * @return String
     */
    protected function myDescription()
    {
        return "The customer is sent a payment reminder email.";
    }

    /**
     * returns true if the Minimum number of days is met....
     * @param Order
     * @return Boolean
     */
    protected function isReadyToGo(Order $order)
    {
        if ($this->MinDays) {
            $log = $order->SubmissionLog();
            if ($log) {
                $createdTS = strtotime($log->Created);
                $nowTS = strtotime('now');
                $startSendingTS = strtotime("+{$this->MinDays} days", $createdTS);
                //current TS = 10
                //order TS = 8
                //add 4 days: 12
                //thus if 12 <= now then go for it (start point in time has passed)
                if ($this->Config()->get("verbose")) {
                    DB::alteration_message("Time comparison: Start Sending TS: ".$startSendingTS." current TS: ".$nowTS.". If SSTS > NowTS then Go for it.");
                }
                return ($startSendingTS <= $nowTS) ? true : false;
            } else {
                user_error("can not find order log for ".$order->ID);
                return false;
            }
        } else {
            //send immediately
            return true;
        }
    }

    /**
     * returns true if it is too late to send the  payment reminder step
     * @param Order
     * @return Boolean
     */
    protected function isExpiredPaymentCheckStep(Order $order) : bool
    {
        if ($this->MaxDays) {
            $log = $order->SubmissionLog();
            if ($log) {
                $createdTS = strtotime($log->Created);
                $nowTS = strtotime('now');
                $stopSendingTS = strtotime('+'.$this->MaxDays.' days', $createdTS);

                return ($stopSendingTS < $nowTS) ? true : false;
            } else {
                user_error("can not find order log for ".$order->ID);
                return false;
            }
        } else {
            return true;
        }
    }

    public function hasBeenSent(Order $order, $checkDateOfOrder = true)
    {
        return OrderEmailRecord::get()->filter(
            array(
                "OrderID" => $order->ID,
                "OrderStepID" => $this->ID,
                "Result" => 1
            )
        )->count() ? true : parent::hasBeenSent($order, false);
    }
}
