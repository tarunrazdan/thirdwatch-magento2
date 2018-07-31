<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Thirdwatch\Mitra\Ui\Component\Listing\Column\Flag;

use Magento\Framework\Escaper;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class Options
 */
class Options implements OptionSourceInterface
{
    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * Constructor
     *
     * @param Escaper $escaper
     */
    public function __construct(Escaper $escaper)
    {
        $this->escaper = $escaper;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create('Thirdwatch\Mitra\Helper\Data');

        return [
            [
                'value' => $helper->getDeclined(),
                'label' => $this->escaper->escapeHtml(__('Declined'))
            ],
            [
                'value' => $helper->getApproved(),
                'label' => $this->escaper->escapeHtml(__('Approved'))
            ],
            [
                'value' => $helper->getFlagged(),
                'label' => $this->escaper->escapeHtml(__('Flagged'))
            ],
            [
                'value' => $helper->getPending(),
                'label' => $this->escaper->escapeHtml(__('Pending'))
            ]
        ];
    }
}
