<?php

namespace Refynd\Console\Commands;

use Refynd\Storage\StorageManager;
use Refynd\Container\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StorageCommand extends Command
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('storage:info')
             ->setDescription('Display storage information and statistics')
             ->addOption('disk', null, InputOption::VALUE_OPTIONAL, 'The storage disk to inspect', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $diskName = $input->getOption('disk');
        $storage = $this->container->make(StorageManager::class);
        
        if ($diskName) {
            $disk = $storage->disk($diskName);
            $output->writeln("<info>Storage Disk: {$diskName}</info>");
        } else {
            $disk = $storage->disk();
            $output->writeln("<info>Default Storage Disk</info>");
        }
        
        // Show files in root directory
        $files = $disk->files();
        $directories = $disk->directories();
        
        $output->writeln('<comment>Directories:</comment>');
        foreach ($directories as $directory) {
            $output->writeln("  📁 {$directory}");
        }
        
        $output->writeln('<comment>Files:</comment>');
        foreach ($files as $file) {
            if ($disk->exists($file)) {
                $size = $disk->size($file);
                $lastModified = date('Y-m-d H:i:s', $disk->lastModified($file));
                $output->writeln("  📄 {$file} ({$size} bytes, modified: {$lastModified})");
            }
        }
        
        $output->writeln('<info>Total files: ' . count($files) . '</info>');
        $output->writeln('<info>Total directories: ' . count($directories) . '</info>');
        
        return Command::SUCCESS;
    }
}
