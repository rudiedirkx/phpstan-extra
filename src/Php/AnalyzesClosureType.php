<?php

namespace rdx\PhpstanExtra\Php;

use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;

trait AnalyzesClosureType {

	protected function typeLacksClosureDetails(Type $type) : bool {
		if (!in_array('Closure', $type->getReferencedClasses())) {
			return false;
		}

		$typeText = $type->describe(VerbosityLevel::precise());
		if ($typeText === 'Closure') {
			return true;
		}

		if ($type instanceof UnionType) {
			foreach ($type->getTypes() as $subtype) {
				if ($this->typeLacksClosureDetails($subtype)) {
					return true;
				}
			}
		}

		// Unsure... A type with Closure that's not a union type?
		// echo "$typeText\n";

		return false;
	}

}
