<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use MagoArab\ProductSync\Model\Config;

/**
 * SyncLog Resource Model
 */
class SyncLog extends AbstractDb
{
    /**
     * @var Config
     */
    private $config;
    
    /**
     * SyncLog Resource constructor.
     *
     * @param Context $context
     * @param Config $config
     * @param string|null $connectionName
     */
    public function __construct(
        Context $context,
        Config $config,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->config = $config;
    }
    
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('magoarab_productsync_log', 'log_id');
    }
    
    /**
     * Clean up old log entries
     *
     * @return $this
     */
    public function cleanOldLogs()
    {
        $connection = $this->getConnection();
        $retentionDays = $this->config->getLogRetentionDays();
        
        if ($retentionDays > 0) {
            $connection->delete(
                $this->getMainTable(),
                ['started_at < ?' => new \Zend_Db_Expr('DATE_SUB(NOW(), INTERVAL ' . $retentionDays . ' DAY)')]
            );
        }
        
        return $this;
    }
}