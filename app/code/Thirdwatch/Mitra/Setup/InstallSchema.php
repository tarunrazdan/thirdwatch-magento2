<?php
/**
 * Created by PhpStorm.
 * User: trazdan
 * Date: 29/07/18
 * Time: 7:08 PM
 */

namespace Thirdwatch\Mitra\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;


class InstallSchema implements InstallSchemaInterface
{
    /**
     * @var string
     */
    private static $table = 'thirdwatch_orders';

    /**
     * @var string
     */
    private static $connectionName = 'sales';

    /**
     * @inheritdoc
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        /** @var AdapterInterface $connection */
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');

        $connection = $setup->startSetup()->getConnection(self::$connectionName);

        $table = $connection->newTable($setup->getTable(static::$table));
        $table->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true]
        );
        $table->addColumn('order_id', Table::TYPE_INTEGER, null, ['unsigned' => true]);
        $table->addColumn('order_increment_id', Table::TYPE_TEXT, 32);
        $table->addColumn('status', Table::TYPE_TEXT, 32, ['default' => $helper->getPending()]);
        $table->addColumn('flag', Table::TYPE_TEXT, 32);
        $table->addColumn('action', Table::TYPE_TEXT, 32);
        $table->addColumn('reasons', Table::TYPE_TEXT, 3072);
        $table->addColumn('score', Table::TYPE_INTEGER, null, ['unsigned' => true]);
        $table->addColumn('created_at', Table::TYPE_TIMESTAMP);
        $table->addColumn('updated_at', Table::TYPE_TIMESTAMP);

        $table->addIndex(
            $setup->getIdxName(
                $setup->getTable(static::$table),
                'order_id',
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            'order_id',
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        );

        $table->addForeignKey(
            $setup->getFkName(
                $setup->getTable(static::$table),
                'order_id',
                $setup->getTable('sales_order'),
                'entity_id'
            ),
            'order_id',
            $setup->getTable('sales_order'),
            'entity_id',
            Table::ACTION_SET_NULL
        );

        $connection->createTable($table);

        $connection->addColumn(
            $setup->getTable('sales_order_grid'),
            'thirdwatch_flag_status',
            [
                'type' => Table::TYPE_TEXT,
                'length' => 32,
                'comment' => 'Thirdwatch Flag Status'
            ]
        );
    }

}