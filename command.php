#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/vendor/autoload.php';

use RedstoneTechnology\ServerBuild\Commands;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(
    new Commands\ServerBuild(
        new \RedstoneTechnology\ServerBuild\Utilities\ServerBuild()
    )
);
$application->run();
