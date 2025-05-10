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
use Psr\Log\LoggerInterface;

/**
 * Controller for stopping product synchronization
 */
class Stop extends Action
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
     * @var CacheInterface
     */
    private $cache;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * Stop constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CacheInterface $cache
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        
        try {
            // Reset sync status to not in progress
            $this->cache->save(
                'false',
                'magoarab_productsync_status',
                ['MAGOARAB_PRODUCTSYNC'],
                86400
            );
            
            $this->logger->info('Sync process was manually stopped by admin.');
            
            return $result->setData([
                'success' => true,
                'message' => __('Synchronization process has been stopped.')
            ]);
        } catch (\Exception $e) {
            $this->logger->critical('Error stopping sync: ' . $e->getMessage(), ['exception' => $e]);
            
            return $result->setData([
                'success' => false,
                'message' => __('An error occurred while stopping sync: %1', $e->getMessage())
            ]);
        }
    }
}