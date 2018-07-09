<?php

namespace Thirdwatch\Mitra\Helper;
require_once(BP . '/lib' . DIRECTORY_SEPARATOR . 'thirdwatch-php' . DIRECTORY_SEPARATOR . 'autoload.php');

use ai\thirdwatch\ApiException;
use Magento\Framework\App\Helper\AbstractHelper;

class Register extends AbstractHelper {

    protected $_customerSession;
    protected $_remoteAddress;

    public function __construct(
    \Magento\Customer\Model\Session $customerSession, \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
    ) {
        $this->_customerSession = $customerSession;
        $this->_remoteAddress = $remoteAddress;
    }

    public function postRegister($customer) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');

        $secretKey = $dataHelper->getKey();
        $config = \ai\thirdwatch\Configuration::getDefaultConfiguration()->setApiKey(
            'X-THIRDWATCH-API-KEY', $secretKey);

        $customerData = $objectManager->create('Magento\Customer\Model\Customer')->load($customer->getId());
        $customerInfo = array();

        try {
            $SID = $this->_customerSession->getSessionId();
            $magentoDateObject = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
            $currentDate = $magentoDateObject->timestamp();
            $remoteAddress = $this->_remoteAddress->getRemoteAddress();

            $customerInfo['_user_id'] = (string) $customerData->getId();
            $customerInfo['_session_id'] = (string) $SID;
            $customerInfo['_device_ip'] = (string) $remoteAddress;
            $customerInfo['_origin_timestamp'] = (string) $currentDate . '000';
            $customerInfo['_user_email'] = (string) $customerData->getEmail();
            $customerInfo['_first_name'] = (string) $customerData->getFirstname();
            $customerInfo['_last_name'] = (string) $customerData->getLastname();

            if ($customerData->getPrimaryBillingAddress()) {
                $customerInfo['_phone'] = (string) $customerData->getPrimaryBillingAddress()->getTelephone();
            }

            $isActive = $customerData->getIsActive();
            if ($isActive) {
                $customerInfo['_account_status'] = '_active';
            } else {
                $customerInfo['_account_status'] = '_inactive';
            }
        } catch (\Exception $e) {
            $logHelper->log("tw-debug: ".$e->getMessage(), "debug");
        }

        try {
            $apiInstance = new \ai\thirdwatch\Api\CreateAccountApi(new \GuzzleHttp\Client(), $config);
            $body = new \ai\thirdwatch\Model\CreateAccount($customerInfo);
            $apiInstance->createAccount($body);
        } catch (ApiException $e) {
            $logHelper->log("tw-debug: ".$e->getMessage());
        }
    }

    public function postCustomerUpdate($customer) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Log');
        $dataHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');

        $secretKey = $dataHelper->getKey();
        $config = \ai\thirdwatch\Configuration::getDefaultConfiguration()->setApiKey(
            'X-THIRDWATCH-API-KEY', $secretKey);

        $customerInfo = array();
        $customerData = $objectManager->create('Magento\Customer\Model\Customer')->load($customer->getId());

        try {
            $SID = $this->_customerSession->getSessionId();
            $magentoDateObject = $objectManager->create('Magento\Framework\Stdlib\DateTime\DateTime');
            $currentDate = $magentoDateObject->timestamp();
            $remoteAddress = $this->_remoteAddress->getRemoteAddress();

            $customerInfo['_user_id'] = (string) $customerData->getId();
            $customerInfo['_session_id'] = (string) $SID;
            $customerInfo['_device_ip'] = (string) $remoteAddress;
            $customerInfo['_origin_timestamp'] = (string) $currentDate . '000';
            $customerInfo['_user_email'] = (string) $customerData->getEmail();
            $customerInfo['_first_name'] = (string) $customerData->getFirstname();
            $customerInfo['_last_name'] = (string) $customerData->getLastname();

            if ($customerData->getPrimaryBillingAddress()) {
                $customerInfo['_phone'] = (string) $customerData->getPrimaryBillingAddress()->getTelephone();
            }

            $isActive = $customerData->getIsActive();
            if ($isActive) {
                $customerInfo['_account_status'] = '_active';
            } else {
                $customerInfo['_account_status'] = '_inactive';
            }

            $comonHelper = $objectManager->create('Thirdwatch\Mitra\Helper\Common');
            $customerInfo['_billing_address'] = $comonHelper->getBillingAddress($customerData->getPrimaryBillingAddress());
            $customerInfo['_shipping_address'] = $comonHelper->getShippingAddress($customerData->getPrimaryShippingAddress());
        } catch (\Exception $e) {
            $logHelper->log("tw-debug: ".$e->getMessage(), "debug");
        }

        try {
            $apiInstance = new \ai\thirdwatch\Api\UpdateAccountApi(new \GuzzleHttp\Client(), $config);
            $body = new \ai\thirdwatch\Model\UpdateAccount($customerInfo);
            $apiInstance->UpdateAccount($body);
        } catch (ApiException $e) {
            $logHelper->log("tw-debug: ".$e->getMessage(), "debug");
        }
    }
}