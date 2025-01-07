<?php

namespace rdx\PhpstanExtra\Ires;

trait CaresAboutClassMethods {

	private function maybeIgnoreName(string $name, string $type) : string {
		if ($this->careAboutMethod($name)) {
// printf("%s %s\n", $type, $name);
			return $name;
		}

		return '';
	}

	private function careAboutMethod(string $name) : bool {
		return !str_starts_with($name, 'get_')
			&& !str_starts_with($name, 'relate_')
			&& !str_starts_with($name, '__');
	}

}
