<?php

namespace Thirdwatch\Mitra\Setup;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;


class InstallData implements InstallDataInterface
{

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');

        $statusDeclined = $objectManager->create('Magento\Sales\Model\Order\Status');
        $statusDeclined->setData('status', $helper->getThirdwatchDeclinedStatusCode())->setData('label', $helper->getThirdwatchDeclinedStatusLabel())->save();
        $statusDeclined->assignState(\Magento\Sales\Model\Order::STATE_CANCELED, true, true);

        $statusDeclined = $objectManager->create('Magento\Sales\Model\Order\Status');
        $statusDeclined->setData('status', $helper->getThirdwatchApprovedStatusCode())->setData('label', $helper->getThirdwatchApprovedStatusLabel())->save();
        $statusDeclined->assignState(\Magento\Sales\Model\Order::STATE_PROCESSING, true, true);

        $statusDeclined = $objectManager->create('Magento\Sales\Model\Order\Status');
        $statusDeclined->setData('status', $helper->getOnHoldStatusCode())->setData('label', $helper->getOnHoldStatusLabel())->save();
        $statusDeclined->assignState(\Magento\Sales\Model\Order::STATE_HOLDED, true, true);

        $statusDeclined = $objectManager->create('Magento\Sales\Model\Order\Status');
        $statusDeclined->setData('status', $helper->getThirdwatchFlaggedStatusCode())->setData('label', $helper->getThirdwatchFlaggedStatusLabel())->save();
        $statusDeclined->assignState(\Magento\Sales\Model\Order::STATE_HOLDED, true, true);

        $setup->endSetup();
    }
}