<?php

namespace h4kuna\Gettext;

use function preg_replace;
use function strtolower;
use function substr;
use const PHP_OS;

class Os
{

	/** OS platforms */
	public const
		LINUX = 'linux',
		MAC = 'mac',
		WINDOWS = 'windows';

	/** @var string */
	private $os;

	/** @var array */
	private $translateLocale = [];

	public function __construct(array $winLocaleTranslate)
	{
		$this->translateLocale[self::WINDOWS] = $winLocaleTranslate;
	}

	public function getWindowsLocale($key)
	{
		$key = preg_replace('/\.utf8$/', '', $key);

		return $this->translateLocale[self::WINDOWS][$key];
	}

	public function getOs()
	{
		if ($this->os !== null) {
			return $this->os;
		}

		switch (strtolower(substr(PHP_OS, 0, 5))) {
			case 'windo':
			case 'winnt':
				$this->os = self::WINDOWS;

				break;
			case 'darwi':
				$this->os = self::MAC;

				break;
			case 'linux':
			case 'freeb':
				$this->os = self::LINUX;

				break;
			default:
				throw new UnsupportedOperationSystemException(
					'Please write to autor. Your system is: "' . PHP_OS . '".',
				);
		}

		return $this->os;
	}

	public function isMac()
	{
		return $this->getOs() === self::MAC;
	}

	public function isLinux()
	{
		return $this->getOs() === self::LINUX;
	}

	public function isWindows()
	{
		return $this->getOs() === self::WINDOWS;
	}

	public function __toString()
	{
		return (string) $this->getOs();
	}

}
