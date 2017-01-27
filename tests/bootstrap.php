<?php

include __DIR__ . "/../vendor/autoload.php";

function dd($var /* ... */)
{
	foreach (func_get_args() as $arg) {
		\Tracy\Debugger::dump($arg);
	}
	exit;
}

Tester\Environment::setup();

// 2# Create Nette Configurator
$configurator = new Nette\Configurator;

$tmp = __DIR__ . '/temp/' . php_sapi_name();
@mkdir($tmp, 0755, TRUE);
@mkdir($tmp . '/cache/latte', 0755, TRUE);
$configurator->enableDebugger($tmp);
$configurator->setTempDirectory($tmp);
$configurator->setDebugMode(FALSE);
$configurator->addConfig(__DIR__ . '/config/test.neon');
$container = $configurator->createContainer();
return $container;



