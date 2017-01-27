<?php

namespace h4kuna\Gettext\Latte;

use Latte\RuntimeException;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Latte\CompileException;
use Latte\Engine;
use Nette\Utils\Finder;
use SplFileInfo;

class LatteCompiler
{

	/** @var array */
	private $mask = ['*.latte'];

	/** @var Template */
	private $template;

	/** @var SplFileInfo[] */
	private $skippedFiles = [];

	/** @var SplFileInfo[] */
	private $files = [];

	/** @var string */
	private $temp;

	public function __construct(TemplateFactory $templateFactory)
	{
		$this->template = $templateFactory->createTemplate(new FakeControl());
		$this->temp = dirname($this->template->getLatte()->getCacheFile('foo'));
	}

	public function addMask($mask)
	{
		$this->mask[] = $mask;
	}

	public function addExclude($path)
	{
		$this->files = array_diff_key($this->files, $this->getFiles($path));
		return $this;
	}

	public function addInclude($path)
	{
		$this->files += $this->getFiles($path);
		return $this;
	}

	/**
	 *
	 * @param string $path
	 * @return SplFileInfo[]
	 */
	private function getFiles($path)
	{
		$fileInfo = new SplFileInfo($path);
		if ($fileInfo->isFile()) {
			return [$fileInfo->getRealPath() => $fileInfo];
		}

		$found = [];
		$finder = call_user_func_array('\Nette\Utils\Finder::findFiles', $this->mask);
		foreach ($finder->from($fileInfo->getRealPath()) as $file) {
			$found[$file->getRealPath()] = $file;
		}
		return $found;
	}

	public function getTemp()
	{
		return $this->temp;
	}

	private function clearTemp()
	{
		/* @var $file SplFileInfo  */
		foreach (Finder::findFiles('*')->from($this->temp) as $file) {
			@unlink($file->getPathname());
		}
	}

	public function getTemplate()
	{
		return $this->template;
	}

	public function prepareFiles()
	{
		if ($this->skippedFiles) {
			$out = $this->skippedFiles;
			$this->skippedFiles = [];
			return $out;
		}

		return $this->files;
	}

	public function run()
	{
		error_reporting(E_ALL & ~(E_NOTICE));
		if (!$this->skippedFiles) {
			$this->clearTemp();
		}

		$latte = $this->template->getLatte();
		/* @var $file SplFileInfo */
		foreach ($this->prepareFiles() as $file) {
			try {
				echo $file->getPathname() . "\n";
				$code = $latte->compile($file->getPathname());
				file_put_contents($latte->getCacheFile($file->getPathname()), $code);
			} catch (RuntimeException $e) {
				if (substr($e->getMessage(), 0, 30) !== 'Cannot include undefined block') {
					throw $e;
				}
			} catch (CompileException $e) {
				$find = NULL;
				if (!preg_match('/Unknown macro \{(.*)\}/U', $e->getMessage(), $find)) {
					throw $e;
				}

				$macroName = $find[1];
				$this->template->getLatte()->onCompile[] = function(Engine $engine) use ($macroName) {
					$engine->addMacro($macroName, new EmptyMacro());
//goto checkFile;
				};
				$this->skippedFiles[] = $file;
			}
		}
		if ($this->skippedFiles) {
			$this->run();
		}
	}

}
