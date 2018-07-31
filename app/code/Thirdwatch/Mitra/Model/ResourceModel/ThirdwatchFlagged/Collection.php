<?php
/**
 * Created by PhpStorm.
 * User: trazdan
 * Date: 30/07/18
 * Time: 10:06 PM
 */

namespace Thirdwatch\Mitra\Model\ResourceModel\ThirdwatchFlagged;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(
            'Thirdwatch\Mitra\Model\ThirdwatchFlagged',
            'Thirdwatch\Mitra\Model\ResourceModel\ThirdwatchFlagged'
        );
    }
}