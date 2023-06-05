<?php

namespace h4kuna\Gettext\Macros;

use h4kuna;
use Latte;
use function array_slice;
use function array_unshift;
use function implode;
use function preg_match;
use function preg_replace;
use function rtrim;
use function strpos;
use function substr;
use function substr_count;

class Gettext extends Latte\Macros\MacroSet
{

	public const GETTEXT = 'ettext';

	/** @var string */
	private $function;

	/** @var bool */
	private $plural;

	/** @var int */
	private $params;

	/** @var bool */
	private $oneParam = true;

	/**
	 * Name => count of arguments
	 *
	 * @var array
	 */
	private static $functions = ['g' => 1, 'ng' => 3, 'dg' => 2, 'dng' => 4];

	/**
	 * @return self
	 */
	public static function install(Latte\Compiler $compiler)
	{
		$me = new static($compiler);
		$me->addMacro('_', [$me, 'unknown']);
		foreach (self::$functions as $prefix => $_n) {
			$me->addMacro($prefix . '_', [$me, self::GETTEXT]);
		}

		return $me;
	}

	public function unknown(Latte\MacroNode $node, Latte\PhpWriter $writer)
	{
		$node->args = $this->detectFunction($node->args);

		return $this->ettext($node, $writer);
	}

	public function ettext(Latte\MacroNode $node, Latte\PhpWriter $writer)
	{
		$this->setFunction($node->name);
		$args = h4kuna\Template\LattePhpTokenizer::toArray($node);
		$argsGettext = $this->getGettextArgs($args);

		$out = $this->function . '(' . implode(', ', $argsGettext) . ')';
		$key = (int) (substr($this->function, 0, 1) == 'd');
		$diff = $this->foundReplace($args[$key]);
		if ($diff) {
			$out = 'sprintf(' . $out . ', ' . implode(', ', array_slice($args, $diff)) . ')';
		}

		$this->function = null;

		return $writer->write('echo %modify(' . $out . ')');
	}

	/**
	 * @param array $args
	 * @return array
	 */
	private function getGettextArgs(array $args)
	{
		$argsGettext = array_slice($args, 0, $this->params);
		if (!$this->plural) {
			return $argsGettext;
		}

		$key = (int) ($this->function == 'dngettext');
		if ($this->oneParam) {
			array_unshift($argsGettext, $argsGettext[$key]);
			$n = $argsGettext[0];
			$argsGettext[0] = $argsGettext[1];
			$argsGettext[1] = $n;
		}

		// set another variable as plural
		foreach ($args as $param) {
			if (preg_match('/plural/i', $param)) {
				$argsGettext[2 + $key] = $param;
			}
		}

		// absolute value
		if (preg_match('/abs/i', $argsGettext[2 + $key])) {
			$argsGettext[2 + $key] = 'abs(' . $argsGettext[2 + $key] . ')';
		}

		return $argsGettext;
	}

	private function setFunction($prefix)
	{
		if (!$this->function) {
			$prefix = rtrim($prefix, '_');
			$this->function = $prefix . self::GETTEXT;
			$this->params = self::$functions[$prefix];
			$this->plural = strpos($prefix, 'n') !== false;
			if ($this->plural && $this->oneParam) {
				--$this->params;
			}
		}
	}

	private function detectFunction($args)
	{
		if ($this->function !== null) {
			return $args;
		}

		$find = null;
		if (preg_match('/(.*)(?:"|\')/U', $args, $find) && isset(self::$functions[$find[1] . 'g'])) {
			$this->setFunction($find[1] . 'g_');
			if ($find[1]) {
				return preg_replace('/^' . $find[1] . '/', '', $args);
			}

			return $args;
		}

		throw new h4kuna\Gettext\UnsupportedTranslateMacroException($args);
	}

	/**
	 * Has term for replace?
	 *
	 * @param string $str
	 * @return int
	 */
	private function foundReplace($str)
	{
		return -1 * substr_count($str, '%s');
	}

}
