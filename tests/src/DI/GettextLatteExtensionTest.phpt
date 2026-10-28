<?php

namespace h4kuna\Gettext\DI;

use Nette\Configurator;
use Nette\Utils\FileSystem;
use Tester\Assert;

require __DIR__ . '/../../../vendor/autoload.php';

$tempDir = __DIR__ . '/../../temp/extension-' . getmypid();
$latteTempDir = $tempDir . '/latte';
FileSystem::write($latteTempDir . '/stale.php', '');

try {
	$configurator = new Configurator();
	$configurator->setDebugMode(true);
	$configurator->setTempDirectory($tempDir);
	$configurator->addStaticParameters(['appDir' => __DIR__ . '/../..']);
	$configurator->addConfig(__DIR__ . '/../../config/test.neon');
	$configurator->addConfig(['gettextLatteExtension' => ['latteTempDir' => $latteTempDir]]);
	$configurator->createContainer();

	Assert::false(is_file($latteTempDir . '/stale.php'));
} finally {
	FileSystem::delete($tempDir);
}
