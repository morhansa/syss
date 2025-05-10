<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Model;

use Exception;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use MagoArab\ProductSync\Api\SyncServiceInterface;
use MagoArab\ProductSync\Api\Data\SyncLogInterface;
use MagoArab\ProductSync\Model\Config;
use MagoArab\ProductSync\Model\Import\SheetReader;
use MagoArab\ProductSync\Model\Import\ProductProcessor;
use MagoArab\ProductSync\Model\SyncLogFactory;
use MagoArab\ProductSync\Model\ResourceModel\SyncLog\CollectionFactory as SyncLogCollectionFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Product synchronization service implementation
 */
class SyncService implements SyncServiceInterface
{
    private const SYNC_STATUS_KEY = 'magoarab_productsync_status';
    private const SYNC_PROGRESS_KEY = 'magoarab_productsync_progress';
    private const SYNC_TOPIC_NAME = 'magoarab.productsync.product';
    private const SYNC_LOG_ID_KEY = 'magoarab_productsync_log_id';
    
    /**
     * @var Config
     */
    private $config;
    
    /**
     * @var SheetReader
     */
    private $sheetReader;
    
    /**
     * @var ProductProcessor
     */
    private $productProcessor;
    
    /**
     * @var PublisherInterface
     */
    private $publisher;
    
    /**
     * @var Json
     */
    private $json;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * @var State
     */
    private $appState;
    
    /**
     * @var DateTime
     */
    private $dateTime;
    
    /**
     * @var SyncLogFactory
     */
    private $syncLogFactory;
    
    /**
     * @var SyncLogCollectionFactory
     */
    private $syncLogCollectionFactory;
    
    /**
     * @param Config $config
     * @param SheetReader $sheetReader
     * @param ProductProcessor $productProcessor
     * @param PublisherInterface $publisher
     * @param Json $json
     * @param LoggerInterface $logger
     * @param State $appState
     * @param DateTime $dateTime
     * @param SyncLogFactory $syncLogFactory
     * @param SyncLogCollectionFactory $syncLogCollectionFactory
     */
    public function __construct(
        Config $config,
        SheetReader $sheetReader,
        ProductProcessor $productProcessor,
        PublisherInterface $publisher,
        Json $json,
        LoggerInterface $logger,
        State $appState,
        DateTime $dateTime,
        SyncLogFactory $syncLogFactory,
        SyncLogCollectionFactory $syncLogCollectionFactory
    ) {
        $this->config = $config;
        $this->sheetReader = $sheetReader;
        $this->productProcessor = $productProcessor;
        $this->publisher = $publisher;
        $this->json = $json;
        $this->logger = $logger;
        $this->appState = $appState;
        $this->dateTime = $dateTime;
        $this->syncLogFactory = $syncLogFactory;
        $this->syncLogCollectionFactory = $syncLogCollectionFactory;
    }
    
