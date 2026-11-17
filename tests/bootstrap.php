<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

// The tests' logger writes to var/log/test.log (see config/services.yaml) and opens its file before anything else
// would create the directory
if (!is_dir(dirname(__DIR__) . '/var/log')) {
    mkdir(dirname(__DIR__) . '/var/log', 0o777, true);
}
