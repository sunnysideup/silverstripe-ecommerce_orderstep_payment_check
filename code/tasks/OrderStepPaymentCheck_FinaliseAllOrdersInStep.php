<?php


class OrderStepPaymentCheck_FinaliseAllOrdersInStep extends BuildTask
{
    protected $title = 'Try to finalise all orders in the Send Payment Reminder step';

    protected $description = "Selects all the orders in the Send Payment Reminder step and tries to finalise them by sending the email.";

    private static $number_of_orders_at_one_time = 5;

    private static $number_of_orders_at_one_time_cli = 500;
    /**
     *@return Integer - number of carts destroyed
     **/
    public function run($request)
    {
        //IMPORTANT!
        $orderStepPaymentCheck = OrderStepPaymentCheck::get()->First();
        if ($orderStepPaymentCheck) {
            Config::inst()->update("OrderStepPaymentCheck", "verbose", true);
            //work out count!

            //set count ...
            $count = null;
            if (isset($_GET["count"])) {
                $count = intval($_GET["count"]);
            }
            if (!intval($count)) {
                $count = Config::inst()->get('OrderStepPaymentCheck_FinaliseAllOrdersInStep', 'number_of_orders_at_one_time');
            }
            if (PHP_SAPI === 'cli') {
                $count = Config::inst()->get('OrderStepPaymentCheck_FinaliseAllOrdersInStep', 'number_of_orders_at_one_time_cli');
            }

            //redo ones from the archived step...
            if (isset($_GET["redoall"]) && $_GET["redoall"] == 1) {
                $orderStepArchived = OrderStep_Archived::get()->first();
                if ($orderStepArchived) {
                    $excludeArray = array(0 => 0);
                    $orders = Order::get()
                        ->filter(
                            array(
                                "StatusID" => $orderStepArchived->ID,
                                "OrderEmailRecord.OrderStepID" => $orderStepPaymentCheck->ID,
                                "OrderEmailRecord.Result" => 1
                            )
                        )
                        ->innerJoin("OrderEmailRecord", "\"OrderEmailRecord\".\"OrderID\" = \"Order\".\"ID\"");
                    if ($orders->count()) {
                        foreach ($orders as $order) {
                            $excludeArray[$order->ID] = $order->ID;
                        }
                    }
                    $orders = Order::get()
                        ->filter(array("StatusID" => $orderStepArchived->ID))
                        ->exclude(array("Order.ID" => $excludeArray));
                    if ($orders->count()) {
                        foreach ($orders as $order) {
                            $order->StatusID = $orderStepPaymentCheck->ID;
                            $order->write();
                            DB::alteration_message("Moving Order #".$order->getTitle()." back to Payment Reminder step to try again");
                        }
                    } else {
                        DB::alteration_message("There are no archived orders to redo.", "deleted");
                    }
                } else {
                    DB::alteration_message("Could not find archived order step.", "deleted");
                }
            }

            $position = 0;
            if (isset($_GET["position"])) {
                $position = intval($_GET["position"]);
            }
            if (!intval($position)) {
                $position = intval(Session::get("OrderStepPaymentCheck_FinaliseAllOrdersInStep"));
                if (!$position) {
                    $position = 0;
                }
            }
            $orders = Order::get()
                ->filter(array("StatusID" => $orderStepPaymentCheck->ID))
                ->sort(array("ID" => "ASC"))
                ->limit($count, $position);
            if ($orders->count()) {
                DB::alteration_message("<h1>Moving $count Orders (starting from $position)</h1>");
                foreach ($orders as $order) {
                    DB::alteration_message("<h2>Attempting Order #".$order->getTitle()."</h2>");
                    $order->tryToFinaliseOrder();
                    $statusAfterRunningInit = OrderStep::get()->byID($order->StatusID);
                    if ($statusAfterRunningInit) {
                        if ($orderStepPaymentCheck->ID == $statusAfterRunningInit->ID) {
                            DB::alteration_message(" - could not move Order ".$order->getTitle().", remains at <strong>".$orderStepPaymentCheck->Name."</strong>");
                        } else {
                            DB::alteration_message(" - Moving Order #".$order->getTitle()." from <strong>".$orderStepPaymentCheck->Name."</strong> to <strong>".$statusAfterRunningInit->Name."</strong>", "created");
                        }
                    } else {
                        DB::alteration_message(" - Order ".$order->ID." has a non-existing orderstep", "deleted");
                    }
                    $position++;
                    Session::set("OrderStepPaymentCheck_FinaliseAllOrdersInStep", $position);
                }
            } else {
                Session::clear("OrderStepPaymentCheck_FinaliseAllOrdersInStep");
                DB::alteration_message("<br /><br /><br /><br /><h1>COMPLETED!</h1>All orders have been moved.", "created");
            }
        } else {
            DB::alteration_message("NO Send Payment Reminder order step.", "deleted");
        }
        if (Session::get("OrderStepPaymentCheck_FinaliseAllOrdersInStep")) {
            DB::alteration_message("WAIT: we are still moving more orders ... Please relaod this page ....", "deleted");
        }
    }
}