    /**
     * @inheritdoc
     */
    public function syncProducts(bool $async = true): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }
        
        if ($this->isSyncInProgress()) {
            return false;
        }
        
        try {
            $this->setInProgress(true);
            $this->resetProgress();
            
            $csvUrl = $this->config->getCsvExportUrl();
            $products = $this->sheetReader->readSheet($csvUrl);
            
            if (empty($products)) {
                $this->logger->warning('No products found in the Google Sheet.');
                $this->setInProgress(false);
                return false;
            }
            
            // Create a new sync log entry
            $syncLog = $this->createSyncLogEntry(count($products));
            
            $this->setSyncTotal(count($products));
            
            if ($async) {
                $this->processProductsAsync($products);
            } else {
                $this->processProductsSync($products);
            }
            
            $this->updateLastSyncTime();
            
            return true;
        } catch (\Exception $e) {
            $this->logger->critical('Product sync error: ' . $e->getMessage(), ['exception' => $e]);
            $this->setInProgress(false);
            
            // Update sync log with error
            $this->completeSyncLogEntry(false, $e->getMessage());
            
            throw $e;
        }
    }
    
    /**
     * Process products synchronously
     *
     * @param array $products
     * @return void
     */
    private function processProductsSync(array $products): void
    {
        try {
            $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($products) {
                $total = count($products);
                $processed = 0;
                $totalUpdated = 0;
                $totalCreated = 0;
                $totalErrors = 0;
                $batchSize = $this->config->getBatchSize();
                
                foreach (array_chunk($products, $batchSize) as $batch) {
                    $result = $this->productProcessor->processProductBatch($batch);
                    $processed += count($batch);
                    $totalUpdated += $result['updated'];
                    $totalCreated += $result['created'];
                    $totalErrors += $result['errors'];
                    
                    $this->updateProgress($processed, $total, $totalUpdated, $totalCreated, $totalErrors);
                    
                    // Update sync log with progress
                    $this->updateSyncLogEntry($processed, $totalUpdated, $totalCreated, $totalErrors);
                    
                    $this->logger->info("Batch processed: {$result['updated']} updated, {$result['created']} created, {$result['errors']} errors");
                }
                
                $this->setInProgress(false);
                
                // Complete sync log
                $this->completeSyncLogEntry(true);
            });
        } catch (\Exception $e) {
            $this->logger->critical('Error processing products: ' . $e->getMessage(), ['exception' => $e]);
            $this->setInProgress(false);
            
            // Update sync log with error
            $this->completeSyncLogEntry(false, $e->getMessage());
            
            throw $e;
        }
    }
    
    /**
     * Process products asynchronously
     *
     * @param array $products
     * @return void
     */
    private function processProductsAsync(array $products): void
    {
        try {
            $batchSize = $this->config->getBatchSize();
            $batchCount = 0;
            $totalBatches = ceil(count($products) / $batchSize);
            
            foreach (array_chunk($products, $batchSize) as $batchNumber => $batch) {
                $batchCount++;
                $data = [
                    'products' => $batch,
                    'batch_number' => $batchNumber + 1,
                    'total_batches' => $totalBatches
                ];
                
                // Convert data to JSON string for message queue
                $serializedData = $this->json->serialize($data);
                
                // Publish message to queue
                $this->publisher->publish(self::SYNC_TOPIC_NAME, $serializedData);
                
                $this->logger->info(sprintf(
                    'Scheduled batch %d of %d with %d products',
                    $batchCount,
                    $totalBatches,
                    count($batch)
                ));
            }
            
            $this->logger->info(sprintf(
                'Successfully scheduled %d batches for processing',
                $batchCount
            ));
        } catch (\Exception $e) {
            $this->logger->error('Failed to schedule product sync: ' . $e->getMessage(), ['exception' => $e]);
            $this->setInProgress(false);
            
            // Update sync log with error
            $this->completeSyncLogEntry(false, $e->getMessage());
            
            throw $e;
        }
    }
    
    /**
     * @inheritdoc
     */
    public function getSyncProgress(): array
    {
        $cache = $this->getCacheInstance();
        $progress = $cache->load(self::SYNC_PROGRESS_KEY);
        
        if (!$progress) {
            return [
                'total' => 0,
                'processed' => 0,
                'updated' => 0,
                'created' => 0,
                'errors' => 0,
                'percent' => 0
            ];
        }
        
        return $this->json->unserialize($progress);
    }
    
    /**
     * @inheritdoc
     */
    public function isSyncInProgress(): bool
    {
        $cache = $this->getCacheInstance();
        $status = $cache->load(self::SYNC_STATUS_KEY);
        
        return $status === 'true';
    }
    
    /**
     * Set sync in progress status
     *
     * @param bool $inProgress
     * @return void
     */
    public function setInProgress(bool $inProgress): void
    {
        $cache = $this->getCacheInstance();
        $cache->save(
            $inProgress ? 'true' : 'false',
            self::SYNC_STATUS_KEY,
            ['MAGOARAB_PRODUCTSYNC'],
            86400
        );
    }
    
    /**
     * Reset sync progress
     *
     * @return void
     */
    public function resetProgress(): void
    {
        $progress = [
            'total' => 0,
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => 0,
            'percent' => 0
        ];
        
        $cache = $this->getCacheInstance();
        $cache->save(
            $this->json->serialize($progress),
            self::SYNC_PROGRESS_KEY,
            ['MAGOARAB_PRODUCTSYNC'],
            86400
        );
    }
    
    /**
     * Set sync total
     *
     * @param int $total
     * @return void
     */
    public function setSyncTotal(int $total): void
    {
        $progress = $this->getSyncProgress();
        $progress['total'] = $total;
        
        $cache = $this->getCacheInstance();
        $cache->save(
            $this->json->serialize($progress),
            self::SYNC_PROGRESS_KEY,
            ['MAGOARAB_PRODUCTSYNC'],
            86400
        );
    }
    
    /**
     * Update sync progress
     *
     * @param int $processed
     * @param int $total
     * @param int $updated
     * @param int $created
     * @param int $errors
     * @return void
     */
    public function updateProgress(
        int $processed,
        int $total,
        int $updated,
        int $created,
        int $errors
    ): void {
        $progress = $this->getSyncProgress();
        
        // Update values
        $progress['processed'] = $processed;
        $progress['updated'] = $updated;
        $progress['created'] = $created;
        $progress['errors'] = $errors;
        $progress['percent'] = ($total > 0) ? round(($processed / $total) * 100) : 0;
        
        $cache = $this->getCacheInstance();
        $cache->save(
            $this->json->serialize($progress),
            self::SYNC_PROGRESS_KEY,
            ['MAGOARAB_PRODUCTSYNC'],
            86400
        );
        
        // If all products processed, mark sync as complete
        if ($progress['processed'] >= $progress['total']) {
            $this->setInProgress(false);
            $this->completeSyncLogEntry(true);
        }
    }
    
    /**
     * Update last sync time
     *
     * @return void
     */
    public function updateLastSyncTime(): void
    {
        $timestamp = $this->dateTime->gmtDate('Y-m-d H:i:s');
        $this->config->setLastSyncTime($timestamp);
    }
    
    /**
     * Process a batch of products
     *
     * @param array $products
     * @return array
     */
    public function processProductBatch(array $products): array
    {
        try {
            // Add logging to track what's being processed
            $this->logger->info('Processing product batch with ' . count($products) . ' products');
            
            // Convert product data to the correct format if needed
            $formattedProducts = $this->prepareProductData($products);
            
            // Process the products with error handling
            $result = $this->productProcessor->processProductBatch($formattedProducts);
            
            // Log the results
            $this->logger->info('Batch processed: ' . json_encode($result));
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->critical('Error processing product batch: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'updated' => 0,
                'created' => 0,
                'errors' => count($products)
            ];
        }
    }
    
    /**
     * Prepare product data for import
     *
     * @param array $products
     * @return array
     */
    private function prepareProductData(array $products): array
    {
        $formattedProducts = [];
        
        foreach ($products as $product) {
            // Ensure all required fields are present
            if (empty($product['sku'])) {
                $this->logger->warning('Skipping product without SKU');
                continue;
            }
            
            // Convert data types if needed
            $formattedProduct = [
                'sku' => trim($product['sku']),
                'name' => isset($product['name']) ? trim($product['name']) : null,
                'price' => isset($product['price']) && is_numeric($product['price']) ? (float)$product['price'] : null,
                'qty' => isset($product['qty']) && is_numeric($product['qty']) ? (float)$product['qty'] : 0,
                'description' => isset($product['description']) ? trim($product['description']) : null
            ];
            
            $formattedProducts[] = $formattedProduct;
        }
        
        return $formattedProducts;
    }
    
    /**
     * Create sync log entry
     *
     * @param int $totalProducts
     * @return SyncLogInterface
     */
    private function createSyncLogEntry(int $totalProducts): SyncLogInterface
    {
        $syncLog = $this->syncLogFactory->create();
        $syncLog->createSyncLog($totalProducts);
        
        // Store log ID in cache for later updates
        $this->getCacheInstance()->save(
            (string)$syncLog->getId(),
            self::SYNC_LOG_ID_KEY,
            ['MAGOARAB_PRODUCTSYNC'],
            86400
        );
        
        $this->logger->info('Created sync log entry with ID: ' . $syncLog->getId());
        
        return $syncLog;
    }
    
    /**
     * Update sync log entry
     *
     * @param int $processed
     * @param int $updated
     * @param int $created
     * @param int $errors
     * @return void
     */
    private function updateSyncLogEntry(int $processed, int $updated, int $created, int $errors): void
    {
        $logId = $this->getCacheInstance()->load(self::SYNC_LOG_ID_KEY);
        
        if (!$logId) {
            $this->logger->warning('No sync log ID found in cache for updating');
            return;
        }
        
        try {
            $syncLog = $this->syncLogFactory->create();
            $syncLog->load($logId);
            
            if (!$syncLog->getId()) {
                $this->logger->warning('Sync log not found with ID: ' . $logId);
                return;
            }
            
            $syncLog->updateSyncLog($processed, $updated, $created, $errors);
            $this->logger->debug('Updated sync log entry with ID: ' . $logId);
        } catch (\Exception $e) {
            $this->logger->error('Error updating sync log: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
    
    /**
     * Complete sync log entry
     *
     * @param bool $isSuccess
     * @param string|null $errorMessage
     * @return void
     */
    private function completeSyncLogEntry(bool $isSuccess, ?string $errorMessage = null): void
    {
        $logId = $this->getCacheInstance()->load(self::SYNC_LOG_ID_KEY);
        
        if (!$logId) {
            $this->logger->warning('No sync log ID found in cache for completion');
            return;
        }
        
        try {
            $syncLog = $this->syncLogFactory->create();
            $syncLog->load($logId);
            
            if (!$syncLog->getId()) {
                $this->logger->warning('Sync log not found with ID: ' . $logId);
                return;
            }
            
            $syncLog->completeSyncLog($isSuccess, $errorMessage);
            $this->logger->info('Completed sync log entry with ID: ' . $logId . ', success: ' . ($isSuccess ? 'yes' : 'no'));
            
            // Remove log ID from cache
            $this->getCacheInstance()->remove(self::SYNC_LOG_ID_KEY);
        } catch (\Exception $e) {
            $this->logger->error('Error completing sync log: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
    
    /**
     * Get cache instance
     *
     * @return \Magento\Framework\App\CacheInterface
     */
    private function getCacheInstance()
    {
        return \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\App\CacheInterface::class);
    }
}