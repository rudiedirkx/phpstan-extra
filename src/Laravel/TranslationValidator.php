<?php

namespace rdx\PhpstanExtra\Laravel;

class TranslationValidator {

	/** @var list<string> */
	protected array $allKeys;

	public function isValidKey(string $key) : bool {
		return $this->keyExists($key);
	}

	public function getMessage(string $type, string $value) : string {
		if ($type == 'expr') {
			return "Funky trans expression: " . $value;
		}

		return sprintf("Invalid trans key '%s'", $value);
	}

	protected function keyExists(string $key) : bool {
		$this->allKeys ??= $this->extractAllKeys();

		if (in_array($key, $this->allKeys)) {
			return true;
		}

		if (!str_ends_with($key, '.') && !str_ends_with($key, '_')) {
			return false;
		}

		foreach ($this->allKeys as $exists) {
			if (str_starts_with($exists, $key)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return list<string>
	 */
	protected function extractAllKeys() : array {
		return $this->getLangKeys('en');
	}

	/////////////////////////////////////////////
	//  From \rdx\transloader\CompareCommand:  //
	/////////////////////////////////////////////

	/**
	 * @return list<string>
	 */
	protected function getLangKeys(string $lang) : array {
		$groups = $this->getGroups($lang);
		$keys = [];
		foreach ($groups as $group) {
			$keys = array_merge($keys, $this->getGroupKeys($lang, $group));
		}

		return $keys;
	}

	/**
	 * @return list<string>
	 */
	protected function getGroups(string $lang) : array {
		$groups = glob(resource_path("lang/$lang/*.php"));
		$groups = array_map(fn($path) => substr(basename($path), 0, -4), $groups);
		return $groups;
	}

	/**
	 * @return list<string>
	 */
	protected function getGroupKeys(string $lang, string $group) : array {
		$filepath = resource_path("lang/$lang/$group.php");
		$dimensional = include $filepath;
		return $this->flattenKeys($group, $dimensional);
	}

	/**
	 * @param array<string, mixed> $dimensional
	 * @return list<string>
	 */
	protected function flattenKeys(string $prefix, array $dimensional) : array {
		$flat = [];
		foreach ($dimensional as $key => $trans) {
			if (is_array($trans)) {
				$flat = array_merge($flat, $this->flattenKeys("$prefix.$key", $trans));
			}
			else {
				$flat[] = "$prefix.$key";
			}
		}

		return $flat;
	}

}
