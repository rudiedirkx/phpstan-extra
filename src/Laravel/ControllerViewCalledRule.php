<?php

namespace rdx\PhpstanExtra\Laravel;

use Generator;
use Larastan\Larastan\Concerns\HasContainer;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final class ControllerViewCalledRule implements Rule {

	use HasContainer, AnalyzesViewParams;

	public function getNodeType() : string {
		return FuncCall::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		return iterator_to_array($this->yieldProblems($node, $scope)); // @phpstan-ignore return.type
	}

	/**
	 * @return Generator<int, RuleError>
	 */
	protected function yieldProblems(Node $node, Scope $scope) : Generator {
		yield from [];

		if (!($node instanceof FuncCall) || !($node->name instanceof Name) || $node->name->getParts() !== ['view']) {
			return;
		}

		$viewNameArg = $node->args[0]->value;
		if (!$this->isAcceptableViewName($viewNameArg)) {
			yield RuleErrorBuilder::message("View name must be a string, or a method call, not a " . get_class($viewNameArg) . ".")
				->identifier('rudie.ControllerViewCalledRule')
				->build();
		}

		$viewName = null;
		if ($viewNameArg instanceof String_) {
			$viewName = $viewNameArg->value;
			// Parse view later?
		}

		if (count($node->args) < 2) {
			return;
		}

		$paramsArg = $node->args[1]->value;
		$paramsItems = $this->getViewParamsArray($paramsArg);
		if ($paramsItems === null) {
			yield RuleErrorBuilder::message("View args must be an array.")
				->identifier('rudie.ControllerViewCalledRule')
				->build();
		}
		if (count($paramsItems) == 0) {
			return;
		}

		$paramsValuesGenerator = $this->getViewParamsValues($paramsItems, $scope, true);
		yield from $paramsValuesGenerator;

		if (!$viewName) {
			return;
		}

		// yield from $this->testView($viewName, $this->hydrateVars($vars));
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

		if ($arg instanceof InterpolatedString) {
			$parts = $arg->parts;
			if (count($parts) == 2 && $this->isVariableOrProperty($parts[1])) {
				if ($parts[0] instanceof InterpolatedStringPart && str_ends_with($parts[0]->value, '/')) {
					return true;
				}
			}
		}

		if ($arg instanceof Concat) {
			if ($arg->left instanceof String_ && $this->isVariableOrProperty($arg->right)) {
				return true;
			}
		}

		return false;
	}



	/**
	 * @param AssocArray $vars
	 * @return Generator<int, RuleError>
	 */
	protected function testView(string $viewName, array $vars) : Generator {
		yield from [];

		$this->getContainer()->make('twig')->enableStrictVariables();
		try {
			$view = $this->getContainer()->make('view')->make($viewName, $vars);
			$view->render();
		}
		catch (\Exception $ex) {
			$viewFile = trim(str_replace(base_path(), '', $ex->getFile()), '/');
			yield RuleErrorBuilder::message(sprintf("Error during rendering: %s At %s:%d", $ex->getMessage(), $viewFile, $ex->getLine()))
				->identifier('rudie.tmp')
				->build();
		}
	}

	/**
	 * @param array<string, string> $vars
	 * @return AssocArray
	 */
	protected function hydrateVars(array $vars) : array {
// dump($vars);
		$vars = array_map($this->hydrateVar(...), $vars);
// dump($vars);
		return $vars;
	}

	protected function hydrateVar(string $type) : mixed {
		if (str_starts_with($type, 'App\\Models\\')) {
			return new $type;
		}

		if (class_exists($type)) {
			try {
				return new $type;
			}
			catch (\Throwable $ex) {
				return null;
			}
		}

		return null;
	}

}
