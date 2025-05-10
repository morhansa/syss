<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Model\Import;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MagoArab\ProductSync\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Product processor class
 */
class ProductProcessor
{
    /**
     * Default price when not provided
     */
    const DEFAULT_PRICE = 0;
    
    /**
     * @var Config
     */
    private $config;
    
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    
    /**
     * @var ProductFactory
     */
    private $productFactory;
    
    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;
    
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * ProductProcessor constructor.
     *
     * @param Config $config
     * @param ProductRepositoryInterface $productRepository
     * @param ProductFactory $productFactory
     * @param StockRegistryInterface $stockRegistry
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        ProductRepositoryInterface $productRepository,
        ProductFactory $productFactory,
        StockRegistryInterface $stockRegistry,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->stockRegistry = $stockRegistry;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }
    
    /**
     * Process a batch of products
     *
     * @param array $products
     * @return array
     */
    public function processProductBatch(array $products): array
    {
        $result = [
            'updated' => 0,
            'created' => 0,
            'errors' => 0
        ];
        
        if (empty($products)) {
            return $result;
        }
        
        $skus = array_column($products, 'sku');
        $existingProducts = $this->getExistingProductsBySku($skus);
        
        foreach ($products as $productData) {
            try {
                $sku = $productData['sku'];
                
                if (isset($existingProducts[$sku])) {
                    // Update existing product
                    $this->updateProduct($existingProducts[$sku], $productData);
                    $result['updated']++;
                } elseif ($this->config->canCreateNewProducts()) {
                    // Create new product
                    $this->createProduct($productData);
                    $result['created']++;
                }
            } catch (Exception $e) {
                $this->logger->error('Error processing product: ' . $e->getMessage(), [
                    'sku' => $productData['sku'] ?? 'unknown',
                    'exception' => $e
                ]);
                $result['errors']++;
            }
        }
        
        return $result;
    }
    
    /**
     * Get existing products by SKUs
     *
     * @param array $skus
     * @return array
     */
    private function getExistingProductsBySku(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }
        
        try {
            // Using direct database query for performance
            $connection = $this->resourceConnection->getConnection();
            $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
            
            $select = $connection->select()
                ->from($productTable, ['entity_id', 'sku'])
                ->where('sku IN (?)', $skus);
            
            $productIds = $connection->fetchPairs($select);
            
            if (empty($productIds)) {
                return [];
            }
            
            // Load products by IDs
            $this->searchCriteriaBuilder->addFilter('entity_id', array_keys($productIds), 'in');
            $searchCriteria = $this->searchCriteriaBuilder->create();
            $products = $this->productRepository->getList($searchCriteria)->getItems();
            
            $result = [];
            foreach ($products as $product) {
                $result[$product->getSku()] = $product;
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('Error retrieving products by SKU: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }
    

// Fixed version of ProductProcessor.php - updateProduct method

/**
 * Update product with new data
 *
 * @param ProductInterface $product
 * @param array $productData
 * @return void
 */
private function updateProduct(ProductInterface $product, array $productData): void
{
    $updatedFields = [];
    $saveProduct = false;
    
    // Add debug logging
    $this->logger->info('Updating product: ' . $product->getSku(), [
        'product_id' => $product->getId(),
        'data' => json_encode($productData)
    ]);
    
    // Update product data if provided
    if (!empty($productData['name']) && $product->getName() !== $productData['name']) {
        $product->setName($productData['name']);
        $saveProduct = true;
        $updatedFields[] = 'name';
    }
    
    // Price update was intentionally skipped, but let's make it optional based on config
    if (isset($productData['price']) && is_numeric($productData['price'])) {
        $newPrice = (float)$productData['price'];
        $currentPrice = (float)$product->getPrice();
        
        // Only update if there's a meaningful difference
        if (abs($newPrice - $currentPrice) > 0.001) {
            $product->setPrice($newPrice);
            $saveProduct = true;
            $updatedFields[] = 'price';
        }
    }
    
    if (!empty($productData['description']) && $product->getDescription() !== $productData['description']) {
        $product->setDescription($productData['description']);
        $saveProduct = true;
        $updatedFields[] = 'description';
    }
    
    // Mark product as synced from sheet
    if (!$product->getData('synced_from_sheet')) {
        $product->setData('synced_from_sheet', 1);
        $saveProduct = true;
    }
    
    // Update last sync timestamp
    $product->setData('last_synced_at', date('Y-m-d H:i:s'));
    $saveProduct = true;
    
    // Save product if any attributes have changed
    if ($saveProduct) {
        try {
            $this->logger->info('Saving product changes for SKU: ' . $product->getSku());
            $this->productRepository->save($product);
            
            // Log updated fields
            if (!empty($updatedFields)) {
                $this->logger->info('Updated product: ' . $product->getSku(), [
                    'fields' => implode(', ', $updatedFields)
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->critical('Failed to save product: ' . $e->getMessage(), [
                'sku' => $product->getSku(),
                'exception' => $e
            ]);
            throw $e;
        }
    } else {
        $this->logger->info('No changes detected for product: ' . $product->getSku());
    }
    
    // Update stock quantity if provided
    if (isset($productData['qty']) && is_numeric($productData['qty'])) {
        $this->updateStockQuantity($product->getSku(), (float)$productData['qty']);
        $updatedFields[] = 'qty';
    }
}
    
    /**
     * Create new product
     *
     * @param array $productData
     * @return ProductInterface
     * @throws CouldNotSaveException
     */
    private function createProduct(array $productData): ProductInterface
    {
        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $this->productFactory->create();
            
            $product->setSku($productData['sku'])
                ->setName($productData['name'] ?? $productData['sku'])
                ->setAttributeSetId($this->config->getDefaultAttributeSetId())
                ->setStatus($this->config->getDefaultStatus())
                ->setVisibility($this->config->getDefaultVisibility())
                ->setTypeId($this->config->getDefaultProductType())
                ->setWebsiteIds($this->config->getDefaultWebsiteIds());
            
            // Always set a price for new products (use provided or default)
            $price = isset($productData['price']) && !empty($productData['price']) ? 
                     (float)$productData['price'] : 
                     self::DEFAULT_PRICE;
            $product->setPrice($price);
            
            if (!empty($productData['description'])) {
                $product->setDescription($productData['description']);
            }
            
            // Mark product as synced from sheet
            $product->setData('synced_from_sheet', 1);
            $product->setData('last_synced_at', date('Y-m-d H:i:s'));
            
            // Save new product
            $product = $this->productRepository->save($product);
            
            // Set stock quantity if provided
            if (isset($productData['qty']) && is_numeric($productData['qty'])) {
                $this->updateStockQuantity($product->getSku(), (float)$productData['qty']);
            }
            
            $this->logger->info('Created new product: ' . $product->getSku(), [
                'price' => $price
            ]);
            
            return $product;
        } catch (Exception $e) {
            $this->logger->error('Error creating product: ' . $e->getMessage(), [
                'sku' => $productData['sku'] ?? 'unknown',
                'exception' => $e
            ]);
            throw $e;
        }
    }
    
    /**
     * Update stock quantity
     *
     * @param string $sku
     * @param float $qty
     * @return void
     */
    private function updateStockQuantity(string $sku, float $qty): void
    {
        try {
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            
            if ($stockItem->getQty() != $qty) {
                $stockItem->setQty($qty);
                $stockItem->setIsInStock($qty > 0);
                $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->error('Stock item not found for SKU: ' . $sku);
        } catch (Exception $e) {
            $this->logger->error('Error updating stock: ' . $e->getMessage(), [
                'sku' => $sku,
                'exception' => $e
            ]);
        }
    }
}