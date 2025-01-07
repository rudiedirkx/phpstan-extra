<?php

namespace rdx\PhpstanExtra\Laravel;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

final class TransFunctionExtension implements DynamicFunctionReturnTypeExtension {

	public function isFunctionSupported(FunctionReflection $functionReflection): bool {
		return $functionReflection->getName() === 'trans';
	}

	public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): ?Type {
		$argNode = $functionCall->getArgs()[0]->value;
		$argType = $scope->getType($argNode);
		$argStringTypes = $argType->getConstantStrings();
		if (!count($argStringTypes)) {
			return null;
		}
		$transKeys = array_map(fn($type) => $type->getValue(), $argStringTypes);

		return $this->getTransType($transKeys[0]);
	}

	private function getTransType(string $transKey) : Type {
		$loader = app('translation.loader');
		if ($loader instanceof \rdx\transloader\TranslationsLoader) $loader->disable();

		$transValue = trans($transKey);

		if (is_array($transValue)) {
			return new ArrayType(new StringType(), new MixedType());
		}
		return new StringType();
	}

}
