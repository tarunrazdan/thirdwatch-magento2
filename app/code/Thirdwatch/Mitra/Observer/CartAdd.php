<?php

namespace Thirdwatch\Mitra\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;

class CartAdd implements ObserverInterface {

    protected $_checkoutSession;

    public function _construct( Session $checkoutSession) {
        $this->_checkoutSession = $checkoutSession;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        try{
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
            $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
            $logHelper->log("tw-debug: Cart Add Execute", "debug");

            $is_plugin_active = $dataHelper->isPluginActive();
            if ($is_plugin_active) {
                $product = $observer->getEvent()->getProduct();
                $logHelper->log("tw-product", "debug");
                $logHelper->log(print_r($product->debug(), true), "debug");
                $objectManager->create('Thirdwatch\Mitra\Helper\Order')->postCart($product);
            }
        } catch (\Exception $e) {
            $logHelper->log("tw-debug: ".$e->getMessage(), "debug");
        }
    }
}
