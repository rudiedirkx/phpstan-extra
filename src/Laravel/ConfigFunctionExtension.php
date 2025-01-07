<?php

namespace rdx\PhpstanExtra\Laravel;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

final class ConfigFunctionExtension implements DynamicFunctionReturnTypeExtension {

	public function isFunctionSupported(FunctionReflection $functionReflection): bool {
		return $functionReflection->getName() === 'config';
	}

	public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): ?Type {
		$arg = $functionCall->getArgs()[0]->value;
		if (!($arg instanceof String_)) {
			return null;
		}

		$config = config($arg->value);

		if (is_bool($config)) {
			return new BooleanType();
		}
		elseif (is_int($config)) {
			return new IntegerType();
		}
		elseif (is_float($config)) {
			return new FloatType();
		}
		elseif (is_string($config)) {
			return new StringType();
		}
		elseif (is_array($config)) {
			$mixed = new MixedType();
			return new ArrayType($mixed, $mixed);
		}

		return null;
	}

}
