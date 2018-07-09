<?php

namespace Thirdwatch\Mitra\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class salesOrderSaveAfter implements ObserverInterface {

    private $storeManager;
    protected $_registry;
    private $timezone;

    public function __construct(StoreManagerInterface $storeManager, Registry $registry, TimezoneInterface $timezone) {
         $this->storeManager = $storeManager;
         $this->_registry = $registry;
         $this->timezone = $timezone;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $logHelper->log("tw-debug: Sales Order Save After", "debug");

        $is_plugin_active = $dataHelper->isPluginActive();
        if ($is_plugin_active) {
            $order = $observer->getEvent()->getOrder();
            if (!$order) {return;}
            $oldState = $order->getOrigData('state');
            $newState = $order->getState();
            $oldStatusLabel = $order->getOrigData('status');
            $newStatusLabel = $order->getStatusLabel();

            $logHelper->log("tw-debug: Old State ".$oldState, "debug");
            $logHelper->log("tw-debug: New State ".$newState, "debug");
            $logHelper->log("tw-debug: Old Status Label ".$oldStatusLabel, "debug");
            $logHelper->log("tw-debug: New Status Label ".$newStatusLabel, "debug");

            if ($order->dataHasChangedFor('state')) {
//                if ($oldState == Order::STATE_HOLDED and $newState == Order::STATE_PROCESSING) {
//                    $logHelper->log("Order : " . $order->getId() . " not notifying on unhold action");
//                    return;
//                }

//                if ($order->thirdwatchInSave) {
//                    $logHelper->log("Order : " . $order->getId() . " is already thirdwatchInSave");
//                    return;
//                }

                $logHelper->log("Inside Order");

//                $statusHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Order\Status');

                $logHelper->log($dataHelper->getThirdwatchFlaggedStatusCode());
                $logHelper->log($dataHelper->getOnHoldStatusCode());
                $logHelper->log($dataHelper->getThirdwatchDeclinedStatusCode());

                if ($newState == "new"){


//                if($oldState == Order::STATE_HOLDED and ($oldStatusLabel == $statusHelper->getThirdwatchFlaggedStatusCode() or $oldStatusLabel == $statusHelper->getOnHoldStatusCode()) or $oldStatusLabel == $statusHelper->getThirdwatchDeclinedStatusCode()){
//
//                    if ( strlen($newState) < 1 and $oldStatusLabel == $statusHelper->getThirdwatchFlaggedStatusCode()) {
//                        if ($order->getBaseTotalDue() > 0){
//                            $order->setState(Order::STATE_PROCESSING, 'processing');
//                            $order->save();
//                            $this->updateOrderStatusOnTw($order);
//                        }
//                        else if ($order->getBaseTotalDue() == 0){
//                            $order->setState(Order::STATE_PROCESSING, 'processing');
//                            $order->save();
//                            $this->updateOrderStatusOnTw($order);
//                        }
//                    }
//
//                    if ( strlen($newState) < 1 and $oldStatusLabel == $statusHelper->getOnHoldStatusCode()) {
//
//                        if ($order->getBaseTotalDue() > 0){
//                            $order->setState(Order::STATE_PROCESSING, 'processing');
//                            $order->save();
//                        }
//                        else if ($order->getBaseTotalDue() == 0){
//                            $order->setState(Order::STATE_PROCESSING, 'processing');
//                            $order->save();
//                        }
//                    }
//
//                    if ( strlen($newState) < 1 and $oldStatusLabel == $statusHelper->getThirdwatchDeclinedStatusCode()) {
//                        if ($order->getBaseTotalDue() > 0){
//                            $order->setState(Order::STATE_PROCESSING, 'processing');
//                            $order->save();
//                            $this->updateOrderStatusOnTw($order);
//                        }
//                        else if ($order->getBaseTotalDue() == 0){
//                            $order->setState(Order::STATE_PROCESSING, 'processing');
//                            $order->save();
//                            $this->updateOrderStatusOnTw($order);
//                        }
//                    }
//                }else {
//                    $paymentMethod = $order->getPayment()->getMethod();
//
//                    $order->thirdwatchInSave = true;
//                    try {
//                        if (!$this->registry->registry("thirdwatch-order")) {
//
//                            $this->registry->register('thirdwatch-order',  $order);
//
//                        }

//                        if ($newState == "new" and $oldState == "") {
//                            $orderHelper->postOrder($order, Thirdwatch_Mitra_Helper_Order::ACTION_TRANSACTION);
                        $orderHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Order');
//                        if ($newState == "new") {
//                            if ($paymentMethod == "cashondelivery" and $order->getBaseTotalDue() > 0){
                                $orderHelper->postOrder($order, \Thirdwatch\Mitra\Helper\Order::ACTION_TRANSACTION);
//                            }

//                        } else if ($newState == "processing") {
//                            if ($order->getBaseTotalDue() < 1) {
//                                $orderHelper->postOrder($order, \Thirdwatch\Mitra\Helper\Order::ACTION_TRANSACTION);
//                            }
//
//                        } else if ($newState == "closed" and $oldState == "complete") {
//                            $orderHelper->postOrder($order, \Thirdwatch\Mitra\Helper\Order::ACTION_UPDATE);
//
//                        } else if ($newState == "complete" and $oldState == "processing") {
//                            $orderHelper->postOrder($order, \Thirdwatch\Mitra\Helper\Order::ACTION_UPDATE);
//                        }
//                        $this->registry->register('thirdwatch-order',  '');
//                    } catch (Exception $e) {
                        // There is no need to do anything here.  The exception has already been handled and a retry scheduled.
                        // We catch this exception so that the order is still saved in Magento.
//                    }
                }
            } else {
                $logHelper->log("Order: '" . $order->getId() . "' state didn't change on save - not posting again: " . $newState);
            }
        }
        
    }

//    private function updateOrderStatusOnTw($order){
//            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
//            $orderId = $observer->getEvent()->getOrderIds();
//            $base_url = $this->storeManager->getStore()->getBaseUrl();
//            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
//            $order = $objectManager->create('\Magento\Sales\Model\Order') ->load($orderId[0]);
//
//            $objectManager->create('Thirdwatch\Mitra\Helper\Log')->log("salesOrderPaymentVoid");
//            $objectManager->create('Thirdwatch\Mitra\Helper\Order')->postOrder($order,
//            \Thirdwatch\Mitra\Helper\Order::ACTION_CANCEL);
//
//
//
//
//$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
//
//        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
//        $logHelper->log("Update Order Status");
//
//        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
//        $secret = $dataHelper->getGeneralConfig('secret_key');
//
//
//        $client = new Zend_Http_Client('https://api.thirdwatch.ai/neo/v1/clientaction');
//        $client->setMethod(Zend_Http_Client::POST);
//        $client->setHeaders('Content-type','application/json');
//
//        $order_id = $order->getIncrementId();
//
//        $jsonRequest = array(
//            'secret'=>$secret,
//            'order_id'=>$order_id,
//            'order_timestamp'=>(string) $this->timezone->date(new \DateTime($order->getCreatedAt())) . '000',
//            'action_type' =>'approved',
//            'message' =>'Accepted on magento dashboard',
//        );
//
//        $client->setRawData(Mage::helper('core')->jsonEncode($jsonRequest));
//
//        try{
//            $response = $client->request();
//
//            if ($response->isSuccessful()) {
//                $logHelper->log($response->getBody());
//            }
//            else{
//                $this->logger->critical("Action not updated on thirdwatch dashboard.");
//                //Mage::throwException("Action not updated on thirdwatch dashboard.");
//            }
//        } catch (Exception $e) {
//            $logHelper->log("Some error while updated action on thirdwatch dashboard.");
//            //Mage::throwException("Some error while updated action on thirdwatch dashboard.");
//        }
//    }

}
