<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Api\Data;

/**
 * Interface for Sync Log Entity
 */
interface SyncLogInterface
{
    /**
     * Constants for keys of data array
     */
    const LOG_ID = 'log_id';
    const STARTED_AT = 'started_at';
    const FINISHED_AT = 'finished_at';
    const STATUS = 'status';
    const TOTAL_PRODUCTS = 'total_products';
    const PROCESSED_PRODUCTS = 'processed_products';
    const UPDATED_PRODUCTS = 'updated_products';
    const CREATED_PRODUCTS = 'created_products';
    const ERROR_COUNT = 'error_count';
    const ERROR_MESSAGE = 'error_message';
    
    /**
     * Status constants
     */
    const STATUS_RUNNING = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_FAILED = 2;
    
    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId();
    
    /**
     * Set ID
     *
     * @param int $id
     * @return $this
     */
    public function setId($id);
    
    /**
     * Get log id
     *
     * @return int|null
     */
    public function getLogId();
    
    /**
     * Set log id
     *
     * @param int $logId
     * @return $this
     */
    public function setLogId($logId);
    
    /**
     * Get started at
     *
     * @return string|null
     */
    public function getStartedAt();
    
    /**
     * Set started at
     *
     * @param string $startedAt
     * @return $this
     */
    public function setStartedAt($startedAt);
    
    /**
     * Get finished at
     *
     * @return string|null
     */
    public function getFinishedAt();
    
    /**
     * Set finished at
     *
     * @param string $finishedAt
     * @return $this
     */
    public function setFinishedAt($finishedAt);
    
    /**
     * Get status
     *
     * @return int
     */
    public function getStatus();
    
    /**
     * Set status
     *
     * @param int $status
     * @return $this
     */
    public function setStatus($status);
    
    /**
     * Get total products
     *
     * @return int
     */
    public function getTotalProducts();
    
    /**
     * Set total products
     *
     * @param int $totalProducts
     * @return $this
     */
    public function setTotalProducts($totalProducts);
    
    /**
     * Get processed products
     *
     * @return int
     */
    public function getProcessedProducts();
    
    /**
     * Set processed products
     *
     * @param int $processedProducts
     * @return $this
     */
    public function setProcessedProducts($processedProducts);
    
    /**
     * Get updated products
     *
     * @return int
     */
    public function getUpdatedProducts();
    
    /**
     * Set updated products
     *
     * @param int $updatedProducts
     * @return $this
     */
    public function setUpdatedProducts($updatedProducts);
    
    /**
     * Get created products
     *
     * @return int
     */
    public function getCreatedProducts();
    
    /**
     * Set created products
     *
     * @param int $createdProducts
     * @return $this
     */
    public function setCreatedProducts($createdProducts);
    
    /**
     * Get error count
     *
     * @return int
     */
    public function getErrorCount();
    
    /**
     * Set error count
     *
     * @param int $errorCount
     * @return $this
     */
    public function setErrorCount($errorCount);
    
    /**
     * Get error message
     *
     * @return string|null
     */
    public function getErrorMessage();
    
    /**
     * Set error message
     *
     * @param string $errorMessage
     * @return $this
     */
    public function setErrorMessage($errorMessage);
}