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
final class ParamTypeClosureRule implements Rule {

	use AnalyzesClosureType;

	public function getNodeType() : string {
		return InClassMethodNode::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$methodRefl = $node->getMethodReflection();

		$params = $methodRefl->getVariants()[0]->getParameters();
		if (!count($params)) return [];

		$errors = [];

		foreach ($params as $param) {
			$type = $param->getType();
			$type = TypeCombinator::removeNull($type);

			if (!$this->typeLacksClosureDetails($type)) continue;

			$errors[] = RuleErrorBuilder::message(sprintf('Parameter $%s type Closure must include input & output details.', $param->getName()))
				->identifier('rudie.ParamTypeClosureRule')
				->build();
		}

		return $errors;
	}

}
