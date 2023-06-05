<?php

namespace h4kuna\Gettext\Latte;

class MagicObject
{

	/** @var mixed */
	private $return;

	public function __construct($return = null)
	{
		$this->return = $return;
	}

	public function __call($name, $arguments)
	{
		return $this->return;
	}

	public function __set($name, $value)
	{
		return $this->return;
	}

	public function __get($name)
	{
		return $this->return;
	}

}
