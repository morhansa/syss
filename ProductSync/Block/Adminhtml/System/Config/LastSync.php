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
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Last Sync time display in system configuration
 */
class LastSync extends Field
{
    /**
     * @var TimezoneInterface
     */
    private $timezone;
    
    /**
     * LastSync constructor.
     *
     * @param Context $context
     * @param TimezoneInterface $timezone
     * @param array $data
     */
    public function __construct(
        Context $context,
        TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->timezone = $timezone;
    }
    
    /**
     * Render the field
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $lastSyncTime = $this->_scopeConfig->getValue(
            'magoarab_productsync/general/last_sync',
            ScopeInterface::SCOPE_STORE
        );
        
        if (empty($lastSyncTime)) {
            return '<span>' . __('Never') . '</span>';
        }
        
        try {
            $formattedDate = $this->timezone->formatDateTime(
                $lastSyncTime,
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::MEDIUM
            );
            
            return '<span>' . $formattedDate . '</span>';
        } catch (\Exception $e) {
            return '<span>' . $lastSyncTime . '</span>';
        }
    }
}