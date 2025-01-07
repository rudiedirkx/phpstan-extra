<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassConst>
 */
final class ClassConstantVisibilityRule implements Rule {

	public function getNodeType() : string {
		return ClassConst::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if (($node->flags & Modifiers::VISIBILITY_MASK) === 0) {
			$constName = $node->consts[0]->name->name;

			$parentClass = $scope->getClassReflection()->getParentClass();
			if ($parentClass && $parentClass->hasConstant($constName)) {
				return [];
			}

			return [
				RuleErrorBuilder::message(sprintf(
					'Class constant %s must declare a visibility keyword.',
					$constName,
				))->identifier('const.visibility')->build(),
			];
		}

		return [];
	}

}
