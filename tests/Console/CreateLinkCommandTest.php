<?php

namespace Spatie\CalendarLinks\Test\Console;

use Spatie\CalendarLinks\Test\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Spatie\CalendarLinks\Console\CreateLinkCommand;
use DateTime;

class CreateLinkCommandTest extends TestCase
{
    /**
     * @var \Spatie\CalendarLinks\Console\CreateLinkCommand
     */
    private $command;

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var array
     */
    private $serviceRegex = [
        'google' => '/https:\/\/calendar.google.com\/calendar\/render.*/',
        'yahoo' => '/https:\/\/calendar.yahoo.com\/.*/',
        'webOutlook' => '/https:\/\/outlook.live.com\/owa\/.*/',
        'ics' => '/data:text\/calendar\;.*/',
    ];

    public function __construct()
    {
        parent::__construct();

        $defaultDateTime = new DateTime();
        $defaultFormat = 'Y-m-d H:i';

        $this->options = [
            'from' => $defaultDateTime->modify('2 days')->format($defaultFormat),
            'to' => $defaultDateTime->modify('next week')->format($defaultFormat),
        ];
    }

    public function setUp()
    {
        $this->command = new CreateLinkCommand();
    }

    /** @test */
    public function it_can_generate_a_link_with_only_mandatory_parameters(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs([0]);
        $commandTester->execute([
            '-t' => $this->options['to']
        ]);

        $output = $commandTester->getDisplay(true);

        $this->assertInternalType('string', $output);
        $this->assertRegExp($this->serviceRegex['google'], $output);
    }

    /** @test */
    public function it_can_generate_a_link_with_range_options(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs([0]);
        $commandTester->execute([
            '-f' => $this->options['from'],
            '-t' => $this->options['to'],
        ]);

        $output = $commandTester->getDisplay(true);

        $this->assertInternalType('string', $output);
        $this->assertRegExp($this->serviceRegex['google'], $output);
    }

    /** @test */
    public function it_can_generate_a_link_with_multiple_services(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            '-t' => $this->options['to'],
            '-s' => ['google', 'yahoo', 'webOutlook', 'ics']
        ]);

        $output = $commandTester->getDisplay(true);

        $this->assertInternalType('string', $output);
        $this->assertRegExp($this->serviceRegex['google'], $output);
        $this->assertRegExp($this->serviceRegex['yahoo'], $output);
        $this->assertRegExp($this->serviceRegex['webOutlook'], $output);
        $this->assertRegExp($this->serviceRegex['ics'], $output);
    }

    /** @test */
    public function it_can_generate_a_link_with_output_raw(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs([0]);
        $commandTester->execute([
            '-t' => $this->options['to'],
            '-r' => true
        ]);

        $output = $commandTester->getDisplay(true);

        $this->assertInternalType('string', $output);
        $this->assertRegExp($this->serviceRegex['google'], $output);
    }
}
