<?php

namespace h4kuna\Gettext\Latte;

use Latte\MacroNode;
use Nette\Latte\IMacro;

class EmptyMacro implements IMacro
{

	public function finalize()
	{
		// Noop
	}

	public function initialize()
	{
		// Noop
	}

	public function nodeClosed(MacroNode $node)
	{
		// Noop
	}

	public function nodeOpened(MacroNode $node)
	{
		$node->isEmpty = $node->closing = true;

		return true;
	}

}
