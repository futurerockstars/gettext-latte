<?php

namespace h4kuna\Gettext;

use Nette\Caching;
use Nette\Http;
use Nette\SmartObject;
use Nette\Utils;
use SplFileInfo;
use function array_combine;
use function array_diff;
use function array_fill_keys;
use function basename;
use function bind_textdomain_codeset;
use function bindtextdomain;
use function copy;
use function count;
use function file_exists;
use function filemtime;
use function filesize;
use function flush;
use function glob;
use function header;
use function implode;
use function is_dir;
use function is_file;
use function key;
use function max;
use function preg_match;
use function preg_quote;
use function readfile;
use function realpath;
use function textdomain;
use function unlink;
use const DIRECTORY_SEPARATOR;

class Dictionary
{

	use SmartObject;

	public const
		PHP_DIR = '/LC_MESSAGES/',
		DOMAIN = 'messages';

	/** @var string */
	private $path;

	/**
	 * List of domains
	 *
	 * @var array
	 */
	private $domains = [];

	/** @var string */
	private $domain;

	/** @var Caching\Cache */
	private $cache;

	/**
	 * Writable directory where versioned *.mo copies are placed when
	 * native gettext in-memory cache busting is enabled. Null = disabled.
	 *
	 * @var string|null
	 */
	private $tempPath;

	/**
	 * Logical domain name => physical (versioned) domain name actually bound
	 * to gettext. Only populated when $tempPath is set.
	 *
	 * @var array<string, string>
	 */
	private $physicalDomains = [];

	public function __construct($path, Caching\IStorage $storage, ?string $tempDir = null)
	{
		$this->cache = new Caching\Cache($storage, 'h4kuna.gettext.dictionary');
		$this->setPath($path)->loadDomains();
		if ($tempDir !== null) {
			$this->setupVersionedDomains($tempDir);
		}
		if (count($this->domains) === 1) {
			$this->setDomain(key($this->domains));
		}
	}

	/**
	 * What domain you want.
	 *
	 * @param string $domain
	 * @return self
	 * @throws GettextException
	 */
	public function setDomain($domain)
	{
		if ($this->domain == $domain) {
			return $this;
		}

		$this->loadDomain($domain);
		textdomain($this->physicalDomains[$domain] ?? $domain);
		$this->domain = $domain;

		return $this;
	}

	/**
	 * Load dictionary if not loaded.
	 *
	 * @param string $domain
	 * @throws DomainDoesNotExistsException
	 */
	public function loadDomain($domain)
	{
		if (!isset($this->domains[$domain])) {
			throw new DomainDoesNotExistsException('This domain does not exists: ' . $domain);
		}

		if ($this->domains[$domain] === false) {
			if (isset($this->physicalDomains[$domain])) {
				bindtextdomain($this->physicalDomains[$domain], $this->tempPath);
				bind_textdomain_codeset($this->physicalDomains[$domain], 'UTF-8');
			} else {
				bindtextdomain($domain, $this->path);
				bind_textdomain_codeset($domain, 'UTF-8');
			}
			$this->domains[$domain] = true;
		}

		return $domain;
	}

	/** @return string */
	public function getDomain()
	{
		return $this->domain;
	}

	/**
	 * Load all dictionaries.
	 *
	 * @param string $default
	 */
	public function loadAllDomains($default)
	{
		foreach ($this->domains as $domain => $_n) {
			$this->loadDomain($domain);
		}

		$this->setDomain($default);
	}

	/**
	 * Offer file download.
	 *
	 * @param string $language
	 * @throws GettextException
	 */
	public function download($language)
	{
		$file = $this->getFile($language, 'po');
		if (file_exists($file)) {
			header('Content-Description: File Transfer');
			header('Content-Disposition: attachment; filename=' . $language . '-' . basename($file));
			header('Content-Length: ' . filesize($file));
			flush();
			readfile($file);
			exit;
		}

		throw new GettextException('File not found: ' . $file);
	}

	/**
	 * Save uploaded files.
	 *
	 * @param string $lang
	 */
	public function upload($lang, Http\FileUpload $po, Http\FileUpload $mo)
	{
		$mo->move($this->getFile($lang, 'mo'));
		$po->move($this->getFile($lang, 'po'));
	}

	/**
	 * Filesystem path for domain.
	 *
	 * @param string $lang
	 * @param string $extension
	 * @return string
	 */
	public function getFile($lang, $extension = 'mo')
	{
		$file = $this->path . $lang . self::PHP_DIR . $this->domain . '.' . $extension;

		if (!is_file($file)) {
			throw new FileNotFoundException($file);
		}

		return $file;
	}

