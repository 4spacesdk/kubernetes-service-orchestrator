<?php
include_once 'Fixtures.php';
include_once 'TestCase.php';

(new \CodeIgniter\Config\DotEnv(__DIR__ . '/../'))->load();
define('CI_DEBUG', 0);

include_once __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';
