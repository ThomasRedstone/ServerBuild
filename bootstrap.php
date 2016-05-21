<?php
/**
 * Created by PhpStorm.
 * User: thomasredstone
 * Date: 21/05/2016
 * Time: 21:20
 */

use RedstoneTechnology\ServerBuild\Commands;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(
    new Commands\ServerBuild(
        new \RedstoneTechnology\ServerBuild\Utilities\ServerBuild(
            new \RedstoneTechnology\ServerBuild\Utilities\Packages()
        )
    )
);
$application->run();
