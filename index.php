<?php

require __DIR__ . '/vendor/autoload.php';

/* Not sure why these are needed. Guessing the autoloader is being unregistered */
/* ahead of shutdown code run by this file. */
require __DIR__ . '/vendor/monolog/monolog/src/Monolog/DateTimeImmutable.php';
require __DIR__ . '/vendor/monolog/monolog/src/Monolog/Formatter/LineFormatter.php';
require __DIR__ . '/vendor/monolog/monolog/src/Monolog/Utils.php';

use Monolog\Logger;

class StreamWrapper
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger(__FILE__);
        $handler = new \Monolog\Handler\TestHandler();
        $this->logger->pushHandler($handler);
    }

    public function dir_closedir(): bool
    {
        $this->logger->info(__METHOD__);
        return true;
    }

    public function dir_opendir(string $path, int $options): bool
    {
        return true;
    }

    public function dir_readdir()
    {
        return false;
    }
}

stream_wrapper_register('test', StreamWrapper::class);
$dir = opendir('test://foo');
$result = readdir($dir);

/* If this line is uncommented, the segfault does not occur. */
/* closedir($dir); */

/* Uncomment this line to get a trace. It doesn't appear to contribute to the segfault. */
/* xdebug_start_trace(__DIR__ . '/xdebug'); */
