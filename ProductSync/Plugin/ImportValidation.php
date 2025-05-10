<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\AbstractValidator;
use MagoArab\ProductSync\Model\Import\SheetReader;
use Psr\Log\LoggerInterface;

/**
 * Validation plugin for imported data
 */
class ImportValidation
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * ImportValidation constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }
    
    /**
     * Validate product data before processing
     *
     * @param SheetReader $subject
     * @param array $result
     * @return array
     */
    public function afterExtractProducts(SheetReader $subject, array $result): array
    {
        if (empty($result)) {
            return $result;
        }
        
        $validatedProducts = [];
        
        foreach ($result as $product) {
            try {
                // Validate required fields
                if (empty($product['sku'])) {
                    $this->logger->warning('Product skipped: SKU is empty');
                    continue;
                }
                
                // Validate SKU format
                if (!$this->isValidSku($product['sku'])) {
                    $this->logger->warning(sprintf(
                        'Product skipped: Invalid SKU format "%s"',
                        $product['sku']
                    ));
                    continue;
                }
                
                // Validate quantity
                if (isset($product['qty']) && !$this->isValidQuantity($product['qty'])) {
                    $this->logger->warning(sprintf(
                        'Invalid quantity "%s" for SKU "%s", setting to 0',
                        $product['qty'],
                        $product['sku']
                    ));
                    $product['qty'] = '0';
                }
                
                // Validate price
                if (isset($product['price']) && !$this->isValidPrice($product['price'])) {
                    $this->logger->warning(sprintf(
                        'Invalid price "%s" for SKU "%s", removing price',
                        $product['price'],
                        $product['sku']
                    ));
                    unset($product['price']);
                }
                
                // Add to validated products
                $validatedProducts[] = $product;
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Error validating product: %s',
                    $e->getMessage()
                ));
            }
        }
        
        return $validatedProducts;
    }
    
    /**
     * Check if SKU is valid
     *
     * @param string $sku
     * @return bool
     */
    private function isValidSku(string $sku): bool
    {
        // SKU should be alphanumeric, can contain dash and underscore
        return (bool)preg_match('/^[a-zA-Z0-9_-]+$/', $sku);
    }
    
    /**
     * Check if quantity is valid
     *
     * @param string $qty
     * @return bool
     */
    private function isValidQuantity(string $qty): bool
    {
        return is_numeric($qty) && (float)$qty >= 0;
    }
    
    /**
     * Check if price is valid
     *
     * @param string $price
     * @return bool
     */
    private function isValidPrice(string $price): bool
    {
        return is_numeric($price) && (float)$price >= 0;
    }
}