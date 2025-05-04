<?php

namespace rdx\PhpstanExtra\Laravel;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

trait AnalyzesViewCall {

	/**
	 * @param FuncCall|MethodCall $node
	 * @return Generator<int, RuleError>
	 */
	protected function yieldCallProblems(Node $node, Scope $scope, bool $nameArray) : Generator {
		yield from [];

		$viewNameArg = $node->args[0]->value;
		$acceptableView = $nameArray ? $this->isAcceptableViewNameArray($viewNameArg) : $this->isAcceptableViewName($viewNameArg);
		if ($acceptableView === false) {
			yield RuleErrorBuilder::message("View name must be a string, or a method call, not a " . get_class($viewNameArg) . ".")
				->identifier('rudie.ControllerViewCalledRule.viewName')
				->build();
		}

		if (count($node->args) < 2) {
			return;
		}

		$paramsArg = $node->args[1]->value;
		$paramsItems = $this->getViewParamsArray($paramsArg);
		if ($paramsItems === null) {
			yield RuleErrorBuilder::message("View args must be an array.")
				->identifier('rudie.ControllerViewCalledRule.viewArgs')
				->build();
		}
		if (count($paramsItems) == 0) {
			return;
		}

		$paramsValuesGenerator = $this->getViewParamsValues($paramsItems, $scope, true);
		yield from $paramsValuesGenerator;
	}

	protected function isAcceptableViewNameArray(Node $name) : ?bool {
		if (!($name instanceof Array_)) {
			return null;
		}

		foreach ($name->items as $item) {
			if ($item->key) {
				return false;
			}

			if (!$this->isAcceptableViewName($item->value)) {
				return false;
			}
		}

		return true;
	}

	protected function isAcceptableViewName(Expr $arg) : bool {
		if ($arg instanceof String_) {
			return true;
		}

		if ($arg instanceof MethodCall) {
			return true;
		}

		if ($arg instanceof Variable) {
			return true;
		}

		// if ($arg instanceof InterpolatedString) {
		// 	$parts = $arg->parts;
		// 	if (count($parts) == 2 && $this->isVariableOrProperty($parts[1])) {
		// 		if ($parts[0] instanceof InterpolatedStringPart && str_ends_with($parts[0]->value, '/')) {
		// 			return true;
		// 		}
		// 	}
		// }

		if ($arg instanceof Concat) {
			if ($arg->left instanceof String_ && $this->isVariableOrProperty($arg->right)) {
				return true;
			}
		}

		return false;
	}

}
