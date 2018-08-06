<?php

namespace Thirdwatch\Mitra\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;


class salesOrderSaveAfter implements ObserverInterface
{

    private $storeManager;
    protected $_registry;
    private $timezone;
    protected $_modelNewsFactory;

    public function __construct(StoreManagerInterface $storeManager, Registry $registry, TimezoneInterface $timezone)
    {
        $this->storeManager = $storeManager;
        $this->_registry = $registry;
        $this->timezone = $timezone;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $twTable = $objectManager->create("Thirdwatch\Mitra\Model\ThirdwatchFlagged");
        $orderHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Order');
        $logHelper->log("tw-debug: Sales Order Save After", "debug");

        $is_plugin_active = $dataHelper->isPluginActive();
        if ($is_plugin_active) {
            $order = $observer->getEvent()->getOrder();

            if (!$order) {
                return;
            }

            $oldState = $order->getOrigData('state');
            $newState = $order->getState();
            $oldStatusLabel = $order->getOrigData('status');
            $newStatusLabel = $order->getStatusLabel();

            $logHelper->log("tw-debug: Old State " . $oldState, "debug");
            $logHelper->log("tw-debug: New State " . $newState, "debug");
            $logHelper->log("tw-debug: Old Status Label " . $oldStatusLabel, "debug");
            $logHelper->log("tw-debug: New Status Label " . $newStatusLabel, "debug");

            if ($order->dataHasChangedFor('state')) {

                $twOrder = $twTable->getCollection()->addFieldToFilter('order_id', $order->getEntityId())->getData();
                if (!$twOrder){
                    $logHelper->log("tw-debug: Order Not Found in thirdwatch table " . $order->getIncrementId(), "debug");
                    if ($oldState == Order::STATE_HOLDED and $newState == Order::STATE_PROCESSING) {
                        $logHelper->log("tw-debug: Order id - " . $order->getIncrementId() . " not notifying on unhold action");
                        return;
                    }
                    $logHelper->log("tw-debug: Payment Method " . $order->getPayment()->getMethod(), "debug");
                    $logHelper->log("tw-debug: Total Due " . $order->getBaseTotalDue(), "debug");

                    $paymentMethod = $order->getPayment()->getMethod();

                    if ($newState == "new") {
                        if ($paymentMethod == "cashondelivery" and $order->getBaseTotalDue() > 0){
                            $orderHelper->postOrder($order, \Thirdwatch\Mitra\Helper\Order::ACTION_TRANSACTION_COD);
                        }

                        if ($paymentMethod == "free" and $order->getBaseTotalDue() == 0){
                            $orderHelper->postOrder($order, \Thirdwatch\Mitra\Helper\Order::ACTION_TRANSACTION_FREE);
                        }

                    } else if ($newState == "processing") {
                        if ($order->getBaseTotalDue() < 1) {
                            $orderHelper->postOrder($order, \Thirdwatch\Mitra\Helper\Order::ACTION_TRANSACTION_PAID);
                        }
                    }
                }
                else{
                    $logHelper->log("tw-debug: Order Already Sent to Thirdwatch, Order Id: " . $order->getIncrementId(), "debug");
//                } else if ($oldState == "complete" and $newState == "closed") {
//                    $orderHelper->postOrder($order, \Thirdwatch\Mitra\Helper\Order::ACTION_UPDATE);
//
//                } else if ($oldState == "processing" and $newState == "complete") {
//                    $orderHelper->postOrder($order, \Thirdwatch\Mitra\Helper\Order::ACTION_UPDATE);
//                }
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
}
