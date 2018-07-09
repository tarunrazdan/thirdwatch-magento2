<?php

class Thirdwatch_Mitra_Model_Observer{

    private function testPostback($url){
        $client = new Varien_Http_Client($url);
        $client->setMethod(Varien_Http_Client::POST);
        $client->setHeaders('Content-type','application/json');
        $client->setRawData(Mage::helper('core')->jsonEncode(array('test'=>True,)));

        try{
            $response = $client->request();
            if ($response->isSuccessful()) {
                return True;
            }
            return False;
        } catch (Exception $e) {
            return False;
        }
    }

    /*
     * this observer handles the event admin_system_config_changed_section_thirdwatch
     * this function will be called whenever the thirdwatch config will be changed.
     * @param $evt
     */
    public function handle_adminSystemConfigChangedSection($evt){
        Mage::helper('mitra/log')->log("handle_adminSystemConfigChangedSection");

        $helper = Mage::helper('mitra');
        $storeURL = $helper->getStoreURL();
        $secret = $helper->getKey();
        $scorePostback = join('/', array(trim($storeURL, '/'), "mitra/response/getresponse/"));
        $actionPostback = join('/', array(trim($storeURL, '/'), "mitra/action/getresponse/"));

        try{
            $testRespScore = $this->testPostback($scorePostback);
            if (!$testRespScore){
                Mage::throwException('Testing score postback failed');
            }
        } catch (Exception $e){
            Mage::throwException('Testing score postback failed');
        }

        try{
            $testRespPostback = $this->testPostback($actionPostback);
            if (!$testRespPostback){
                Mage::throwException('Testing response postback failed');
            }
        } catch (Exception $e){
            Mage::throwException('Testing response postback failed');
        }

        $client = new Varien_Http_Client('https://staging.thirdwatch.co/neo/v1/addpostbackurl/');
        $client->setMethod(Varien_Http_Client::POST);
        $client->setHeaders('Content-type','application/json');
        $jsonRequest = array(
            'score_postback'=>$scorePostback,
            'action_postback'=>$actionPostback,
            'secret'=>$secret
        );
        $client->setRawData(Mage::helper('core')->jsonEncode($jsonRequest));

        try{
            $response = $client->request();

            if ($response->isSuccessful()) {
                Mage::helper('mitra/log')->log($response->getBody());
            }
            else{
                Mage::throwException("Postback Url not registered successfully. Please try again.");
            }
        } catch (Exception $e) {
            Mage::helper('mitra/log')->log($e->getMessage());
            Mage::throwException("Postback Url not registered successfully. Please try again.");
        }
    }

    private function updateOrderStatusOnTw($order){
        Mage::helper('mitra/log')->log("Update Order Status");

        $helper = Mage::helper('mitra');
        $secret = $helper->getKey();

        $client = new Varien_Http_Client('https://api.thirdwatch.ai/neo/v1/clientaction');
        $client->setMethod(Varien_Http_Client::POST);
        $client->setHeaders('Content-type','application/json');

        $order_id = $order->getIncrementId();

        $jsonRequest = array(
            'secret'=>$secret,
            'order_id'=>$order_id,
            'order_timestamp'=>(string)Varien_Date::toTimestamp($order->getCreatedAt()) . '000',
            'action_type' =>'approved',
            'message' =>'Accepted on magento dashboard',
        );

        $client->setRawData(Mage::helper('core')->jsonEncode($jsonRequest));

        try{
            $response = $client->request();

            if ($response->isSuccessful()) {
                Mage::helper('mitra/log')->log($response->getBody());
            }
            else{
                Mage::throwException("Action not updated on thirdwatch dashboard.");
            }
        } catch (Exception $e) {
            Mage::helper('mitra/log')->log($e->getMessage());
            Mage::throwException("Some error while updated action on thirdwatch dashboard.");
        }

    }

