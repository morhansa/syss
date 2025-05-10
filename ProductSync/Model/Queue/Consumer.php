<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Model\Queue;

use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Serialize\Serializer\Json;
use MagoArab\ProductSync\Model\SyncService;
use MagoArab\ProductSync\Model\Import\ProductProcessor;
use Psr\Log\LoggerInterface;

/**
 * Queue consumer for product synchronization
 */
class Consumer
{
    /**
     * @var State
     */
    private $appState;
    
    /**
     * @var ProductProcessor
     */
    private $productProcessor;
    
    /**
     * @var Json
     */
    private $json;
    
    /**
     * @var SyncService
     */
    private $syncService;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * Consumer constructor.
     *
     * @param State $appState
     * @param ProductProcessor $productProcessor
     * @param Json $json
     * @param SyncService $syncService
     * @param LoggerInterface $logger
     */
    public function __construct(
        State $appState,
        ProductProcessor $productProcessor,
        Json $json,
        SyncService $syncService,
        LoggerInterface $logger
    ) {
        $this->appState = $appState;
        $this->productProcessor = $productProcessor;
        $this->json = $json;
        $this->syncService = $syncService;
        $this->logger = $logger;
    }
    
    /**
     * Process the message from the queue
     *
     * @param string $message
     * @return void
     */
    public function process(string $message): void
    {
        try {
            $this->logger->info('Processing message from queue');
            $data = $this->json->unserialize($message);
            
            if (!isset($data['products']) || empty($data['products'])) {
                $this->logger->warning('No products found in the queue message.');
                return;
            }
            
            $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($data) {
                $products = $data['products'];
                $batchNumber = $data['batch_number'] ?? 1;
                $totalBatches = $data['total_batches'] ?? 1;
                
                $this->logger->info(sprintf(
                    'Processing product batch %d of %d with %d products',
                    $batchNumber,
                    $totalBatches,
                    count($products)
                ));
                
                $result = $this->productProcessor->processProductBatch($products);
                
                // Update sync progress
                $processedCount = $batchNumber * count($products);
                $totalCount = $totalBatches * count($products);
                
                $this->syncService->updateProgress(
                    $processedCount,
                    $totalCount,
                    $result['updated'],
                    $result['created'],
                    $result['errors']
                );
                
                $this->logger->info(sprintf(
                    'Batch %d/%d processed: %d updated, %d created, %d errors',
                    $batchNumber,
                    $totalBatches,
                    $result['updated'],
                    $result['created'],
                    $result['errors']
                ));
            });
        } catch (\Exception $e) {
            $this->logger->critical('Error processing product sync queue message: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }
}