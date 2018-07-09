<?php

namespace Thirdwatch\Mitra\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;

class CartRemove implements ObserverInterface {

    protected $_customerSession;

    public function _construct( Session $customerSession) {
        $this->_customerSession = $customerSession;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $logHelper->log("tw-debug: Cart Remove Execute", "debug");

        $is_plugin_active = $dataHelper->isPluginActive();
        if ($is_plugin_active) {
            $logHelper->log(print_r($observer->getEvent()->debug(), true), "debug");
            $item = $observer->getEvent()->getQuoteItem();
            $logHelper->log("tw-item", "debug");
            $logHelper->log(print_r($item->debug(), true), "debug");
            $objectManager->create('Thirdwatch\Mitra\Helper\Order')->removeCart($item);
        }
    }
}
