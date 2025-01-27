<?php

namespace rdx\PhpstanExtra\Ires;

use App\Services\Http\AppController;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Throwable;

/**
 * @implements Rule<FuncCall>
 */
final class RouteCallRule implements Rule {

	public function getNodeType() : string {
		return FuncCall::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if (!($node->name instanceof Name) || $node->name->toLowerString() !== 'route') {
			return [];
		}

		$argExprs = $node->args;
		if (count($argExprs) < 1) {
			return [];
		}

		$nameArgType = $scope->getType(array_shift($argExprs)->value);
		$strings = $nameArgType->getConstantStrings();
		if (!count($strings)) {
			return [];
		}

		$strings = array_map(fn($string) => $string->getValue(), $strings);

		$args = array_fill(0, count($argExprs), 123);
		try {
			foreach ($strings as $routeName) {
				$route = AppController::route($routeName, ...$args);
			}
		}
		catch (Throwable $ex) {
			return [
				RuleErrorBuilder::message(sprintf('route() error: %s (%s)', $ex->getMessage(), get_class($ex)))
					->identifier('rudie.RouteCallRule')
					->build(),
			];
		}

		return [];
	}

}
