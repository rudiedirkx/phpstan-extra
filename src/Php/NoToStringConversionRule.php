<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<CallLike>
 */
class NoToStringConversionRule implements Rule {

	private ReflectionProvider $reflectionProvider;

	/** @var array<string, Type> */
	private array $types;

	public function __construct(ReflectionProvider $reflectionProvider) {
		$this->reflectionProvider = $reflectionProvider;
	}

	public function getNodeType() : string {
		return CallLike::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$args = $node->getArgs();
		if (!count($args)) return [];

		$stringableArgTypes = [];
		foreach ($args as $i => $arg) {
			$type = $scope->getType($arg->value);
			$classNames = $type->getObjectClassNames();
			if (!count($classNames)) continue;
			$stringable = $type->isSuperTypeOf(new ObjectType(\Stringable::class));
			$argTypeClasses = $type->getObjectClassReflections();
			if (!count($argTypeClasses)) continue;
			foreach ($argTypeClasses as $refl) {
				if ($refl->hasMethod('__toString')) {
					$stringableArgTypes[$i] = $type;
					continue 2;
				}
			}
		}

		if (!count($stringableArgTypes)) return [];
// dump($node);
// dump($stringableArgTypes);
// return [];

		if ($node instanceof FuncCall) {
			if (!($node->name instanceof Name)) return [];
			if (!$this->reflectionProvider->hasFunction($node->name, $scope)) return [];

			$refl = $this->reflectionProvider->getFunction($node->name, $scope);
			if (in_array($refl->getName(), ['call_user_func', 'call_user_func_array'])) return [];

			$parametersAcceptor = $refl->getVariants()[0];
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
			$parametersAcceptor = $method->getVariants()[0];
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
			$parametersAcceptor = $method->getVariants()[0];
		}
		elseif ($node instanceof New_) {
			if (!($node->class instanceof Name)) {
				return [];
			}
			$classType = $scope->resolveTypeByName($node->class);
			if (!$classType->hasMethod('__construct')->yes()) {
				return [];
			}
			$method = $classType->getMethod('__construct', $scope);
			$parametersAcceptor = $method->getVariants()[0];
		}
		else {
			return [];
		}

		$errors = [];
		foreach ($parametersAcceptor->getParameters() as $i => $param) {
			if (!isset($stringableArgTypes[$i])) continue;
			$argType = $stringableArgTypes[$i];
			if (
				!$this->ignoreArgType($argType) &&
				!$param->getType()->accepts($argType, false)->no() &&
				$param->getType()->accepts($argType, true)->no()
			) {
// dump($parametersAcceptor);
// dump($param);
				$errors[] = RuleErrorBuilder::message(sprintf(
					'Stringable %s not allowed into string param $%s.',
					$argType->describe(VerbosityLevel::typeOnly()),
					$param->getName(),
				))
					->identifier('rudie.NoToStringConversionRule')
					->build();
			}
		}

		return $errors;
	}

	protected function ignoreArgType(Type $argType) : bool {
		// echo $argType->describe(VerbosityLevel::typeOnly()) . "\n";

		foreach ([
			'Psr\Http\Message\StreamInterface',
			'Illuminate\Support\HtmlString',
		] as $className) {
			$this->types[$className] ??= new ObjectType($className);
			if (!$this->types[$className]->isSuperTypeOf($argType)->no()) {
				return true;
			}
		}

		return false;
	}

}
