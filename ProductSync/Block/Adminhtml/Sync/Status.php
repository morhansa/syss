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
use MagoArab\ProductSync\Model\ResourceModel\SyncLog\Collection as SyncLogCollection;
use MagoArab\ProductSync\Model\ResourceModel\SyncLog\CollectionFactory as SyncLogCollectionFactory;

/**
 * Sync Status Block
 */
class Status extends Template
{
    /**
     * @var SyncServiceInterface
     */
    private $syncService;
    
    /**
     * @var SyncLogCollectionFactory
     */
    private $syncLogCollectionFactory;
    
    /**
     * Status constructor.
     *
     * @param Context $context
     * @param SyncServiceInterface $syncService
     * @param SyncLogCollectionFactory $syncLogCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        SyncServiceInterface $syncService,
        SyncLogCollectionFactory $syncLogCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->syncService = $syncService;
        $this->syncLogCollectionFactory = $syncLogCollectionFactory;
    }
    
    /**
     * Get latest sync logs
     *
     * @param int $limit
     * @return SyncLogCollection
     */
    public function getLatestSyncLogs(int $limit = 5): SyncLogCollection
    {
        /** @var SyncLogCollection $collection */
        $collection = $this->syncLogCollectionFactory->create();
        $collection->setOrder('started_at', 'DESC');
        $collection->setPageSize($limit);
        
        return $collection;
    }
    
    /**
     * Format date for display
     *
     * @param mixed $date
     * @param int $format
     * @param bool $showTime
     * @param string|null $timezone
     * @return string
     */
    public function formatDate($date = null, $format = \IntlDateFormatter::MEDIUM, $showTime = false, $timezone = null): string
    {
        return parent::formatDate($date, $format, $showTime, $timezone);
    }
    
    /**
     * Get status label
     *
     * @param int $status
     * @return string
     */
    public function getStatusLabel(int $status): string
    {
        $statusLabels = [
            0 => __('Running'),
            1 => __('Completed'),
            2 => __('Failed')
        ];
        
        $label = $statusLabels[$status] ?? __('Unknown');
        // تحويل كائن Phrase إلى نص
        return (string)$label;
    }
    
    /**
     * Get status class
     *
     * @param int $status
     * @return string
     */
    public function getStatusClass(int $status): string
    {
        $statusClasses = [
            0 => 'notice',
            1 => 'success',
            2 => 'error'
        ];
        
        return $statusClasses[$status] ?? '';
    }
    
    /**
     * Get logs view URL
     *
     * @return string
     */
    public function getLogsUrl(): string
    {
        return $this->getUrl('magoarab_productsync/log');
    }
}