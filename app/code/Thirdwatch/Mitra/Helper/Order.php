<?php

namespace Thirdwatch\Mitra\Helper;

require_once(BP . '/lib' . DIRECTORY_SEPARATOR . 'thirdwatch-php' . DIRECTORY_SEPARATOR . 'autoload.php');

use ai\thirdwatch\ApiException;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Customer\Model\Session;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\OrderFactory;
//use Magento\Sales\Model\Order as MageOrder;

class Order extends AbstractHelper {

    const ACTION_CREATE = 'create';
    const ACTION_TRANSACTION = 'transaction';
    const ACTION_UPDATE = 'update';
    const ACTION_CANCEL = 'cancel';
    const ACTION_REFUND = 'refund';
    const ACTION_ONLY_TRANSACTION = 'onlyTransaction';

    private $_customer = array();
    protected $requestData = array();
    protected $_customerSession;
    protected $_remoteAddress;
    protected $storeManager;
    private $timezone;
     protected $_orderFactory;

    public function __construct(
    Session $customerSession, RemoteAddress $remoteAddress, ScopeConfigInterface $scopeConfig,
            StoreManagerInterface $storeManager, TimezoneInterface $timezone, OrderFactory $orderFactory
    ) {
        $this->_customerSession = $customerSession;
        $this->_remoteAddress = $remoteAddress;
        $this->scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->timezone = $timezone;
        $this->_orderFactory = $orderFactory;
    }

    /*
     * Submit an order to Thirdwatch.
     * @param Mage_Sales_Model_Order $order
     * @param string $action - one of self::ACTION_*
     */

    public function postOrder($order, $action) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();        
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');

