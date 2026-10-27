<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<ArrayDimFetch>
 */
final class NullArrayKeyRule implements Rule {

	public function getNodeType() : string {
		return ArrayDimFetch::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if ($node->dim === null) {
			return [];
		}

		if ($scope->getType($node->var)->isArray()->no()) {
			return [];
		}

		$keyType = $scope->getType($node->dim);
		if (!TypeCombinator::containsNull($keyType)) {
			return [];
		}

		return [
			RuleErrorBuilder::message(sprintf('Array key type %s might be null.', $keyType->describe(VerbosityLevel::typeOnly())))
				->identifier('rudie.NullArrayKeyRule')
				->build(),
		];
	}

}
