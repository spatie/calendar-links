#!/usr/bin/env php

<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Spatie\CalendarLinks\Console\CreateLinkCommand;

$app = new Application();
$app->add(new CreateLinkCommand());
$app->run();
