<?php

namespace h4kuna\Gettext\Macros;

use Latte\Loaders\StringLoader;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Tester\Assert;

$container = include __DIR__ . '/../../bootstrap.php';

$latte = $container->getByType(LatteFactory::class)->create();
$latte->setLoader(new StringLoader());

$code = $latte->compile("<p>\n\n{_'Hello'}\n</p>");

Assert::match('%A%echo %a%gettext(\'Hello\')%a% /* line 3 */;%A%', $code);
