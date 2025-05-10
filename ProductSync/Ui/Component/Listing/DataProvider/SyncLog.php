<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Ui\Component\Listing\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use MagoArab\ProductSync\Model\ResourceModel\SyncLog\Collection;
use MagoArab\ProductSync\Model\ResourceModel\SyncLog\CollectionFactory;

/**
 * SyncLog DataProvider for UI Component
 */
class SyncLog extends DataProvider
{
    /**
     * @var Collection
     */
    protected $collection;
    
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;
    
    /**
     * SyncLog constructor.
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->collectionFactory = $collectionFactory;
    }
    
    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }
        
        $items = $this->getCollection()->toArray();
        
        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => array_values($items['items'] ?? []),
        ];
    }
    
    /**
     * Get collection
     *
     * @return Collection
     */
    public function getCollection()
    {
        if ($this->collection === null) {
            $this->collection = $this->collectionFactory->create();
        }
        
        return $this->collection;
    }
    
    /**
     * Add filter
     *
     * @param Filter $filter
     * @return void
     */
    public function addFilter(Filter $filter)
    {
        // Map status to its numeric value
        if ($filter->getField() === 'status') {
            $value = $filter->getValue();
            if ($value === 'running') {
                $filter = $this->filterBuilder
                    ->setField($filter->getField())
                    ->setValue(0)
                    ->setConditionType($filter->getConditionType())
                    ->create();
            } elseif ($value === 'completed') {
                $filter = $this->filterBuilder
                    ->setField($filter->getField())
                    ->setValue(1)
                    ->setConditionType($filter->getConditionType())
                    ->create();
            } elseif ($value === 'failed') {
                $filter = $this->filterBuilder
                    ->setField($filter->getField())
                    ->setValue(2)
                    ->setConditionType($filter->getConditionType())
                    ->create();
            }
        }
        
        $this->getCollection()->addFieldToFilter(
            $filter->getField(),
            [$filter->getConditionType() => $filter->getValue()]
        );
    }
}