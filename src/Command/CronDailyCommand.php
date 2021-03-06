<?php
declare(strict_types=1);

namespace App\Command;

use App\Event\CronDailyEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CronDailyCommand extends Command
{
    protected static $defaultName = 'cron:daily';

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $dispatcher;

    public function __construct(LoggerInterface $logger, EventDispatcherInterface $dispatcher)
    {
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Calls all event subscribers listening '
            .'to the "cron.daily" event. To be called via crontab automatically.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Running CronDailyEvent');
        $this->dispatcher->dispatch(new CronDailyEvent());

        return 0;
    }
}
