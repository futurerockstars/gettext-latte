<?php

use Nette\Configurator;
use Nette\Utils;
use Tester\Environment;

include __DIR__ . '/../vendor/autoload.php';

$tempDir = __DIR__ . '/temp';
$logDir = $tempDir . '/log';
Utils\FileSystem::createDir($tempDir . '/cache/latte');
Utils\FileSystem::createDir($logDir);

$configurator = new Configurator();
$configurator->setDebugMode(true);
$configurator->enableDebugger($logDir);
$configurator->setTempDirectory($tempDir);
$configurator->addConfig(__DIR__ . '/config/test.neon');
$container = $configurator->createContainer();

Environment::setup();

return $container;
