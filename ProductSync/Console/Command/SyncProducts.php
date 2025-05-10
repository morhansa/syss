<?php
/**
 * @category  MagoArab
 * @package   MagoArab_ProductSync
 * @author    MagoArab Developer
 * @copyright Copyright (c) 2025 MagoArab (https://www.magoarab.com)
 */
declare(strict_types=1);

namespace MagoArab\ProductSync\Console\Command;

use Magento\Framework\Console\Cli;
use MagoArab\ProductSync\Api\SyncServiceInterface;
use MagoArab\ProductSync\Model\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command for product synchronization
 */
class SyncProducts extends Command
{
    /**
     * Command option keys
     */
    private const OPTION_SYNC_ASYNC = 'async';
    
    /**
     * @var Config
     */
    private $config;
    
    /**
     * @var SyncServiceInterface
     */
    private $syncService;
    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * SyncProducts constructor.
     *
     * @param Config $config
     * @param SyncServiceInterface $syncService
     * @param LoggerInterface $logger
     * @param string|null $name
     */
    public function __construct(
        Config $config,
        SyncServiceInterface $syncService,
        LoggerInterface $logger,
        string $name = null
    ) {
        parent::__construct($name);
        $this->config = $config;
        $this->syncService = $syncService;
        $this->logger = $logger;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $options = [
            new InputOption(
                self::OPTION_SYNC_ASYNC,
                null,
                InputOption::VALUE_NONE,
                'Run sync asynchronously (in the background)'
            )
        ];
        
        $this->setName('magoarab:productsync:sync')
            ->setDescription('Synchronize products from Google Sheets')
            ->setDefinition($options);
        
        parent::configure();
    }
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->config->isEnabled()) {
            $output->writeln('<error>Product Sync module is disabled in configuration.</error>');
            return Cli::RETURN_FAILURE;
        }
        
        $async = (bool)$input->getOption(self::OPTION_SYNC_ASYNC);
        $output->writeln('<info>Starting product synchronization from Google Sheets...</info>');
        
        try {
            if ($this->syncService->isSyncInProgress()) {
                $output->writeln('<error>Sync is already in progress. Please wait for it to complete.</error>');
                return Cli::RETURN_FAILURE;
            }
            
            $result = $this->syncService->syncProducts($async);
            
            if ($result) {
                if ($async) {
                    $output->writeln('<info>Product synchronization has been initiated and is running in the background.</info>');
                } else {
                    $output->writeln('<info>Product synchronization completed successfully.</info>');
                    
                    $progress = $this->syncService->getSyncProgress();
                    $output->writeln(sprintf(
                        '<info>Total: %d, Updated: %d, Created: %d, Errors: %d</info>',
                        $progress['total'],
                        $progress['updated'],
                        $progress['created'],
                        $progress['errors']
                    ));
                }
                
                return Cli::RETURN_SUCCESS;
            } else {
                $output->writeln('<error>Failed to start product synchronization.</error>');
                return Cli::RETURN_FAILURE;
            }
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            $this->logger->critical('Error in console sync command: ' . $e->getMessage(), ['exception' => $e]);
            return Cli::RETURN_FAILURE;
        }
    }
}