        switch ($action) {
            case self::ACTION_CREATE:
                $logHelper->log("ACTION_CREATE");
                $this->createOrder($order);
                break;
            case self:: ACTION_TRANSACTION:
                $logHelper->log("ACTION_TRANSACTION");
                $this->createOrder($order);
                $this->createTransaction($order, '_sale');
                $order->setState(\Magento\Sales\Model\Order::STATE_HOLDED)
                    ->setStatus('thirdwatch_holded');
                $order->save();
                break;
            case self:: ACTION_CANCEL:
                $logHelper->log("ACTION_CANCEL");
                $this->createTransaction($order, '_void');
                break;
            case self:: ACTION_REFUND:
                $logHelper->log("ACTION_REFUND");
                $this->createTransaction($order, '_refund');
                break;
            case self:: ACTION_ONLY_TRANSACTION:
                $logHelper->log("ACTION_ONLY_TRANSACTION");
                $this->createTransaction($order, '_sale');
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, 'thirdwatch_holded');
                $order->save();
                break;
            case self::ACTION_UPDATE:
                $logHelper->log("ACTION_UPDATE");
                $this->updateOrderStatus($order);
                break;
        }
    }

    public function getOrderOrigId($order) {
        if (!$order) {
            return null;
        }
        return $order->getId() . '_' . $order->getIncrementId();
    }

    public function getIncrementOrderId($order) {
        if (!$order) {
            return null;
        }
        return $order->getIncrementId();
    }

    public function loadOrderByIncId($full_orig_id) {
        if (!$full_orig_id) {
            return null;
        }

        //return Mage::getModel('sales/order')->loadByIncrementId($full_orig_id);
        return $this->_orderFactory->create()->loadByIncrementId($full_orig_id);
    }

    public function checkOnSale($product){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');

        $logHelper->log("tw-debug8: ".(string)$product->getSpecialPrice(), "debug");
        $logHelper->log("tw-debug9: ".(string)$product->getPrice(), "debug");
        $logHelper->log("tw-debug10: ".(string)$product->getFinalPrice(), "debug");

        $specialprice = $product->getSpecialPrice();
        $specialPriceFromDate = $product->getSpecialFromDate();
        $specialPriceToDate = $product->getSpecialToDate();
        $today =  time();

        if ($specialprice && ($product->getPrice()>$product->getFinalPrice())):
            if($today >= strtotime( $specialPriceFromDate) && $today <= strtotime($specialPriceToDate) ||
                $today >= strtotime( $specialPriceFromDate) && is_null($specialPriceToDate)):
                return True;
            endif;
        endif;
        return False;
    }

    /**
     * This function is called whenever an item is added to the cart or removed from the cart.
     */
    private function getLineItemData($objectManager, $logHelper, $prod, $item) {
        $prodType = null;
        $category = null;
        $subCategories = null;
        $brand = null;
        $isSale = False;

        $price = $item->getPrice();
        $logHelper->log("tw-price: ".(string) $price, "debug");
        $discountPrice = $item->getPrice();
        $description = $prod->getDescription();
        $shortDescription = $prod->getShortDescription();

        $logHelper->log("tw-discount-price: ".(string) $discountPrice, "debug");

        if ($prod) {
            $categoryIds = $prod->getCategoryIds();
            foreach ($categoryIds as $categoryId) {
                $cat = $objectManager->create('Magento\Catalog\Model\Category')->load($categoryId);
                $catName = $cat->getName();
                if (!empty($catName)) {
                    if (empty($category)) {
                        $category = $catName;
                    } else if (empty($subCategories)) {
                        $subCategories = $catName;
                    } else {
                        $subCategories = $subCategories . '|' . $catName;
                    }
                }
            }

            if (!empty($subCategories)){
                $category = $category . '|' . $subCategories;
            }

            if ($prod->getManufacturer()) {
                $brand = $prod->getAttributeText('manufacturer');
            }

            $isOnSale = $this->checkOnSale($prod);
            $logHelper->log("tw-is_on_sales: ".(string) $isOnSale, "debug");

            if ($isOnSale){
                $price = $prod->getPrice();
                $discountPrice = $prod->getFinalPrice();
                $isSale = True;
            }

            if ($prod->getDescription()){
                $description = $prod->getDescription();
            }

            if ($prod->getShortDescription()){
                $shortDescription = $prod->getShortDescription();
            }
        }
       
        $currencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $countryCode = $this->scopeConfig->getValue('general/store_information/country_id');

        $lineItemData = array();
        $lineItemData['_price'] = (string) $price;
        $lineItemData['_quantity'] = intval($item->getQty());
        $lineItemData['_product_title'] = (string) $prod->getName();
        $lineItemData['_sku'] = (string) $prod->getSku();
        $lineItemData['_item_id'] = (string) $prod->getId();
        $lineItemData['_product_weight'] = (string) $prod->getWeight();
        $lineItemData['_category'] = (string) $category;
        $lineItemData['_brand'] = (string) $brand;
        $lineItemData['_description'] = (string) strip_tags($description);
        $lineItemData['_description_short'] = (string) strip_tags($shortDescription);
        $lineItemData['_manufacturer'] = (string) $brand;
        $lineItemData['_currency_code'] = (string) $currencyCode;
        $lineItemData['_country'] = (string) $countryCode;
        $lineItemData['_is_on_sale'] = $isSale;
        $lineItemData['_discount_price'] = (string) $discountPrice;

        $itemJson = new \ai\thirdwatch\Model\Item($lineItemData);
        return $itemJson;
    }

    private function getConfig($objectManager){
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $secretKey = $dataHelper->getKey();
        $config = \ai\thirdwatch\Configuration::getDefaultConfiguration()->setApiKey(
            'X-THIRDWATCH-API-KEY', $secretKey);
        return $config;
    }

    private function getCustomerData($objectManager){
        $customerArray = array();

        $customer = $this->_customerSession->getCustomer();
        $customerData = $objectManager->create('Magento\Customer\Model\Customer')->load($customer->getId());

        $customerArray['sessionId'] = $this->_customerSession->getSessionId();
        $customerArray['customerId'] = $customerData->getId();
        return $customerArray;
    }

    private function getCurrentTimeStamp($objectManager){
        $magentoDateObject = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
        $currentDate = $magentoDateObject->timestamp().'000';
        return $currentDate;
    }

    private function getRemoteAddress(){
        $remoteAddress = $this->_remoteAddress->getRemoteAddress();
        return $remoteAddress;
    }

    private function createCartData($objectManager, $logHelper, $prod, $item){
        try {
            $cartData = array();
            $customerData = $this->getCustomerData($objectManager);
            $cartData['_user_id'] = (string) $customerData['customerId'];
            $cartData['_session_id'] = (string) $customerData['sessionId'];
            $cartData['_device_ip'] = (string) $this->getRemoteAddress();
            $cartData['_origin_timestamp'] = (string) $this->getCurrentTimeStamp($objectManager);
            $cartData['_item'] = $this->getLineItemData($objectManager, $logHelper, $prod, $item);
            return $cartData;
        } catch (\Exception $e) {
            $logHelper->log("tw-debug: ".$e->getMessage(), "debug");
        }
    }

    private function requestAddCartApi($objectManager, $logHelper, $cartJson){
        try {
            $apiInstance = new \ai\thirdwatch\Api\AddToCartApi(new \GuzzleHttp\Client(), $this->getConfig($objectManager));
            $body = new \ai\thirdwatch\Model\AddToCart($cartJson);
            $apiInstance->addToCart($body);
        } catch (ApiException $e) {
            $logHelper->log("tw-debug: ".$e->getMessage(), "debug");
        }
    }

    public function postCart($prod) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $logHelper->log("tw-debug: Inside Post Cart", "debug");

        if ($prod->getTypeId() == "simple"){
            $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
            $quote = $cart->getQuote();
            $item = $quote->getItemByProduct( $prod );
            $logHelper->log("tw-item", "debug");
            $logHelper->log(print_r($item->debug(), True), "debug");
            $cartJson = $this->createCartData($objectManager, $logHelper, $prod, $item);
            $this->requestAddCartApi($objectManager, $logHelper, $cartJson);

        } else if ($prod->getTypeId() == "configurable"){
            $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
            $quote = $cart->getQuote();
            $item = $quote->getItemByProduct( $prod );
            $logHelper->log("tw-item", "debug");
            $logHelper->log(print_r($item->debug(), True), "debug");
            $subItems = $item['qty_options'];
            foreach ($subItems as $subItem) {
                $cartJson = $this->createCartData($objectManager, $logHelper, $subItem->getProduct(), $item);
                $this->requestAddCartApi($objectManager, $logHelper, $cartJson);
            }

        } else if ($prod->getTypeId() == "grouped"){
            $groupInstance = $objectManager->get('\Magento\GroupedProduct\Model\Product\Type\Grouped');
            $childProductCollection = $groupInstance->getAssociatedProducts($prod);
            foreach ($childProductCollection as $child){
                $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
                $quote = $cart->getQuote();
                $item = $quote->getItemByProduct( $child );
                $logHelper->log("tw-item", "debug");
                $logHelper->log(print_r($item->debug(), True), "debug");
                $cartJson = $this->createCartData($objectManager, $logHelper, $child, $item);
                $this->requestAddCartApi($objectManager, $logHelper, $cartJson);
            }

        } else if ($prod->getTypeId() == "bundle"){
            $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
            $quote = $cart->getQuote();
            $item = $quote->getItemByProduct( $prod );
            $logHelper->log("tw-item", "debug");
            $logHelper->log(print_r($item->debug(), True), "debug");
            $subItems = $item['qty_options'];
            foreach ($subItems as $subItem) {
                $cartJson = $this->createCartData($objectManager, $logHelper, $subItem->getProduct(), $item);
                $this->requestAddCartApi($objectManager, $logHelper, $cartJson);
            }
        } else if ($prod->getTypeId() == "virtual") {
            $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
            $quote = $cart->getQuote();
            $item = $quote->getItemByProduct( $prod );
            $logHelper->log("tw-item", "debug");
            $logHelper->log(print_r($item->debug(), True), "debug");
            $cartJson = $this->createCartData($objectManager, $logHelper, $prod, $item);
            $this->requestAddCartApi($objectManager, $logHelper, $cartJson);
        }
    }

    private function requestRemoveCartApi($objectManager, $logHelper, $cartJson){
        try {
            $apiInstance = new \ai\thirdwatch\Api\RemoveFromCartApi(new \GuzzleHttp\Client(), $this->getConfig($objectManager));
            $body = new \ai\thirdwatch\Model\RemoveFromCart($cartJson);
            $apiInstance->removeFromCart($body);
        } catch (ApiException $e) {
            $logHelper->log("tw-debug: ".$e->getMessage(), "debug");
        }
    }

    public function removeCart($item) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');

        $logHelper->log(print_r("Product ID", True), "debug");
        $logHelper->log(print_r($item->getProductId(), True), "debug");

