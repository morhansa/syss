<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\DateTime;
use MagoArab\ProductSync\Api\Data\SyncLogInterface;
use MagoArab\ProductSync\Model\ResourceModel\SyncLog as SyncLogResource;

/**
 * Sync Log Model
 */
class SyncLog extends AbstractModel implements SyncLogInterface
{
    /**
     * @var DateTime
     */
    private $dateTime;
    
    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param DateTime $dateTime
     * @param array $data
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        DateTime $dateTime,
        array $data = [],
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null
    ) {
        $this->dateTime = $dateTime;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }
    
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(SyncLogResource::class);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getLogId()
    {
        return $this->getData(self::LOG_ID);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setLogId($logId)
    {
        return $this->setData(self::LOG_ID, $logId);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getStartedAt()
    {
        return $this->getData(self::STARTED_AT);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setStartedAt($startedAt)
    {
        return $this->setData(self::STARTED_AT, $startedAt);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getFinishedAt()
    {
        return $this->getData(self::FINISHED_AT);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setFinishedAt($finishedAt)
    {
        return $this->setData(self::FINISHED_AT, $finishedAt);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        return (int)$this->getData(self::STATUS);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getTotalProducts()
    {
        return (int)$this->getData(self::TOTAL_PRODUCTS);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setTotalProducts($totalProducts)
    {
        return $this->setData(self::TOTAL_PRODUCTS, $totalProducts);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getProcessedProducts()
    {
        return (int)$this->getData(self::PROCESSED_PRODUCTS);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setProcessedProducts($processedProducts)
    {
        return $this->setData(self::PROCESSED_PRODUCTS, $processedProducts);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getUpdatedProducts()
    {
        return (int)$this->getData(self::UPDATED_PRODUCTS);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setUpdatedProducts($updatedProducts)
    {
        return $this->setData(self::UPDATED_PRODUCTS, $updatedProducts);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCreatedProducts()
    {
        return (int)$this->getData(self::CREATED_PRODUCTS);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setCreatedProducts($createdProducts)
    {
        return $this->setData(self::CREATED_PRODUCTS, $createdProducts);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getErrorCount()
    {
        return (int)$this->getData(self::ERROR_COUNT);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setErrorCount($errorCount)
    {
        return $this->setData(self::ERROR_COUNT, $errorCount);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getErrorMessage()
    {
        return $this->getData(self::ERROR_MESSAGE);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setErrorMessage($errorMessage)
    {
        return $this->setData(self::ERROR_MESSAGE, $errorMessage);
    }
    
    /**
     * Create a new sync log entry
     *
     * @param int $totalProducts
     * @return $this
     */
    public function createSyncLog(int $totalProducts)
    {
        $this->setStartedAt($this->dateTime->gmtDate('Y-m-d H:i:s'))
             ->setStatus(self::STATUS_RUNNING)
             ->setTotalProducts($totalProducts)
             ->setProcessedProducts(0)
             ->setUpdatedProducts(0)
             ->setCreatedProducts(0)
             ->setErrorCount(0)
             ->save();
        
        return $this;
    }
    
    /**
     * Update sync log
     *
     * @param int $processed
     * @param int $updated
     * @param int $created
     * @param int $errors
     * @return $this
     */
    public function updateSyncLog(int $processed, int $updated, int $created, int $errors)
    {
        $this->setProcessedProducts($processed)
             ->setUpdatedProducts($updated)
             ->setCreatedProducts($created)
             ->setErrorCount($errors)
             ->save();
        
        return $this;
    }
    
    /**
     * Complete sync log
     *
     * @param bool $isSuccess
     * @param string|null $errorMessage
     * @return $this
     */
    public function completeSyncLog(bool $isSuccess, ?string $errorMessage = null)
    {
        $this->setFinishedAt($this->dateTime->gmtDate('Y-m-d H:i:s'))
             ->setStatus($isSuccess ? self::STATUS_COMPLETED : self::STATUS_FAILED);
        
        if (!$isSuccess && $errorMessage) {
            $this->setErrorMessage($errorMessage);
        }
        
        $this->save();
        
        return $this;
    }
}