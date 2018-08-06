<?php

namespace Thirdwatch\Mitra\Block\Adminhtml\Order\View;

class Custom extends \Magento\Backend\Block\Template
{
    public function getThirdwatchOrders(){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $twTable = $objectManager->create('Thirdwatch\Mitra\Model\ThirdwatchFlagged');
        $logger = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $twOrder = $twTable->getCollection()->addFieldToFilter('order_id', $this->getOrderId())->getData();

        if ($twOrder){
            $status = "";
            foreach ($twOrder as $twOrderItems) {
                $status = $twOrderItems['status'];
            }
            return $status;
        }
        return "";
    }

    public function isEmptyCase()
    {
        return false;
    }

    private function getOrderId()
    {
        return (int) $this->getRequest()->getParam('order_id');
    }

    public function getThirdwatchStatus(){
        return $this->getThirdwatchOrders();
    }
}