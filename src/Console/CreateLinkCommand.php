<?php

namespace Spatie\CalendarLinks\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Spatie\CalendarLinks\Link;
use DateTime;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateLinkCommand extends Command
{
    /**
     * @var string
     */
    private $commandName = 'create';

    /**
     * @var string
     */
    private $commandDescription = 'Generate a link to create an event on specific service';

    protected function configure(): void
    {
        $helpContent = '
    Example of a default request:

        <fg=black;bg=yellow>calendar_links create --to="2018-02-01 18:00"</>

    Example of a request with start and end date:

        <fg=black;bg=yellow>calendar_links create --from="2019-02-01 18:00" --to="2019-02-01 22:00"</></>

    Example of a request for multiple links:

        <fg=black;bg=yellow>calendar_links create --from="2019-02-01 18:00" --to="2019-02-01 22:00" -s google -s yahoo</>

    Example of a raw request:

        <fg=black;bg=yellow>calendar_links create --from="2019-02-01 18:00" --to="2019-02-01 22:00" -r true</>
        ';

        $this
            ->setName($this->commandName)
            ->setDescription($this->commandDescription)
            ->setHelp($helpContent)
            ->addOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $raw = $input->getOption('raw');
        $services = $input->getOption('services');
        $from = DateTime::createFromFormat('Y-m-d H:i', $input->getOption('from'));
        $to = DateTime::createFromFormat('Y-m-d H:i', $input->getOption('to'));

        $link = Link::create('Sebastian\'s birthday', $from, $to)
            ->description('Cookies & cocktails!')
            ->address('Samberstraat 69D, 2060 Antwerpen');

        $contentOutput = function ($service) use ($raw, $link) {
            $link = $link->$service();

            if ($raw) {
                return [$link, ''];
            }

            return [
                "<fg=black;bg=cyan>$service link</>",
                $link,
                ''
            ];
        };

        if (!empty($services)) {
            foreach ($services as $service) {
                $output->writeln($contentOutput($service));
            }

            if (!$raw) {
                $io->success('');
            }

            return;
        }

        $service = $io->choice('Select the service to which the link will be generated', ['google', 'yahoo', 'webOutlook', 'ics'], 'google');

        $output->writeln($contentOutput($service));

        $io->success('');
    }

    private function addOptions(): self
    {
        $this
            ->addOption('from', 'f', InputOption::VALUE_OPTIONAL, 'The event start date', date('Y-m-d H:i'))
            ->addOption('to', 't', InputOption::VALUE_REQUIRED, 'The event end date')
            ->addOption(
                'services',
                's',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'The service to which the link will be generated'
            )
            ->addOption(
                'raw',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Remove any formattation from output content (true|false)',
                false
            );

        return $this;
    }
}
