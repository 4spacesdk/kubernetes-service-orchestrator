<?php
// Everything under tests/ is autoloaded: see the psr-4 block in composer.json. The classes
// used to be included by hand from here, which meant a new base class had to be remembered
// in two places, and PhpStorm flagged every file in the folder as sitting outside the
// project's namespace map.

(new \CodeIgniter\Config\DotEnv(__DIR__ . '/../'))->load();
define('CI_DEBUG', false); // CodeIgniter 4.7 types this as bool

// Before a test can have cleared it - see the class for why that happens.
\App\ClusterEnvironment::remember();

include_once __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

// Straight after CodeIgniter has installed its error handler, so this one sits on top of it:
// a deprecation raised in our own code fails the test, one raised in vendor/ is counted.
\App\VendorDeprecations::install();

// A promise nobody handled is otherwise a line on stderr that no assertion can see.
\App\UnhandledRejections::listen();

// Before anything can emit one: see the class.
\App\SilentPush::install();

// Nor may a test start a cron job: it would run outside the test, against the development
// database. `JobbyApiTest` puts a recorder here instead.
\App\Controllers\Jobby::$start = static function (string $command): void {};

// Function files, which psr-4 cannot autoload - a file with no class in it is never looked
// for. `NoSleepInCommands.php` was written and never included, so the five two-second
// sleeps in `PullContainerRegistries::run()` were still being waited out.
require_once __DIR__ . '/_fakes/NoSleepInCommands.php';
