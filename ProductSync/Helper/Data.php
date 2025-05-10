<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use MagoArab\ProductSync\Model\Config;

/**
 * Helper class
 */
class Data extends AbstractHelper
{
    /**
     * @var Config
     */
    private $config;
    
    /**
     * @var DateTime
     */
    private $dateTime;
    
    /**
     * @var TimezoneInterface
     */
    private $timezone;
    
    /**
     * Data constructor.
     *
     * @param Context $context
     * @param Config $config
     * @param DateTime $dateTime
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        Context $context,
        Config $config,
        DateTime $dateTime,
        TimezoneInterface $timezone
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->dateTime = $dateTime;
        $this->timezone = $timezone;
    }
    
    /**
     * Check if module is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }
    
    /**
     * Format date for display
     *
     * @param string $date
     * @param int $format
     * @return string
     */
    public function formatDate(string $date, int $format = \IntlDateFormatter::MEDIUM): string
    {
        try {
            return $this->timezone->formatDateTime(
                $date,
                $format,
                $format,
                null,
                null
            );
        } catch (\Exception $e) {
            return $date;
        }
    }
    
    /**
     * Get next scheduled sync time
     *
     * @return string|null
     */
    public function getNextScheduledSyncTime(): ?string
    {
        if (!$this->config->isScheduleEnabled()) {
            return null;
        }
        
        try {
            $cronExpression = $this->config->getCronExpression();
            if (empty($cronExpression)) {
                return null;
            }
            
            $cron = \Cron\CronExpression::factory($cronExpression);
            $nextRunDate = $cron->getNextRunDate();
            
            return $this->formatDate(
                $nextRunDate->format('Y-m-d H:i:s'),
                \IntlDateFormatter::MEDIUM
            );
        } catch (\Exception $e) {
            $this->_logger->error('Error calculating next sync time: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calculate next run time for display
     *
     * @return string
     */
    public function getNextRunTimeDisplay(): string
    {
        $nextTime = $this->getNextScheduledSyncTime();
        
        if (!$this->config->isEnabled()) {
            return (string)__('Module is disabled');
        }
        
        if (!$this->config->isScheduleEnabled()) {
            return (string)__('Scheduled sync is disabled');
        }
        
        if ($nextTime === null) {
            return (string)__('Invalid cron expression');
        }
        
        return (string)__('Next sync: %1', $nextTime);
    }
}