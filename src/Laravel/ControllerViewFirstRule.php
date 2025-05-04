<?php

namespace rdx\PhpstanExtra\Laravel;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Larastan\Larastan\Concerns\HasContainer;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\ObjectType;

/**
 * @implements Rule<MethodCall>
 */
final class ControllerViewFirstRule implements Rule {

	use HasContainer, AnalyzesViewCall, AnalyzesViewParams;

	public function getNodeType() : string {
		return MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$objectType = $scope->getType($node->var);
		if (!(new ObjectType(ViewFactory::class))->isSuperTypeOf($objectType)->yes()) {
			return [];
		}

		if (!($node->name instanceof Identifier) || $node->name->name !== 'first') {
			return [];
		}

		// @phpstan-ignore return.type
		return array_values(iterator_to_array($this->yieldCallProblems($node, $scope, nameArray: true)));
	}

}
