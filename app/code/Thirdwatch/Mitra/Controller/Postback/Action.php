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
        $request = $this->getRequest();
        $response = $this->getResponse();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $logHelper->log("tw-debug: Postback Action Execute", "debug");
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
                $logHelper->log("tw-debug: " . "Postback URL Successfully Tested", "debug");
                return;
            }

            if (!array_key_exists('order_id', $jsonBody)){
                $logHelper->log("tw-debug: " . "Order Id doesnot exists.", "debug");
            }

            $orderId = $jsonBody['order_id'];
            $flag = $jsonBody['flag'];

            $order = $this->loadOrderByIncId($orderId);

            if (!$order || !$order->getId()) {
                $statusCode = 400;
                $msg = 'Could not find order to update.';
            } else {
                try {
                    if ($flag === "green"){
                        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                            ->setStatus('thirdwatch_approved');
                    } else{
                        $order->setState(\Magento\Sales\Model\Order::STATE_HOLDED)
                            ->setStatus('thirdwatch_declined');
                    }
                    $statusCode = 200;
                    $msg = 'Order-Update event triggered.';
                } catch (\Exception $e) {
                    $exceptionMessage = 'SQLSTATE[40001]: Serialization '
                        . 'failure: 1213 Deadlock found when trying to get '
                        . 'lock; try restarting transaction';

                    if ($e->getMessage() === $exceptionMessage) {
                        throw new \Exception('Deadlock exception handled.');
                    } else {
                        throw $e;
                    }
                }
            }
        } catch (\Exception $e) {
            $logHelper->log("tw-debug: ERROR: while processing notification for order $orderId", "debug");
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