<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
class ClassMethodVisibilityRule implements Rule {

	public function getNodeType() : string {
		return ClassMethod::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if (($node->flags & Modifiers::VISIBILITY_MASK) === 0) {
			return [
				RuleErrorBuilder::message(sprintf(
					'Class method %s() must declare a visibility keyword.',
					$node->name->name,
				))->identifier('method.visibility')->build(),
			];
		}

		return [];
	}

}
