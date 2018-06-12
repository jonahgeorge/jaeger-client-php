<?php

use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\NoopFinalHandler;
use Zend\Diactoros\Server;

require __DIR__ . '/../vendor/autoload.php';

$app = new MiddlewarePipe();

$server = Server::createServer($app,
  $_SERVER,
  $_GET,
  $_POST,
  $_COOKIE,
  $_FILES
);

$server->listen(new NoopFinalHandler());

