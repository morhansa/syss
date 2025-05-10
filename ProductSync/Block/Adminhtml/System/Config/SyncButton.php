<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Sync button in system configuration
 */
class SyncButton extends Field
{
    /**
     * @var string
     */
    protected $_template = 'MagoArab_ProductSync::system/config/sync_button.phtml';
    
    /**
     * Remove scope label
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }
    
    /**
     * Return element html
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
    
    /**
     * Return ajax url for sync button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('magoarab_productsync/sync/manual');
    }
    
    /**
     * Generate button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData(
            [
                'id' => 'sync_button',
                'class' => 'primary',
                'label' => __('Sync Now'),
            ]
        );
        
        return $button->toHtml();
    }
}