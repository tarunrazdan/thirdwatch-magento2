<?php

namespace Thirdwatch\Mitra\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;

class CartUpdate implements ObserverInterface {

    protected $_customerSession;

    public function __construct(Session $customerSession) {
        $this->_customerSession = $customerSession;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $logHelper->log("tw-debug: Cart Update Execute", "debug");

        $is_plugin_active = $dataHelper->isPluginActive();
        if ($is_plugin_active) {
            $item = $observer->getEvent()->getQuoteItem();
            $objectManager->create('Thirdwatch\Mitra\Helper\Order')->postCart($item);
        }
    }
}
