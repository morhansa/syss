<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Block\Adminhtml\Sync;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use MagoArab\ProductSync\Api\SyncServiceInterface;
use MagoArab\ProductSync\Helper\Data as Helper;

/**
 * Sync Progress Block
 */
class Progress extends Template
{
    /**
     * @var SyncServiceInterface
     */
    private $syncService;
    
    /**
     * @var Helper
     */
    private $helper;
    
    /**
     * Progress constructor.
     *
     * @param Context $context
     * @param SyncServiceInterface $syncService
     * @param Helper $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        SyncServiceInterface $syncService,
        Helper $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->syncService = $syncService;
        $this->helper = $helper;
    }
    
    /**
     * Check if module is enabled
     *
     * @return bool
     */
    public function isModuleEnabled(): bool
    {
        return $this->helper->isEnabled();
    }
    
    /**
     * Get sync progress data
     *
     * @return array
     */
    public function getSyncProgress(): array
    {
        return $this->syncService->getSyncProgress();
    }
    
    /**
     * Check if sync is in progress
     *
     * @return bool
     */
    public function isSyncInProgress(): bool
    {
        return $this->syncService->isSyncInProgress();
    }
    
    /**
     * Get next run time display
     *
     * @return string
     */
    public function getNextRunTimeDisplay(): string
    {
        return $this->helper->getNextRunTimeDisplay();
    }
    
    /**
     * Get status check URL
     *
     * @return string
     */
    public function getStatusUrl(): string
    {
        return $this->getUrl('magoarab_productsync/sync/status');
    }
    
    /**
     * Get manual sync URL
     *
     * @return string
     */
    public function getManualSyncUrl(): string
    {
        return $this->getUrl('magoarab_productsync/sync/manual');
    }
    
    /**
     * Get stop sync URL
     *
     * @return string
     */
    public function getStopSyncUrl(): string
    {
        return $this->getUrl('magoarab_productsync/sync/stop');
    }
}