<?php

namespace rdx\PhpstanExtra\Laravel;

use Generator;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

trait AnalyzesViewParams {

	/**
	 * @param list<ArrayItem> $items
	 * @return Generator<int, string|RuleError, *, (array<string, string>|array<string, Type>)>
	 */
	protected function getViewParamsValues(array $items, Scope $scope, bool $stringTypes) : Generator {
		$vars = [];
		foreach ($items as $i => $item) {
			if (!($item->key instanceof String_)) {
				yield "View arg $i must have a string key.";
				continue;
			}
			$keyName = $item->key->value;

			if (!$this->isAcceptableParam($item->value, $scope)) {
				yield RuleErrorBuilder::message(sprintf("View arg '%s' is too complex (%s).", $keyName, get_class($item->value)))
					->line($item->getStartLine())
					->build();
			}

			$valueType = $scope->getType($item->value);
			$valueType = TypeCombinator::removeNull($valueType);
			$valueTypeString = $valueType->describe(VerbosityLevel::typeOnly());
			$vars[$keyName] = $stringTypes ? $valueTypeString : $valueType;

			if (str_contains($valueTypeString, '&iterable<')) {
				yield RuleErrorBuilder::message(sprintf("View arg '%s' is an unclear iterable: '%s'.", $keyName, $valueTypeString))
					->line($item->getStartLine())
					->build();
			}
			elseif (str_contains($valueTypeString, 'Illuminate\Database\Eloquent\Collection')) {
				yield RuleErrorBuilder::message(sprintf("View arg '%s' is an unclear collection: '%s'.", $keyName, $valueTypeString))
					->line($item->getStartLine())
					->build();
			}
			elseif (str_contains($valueTypeString, 'Illuminate\Database\Eloquent\Model')) {
				yield RuleErrorBuilder::message(sprintf("View arg '%s' is an unclear model: '%s'.", $keyName, $valueTypeString))
					->line($item->getStartLine())
					->build();
			}
			elseif ($valueTypeString === 'object') {
				yield RuleErrorBuilder::message(sprintf("View arg '%s' is 'object'.", $keyName))
					->line($item->getStartLine())
					->build();
			}
			elseif (preg_match('#\b(mixed)\b#', $valueTypeString, $match) && $valueTypeString !== $match[1]) {
				yield RuleErrorBuilder::message(sprintf("View arg '%s' is/contains %s: '%s'.", $keyName, $match[1], $valueTypeString))
					->line($item->getStartLine())
					->build();
			}
			elseif (preg_match('#\barray(?![<\{])#', $valueTypeString, $match)) {
				yield RuleErrorBuilder::message(sprintf("View arg '%s' is/contains untyped array: '%s'.", $keyName, $valueTypeString))
					->line($item->getStartLine())
					->build();
			}
		}

		return $vars;
	}

	/**
	 * @return null|list<ArrayItem>
	 */
	protected function getViewParamsArray(Expr $paramsArg) : ?array {
		if ($paramsArg instanceof Array_) {
			return $paramsArg->items;
		}

		if ($paramsArg instanceof Variable) {
			// We'll assume it's a valid array
			return [];
		}

		if ($paramsArg instanceof Plus) {
			if ($paramsArg->left instanceof Array_) {
				return $paramsArg->left->items;
			}
			if ($paramsArg->right instanceof Array_) {
				return $paramsArg->right->items;
			}
		}

		return null;
	}

	protected function isAcceptableParam(Expr $param, Scope $scope) : bool {
		if ($param instanceof ConstFetch) {
			return true;
		}

		if ($param instanceof ArrayDimFetch) {
			return $param->dim instanceof String_;
		}

		if ($this->isVariableOrProperty($param)) {
			return true;
		}

		if ($this->isFormerMethodCall($param, $scope)) {
			return true;
		}

		if ($param instanceof BooleanNot) {
			return $this->isAcceptableParam($param->expr, $scope);
		}

		return false;
	}

	protected function isVariableOrProperty(Expr $expr) : bool {
		if ($expr instanceof String_) {
			return true;
		}

		if ($expr instanceof Variable) {
			return true;
		}

		if ($expr instanceof PropertyFetch) {
			return $this->isVariableOrProperty($expr->var);
		}

		return false;
	}

	protected function isFormerMethodCall(Expr $expr, Scope $scope) : bool {
		if (!($expr instanceof MethodCall)) {
			return false;
		}

		if (!($expr->var instanceof Variable)) {
			return false;
		}

		// if ($expr->var->name == 'former') {
		// 	return true;
		// }

		$objectType = $scope->getVariableType($expr->var->name);
		if (($className = ($objectType->getObjectClassNames()[0] ?? null)) && str_ends_with($className, 'Former')) {
			return true;
		}

		return false;
	}

}