//
//        $customer = $this->_customerSession->getCustomer();
//        $cartData = array();
//        $customerData = $objectManager->create('Magento\Customer\Model\Customer')->load($customer->getId());
//
//
//        try {
//            $SID = $this->_customerSession->getSessionId();
//            $magentoDateObject = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
//            $currentDate = $magentoDateObject->timestamp();
//            $remoteAddress = $this->_remoteAddress->getRemoteAddress();
//
//            $cartData['_user_id'] = (string) $customerData->getId();
//            $cartData['_session_id'] = (string) $SID;
//            $cartData['_device_ip'] = (string) $remoteAddress;
//            $cartData['_origin_timestamp'] = (string) $currentDate . '000';
//            $cartData['_item'] = $this->getLineItemData($item);
//        } catch (\Exception $e) {
//            $logHelper->log("tw-debug: ".$e->getMessage(), "debug");
//        }

//        $product = $item->getProduct();
//        $logHelper->log(print_r($product->debug(), True), "debug");
//
//        $logHelper->log(print_r($product->getProductId(), True), "debug");
        $prod = $objectManager->create('Magento\Catalog\Model\Product')->load($item->getProductId());
        $logHelper->log("tw-product", "debug");
        $logHelper->log(print_r($prod->debug(), True), "debug");
        $cartJson = $this->createCartData($objectManager, $logHelper, $prod, $item);
        $this->requestRemoveCartApi($objectManager, $logHelper, $cartJson);
    }

    /**
     * This function is called whenever an order is placed.
     */
    private function getOrderItemData($val) {
        $prodType = null;
        $category = null;
        $subCategories = null;
        $brand = null;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $logHelper->log("inside get order item data 1");
        $product = $objectManager->create('Magento\Catalog\Model\Product')->load($val->getProductId());
        $logHelper->log("inside get order item data 2");

        if ($product) {
            $prodType = $product->getTypeId();
            $logHelper->log("inside get order item data 3");
            $categoryIds = $product->getCategoryIds();
            $logHelper->log("inside get order item data 4");
            foreach ($categoryIds as $categoryId) {
                $logHelper->log("inside get order item data 5");
                $cat = $objectManager->create('Magento\Catalog\Model\Category')->load($categoryId);
                $logHelper->log("inside get order item data 6");
                $catName = $cat->getName();
                $logHelper->log("inside get order item data 7");
                if (!empty($catName)) {
                    $logHelper->log("inside get order item data 8");
                    if (empty($category)) {
                        $logHelper->log("inside get order item data 9");
                        $category = $catName;
                        $logHelper->log("inside get order item data 10");
                    } else if (empty($subCategories)) {
                        $logHelper->log("inside get order item data 11");
                        $subCategories = $catName;
                        $logHelper->log("inside get order item data 12");
                    } else {
                        $logHelper->log("inside get order item data 13");
                        $subCategories = $subCategories . '|' . $catName;
                        $logHelper->log("inside get order item data 14");
                    }
                }
            }
            $logHelper->log("inside get order item data 15");

            if ($product->getManufacturer()) {
                $logHelper->log("inside get order item data 16");
                $brand = $product->getAttributeText('manufacturer');
            }
            $logHelper->log("inside get order item data 17");
        }
        $logHelper->log("inside get order item data 18");

        $currencyCode = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $logHelper->log("inside get order item data 19");
        $countryCode = $this->scopeConfig->getValue('general/store_information/country_id');
        $logHelper->log("inside get order item data 20");

        $lineItemData = array();
        $lineItemData['_price'] = (string) $val->getPrice();
        $logHelper->log("inside get order item data 21");
        $lineItemData['_quantity'] = intval($val->getQtyOrdered());
        $lineItemData['_product_title'] = (string) $val->getName();
        $lineItemData['_sku'] = (string) $val->getSku();
        $logHelper->log("inside get order item data 22");
        $lineItemData['_item_id'] = (string) $product->getId();
        $lineItemData['_product_weight'] = (string) $val->getWeight();
        $logHelper->log("inside get order item data 23");
        $lineItemData['_category'] = (string) $category;
        $lineItemData['_brand'] = (string) $brand;
        $logHelper->log("inside get order item data 24");
        $lineItemData['_description'] = (string) $product->getDescription();
        $logHelper->log("inside get order item data 25");
        $lineItemData['_description_short'] = (string) $product->getShortDescription();
        $logHelper->log("inside get order item data 26");
        $lineItemData['_manufacturer'] = (string) $brand;
        $lineItemData['_currency_code'] = (string) $currencyCode;
        $lineItemData['_country'] = (string) $countryCode;
        $logHelper->log("inside get order item data 27");

        $itemJson = new \ai\thirdwatch\Model\Item($lineItemData);
        return $itemJson;
    }

    private function getLineItems($model) {
        $lineItems = array();
        foreach ($model->getAllVisibleItems() as $key => $val) {
            $lineItems[] = $this->getOrderItemData($val);
        }
        return $lineItems;
    }

    private function getPaymentDetails($model) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();        
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
//        $order = $this->loadOrderByIncId($this->getIncrementOrderId($model));
        $paymentData = array();

        try {
            $payment = $model->getPayment();
            $logHelper->log("Inside Payment 1");

            $paymentData['_payment_type'] = (string) $payment->getMethodInstance()->getTitle();
            $logHelper->log("Inside Payment 2");
            $paymentData['_amount'] = (string) $model->getGrandTotal();
            $logHelper->log("Inside Payment 3");
            $paymentData['_currency_code'] = (string) $model->getOrderCurrencyCode();
            $logHelper->log("Inside Payment 4");
            $paymentData['_payment_gateway'] = (string) $payment->getMethodInstance()->getTitle();
            $logHelper->log("Inside Payment 5");
        } catch (Exception $e) {
            $logHelper->log($e->getMessage());
        }
        $paymentJson = new \ai\thirdwatch\Model\PaymentMethod($paymentData);
        return $paymentJson;
    }