	/**
	 * Check for available domain.
	 *
	 * @return array
	 */
	private function loadDomains()
	{
		$this->domains = $this->cache->load(self::DOMAIN);
		if ($this->domains !== null) {
			return $this->domains;
		}

		$files = $match = $domains = [];
		$find = Utils\Finder::findFiles('*.po');
		foreach ($find->from($this->path) as $file) {
			/** @var SplFileInfo $file */
			if (preg_match('/' . preg_quote($this->path, '/') . '(.*)(?:\\\|\/)/U', $file->getPath(), $match)) {
				$_dictionary = $file->getBasename('.po');
				$domains[$match[1]][$_dictionary] = $_dictionary;
				$files[] = $file->getPathname();
			}
		}

		$dictionary = $domains;
		foreach ($domains as $lang => $_domains) {
			unset($dictionary[$lang]);
			foreach ($dictionary as $value) {
				$diff = array_diff($_domains, $value);
				if ($diff) {
					throw new GettextException(
						'For this language (' . $lang . ') you have one or more different dicitonaries: ' . implode(
							'.mo, ',
							$diff,
						) . '.mo',
					);
				}
			}
		}

		if (!isset($_domains)) {
			// @todo https://github.com/josscrowcroft/php.mo
			throw new GettextException('Let\'s generate *.mo files.');
		}

		$data = array_combine($_domains, array_fill_keys($_domains, false));

		return $this->domains = $this->cache->save(self::DOMAIN, $data, [Caching\Cache::FILES => $files]);
	}

	/**
	 * Native gettext caches *.mo files per (domain, locale) in process memory
	 * for the whole lifetime of the worker. Editing a *.mo on disk does NOT
	 * invalidate that cache, so translation updates only become visible after
	 * a process restart.
	 *
	 * Workaround: derive a version suffix from each *.mo file's mtime, copy
	 * the *.mo files into a writable temp tree under a versioned domain name,
	 * and bind/textdomain to that versioned name. When a *.mo changes on disk
	 * the version suffix changes too, so gettext sees a fresh domain and
	 * reloads the file - no restart needed.
	 *
	 * @throws DirectoryNotFoundException
	 */
	private function setupVersionedDomains(string $tempDir): void
	{
		Utils\FileSystem::createDir($tempDir);
		$resolved = realpath($tempDir);
		if ($resolved === false) {
			throw new DirectoryNotFoundException($tempDir);
		}
		$this->tempPath = $resolved . DIRECTORY_SEPARATOR;

		foreach (array_keys($this->domains) as $domain) {
			$version = $this->computeDomainVersion($domain);
			if ($version === null) {
				continue;
			}
			$physical = $domain . '_v' . $version;
			$this->materializeVersionedCopies($domain, $physical);
			$this->physicalDomains[$domain] = $physical;
		}
	}

	/**
	 * @return int|null Max mtime across all per-language *.mo files of $domain,
	 *                  or null if no *.mo files were found.
	 */
	private function computeDomainVersion(string $domain): ?int
	{
		$mtime = null;
		foreach ($this->listLanguageDirs() as $langDir) {
			$mo = $langDir . self::PHP_DIR . $domain . '.mo';
			if (is_file($mo)) {
				$current = filemtime($mo);
				$mtime = $mtime === null ? $current : max($mtime, $current);
			}
		}
		return $mtime;
	}

	private function materializeVersionedCopies(string $domain, string $physical): void
	{
		foreach ($this->listLanguageDirs() as $langDir) {
			$src = $langDir . self::PHP_DIR . $domain . '.mo';
			if (!is_file($src)) {
				continue;
			}

			$lang = basename($langDir);
			$targetDir = $this->tempPath . $lang . self::PHP_DIR;
			Utils\FileSystem::createDir($targetDir);
			$target = $targetDir . $physical . '.mo';

			if (is_file($target)) {
				continue;
			}

			foreach (glob($targetDir . $domain . '_v*.mo') ?: [] as $old) {
				@unlink($old);
			}
			copy($src, $target);
		}
	}

	/**
	 * @return iterable<string> Absolute paths to per-language directories
	 *                          (e.g. /path/to/locale/cs).
	 */
	private function listLanguageDirs(): iterable
	{
		foreach (new \DirectoryIterator($this->path) as $entry) {
			if ($entry->isDot() || !$entry->isDir()) {
				continue;
			}
			yield $entry->getPathname();
		}
	}

	/**
	 * Check dictionary path.
	 *
	 * @param string $path
	 * @throws DirectoryNotFoundException
	 */
	private function setPath($path)
	{
		$this->path = realpath($path);
		if (!$this->path) {
			throw new DirectoryNotFoundException($path);
		}

		$this->path .= DIRECTORY_SEPARATOR;

		return $this;
	}

}
