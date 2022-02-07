<?php

use Parse\Commands\ParseCommand;
use Symfony\Component\Console\Application;

require __DIR__ . "/vendor/autoload.php";

$application = new Application("parse", '0.0.1');
$application->add(new ParseCommand());
$application->run();
