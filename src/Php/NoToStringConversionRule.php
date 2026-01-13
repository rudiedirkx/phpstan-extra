<?php
namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<CallLike>
 */
class NoToStringConversionRule implements Rule {

	public function getNodeType() : string {
		return CallLike::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		if ($node instanceof FuncCall) {
			if ($node->name instanceof Name) {
				$calleeType = $scope->getFunction($node->name, null);
				$parametersAcceptors = $calleeType->getVariants();
			}
			else {
				$calleeType = $scope->getType($node->name);
				$parametersAcceptors = $calleeType->getCallableParametersAcceptors($scope);
			}
		}
		elseif ($node instanceof MethodCall) {
			$varType = $scope->getType($node->var);
			if (!$node->name instanceof Identifier) {
				return [];
			}
			if (!$varType->hasMethod($node->name->name)->yes()) {
				return [];
			}
			$method = $varType->getMethod($node->name->name, $scope);
			$parametersAcceptors = $method->getVariants();
		}
		elseif ($node instanceof StaticCall) {
			$classType = $node->class instanceof Name ? $scope->resolveTypeByName($node->class) : $scope->getType($node->class);
			if (!$node->name instanceof Identifier) {
				return [];
			}
			if (!$classType->hasMethod($node->name->name)->yes()) {
				return [];
			}
			$method = $classType->getMethod($node->name->name, $scope);
			$parametersAcceptors = $method->getVariants();
		}
		elseif ($node instanceof New_) {
			if ($node->class instanceof Class_) {
				// Anonymous class - skip for now as it's complex to analyze
				return [];
			}
			$classType = $node->class instanceof Name ? $scope->resolveTypeByName($node->class) : $scope->getType($node->class);
			if (!$classType->hasMethod('__construct')->yes()) {
				return [];
			}
			$method = $classType->getMethod('__construct', $scope);
			$parametersAcceptors = $method->getVariants();
		}
		else {
			return [];
		}

		if (count($parametersAcceptors) === 0) {
			return [];
		}

		$parametersAcceptor = $parametersAcceptors[0];
		$parameters = $parametersAcceptor->getParameters();

		$errors = [];

		foreach ($node->getArgs() as $position => $arg) {
			if (!isset($parameters[$position])) {
				continue;
			}

			$argumentType = $scope->getType($arg->value);
			$parameterType = $parameters[$position]->getType();

			// Skip mixed parameters - they accept everything
			if ($parameterType instanceof MixedType) {
				continue;
			}

			// Check if parameter accepts string
			$testString = new ConstantStringType('test');
			if (!$parameterType->accepts($testString, true)->yes()) {
				continue;
			}

			// Check if argument could be an object with __toString
			// We need to check if there's any possible object type in the argument
			$couldBeToStringObject = false;

			// Check if the type is or contains an object
			if ($argumentType->isObject()->yes() || $argumentType->isObject()->maybe()) {
				// Check if it has or might have __toString
				$hasToStringResult = $argumentType->hasMethod('__toString');
				if ($hasToStringResult->yes() || $hasToStringResult->maybe()) {
					// Only error if parameter definitely doesn't accept the object
					$acceptsObject = $parameterType->accepts($argumentType, true);
					if ($acceptsObject->no()) {
						$couldBeToStringObject = true;
					}
				}
			}

			if (!$couldBeToStringObject) {
				continue;
			}

			$functionName = $this->getCallName($node, $scope);
			$paramName = $parameters[$position]->getName();

			$errors[] = RuleErrorBuilder::message(
				sprintf(
					'Parameter $%s of %s expects string, %s given.',
					$paramName,
					$functionName,
					$argumentType->describe(VerbosityLevel::typeOnly())
				)
			)->build();
		}

		return $errors;
	}

	private function getCallName(CallLike $node, Scope $scope) : string {
		if ($node instanceof FuncCall) {
			if ($node->name instanceof Name) {
				return $node->name->toString() . '()';
			}
			return 'function()';
		}

		if ($node instanceof StaticCall) {
			$className = $node->class instanceof Name
				? $node->class->toString()
				: $scope->getType($node->class)->describe(VerbosityLevel::typeOnly());

			$methodName = $node->name instanceof Identifier
				? $node->name->toString()
				: 'method';

			return $className . '::' . $methodName . '()';
		}

		if ($node instanceof MethodCall) {
			$methodName = $node->name instanceof Identifier
				? $node->name->toString()
				: 'method';

			return $scope->getType($node->var)->describe(VerbosityLevel::typeOnly())
				. '->' . $methodName . '()';
		}

		if ($node instanceof New_) {
			$className = $node->class instanceof Name
				? $node->class->toString()
				: $scope->getType($node->class)->describe(VerbosityLevel::typeOnly());

			return 'new ' . $className . '()';
		}

		return 'call()';
	}

}
