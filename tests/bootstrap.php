<?php

use Nette\Configurator;
use Nette\Utils;
use Tester\Environment;

include __DIR__ . '/../vendor/autoload.php';

$tempDir = __DIR__ . '/temp';
$logDir = $tempDir . '/log';
// One level at a time: parallel test processes race on a recursive mkdir
foreach ([$tempDir . '/cache', $tempDir . '/cache/latte', $logDir] as $dir) {
	Utils\FileSystem::createDir($dir);
}

$configurator = new Configurator();
$configurator->setDebugMode(true);
$configurator->enableDebugger($logDir);
$configurator->setTempDirectory($tempDir);
$configurator->addConfig(__DIR__ . '/config/test.neon');
$container = $configurator->createContainer();

Environment::setup();

return $container;
