<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Api;

/**
 * Interface for product synchronization service
 */
interface SyncServiceInterface
{
    /**
     * Sync products from Google Sheet
     *
     * @param bool $async Whether to process synchronization asynchronously
     * @return bool
     */
    public function syncProducts(bool $async = true): bool;
    
    /**
     * Get sync progress
     *
     * @return array
     */
    public function getSyncProgress(): array;
    
    /**
     * Check if sync is in progress
     *
     * @return bool
     */
    public function isSyncInProgress(): bool;
}