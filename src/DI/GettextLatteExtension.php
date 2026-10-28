<?php

namespace h4kuna\Gettext\DI;

use h4kuna\Gettext\Dictionary;
use h4kuna\Gettext\GettextSetup;
use h4kuna\Gettext\Latte\LatteCompiler;
use h4kuna\Gettext\Os;
use Nette\DI;
use Nette\PhpGenerator;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils;
use SplFileInfo;
use function assert;
use function file_exists;
use function unlink;
use const PHP_SAPI;

/**
 * @property-read array<mixed> $config
 */
class GettextLatteExtension extends DI\CompilerExtension
{

	private string $appDir;

	public function __construct(string $appDir)
	{
		$this->appDir = $appDir;
	}

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'langs' => Expect::array(['cs' => 'cs_CZ.utf8', 'en' => 'en_US.utf8'])->mergeDefaults(false),
			'dictionaryPath' => Expect::string("$this->appDir/../locale/"),
			'session' => Expect::anyOf(Expect::string(), false)->default('+1 week'),
			'loadAllDomains' => Expect::string('messages'),
			'tempDir' => Expect::string()->required(),
			'latteTempDir' => Expect::string()->nullable(),
			'localeTranslate' => Expect::array([
				'en_US' => 'English_United_States',
				'en_EN' => 'English_United_Kingdom',
				'de_DE' => 'German_Standard',
				'sk_SK' => 'Slovak',
				'cs_CZ' => 'Czech',
				'it_IT' => 'Italian_Standard',
			])->mergeDefaults(true),
		])->castTo('array');
	}

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$config = $this->config;

		// os
		$builder->addDefinition($this->prefix('os'))
			->setFactory(Os::class)
			->setArguments([$config['localeTranslate']]);

		// dictionary
		$builder->addDefinition($this->prefix('dictionary'))
			->setFactory(Dictionary::class)
			->setArguments([$config['dictionaryPath'], '@cache.storage', $config['tempDir']]);

		// setup
		$gettext = $builder->addDefinition($this->prefix('gettext'))
			->setFactory(GettextSetup::class)
			->setArguments([$config['langs'], $this->prefix('@dictionary'), $this->prefix('@os')]);

		if ($config['loadAllDomains']) {
			$gettext->addSetup('loadAllDomains', [$config['loadAllDomains']]);
		}

		if ($config['session'] && PHP_SAPI != 'cli') {
			$gettext->addSetup('setSession', [$builder->getDefinition('session.session'), $config['session']]);
		}

		// compiler
		$builder->addDefinition($this->prefix('compiler'))
			->setFactory(LatteCompiler::class)
			->setArguments([$builder->getDefinition('latte.templateFactory')]);

		$latte = $builder->getDefinition('latte.latteFactory');
		assert($latte instanceof DI\Definitions\FactoryDefinition);
		$latte->getResultDefinition()->addSetup(
			'?->onCompile[] = function($engine) { h4kuna\Gettext\Macros\Gettext::install($engine->getCompiler()); }',
			['@self'],
		);
	}

	public function afterCompile(PhpGenerator\ClassType $class)
	{
		/**
		 * old template must regenerate
		 * if you use translate macro {_''} and after start this extension, you will see only exception
		 * Nette\MemberAccessException
		 * Call to undefined method Nette\Templating\FileTemplate::translate()
		 * let's clear temp directory
		 * _Nette.FileTemplate
		 */
		$parameters = $this->getContainerBuilder()->parameters;
		$temp = $this->config['latteTempDir'] ?? $parameters['tempDir'] . '/cache/latte';
		if (file_exists($temp) && $parameters['debugMode']) {
			foreach (Utils\Finder::find('*')->in($temp) as $file) {
				/** @var SplFileInfo $file */
				@unlink($file->getPathname());
			}
		}
	}

}
