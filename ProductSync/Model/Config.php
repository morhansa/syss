<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'magoarab_productsync/general/enabled';
    private const XML_PATH_SHEET_URL = 'magoarab_productsync/general/sheet_url';
    private const XML_PATH_IS_SCHEDULE_ENABLED = 'magoarab_productsync/general/is_schedule_enabled';
    private const XML_PATH_CRON_EXPR = 'magoarab_productsync/general/cron_expr';
    private const XML_PATH_BATCH_SIZE = 'magoarab_productsync/general/batch_size';
    private const XML_PATH_LAST_SYNC = 'magoarab_productsync/general/last_sync';
    
    private const XML_PATH_SKU_COLUMN = 'magoarab_productsync/mapping/sku_column';
    private const XML_PATH_NAME_COLUMN = 'magoarab_productsync/mapping/name_column';
    private const XML_PATH_QTY_COLUMN = 'magoarab_productsync/mapping/qty_column';
    private const XML_PATH_PRICE_COLUMN = 'magoarab_productsync/mapping/price_column';
    private const XML_PATH_DESCRIPTION_COLUMN = 'magoarab_productsync/mapping/description_column';
    
    private const XML_PATH_CREATE_NEW_PRODUCTS = 'magoarab_productsync/advanced/create_new_products';
    private const XML_PATH_DEFAULT_ATTRIBUTE_SET = 'magoarab_productsync/advanced/default_attribute_set';
    private const XML_PATH_DEFAULT_PRODUCT_TYPE = 'magoarab_productsync/advanced/default_product_type';
    private const XML_PATH_DEFAULT_STATUS = 'magoarab_productsync/advanced/default_status';
    private const XML_PATH_DEFAULT_VISIBILITY = 'magoarab_productsync/advanced/default_visibility';
    private const XML_PATH_DEFAULT_WEBSITE_IDS = 'magoarab_productsync/advanced/default_website_ids';
    private const XML_PATH_LOG_RETENTION_DAYS = 'magoarab_productsync/advanced/log_retention_days';
    
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * @var WriterInterface
     */
    private $configWriter;
    
    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
    }
    
    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get Google Sheet URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getSheetUrl(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_SHEET_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get CSV export URL from Google Sheet URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCsvExportUrl(?int $storeId = null): string
    {
        $sheetUrl = $this->getSheetUrl($storeId);
        
        // Extract key and gid from the Google Sheet URL
        preg_match('/\/d\/([a-zA-Z0-9-_]+)\/.*gid=(\d+)/', $sheetUrl, $matches);
        
        if (isset($matches[1]) && isset($matches[2])) {
            $key = $matches[1];
            $gid = $matches[2];
            return "https://docs.google.com/spreadsheets/d/{$key}/export?format=csv&gid={$gid}";
        }
        
        // Alternative format for edit URLs
        preg_match('/\/d\/([a-zA-Z0-9-_]+)\/edit/', $sheetUrl, $matches);
        if (isset($matches[1])) {
            $key = $matches[1];
            return "https://docs.google.com/spreadsheets/d/{$key}/export?format=csv&gid=0";
        }
        
        return $sheetUrl;
    }
    
    /**
     * Check if scheduled sync is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isScheduleEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_IS_SCHEDULE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get cron expression
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCronExpression(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_CRON_EXPR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get batch size
     *
     * @param int|null $storeId
     * @return int
     */
    public function getBatchSize(?int $storeId = null): int
    {
        $batchSize = (int)$this->scopeConfig->getValue(
            self::XML_PATH_BATCH_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $batchSize > 0 ? $batchSize : 50;
    }
    
    /**
     * Get field mapping
     *
     * @param int|null $storeId
     * @return array
     */
    public function getFieldMapping(?int $storeId = null): array
    {
        return [
            'sku' => $this->getSkuColumn($storeId),
            'name' => $this->getNameColumn($storeId),
            'qty' => $this->getQtyColumn($storeId),
            'price' => $this->getPriceColumn($storeId),
            'description' => $this->getDescriptionColumn($storeId)
        ];
    }
    
    /**
     * Get SKU column
     *
     * @param int|null $storeId
     * @return string
     */
    public function getSkuColumn(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_SKU_COLUMN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'sku';
    }
    
    /**
     * Get Name column
     *
     * @param int|null $storeId
     * @return string
     */
    public function getNameColumn(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_NAME_COLUMN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'name';
    }
    
    /**
     * Get Quantity column
     *
     * @param int|null $storeId
     * @return string
     */
    public function getQtyColumn(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_QTY_COLUMN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'qty';
    }
    
    /**
     * Get Price column
     *
     * @param int|null $storeId
     * @return string
     */
    public function getPriceColumn(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PRICE_COLUMN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'price';
    }
    
    /**
     * Get Description column
     *
     * @param int|null $storeId
     * @return string
     */
    public function getDescriptionColumn(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_DESCRIPTION_COLUMN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'description';
    }
    
    /**
     * Check if new products should be created
     *
     * @param int|null $storeId
     * @return bool
     */
    public function canCreateNewProducts(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CREATE_NEW_PRODUCTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get default attribute set ID
     *
     * @param int|null $storeId
     * @return int
     */
    public function getDefaultAttributeSetId(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_ATTRIBUTE_SET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get default product type
     *
     * @param int|null $storeId
     * @return string
     */
    public function getDefaultProductType(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_PRODUCT_TYPE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'simple';
    }
    
    /**
     * Get default status
     *
     * @param int|null $storeId
     * @return int
     */
    public function getDefaultStatus(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_STATUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get default visibility
     *
     * @param int|null $storeId
     * @return int
     */
    public function getDefaultVisibility(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_VISIBILITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    
    /**
     * Get default website IDs
     *
     * @param int|null $storeId
     * @return array
     */
    public function getDefaultWebsiteIds(?int $storeId = null): array
    {
        $websiteIds = $this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_WEBSITE_IDS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $websiteIds ? explode(',', $websiteIds) : [];
    }
    
    /**
     * Get log retention days
     *
     * @param int|null $storeId
     * @return int
     */
    public function getLogRetentionDays(?int $storeId = null): int
    {
        $days = (int)$this->scopeConfig->getValue(
            self::XML_PATH_LOG_RETENTION_DAYS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        
        return $days > 0 ? $days : 30;
    }
    
    /**
     * Set last sync time
     *
     * @param string $timestamp
     * @return void
     */
    public function setLastSyncTime(string $timestamp): void
    {
        $this->configWriter->save(
            self::XML_PATH_LAST_SYNC,
            $timestamp,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );
    }
}