    /**
     * this observer handles the event sales_order_payment_void
     * this function will be called whenever the payment gets void.
     * @param $evt
     */
    public function salesOrderPaymentVoid($evt)
    {
        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("salesOrderPaymentVoid");
            $order = $evt->getPayment()->getOrder();
            Mage::helper('mitra/order')->postOrder($order, Thirdwatch_Mitra_Helper_Order::ACTION_CANCEL);
        }
    }

    /**
     * this observer handles the event sales_order_payment_refund
     * this function is called whenever the payment will be refunded.
     * @param $evt
     */
    public function salesOrderPaymentRefund($evt)
    {
        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("salesOrderPaymentRefund");
            $order = $evt->getPayment()->getOrder();
            Mage::helper('mitra/order')->postOrder($order, Thirdwatch_Mitra_Helper_Order::ACTION_REFUND);
        }
    }

    /**
     * this observer handles the event sales_order_payment_cancel
     * this function will be called whenever the payment gets cancelled.
     * @param $evt
     */
    public function salesOrderPaymentCancel($evt)
    {
        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("salesOrderPaymentCancel");
            $order = $evt->getPayment()->getOrder();
            Mage::helper('mitra/order')->postOrder($order, Thirdwatch_Mitra_Helper_Order::ACTION_CANCEL);
        }
    }

    /**
     * this observer handles the event sales_order_save_after
     * this function will be called whenever the order save will be called in the system. It handles the creation and updatation of the
     * @param $evt
     */
    public function salesOrderSaveAfter($evt){
        $order = $evt->getOrder();
        if (!$order) {
            return;
        }

        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("salesOrderSaveAfter");

            $order = $evt->getOrder();
            if (!$order) {
                return;
            }

            $newState = $order->getState();
            $oldState = $order->getOrigData('state');
            $oldStatusLabel = $order->getOrigData('status');


            Mage::helper('mitra/log')->log("New State : ". $newState);
            Mage::helper('mitra/log')->log("Old State : ". $oldState);

            if ($order->dataHasChangedFor('state')) {

                if ($oldState == Mage_Sales_Model_Order::STATE_HOLDED and $newState == Mage_Sales_Model_Order::STATE_PROCESSING) {
                    Mage::helper('mitra/log')->log("Order : " . $order->getId() . " not notifying on unhold action");
                    return;
                }

                Mage::helper('mitra/log')->log("Order: " . $order->getId() . " state changed from: " . $oldState . " to: " . $newState);

                if ($order->thirdwatchInSave) {
                    Mage::helper('mitra/log')->log("Order : " . $order->getId() . " is already thirdwatchInSave");
                    return;
                }

                $statusHelper = Mage::helper('mitra/order_status');

//                Mage::helper('mitra/log')->log($statusHelper->getThirdwatchFlaggedStatusCode());
//                Mage::helper('mitra/log')->log($statusHelper->getOnHoldStatusCode());
//                Mage::helper('mitra/log')->log($statusHelper->getThirdwatchDeclinedStatusCode());

                if($oldState == Mage_Sales_Model_Order::STATE_HOLDED and ($oldStatusLabel == $statusHelper->getThirdwatchFlaggedStatusCode() or $oldStatusLabel == $statusHelper->getOnHoldStatusCode()) or $oldStatusLabel == $statusHelper->getThirdwatchDeclinedStatusCode()){

                    if ( strlen($newState) < 1 and $oldStatusLabel == $statusHelper->getThirdwatchFlaggedStatusCode()) {
                        if ($order->getBaseTotalDue() > 0){
                            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing');
                            $order->save();
                            $this->updateOrderStatusOnTw($order);
                        }
                        else if ($order->getBaseTotalDue() == 0){
                            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing');
                            $order->save();
                            $this->updateOrderStatusOnTw($order);
                        }
                    }

                    if ( strlen($newState) < 1 and $oldStatusLabel == $statusHelper->getOnHoldStatusCode()) {

                        if ($order->getBaseTotalDue() > 0){
                            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing');
                            $order->save();
                        }
                        else if ($order->getBaseTotalDue() == 0){
                            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing');
                            $order->save();
                        }
                    }

                    if ( strlen($newState) < 1 and $oldStatusLabel == $statusHelper->getThirdwatchDeclinedStatusCode()) {
                        if ($order->getBaseTotalDue() > 0){
                            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing');
                            $order->save();
                            $this->updateOrderStatusOnTw($order);
                        }
                        else if ($order->getBaseTotalDue() == 0){
                            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing');
                            $order->save();
                            $this->updateOrderStatusOnTw($order);
                        }
                    }
                }else {
                    $paymentMethod = $order->getPayment()->getMethod();
                    $order->thirdwatchInSave = true;
                    try {
                        if (!Mage::registry("thirdwatch-order")) {
                            Mage::register("thirdwatch-order", $order);
                        }

//                        if ($newState == "new" and $oldState == "") {
//                            Mage::helper('mitra/order')->postOrder($order, Thirdwatch_Mitra_Helper_Order::ACTION_TRANSACTION);

                        if ($newState == "new") {
                            if ($paymentMethod == "cashondelivery" and $order->getBaseTotalDue() > 0){
                                Mage::helper('mitra/order')->postOrder($order, Thirdwatch_Mitra_Helper_Order::ACTION_TRANSACTION);
                            }

                        } else if ($newState == "processing") {
                            if ($order->getBaseTotalDue() < 1) {
                                Mage::helper('mitra/order')->postOrder($order, Thirdwatch_Mitra_Helper_Order::ACTION_TRANSACTION);
                            }

                        } else if ($newState == "closed" and $oldState == "complete") {
                            Mage::helper('mitra/order')->postOrder($order, Thirdwatch_Mitra_Helper_Order::ACTION_UPDATE);

                        } else if ($newState == "complete" and $oldState == "processing") {
                            Mage::helper('mitra/order')->postOrder($order, Thirdwatch_Mitra_Helper_Order::ACTION_UPDATE);
                        }
                        Mage::unregister("thirdwatch-order");
                    } catch (Exception $e) {
                        // There is no need to do anything here.  The exception has already been handled and a retry scheduled.
                        // We catch this exception so that the order is still saved in Magento.
                    }
                }
            } else {
                Mage::helper('mitra/log')->log("Order: '" . $order->getId() . "' state didn't change on save - not posting again: " . $newState);
            }
        }
    }

    /**
     * this observer handles the event customer_login
     * this function is called whenever the user login and after the user gets registered.
     * @param $evt
     */
    public function customerLogin($evt){
        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("customerLogin");
            $customer = $evt->getCustomer();
            Mage::helper('mitra/login')->postLogin($customer);
        }
    }

    /**
     * this observer handles the event customer_logout
     * this function is called whenever the user logs out of the system.
     * @param $evt
     */
    public function customerLogout($evt){
        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("customerLogout");
            $customer = $evt->getCustomer();
            Mage::helper('mitra/login')->postLogout($customer);
        }
    }

    /**
     * this observer handles the event customer_register_success
     * this function is called whenever the user register. It will call customer login function after successful registration.
     * @param $evt
     */
    public function customerRegisterSuccess($evt){
        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("customerRegisterSuccess");
            $customer = $evt->getCustomer();
            Mage::helper('mitra/register')->postRegister($customer);
        }
    }

    /**
     * this observer handles the event customer_save_after
     * this function is called whenever the user update his account details.
     * Checks if the user is first time user or edit is being done on the user.
     * @param $evt
     */
    public function customerUpdate($evt){
        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("customerUpdate");
            $customer = $evt->getCustomer();
            if ($customer->getOrigData()) {
                Mage::helper('mitra/register')->postCustomerUpdate($customer);
            }
        }
    }

    /**
     * this observer handles the event checkout_cart_product_add_after
     * @param $evt
     * $item class -- Mage_Sales_Model_Quote_Item
     */
    public function cartAdd($evt){
        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("cartAdd");
            $product = $evt->getEvent()->getProduct();
            $quote = Mage::getModel('checkout/cart')->getQuote();
            $item = $quote->getItemByProduct( $product );
            Mage::helper('mitra/order')->postCart($item);
        }
    }


    /**
     * this observer handles the event checkout_cart_product_add_after
     * @param $evt
     * $item class -- Mage_Sales_Model_Quote_Item
     */
    public function cartUpdate($evt){
        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("cartUpdate");
            $item = $evt->getQuoteItem();
            Mage::helper('mitra/order')->postCart($item);
        }
    }

    /**
     * this observer handles the event sales_quote_remove_item
     * @param $evt
     * $item class -- Mage_Sales_Model_Quote_Item
     */
    public function cartRemove($evt){
        if (Mage::helper('mitra')->isPluginActive()) {
            Mage::helper('mitra/log')->log("cartRemove");
            $item = $evt->getQuoteItem();
            Mage::helper('mitra/order')->removeCart($item);
        }
    }
}