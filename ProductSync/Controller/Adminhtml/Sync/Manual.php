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
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use MagoArab\ProductSync\Api\SyncServiceInterface;
use MagoArab\ProductSync\Model\Config;
use MagoArab\ProductSync\Model\Import\SheetReader;
use Psr\Log\LoggerInterface;

/**
 * Controller for manual product synchronization
 */
class Manual extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'MagoArab_ProductSync::sync';
    
    /**
     * Maximum number of products to process in a single request
     */
    const MAX_PRODUCTS_PER_REQUEST = 10;
    
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    
    /**
     * @var SyncServiceInterface
     */
    private $syncService;
    
    /**
     * @var Config
     */
    private $config;
    
    /**
     * @var SheetReader
     */
    private $sheetReader;
    
    /**
     * @var CacheInterface
     */
    private $cache;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * Manual constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param SyncServiceInterface $syncService
     * @param Config $config
     * @param SheetReader $sheetReader
     * @param CacheInterface $cache
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        SyncServiceInterface $syncService,
        Config $config,
        SheetReader $sheetReader,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->syncService = $syncService;
        $this->config = $config;
        $this->sheetReader = $sheetReader;
        $this->cache = $cache;
        $this->logger = $logger;
    }
    

// Fixed version of Manual.php controller's execute method

/**
 * Execute action
 *
 * @return \Magento\Framework\Controller\ResultInterface
 */
public function execute()
{
    $result = $this->resultJsonFactory->create();
    
    if (!$this->config->isEnabled()) {
        return $result->setData([
            'success' => false,
            'message' => __('Product Sync is disabled. Please enable it in the configuration.')
        ]);
    }
    
    // Get the batch number parameter
    $batchNumber = (int)$this->getRequest()->getParam('batch', 0);
    
    // Add better logging
    $this->logger->info('Manual sync requested. Batch number: ' . $batchNumber);
    
    // First batch - start sync
    if ($batchNumber === 0) {
        // Check if sync is already in progress
        if ($this->syncService->isSyncInProgress()) {
            return $result->setData([
                'success' => false,
                'message' => __('Sync is already in progress. Please wait for it to complete.')
            ]);
        }
        
        try {
            // Start a new sync by fetching products
            $this->syncService->setInProgress(true);
            $this->syncService->resetProgress();
            
            $csvUrl = $this->config->getCsvExportUrl();
            $this->logger->info('Reading data from CSV URL: ' . $csvUrl);
            
            $products = $this->sheetReader->readSheet($csvUrl);
            
            if (empty($products)) {
                $this->syncService->setInProgress(false);
                $this->logger->warning('No products found in the Google Sheet.');
                return $result->setData([
                    'success' => false,
                    'message' => __('No products found in the Google Sheet.')
                ]);
            }
            
            $this->logger->info('Found ' . count($products) . ' products in the Google Sheet');
            
            // Store products in cache
            $this->cache->save(
                json_encode($products),
                'magoarab_productsync_products',
                ['MAGOARAB_PRODUCTSYNC'],
                86400
            );
            
            // Set total products count
            $this->syncService->setSyncTotal(count($products));
            
            $totalBatches = ceil(count($products) / self::MAX_PRODUCTS_PER_REQUEST);
            
            // Return success with next batch to process
            return $result->setData([
                'success' => true,
                'message' => __('Starting sync with %1 products in %2 batches', count($products), $totalBatches),
                'next_batch' => 1,
                'total_batches' => $totalBatches,
                'total_products' => count($products),
                'continue' => true
            ]);
        } catch (\Exception $e) {
            $this->syncService->setInProgress(false);
            $this->logger->critical('Error initiating manual sync: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $result->setData([
                'success' => false,
                'message' => __('An error occurred: %1', $e->getMessage())
            ]);
        }
    } else {
        // Processing a batch
        try {
            // Get stored products
            $storedProductsJson = $this->cache->load('magoarab_productsync_products');
            if (!$storedProductsJson) {
                $this->syncService->setInProgress(false);
                return $result->setData([
                    'success' => false,
                    'message' => __('Product data not found. Please restart the sync.')
                ]);
            }
            
            $allProducts = json_decode($storedProductsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->critical('JSON decode error: ' . json_last_error_msg());
                $this->syncService->setInProgress(false);
                return $result->setData([
                    'success' => false,
                    'message' => __('Error decoding product data. Please restart the sync.')
                ]);
            }
            
            $totalProducts = count($allProducts);
            $totalBatches = ceil($totalProducts / self::MAX_PRODUCTS_PER_REQUEST);
            
            // Get current batch of products
            $startIndex = ($batchNumber - 1) * self::MAX_PRODUCTS_PER_REQUEST;
            $batchProducts = array_slice($allProducts, $startIndex, self::MAX_PRODUCTS_PER_REQUEST);
            
            // Add more logging
            $this->logger->info(sprintf(
                'Processing batch %d of %d with %d products',
                $batchNumber,
                $totalBatches,
                count($batchProducts)
            ));
            
            // Process current batch
            $batchResult = $this->syncService->processProductBatch($batchProducts);
            
            // Log the results
            $this->logger->info(sprintf(
                'Batch %d results: Updated: %d, Created: %d, Errors: %d',
                $batchNumber,
                $batchResult['updated'],
                $batchResult['created'],
                $batchResult['errors']
            ));
            
            // Update progress
            $processed = min($batchNumber * self::MAX_PRODUCTS_PER_REQUEST, $totalProducts);
            $this->syncService->updateProgress(
                $processed,
                $totalProducts,
                $batchResult['updated'],
                $batchResult['created'],
                $batchResult['errors']
            );
            
            // Determine if there are more batches to process
            $nextBatch = $batchNumber + 1;
            $continue = $nextBatch <= $totalBatches;
            
            // If this was the last batch, mark sync as complete
            if (!$continue) {
                $this->syncService->setInProgress(false);
                $this->syncService->updateLastSyncTime();
                
                // Clean up stored products
                $this->cache->remove('magoarab_productsync_products');
            }
            
            $progress = $this->syncService->getSyncProgress();
            
            // Return result with progress information
            return $result->setData([
                'success' => true,
                'message' => $continue ? 
                    __('Processed batch %1 of %2', $batchNumber, $totalBatches) : 
                    __('Sync completed successfully.'),
                'next_batch' => $nextBatch,
                'total_batches' => $totalBatches,
                'progress' => $progress,
                'continue' => $continue
            ]);
        } catch (\Exception $e) {
            $this->logger->critical('Error processing batch ' . $batchNumber . ': ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $result->setData([
                'success' => false,
                'message' => __('Error processing batch %1: %2', $batchNumber, $e->getMessage())
            ]);
        }
    }
}
}