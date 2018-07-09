<?php

namespace Thirdwatch\Mitra\Helper;
require_once(BP . '/lib' . DIRECTORY_SEPARATOR . 'thirdwatch-php' . DIRECTORY_SEPARATOR . 'autoload.php');

use Magento\Framework\App\Helper\AbstractHelper;

class Status extends AbstractHelper {

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
}