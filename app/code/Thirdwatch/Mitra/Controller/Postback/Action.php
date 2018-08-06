<?php

/**
 * This class is being used to handle the postback request from thirdwatch
 * to update the status of the order which were sent to thirdwatch.
 */
namespace Thirdwatch\Mitra\Controller\Postback;


class Action extends \Magento\Framework\App\Action\Action
{

    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $twTable = $objectManager->create('Thirdwatch\Mitra\Model\ThirdwatchFlagged');
        $helper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $logger = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $orderConnection = $objectManager->create('Magento\Sales\Model\ResourceModel\Order\Grid\Collection')->getConnection();

        $logger->log("tw-debug: Postback Executed", "debug");

        $request = $this->getRequest();
        $response = $this->getResponse();
        $jsonManager = $objectManager->get('\Magento\Framework\Json\Decoder');
        $statusCode = 200;
        $orderId = null;
        $msg = null;

        try {
            $body = $request->getContent();
            $jsonBody = $jsonManager->decode($body);

            if (array_key_exists('test', $jsonBody)) {
                $response->setHttpResponseCode($statusCode);
                $response->setHeader('Content-Type', 'application/json');
                $response->setBody('{}');
                $logger->log("tw-debug: Postback URL Successfully Tested", "debug");
                return;
            }

            if (!array_key_exists('order_id', $jsonBody)){
                $logger->log("tw-debug: Order Id doesnot exists", "debug");
            }

            $orderId = $jsonBody['order_id'];
            $flag = $jsonBody['flag'];
            $reasons = $jsonBody['reasons'];
            $score = $jsonBody['score'];

            $order = $this->loadOrderByIncId($orderId);

            if (!$order || !$order->getId()) {
                $statusCode = 400;
                $msg = 'Could not find order to update.';
            } else {
                try {
                    if ($flag === "green"){
                        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                        $twOrder = $twTable->getCollection()->addFieldToFilter('order_increment_id', $orderId)->getData();

                        if ($twOrder){
                            $twOrder = $twTable->getCollection()->addFieldToFilter('order_increment_id', $orderId);
                            foreach($twOrder as $twOrderItems)
                            {
                                $twOrderItems->setFlag("GREEN");
                                $twOrderItems->setScore($score);
                                $twOrderItems->setStatus($helper->getApproved());
                            }
                            $twOrder->save();
                        }
                        $order->save();
                        $orderConnection->update('sales_order_grid', ['thirdwatch_flag_status' => $helper->getApproved()], ["entity_id = ?" => $order->getId()]);

                    } else {
                        $twOrder = $twTable->getCollection()->addFieldToFilter('order_increment_id', $orderId)->getData();

                        if ($twOrder) {
                            $twOrder = $twTable->getCollection()->addFieldToFilter('order_increment_id', $orderId);
                            foreach ($twOrder as $twOrderItems) {
                                $twOrderItems->setFlag("RED");
                                $twOrderItems->setScore($score);
                                $twOrderItems->setStatus($helper->getFlagged());
                            }
                            $twOrder->save();
                        }
                        $order->save();
                        $result = $orderConnection->update('sales_order_grid', ['thirdwatch_flag_status' => $helper->getFlagged()], ["entity_id = ?" => $order->getId()]);
                        $logger->log("tw-debug: Query Result ".$result, "debug");
                    }
                    $statusCode = 200;
                    $msg = 'Order-Update event triggered.';
                } catch (\Exception $e) {
                    $logger->log("tw-debug: Postback Exception ".$e->getMessage(), "debug");
                }
            }
        } catch (\Exception $e) {
            $logger->log("tw-debug: Postback Exception".$e->getMessage(), "debug");
            $statusCode = 500;
            $msg = "Internal Error";
        }

        $response->setHttpResponseCode($statusCode);
        $response->setHeader('Content-Type', 'application/json');
        $response->setBody('{ "order" : { "id" : "' . $orderId . '", "description" : "' . $msg . '" } }');
    }

    public function loadOrderByIncId($full_orig_id) {
        if (!$full_orig_id) {
            return null;
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('\Magento\Sales\Model\Order')->loadByIncrementId($full_orig_id);
        return $order;
    }

}