<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Expr\ErrorSuppress;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ErrorSuppress>
 */
final class ErrorSuppressionRule implements Rule {

	public function __construct(
		protected bool $enabled,
		protected bool $allowFunctionCall,
	) {}

	public function getNodeType() : string {
		return ErrorSuppress::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if (!$this->enabled) {
			return [];
		}

		// Allow on function calls.
		if ($this->allowFunctionCall && $node->expr instanceof FuncCall) {
			return [];
		}

		// But nowhere else.
		return [
			RuleErrorBuilder::message("Error suppression with @ is not allowed.")
				->identifier('rudie.ErrorSuppressionRule')
				->build(),
		];
	}

}
