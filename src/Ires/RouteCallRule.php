<?php

namespace rdx\PhpstanExtra\Ires;

use App\Services\Http\AppController;
use Framework\Http\Controller;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use PHPStan\File\RelativePathHelper;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Throwable;

/**
 * @implements Rule<StaticCall>
 */
final class RouteCallRule implements Rule {

	private ObjectType $controllerType;

	public function getNodeType() : string {
		return StaticCall::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if (!($node->name instanceof Identifier) || $node->name->name !== 'route') {
			return [];
		}

		if ($node->class instanceof FullyQualified) {
			$className = $node->class->toString();
		}
		else {
			$className = $scope->resolveName($node->class);
		}
		$this->controllerType ??= new ObjectType(Controller::class);
		if (!$this->controllerType->isSuperTypeOf(new ObjectType($className))->yes()) {
			return [];
		}

		if ($node->class->toString() !== 'App\Services\Http\AppController') {
			return [
				RuleErrorBuilder::message('route() must always be called on AppController')
					->identifier('rudie.RouteCallRule')
					->build(),
			];
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
