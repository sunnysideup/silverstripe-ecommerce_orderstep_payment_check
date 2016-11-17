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
        'MaxDays' => 'Int'
    );

    private static $defaults = array(
        'CustomerCanEdit' => 0,
        'CustomerCanCancel' => 1,
        'CustomerCanPay' => 0,
        'Name' => 'Send Payment Check',
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
                CheckboxField::create('SendPaymentCheckEmail', 'Send payment check email to customer?'),
                $minDaysField = NumericField::create('MinDays', "<strong>Min Days</strong> before sending"),
                $maxDaysField = NumericField::create('MaxDays', "<strong>Max Days</strong> before sending")
            ),
            "EmailSubject"
        );
        $minDaysField->setRightTitle('What is the <strong>mininum number of days to wait after a payment has failed</strong> before this email should be sent?');
        $maxDaysField->setRightTitle('What is the <strong>maxinum number of days to wait after a payment has failed</strong> before this email should be sent?<br><strong>If set to zero, this step will be ignored.</strong>');
        return $fields;
    }

    public function initStep(Order $order)
    {
        //make sure we can send emails at all.
        if ($this->SendPaymentCheckEmail) {
            Config::inst()->update("Order_Email", "number_of_days_to_send_update_email", 360);
        }

        return true;
    }

    public function doStep(Order $order)
    {
        //if the order has been paid then do not worry about it at all!
        if ($order->IsPaid()) {
            return true;
        }
        //ignore altogether?
        elseif ($this->SendPaymentCheckEmail) {
            // too late to send
            if ($this->isExpiredPaymentCheckStep($order)) {
                if ($this->Config()->get("verbose")) {
                    DB::alteration_message(" - Time to send payment check is expired ... archive email");
                }
                return true;
            }
            //is now the right time to send?
            elseif ($this->isReadyToGo($order)) {
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
                        $subject,
                        $message,
                        $resend = false,
                         $adminOnly = false,
                         $this->getEmailClassName()
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
     * can continue if emails has been sent or if there is no need to send a receipt.
     * @param DataObject $order Order
     * @return DataObject | Null - DataObject = next OrderStep
     **/
    public function nextStep(Order $order)
    {
        if ($this->isExpiredPaymentCheckStep($order)) {
        //archive order as we have lost faith ....
            return $lastOrderStep = OrderStep::get()->last();
        } elseif (
            ! $this->SendPaymentCheckEmail || //not sure if we need this
             $this->hasBeenSent($order, false) ||
             $this->isExpiredPaymentCheckStep($order) ||
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
        return "The customer is sent a payment check email.";
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
     * returns true if it is too late to send the  payment check step
     * @param Order
     * @return Boolean
     */
    protected function isExpiredPaymentCheckStep(Order $order)
    {
        if ($this->MaxDays) {
            $log = $order->SubmissionLog();
            if ($log) {
                $createdTS = strtotime($log->Created);
                $nowTS = strtotime('now');
                $stopSendingTS = strtotime("+{$this->MaxDays} days", $createdTS);
                return ($stopSendingTS < $nowTS) ? true : false;
            } else {
                user_error("can not find order log for ".$order->ID);
            }
        }
        //send forever
        return false;
    }

    public function hasBeenSent(Order $order, $checkDateOfOrder = true)
    {
        return OrderEmailRecord::get()->filter(
            array(
                "OrderEmailRecord.OrderID" => $order->ID,
                "OrderEmailRecord.OrderStepID" => $this->ID,
                "OrderEmailRecord.Result" => 1
            )
        )->count() ? true : false;
    }
}
