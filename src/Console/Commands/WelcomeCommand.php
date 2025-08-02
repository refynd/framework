<?php

namespace Refynd\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * WelcomeCommand - Display Refynd welcome message
 */
class WelcomeCommand extends Command
{
    protected static string $defaultName = 'welcome';
    protected static string $defaultDescription = 'Display welcome message';

    protected function configure(): void
    {
        $this->setName('welcome')
             ->setDescription('Display the Refynd welcome message');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(['',
            '🔨 <fg = blue > Welcome to Refynd Framework</fg = blue>',
            '<fg = yellow>═══════════════════════════════════════</fg = yellow>',
            '',
            '<fg = green > Refynd</fg = green> is a powerful, elegant PHP framework designed for',
            'crafting exceptional web applications with confidence and joy.',
            '',
            '<comment > Available Commands:</comment>',
            '  <info > php smith serve:app</info>        Start the development server',
            '  <info > php smith build:controller</info>  Create a new controller',
            '  <info > php smith make:record</info>       Create a new Ledger record model',
            '  <info > php smith run:migrate</info>       Run database migrations',
            '  <info > php smith test:all</info>          Run the test suite',
            '',
            '<comment > Documentation:</comment>',
            '  Visit <fg = cyan > https://refynd.dev/docs</fg = cyan> for comprehensive guides',
            '',
            '<fg = yellow > Happy crafting! 🚀</fg = yellow>',
            '',]);

        return Command::SUCCESS;
    }
}
