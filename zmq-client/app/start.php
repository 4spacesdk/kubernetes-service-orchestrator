<?php
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "You need to run `composer install`. Comment out last line of docker file, rebuild and run again. Then composer install. Down. Revert dockerfile. Up again.";
    exit(1);
}

function log_($message, $level = 'debug') {
    $now = date("Y-m-d\TH:i:s") . substr((string)microtime(), 1, 8);
    echo $now . " " . str_pad($level, 10, " ") . " " . $message . "\n";
}

function php($uri) {
    $path = __DIR__ . '/../../ci4/public/';
    return shell_exec("php -d short_open_tag=on -d memory_limit=3072M -d allow_url_fopen=on {$path}index.php {$uri}");
}

require dirname(__DIR__) . '/vendor/autoload.php';
new \App\Subscriber();
