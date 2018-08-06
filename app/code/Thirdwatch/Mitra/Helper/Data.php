<?php

/*
 * To fetch thirdwatch config data and return the value.
 */

namespace Thirdwatch\Mitra\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper {

   public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig) {

        $this->_scopeConfig = $scopeConfig;
    }

    public function getKey() {
        return $this->_scopeConfig->getValue('mitra/general/secret_key', ScopeInterface::SCOPE_STORE);
    }

    public function getStoreURL() {
        return $this->_scopeConfig->getValue('mitra/general/store_url', ScopeInterface::SCOPE_STORE);
    }

    public function isPluginActive()
    {
        return $this->_scopeConfig->getValue('mitra/general/plugin_active', ScopeInterface::SCOPE_STORE);
    }

    public function getOnHoldStatusCode()
    {
        return 'thirdwatch_holded';
    }

    public function getOnHoldStatusLabel()
    {
        return 'Hold (Thirdwatch)';
    }

    public function getThirdwatchDeclinedStatusCode()
    {
        return 'thirdwatch_declined';
    }

    public function getThirdwatchDeclinedStatusLabel()
    {
        return 'Declined (Thirdwatch)';
    }

    public function getThirdwatchApprovedStatusCode()
    {
        return 'thirdwatch_approved';
    }

    public function getThirdwatchApprovedStatusLabel()
    {
        return 'Approved (Thirdwatch)';
    }

    public function getThirdwatchFlaggedStatusCode()
    {
        return 'thirdwatch_flagged';
    }

    public function getThirdwatchFlaggedStatusLabel()
    {
        return 'Flagged (Thirdwatch)';
    }

    public function getPending(){
       return 'PENDING';
    }

    public function getDeclined(){
        return 'DECLINED';
    }

    public function getApproved(){
        return 'APPROVED';
    }

    public function getFlagged(){
        return 'FLAGGED';
    }

    public function getSent(){
        return 'SENT';
    }
}
