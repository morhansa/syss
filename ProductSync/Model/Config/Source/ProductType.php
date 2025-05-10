<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for product types
 */
class ProductType implements OptionSourceInterface
{
    /**
     * Product types
     */
    const TYPE_SIMPLE = 'simple';
    const TYPE_VIRTUAL = 'virtual';
    const TYPE_CONFIGURABLE = 'configurable';
    const TYPE_DOWNLOADABLE = 'downloadable';
    const TYPE_BUNDLE = 'bundle';
    
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::TYPE_SIMPLE, 'label' => __('Simple Product')],
            ['value' => self::TYPE_VIRTUAL, 'label' => __('Virtual Product')],
            ['value' => self::TYPE_CONFIGURABLE, 'label' => __('Configurable Product')],
            ['value' => self::TYPE_DOWNLOADABLE, 'label' => __('Downloadable Product')],
            ['value' => self::TYPE_BUNDLE, 'label' => __('Bundle Product')]
        ];
    }
}