<?php

declare(strict_types=1);

use Goomer\Bootstrap\Container;
use Goomer\Shared\Http\HttpApp;

require dirname(__DIR__) . '/vendor/autoload.php';

HttpApp::create(Container::create())->run();
