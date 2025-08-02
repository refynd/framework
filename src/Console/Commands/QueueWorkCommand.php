<?php

namespace Refynd\Console\Commands;

use Refynd\Queue\QueueWorker;
use Refynd\Container\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueWorkCommand extends Command
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('queue:work')
             ->setDescription('Start processing jobs on the queue')
             ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'The queue to work', 'default')
             ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Sleep time between jobs', 3);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = $input->getOption('queue');
        $sleep = (int) $input->getOption('sleep');
        
        $output->writeln("<info>Starting queue worker for queue: {$queueName}</info>");
        $output->writeln("<comment>Press Ctrl+C to stop</comment>");
        
        $worker = $this->container->make(QueueWorker::class);
        
        // Handle graceful shutdown
        pcntl_signal(SIGTERM, function() use ($worker) {
            $worker->stop();
        });
        pcntl_signal(SIGINT, function() use ($worker) {
            $worker->stop();
        });
        
        $worker->work($queueName, $sleep);
        
        $output->writeln('<info>Queue worker stopped</info>');
        return Command::SUCCESS;
    }
}
