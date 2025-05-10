<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use MagoArab\ProductSync\Api\SyncServiceInterface;
use MagoArab\ProductSync\Model\ResourceModel\SyncLog\CollectionFactory as SyncLogCollectionFactory;

/**
 * Sync Status Controller for AJAX requests
 */
class Status extends Action implements HttpGetActionInterface
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'MagoArab_ProductSync::sync';
    
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    
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
     * @param JsonFactory $resultJsonFactory
     * @param SyncServiceInterface $syncService
     * @param SyncLogCollectionFactory $syncLogCollectionFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        SyncServiceInterface $syncService,
        SyncLogCollectionFactory $syncLogCollectionFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->syncService = $syncService;
        $this->syncLogCollectionFactory = $syncLogCollectionFactory;
    }
    
    /**
     * Get sync status action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        
        // Get the latest sync log for progress info
        $syncLogCollection = $this->syncLogCollectionFactory->create();
        $syncLogCollection->setOrder('started_at', 'DESC');
        $syncLogCollection->setPageSize(1);
        $latestLog = $syncLogCollection->getFirstItem();
        
        $progress = $this->syncService->getSyncProgress();
        $inProgress = $this->syncService->isSyncInProgress();
        
        // Use sync log data if available to enhance progress info
        if ($latestLog && $latestLog->getId()) {
            // If we have a sync log and in_progress is true, or log status is running (0)
            if ($inProgress || (int)$latestLog->getStatus() === 0) {
                // Make sure our progress data is up to date with the log
                if ($progress['processed'] === 0 && $latestLog->getProcessedProducts() > 0) {
                    $progress['processed'] = $latestLog->getProcessedProducts();
                }
                
                if ($progress['updated'] === 0 && $latestLog->getUpdatedProducts() > 0) {
                    $progress['updated'] = $latestLog->getUpdatedProducts();
                }
                
                if ($progress['created'] === 0 && $latestLog->getCreatedProducts() > 0) {
                    $progress['created'] = $latestLog->getCreatedProducts();
                }
                
                if ($progress['errors'] === 0 && $latestLog->getErrorCount() > 0) {
                    $progress['errors'] = $latestLog->getErrorCount();
                }
                
                if ($progress['total'] === 0 && $latestLog->getTotalProducts() > 0) {
                    $progress['total'] = $latestLog->getTotalProducts();
                }
                
                // Recalculate percent
                if ($progress['total'] > 0) {
                    $progress['percent'] = round(($progress['processed'] / $progress['total']) * 100);
                }
            }
        }
        
        $data = [
            'in_progress' => $inProgress,
            'total' => $progress['total'],
            'processed' => $progress['processed'],
            'updated' => $progress['updated'],
            'created' => $progress['created'],
            'errors' => $progress['errors'],
            'percent' => $progress['percent']
        ];
        
        return $result->setData($data);
    }
}