<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Model\Import;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use MagoArab\ProductSync\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Google Sheet reader class
 */
class SheetReader
{
    /**
     * @var Config
     */
    private $config;
    
    /**
     * @var Curl
     */
    private $curl;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * SheetReader constructor.
     *
     * @param Config $config
     * @param Curl $curl
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->curl = $curl;
        $this->logger = $logger;
    }
    
    /**
     * Read Google Sheet and return products array
     *
     * @param string $url
     * @return array
     * @throws LocalizedException
     */
    public function readSheet(string $url): array
    {
        try {
            // Convert input URL to export URL if needed
            $exportUrl = $this->convertToExportUrl($url);
            $this->logger->info("Fetching from URL: " . $exportUrl);
            
            // Set up CURL options
            $this->curl->setOption(CURLOPT_FOLLOWLOCATION, true);
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_TIMEOUT, 30);
            $this->curl->setOption(CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            
            // Get CSV content from Google Sheets
            $this->curl->get($exportUrl);
            $response = $this->curl->getBody();
            
            if (empty($response)) {
                $this->logger->error("Empty response from Google Sheets. Response code: " . $this->curl->getStatus());
                throw new LocalizedException(__('Empty response from Google Sheets. Please check the URL.'));
            }
            
            // Log sample of the response for debugging
            $this->logger->debug("Response sample (first 500 chars): " . substr($response, 0, 500));
            
            // Parse CSV
            $rows = $this->parseCsv($response);
            if (empty($rows)) {
                throw new LocalizedException(__('No data found in the CSV.'));
            }
            
            $this->logger->info("Found " . count($rows) . " rows in the CSV file.");
            
            // Get field mapping
            $mapping = $this->config->getFieldMapping();
            $this->logger->debug("Using field mapping: " . json_encode($mapping));
            
            // Extract products from CSV
            $products = $this->extractProducts($rows, $mapping);
            $this->logger->info("Extracted " . count($products) . " products from CSV.");
            
            return $products;
        } catch (\Exception $e) {
            $this->logger->critical('Error reading Google Sheet: ' . $e->getMessage(), ['exception' => $e]);
            throw new LocalizedException(__('Failed to read Google Sheet: %1', $e->getMessage()));
        }
    }
    
    /**
     * Convert any Google Sheets URL to an export URL
     *
     * @param string $url
     * @return string
     */
    private function convertToExportUrl(string $url): string
    {
        // Case 1: Direct export URL
        if (strpos($url, '/export?') !== false) {
            return $url;
        }
        
        // Case 2: Standard edit URL with key and gid
        // Example: https://docs.google.com/spreadsheets/d/KEY/edit#gid=GID
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)\/.*gid=(\d+)/', $url, $matches)) {
            $key = $matches[1];
            $gid = $matches[2];
            return "https://docs.google.com/spreadsheets/d/{$key}/export?format=csv&gid={$gid}";
        }
        
        // Case 3: Standard edit URL with key only
        // Example: https://docs.google.com/spreadsheets/d/KEY/edit
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)\/edit/', $url, $matches)) {
            $key = $matches[1];
            return "https://docs.google.com/spreadsheets/d/{$key}/export?format=csv&gid=0";
        }
        
        // Case 4: Just the key
        if (preg_match('/^[a-zA-Z0-9-_]+$/', $url)) {
            return "https://docs.google.com/spreadsheets/d/{$url}/export?format=csv&gid=0";
        }
        
        // Use as-is if we can't match any pattern
        return $url;
    }
    
    /**
     * Parse CSV content
     *
     * @param string $content
     * @return array
     */
    private function parseCsv(string $content): array
    {
        $rows = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $i => $line) {
            // Skip empty lines
            if (empty(trim($line))) {
                continue;
            }
            
            // Parse CSV line
            $rowData = str_getcsv($line);
            
            // Log parsing of first few rows for debugging
            if ($i < 3) {
                $this->logger->debug("Row {$i} has " . count($rowData) . " columns: " . json_encode($rowData));
            }
            
            if (!empty($rowData)) {
                $rows[] = $rowData;
            }
        }
        
        return $rows;
    }
    

