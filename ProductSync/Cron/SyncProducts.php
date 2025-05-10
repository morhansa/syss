<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Cron;

use Magento\Framework\Exception\LocalizedException;
use MagoArab\ProductSync\Api\SyncServiceInterface;
use MagoArab\ProductSync\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Cron job for product synchronization
 */
class SyncProducts
{
    /**
     * @var Config
     */
    private $config;
    
    /**
     * @var SyncServiceInterface
     */
    private $syncService;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * SyncProducts constructor.
     *
     * @param Config $config
     * @param SyncServiceInterface $syncService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        SyncServiceInterface $syncService,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->syncService = $syncService;
        $this->logger = $logger;
    }
    
    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isScheduleEnabled()) {
            return;
        }
        
        if ($this->syncService->isSyncInProgress()) {
            $this->logger->info('Product sync is already in progress. Skipping scheduled run.');
            return;
        }
        
        try {
            $this->logger->info('Starting scheduled product sync from Google Sheets.');
            $result = $this->syncService->syncProducts(true);
            
            if ($result) {
                $this->logger->info('Scheduled product sync initiated successfully.');
            } else {
                $this->logger->warning('Failed to initiate scheduled product sync.');
            }
        } catch (LocalizedException $e) {
            $this->logger->error('Error in scheduled product sync: ' . $e->getMessage(), ['exception' => $e]);
        } catch (\Exception $e) {
            $this->logger->critical('Critical error in scheduled product sync: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}