<?php

namespace Thirdwatch\Mitra\Helper;

require_once(BP . '/lib' . DIRECTORY_SEPARATOR . 'thirdwatch-php' . DIRECTORY_SEPARATOR . 'autoload.php');

use ai\thirdwatch\ApiException;
use Magento\Framework\App\Helper\AbstractHelper;


class Login extends AbstractHelper {

    protected $_customerSession;
    protected $_remoteAddress;

    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress){
        $this->_customerSession = $customerSession;
        $this->_remoteAddress = $remoteAddress;
    }

    public function postLogin($customer) {

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
            $customerInfo['_login_status'] = "_success";
        } catch (Exception $e) {
            $logHelper->log($e->getMessage());
        }

        try {
            $apiInstance = new \ai\thirdwatch\Api\LoginApi(new \GuzzleHttp\Client(), $config);
            $body = new \ai\thirdwatch\Model\Login($customerInfo);
            $apiInstance->login($body);
        } catch (ApiException $e) {
            $logHelper->log($e->getMessage());
        }
    }

    public function postLogout($customer) {
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
        } catch (Exception $e) {
            $logHelper->log($e->getMessage());
        }

        try {
            $apiInstance = new \ai\thirdwatch\Api\LogoutApi(new \GuzzleHttp\Client(), $config);
            $body = new \ai\thirdwatch\Model\Logout($customerInfo);
            $apiInstance->logout($body);
        } catch (ApiException $e) {
            $logHelper->log($e->getMessage());
        }
    }
}
