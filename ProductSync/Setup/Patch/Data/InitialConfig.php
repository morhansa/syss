<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Initial configuration data patch
 */
class InitialConfig implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    
    /**
     * @var WriterInterface
     */
    private $configWriter;
    
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * InitialConfig constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param WriterInterface $configWriter
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
    }
    
    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();
        
        // Set default configuration values
        $this->configWriter->save('magoarab_productsync/general/enabled', 1);
        $this->configWriter->save('magoarab_productsync/general/sheet_url', '');
        $this->configWriter->save('magoarab_productsync/general/is_schedule_enabled', 0);
        $this->configWriter->save('magoarab_productsync/general/cron_expr', '0 */6 * * *');
        $this->configWriter->save('magoarab_productsync/general/batch_size', 50);
        
        // Field mapping
        $this->configWriter->save('magoarab_productsync/mapping/sku_column', 'sku');
        $this->configWriter->save('magoarab_productsync/mapping/name_column', 'name');
        $this->configWriter->save('magoarab_productsync/mapping/qty_column', 'qty');
        $this->configWriter->save('magoarab_productsync/mapping/price_column', 'price');
        $this->configWriter->save('magoarab_productsync/mapping/description_column', 'description');
        
        // Advanced settings
        $this->configWriter->save('magoarab_productsync/advanced/create_new_products', 1);
        $this->configWriter->save('magoarab_productsync/advanced/default_product_type', 'simple');
        $this->configWriter->save('magoarab_productsync/advanced/default_status', 1);
        $this->configWriter->save('magoarab_productsync/advanced/default_visibility', 4);
        $this->configWriter->save('magoarab_productsync/advanced/log_retention_days', 30);
        
        $this->moduleDataSetup->endSetup();
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}