//    private function loadOrderByOrigId($full_orig_id)
//    {
//        if (!$full_orig_id) {
//            return null;
//        }
//
//        $magento_ids = explode("_", $full_orig_id);
//        $order_id = $magento_ids[0];
//        $increment_id = $magento_ids[1];
//
//        if ($order_id && $increment_id) {
//            return Mage::getModel('sales/order')->getCollection()
//                ->addFieldToFilter('entity_id', $order_id)
//                ->addFieldToFilter('increment_id', $increment_id)
//                ->getFirstItem();
//        }
//        return Mage::getModel('sales/order')->load($order_id);
//    }

    private function checkIsPrepaid($order) {
        if ($order->getBaseTotalDue() > 0) {
            return False;
        } else {
            return True;
        }
    }

    private function getOrder($model) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $commonHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Common');
        $logHelper->log("Get Order");
        $orderData = array();

        $customer = $this->_customerSession->getCustomer();
        $customerData = $objectManager->create('Magento\Customer\Model\Customer')->load($customer->getId());
        $SID = $this->_customerSession->getSessionId();
        $logHelper->log("Get Order 2");

        try {
//            $magentoDateObject = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
//            $currentDate = $magentoDateObject->timestamp();
            $remoteAddress = $this->_remoteAddress->getRemoteAddress();
            $logHelper->log("Get Order 3");

            $orderData['_user_id'] = (string) $customerData->getId();
            $orderData['_session_id'] = (string) $SID;
            $orderData['_device_ip'] = (string) $remoteAddress;
            $logHelper->log("Get Order before timezone");
            $orderData['_origin_timestamp'] = (string) (new \DateTime($model->getCreatedAt()))->getTimestamp();
            $logHelper->log("Get Order 4");
            $orderData['_order_id'] = (string) $this->getIncrementOrderId($model);
            $orderData['_user_email'] = (string) $model->getCustomerEmail();
            $orderData['_amount'] = (string) $model->getGrandTotal();
            $logHelper->log("Get Order 5");
            $orderData['_currency_code'] = (string) $model->getOrderCurrencyCode();
            $orderData['_is_pre_paid'] = $this->checkIsPrepaid($model);

            $orderData['_billing_address'] = $commonHelper->getBillingAddress($model->getBillingAddress());
            $logHelper->log("Get Order 6");


            if ($model->getShippingAddress()) {
                $logHelper->log("Get Order 7");
                $orderData['_shipping_address'] = $commonHelper->getShippingAddress($model->getShippingAddress());
                $logHelper->log("Get Order 8");
            } else {
                $logHelper->log("Get Order 9");
                $orderData['_shipping_address'] = $commonHelper->getShippingAddress($model->getBillingAddress());
                $logHelper->log("Get Order 10");
            }
            $logHelper->log("Get Order 11");
            $orderData['_items'] = $this->getLineItems($model);
            $logHelper->log("Get Order 12");
            $orderData['_payment_methods'] = array($this->getPaymentDetails($model));
            $logHelper->log("Get Order 13");
        } catch (Exception $e) {
            $logHelper->log($e->getMessage());
        }
        return $orderData;
    }

    public function createOrder($model) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');

        $secretKey = $dataHelper->getKey();
        $config = \ai\thirdwatch\Configuration::getDefaultConfiguration()->setApiKey(
            'X-THIRDWATCH-API-KEY', $secretKey);

        try {
            $orderData = $this->getOrder($model);
            $apiInstance = new \ai\thirdwatch\Api\CreateOrderApi(new \GuzzleHttp\Client(), $config);
            $body = new \ai\thirdwatch\Model\CreateOrder($orderData);
            $apiInstance->createOrder($body);
        } catch (ApiException $e) {
            $logHelper->log($e);
        }
    }

    private function getOrderStatus($model) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();        
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $orderData = array();
        $customer = $this->_customerSession->getCustomer();
        $customerData = $objectManager->create('Magento\Customer\Model\Customer')
                ->load($customer->getId());
        $SID = $this->_customerSession->getSessionId();

        try {
            $orderData['_user_id'] = (string) $customerData->getId();
            $orderData['_session_id'] = (string) $SID;
            $orderData['_order_id'] = (string) $this->getIncrementOrderId($model);
            $orderData['_order_status'] = (string) $model->getState();
            $orderData['_reason'] = '';
            $orderData['_shipping_cost'] = '';
            $orderData['_tracking_number'] = '';
            $orderData['_tracking_method'] = '';
        } catch (Exception $e) {
            $logHelper->log($e->getMessage());
        }
        return $orderData;
    }

    public function updateOrderStatus($model) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();        
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $logHelper->log("Create Order");
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $thirdwatchKey = $dataHelper->getGeneralConfig('secret_key');
        $config = \ai\thirdwatch\Configuration::getDefaultConfiguration()->setApiKey('X-THIRDWATCH-API-KEY', $thirdwatchKey);

        try {
            $orderData = $this->getOrderStatus($model);
            $api_instance = new \ai\thirdwatch\Api\OrderStatusApi(new \GuzzleHttp\Client(), $config);
            $body = new \ai\thirdwatch\Model\OrderStatus($orderData);
        } catch (Exception $e) {
             $logHelper->log($e);
        }

        try {
            $api_instance->orderStatus($body);
        } catch (Exception $e) {
             $logHelper->log($e->getMessage());
        }
    }

    private function getTransaction($model, $txnType) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $commonHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Common');

        $orderData = array();
        $customer = $this->_customerSession->getCustomer();
        $customerData = $objectManager->create('Magento\Customer\Model\Customer')->load($customer->getId());
        $SID = $this->_customerSession->getSessionId();
        $txnId = '';

        try {
            $payment = $model->getPayment();
            $txnId = $payment->getLastTransId();
        } catch (Exception $e) {
            $logHelper->log($e->getMessage());
        }

        try {
             $remoteAddress = $this->_remoteAddress->getRemoteAddress();
            $orderData['_user_id'] = (string) $customerData->getId();
            $orderData['_session_id'] = (string) $SID;
            $orderData['_device_ip'] = (string) $remoteAddress;
            $orderData['_origin_timestamp'] = (string) (new \DateTime($model->getCreatedAt()))->getTimestamp();
            $orderData['_order_id'] = (string) $this->getIncrementOrderId($model);
            $orderData['_user_email'] = (string) $model->getCustomerEmail();
            $orderData['_amount'] = (string) $model->getGrandTotal();
            $orderData['_currency_code'] = (string) $model->getOrderCurrencyCode();
            $orderData['_billing_address'] = $commonHelper->getBillingAddress($model->getBillingAddress());
            $orderData['_shipping_address'] = $commonHelper->getShippingAddress($model->getShippingAddress());
            $orderData['_items'] = $this->getLineItems($model);
            $orderData['_payment_method'] = $this->getPaymentDetails($model);

            if ($txnId) {
                $orderData['_transaction_id'] = $txnId;
            }

            $orderData['_transaction_type'] = $txnType;
            $orderData['_transaction_status'] = '_success';
        } catch (Exception $e) {
            $logHelper->log($e->getMessage());
        }
        return $orderData;
    }

    public function createTransaction($model, $txnType) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');
        $secretKey = $dataHelper->getKey();
        $config = \ai\thirdwatch\Configuration::getDefaultConfiguration()->setApiKey(
            'X-THIRDWATCH-API-KEY', $secretKey);

        try {
            $orderData = $this->getTransaction($model, $txnType);
            $api_instance = new \ai\thirdwatch\Api\TransactionApi(new \GuzzleHttp\Client(), $config);
            $body = new \ai\thirdwatch\Model\Transaction($orderData);
        } catch (Exception $e) {
            $logHelper->log($e->getMessage());
        }

        try {
            $api_instance->transaction($body);
        } catch (Exception $e) {
            $logHelper->log($e->getMessage());
        }
    }

}