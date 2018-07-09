<?php

namespace Thirdwatch\Mitra\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;


class CustomerUpdate implements ObserverInterface {
      protected $_customerRepository;
      protected $_customerSession;
    
      public function __construct(
        Session $customerSession,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->_customerSession = $customerSession;
        $this->_customerRepository = $customerRepository;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $logHelper->log("tw-debug: Update Account Execute", "debug");

        $is_plugin_active = $dataHelper->isPluginActive();
        if ($is_plugin_active) {
            $customerId = $this->_customerSession->getCustomerId();
            $customerDataObject = $this->_customerRepository->getById($customerId);
            $objectManager->create('Thirdwatch\Mitra\Helper\Register')->postCustomerUpdate($customerDataObject);
        }
    }
}
