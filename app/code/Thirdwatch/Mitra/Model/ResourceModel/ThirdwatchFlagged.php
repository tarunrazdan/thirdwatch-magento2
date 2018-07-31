<?php
/**
 * Created by PhpStorm.
 * User: trazdan
 * Date: 30/07/18
 * Time: 9:58 PM
 */

namespace Thirdwatch\Mitra\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;


class ThirdwatchFlagged extends AbstractDb
{
    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('thirdwatch_orders', 'entity_id');
    }
}