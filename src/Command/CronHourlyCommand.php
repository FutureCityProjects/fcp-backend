<?php
declare(strict_types=1);

namespace App\Command;

use App\Event\CronHourlyEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CronHourlyCommand extends Command
{
    protected static $defaultName = 'cron:hourly';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(LoggerInterface $logger, EventDispatcherInterface $dispatcher)
    {
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Calls all event subscribers listening '
            .'to the "cron.hourly" event. To be called via crontab automatically.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('Running CronHourlyEvent');
        $this->dispatcher->dispatch(new CronHourlyEvent());
    }
}
