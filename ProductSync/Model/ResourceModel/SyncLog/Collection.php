<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Model\ResourceModel\SyncLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MagoArab\ProductSync\Model\SyncLog;
use MagoArab\ProductSync\Model\ResourceModel\SyncLog as SyncLogResource;

/**
 * SyncLog Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'log_id';
    
    /**
     * Initialize model and resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(SyncLog::class, SyncLogResource::class);
    }
}