<?php

namespace Thirdwatch\Mitra\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

class salesOrderPaymentCancel implements ObserverInterface {

     private $storeManager;
    protected $_checkoutSession;

    public function __construct(
        StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession   
    ) {
        $this->storeManager = $storeManager;
        $this->_checkoutSession = $checkoutSession;
    }


    public function execute(\Magento\Framework\Event\Observer $observer) {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $is_plugin_active = $dataHelper->getGeneralConfig('plugin_active');
        if ($is_plugin_active) {           
            $orderId = $observer->getEvent()->getOrderIds();
        $base_url = $this->storeManager->getStore()->getBaseUrl();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('\Magento\Sales\Model\Order') ->load($orderId[0]);

            
            $objectManager->create('Thirdwatch\Mitra\Helper\Log')->log("salesOrderPaymentCancel");
            $objectManager->create('Thirdwatch\Mitra\Helper\Order')->postOrder($order, \Thirdwatch\Mitra\Helper\Order::ACTION_CANCEL);
        }
    }

}
