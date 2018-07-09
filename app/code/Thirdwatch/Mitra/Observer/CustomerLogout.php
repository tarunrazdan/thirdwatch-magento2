<?php

namespace Thirdwatch\Mitra\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerLogout implements ObserverInterface {

    protected $_customerSession;

    public function _construct(\Magento\Customer\Model\Session $customerSession) {
        $this->_customerSession = $customerSession;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $logHelper->log("tw-debug: Logout Execute", "debug");

        $is_plugin_active = $dataHelper->isPluginActive();
        if ($is_plugin_active) {
            $customer = $observer->getEvent()->getData('customer');
            $objectManager->create('Thirdwatch\Mitra\Helper\Login')->postLogout($customer);
        }
    }
}
