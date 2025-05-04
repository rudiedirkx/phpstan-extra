<?php

namespace rdx\PhpstanExtra\Laravel;

use Larastan\Larastan\Concerns\HasContainer;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<FuncCall>
 */
final class ControllerViewCalledRule implements Rule {

	use HasContainer, AnalyzesViewCall, AnalyzesViewParams;

	public function getNodeType() : string {
		return FuncCall::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if (!($node->name instanceof Name) || $node->name->toLowerString() !== 'view' || count($node->args) == 0) {
			return [];
		}

		// @phpstan-ignore return.type
		return array_values(iterator_to_array($this->yieldCallProblems($node, $scope, nameArray: false)));
	}

}
