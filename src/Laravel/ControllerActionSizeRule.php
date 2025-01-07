<?php

namespace rdx\PhpstanExtra\Laravel;

use App\Base\Controller;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
final class ControllerActionSizeRule implements Rule {

	public function __construct(
		private int $allowMaxLines = 999,
	) {}

	public function getNodeType() : string {
		return ClassMethod::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$methodName = $node->name->name;

		$classReflection = $scope->getClassReflection();
		if (!$classReflection->isSubclassOf(Controller::class)) {
			return [];
		}

		if (($node->flags & Modifiers::PUBLIC) == 0) {
			return [];
		}

		$start = $node->name->getStartLine();
		$end = $node->getEndLine();
		if ($start < 1 || $end < 1) {
			return [];
		}

		$lines = $end - $start - 1;
		if ($lines <= $this->allowMaxLines) {
			return [];
		}

		return [
			RuleErrorBuilder::message(sprintf("Too many LOC (% 3d) in %s::%s()", $lines, $classReflection->getName(), $methodName))
				->identifier('rudie.ControllerActionSizeRule')
				->build(),
		];
	}

}
