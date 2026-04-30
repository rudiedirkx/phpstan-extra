<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;

/**
 * @implements Rule<InClassMethodNode>
 */
final class ReturnTypeClosureRule implements Rule {

	use AnalyzesClosureType;

	public function getNodeType() : string {
		return InClassMethodNode::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$methodRefl = $node->getMethodReflection();
		$returnType = $methodRefl->getReturnType();
		if (!$returnType) return [];

		$returnType = TypeCombinator::removeNull($returnType);

		if (!$this->typeLacksClosureDetails($returnType)) return [];

		return [
			RuleErrorBuilder::message('Return type Closure must include input & output details.')
				->identifier('rudie.ReturnTypeClosureRule')
				->build(),
		];
	}

}
