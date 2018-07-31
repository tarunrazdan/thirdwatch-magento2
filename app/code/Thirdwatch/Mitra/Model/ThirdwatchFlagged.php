<?php
/**
 * Created by PhpStorm.
 * User: trazdan
 * Date: 30/07/18
 * Time: 9:56 PM
 */

namespace Thirdwatch\Mitra\Model;

use Magento\Framework\Model\AbstractModel;


class ThirdwatchFlagged extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('Thirdwatch\Mitra\Model\ResourceModel\ThirdwatchFlagged');
    }
}