/**
 * Extract products from CSV rows
 *
 * @param array $rows
 * @param array $mapping
 * @return array
 * @throws LocalizedException
 */
private function extractProducts(array $rows, array $mapping): array
{
    if (empty($rows)) {
        throw new LocalizedException(__('CSV must contain at least one row.'));
    }
    
    $products = [];
    $header = array_map('trim', $rows[0]);
    
    // Log header row for debugging
    $this->logger->debug("Header row: " . json_encode($header));
    
    // If there's only a header, no products
    if (count($rows) < 2) {
        $this->logger->warning('CSV only contains a header row, no product data.');
        return [];
    }
    
    // Get column indexes from header
    $columnIndexes = $this->getColumnIndexes($header, $mapping);
    $this->logger->debug("Column indexes: " . json_encode($columnIndexes));
    
    // Check if required columns are present
    if ($columnIndexes['sku'] === null) {
        $this->logger->error("SKU column not found in CSV header. Header: " . implode(', ', $header) . 
                            ", Looking for: " . $mapping['sku']);
        throw new LocalizedException(__('SKU column "%1" not found in CSV header.', $mapping['sku']));
    }
    
    // Skip header row
    $totalRows = count($rows);
    $this->logger->info("Processing {$totalRows} rows from CSV");
    
    for ($i = 1; $i < $totalRows; $i++) {
        $row = $rows[$i];
        
        // Skip rows that don't have enough columns for SKU
        if (count($row) <= $columnIndexes['sku']) {
            $this->logger->warning('Skipping row ' . ($i + 1) . ' due to insufficient columns. Row has ' . 
                                  count($row) . ' columns, SKU is at index ' . $columnIndexes['sku']);
            continue;
        }
        
        try {
            $product = [];
            
            // Always get SKU first
            $sku = isset($row[$columnIndexes['sku']]) ? trim($row[$columnIndexes['sku']]) : '';
            if (empty($sku)) {
                $this->logger->warning('Skipping row ' . ($i + 1) . ' due to missing SKU.');
                continue;
            }
            $product['sku'] = $sku;
            
            // Map other columns to product data if present
            foreach ($mapping as $field => $headerName) {
                if ($field === 'sku') {
                    continue; // Already processed
                }
                
                if (isset($columnIndexes[$field]) && $columnIndexes[$field] !== null) {
                    // Only add the field if the column exists in the row
                    if (isset($row[$columnIndexes[$field]])) {
                        $product[$field] = trim($row[$columnIndexes[$field]]);
                    }
                }
            }
            
            // If we at least have an SKU, add the product
            if (!empty($product['sku'])) {
                $products[] = $product;
                
                // Log progress periodically
                if ($i % 500 === 0 || $i === $totalRows - 1) {
                    $this->logger->info("Extracted {$i} of {$totalRows} products");
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Error processing row ' . ($i + 1) . ': ' . $e->getMessage());
        }
    }
    
    $this->logger->info("Successfully extracted " . count($products) . " products from CSV");
    return $products;
}
    
    /**
     * Get column indexes from header
     *
     * @param array $header
     * @param array $mapping
     * @return array
     */
    private function getColumnIndexes(array $header, array $mapping): array
    {
        $columnIndexes = [];
        
        foreach ($mapping as $field => $headerName) {
            $columnIndexes[$field] = null;
            
            // Try exact match
            $index = array_search(strtolower(trim($headerName)), array_map('strtolower', array_map('trim', $header)));
            if ($index !== false) {
                $columnIndexes[$field] = $index;
                continue;
            }
            
            // Try partial match
            foreach ($header as $i => $name) {
                if (stripos(trim($name), trim($headerName)) !== false) {
                    $columnIndexes[$field] = $i;
                    break;
                }
            }
        }
        
        return $columnIndexes;
    }
}