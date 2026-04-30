<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure as ClosureNode;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClosureNode>
 */
final class ClosureParamTypesRule implements Rule {

	public function getNodeType() : string {
		return ClosureNode::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$errors = [];

		foreach ($node->params as $param) {
			if (!$param->type) {
				$errors[] = RuleErrorBuilder::message(sprintf('Anonymous function param $%s must have a type.', $param->var->name))
					->identifier('rudie.ClosureParamTypesRule')
					->build();
			}
		}

		return $errors;
	}

}
