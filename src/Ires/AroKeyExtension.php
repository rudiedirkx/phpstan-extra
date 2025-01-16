<?php

namespace rdx\PhpstanExtra\Ires;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Type;

final class AroKeyExtension implements DynamicFunctionReturnTypeExtension {

	public function isFunctionSupported(FunctionReflection $functionReflection) : bool {
		return $functionReflection->getName() === 'aro_key';
	}

	public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope) : ?Type {
		if (count($functionCall->args) < 1) {
			return null;
		}

		$objectsArg = $functionCall->args[0];
		$objectsType = $scope->getType($objectsArg->value);

		$arrays = $objectsType->getArrays();
		if (count($arrays) != 1) {
			return null;
		}

		$objectType = $arrays[0]->getItemType();
		$aroClasses = $objectType->getObjectClassReflections();
		if (count($aroClasses) != 1) {
			return null;
		}

		$aroClass = $aroClasses[0];

		$keyArg = $functionCall->args[1] ?? null;
		if ($keyArg) {
			$keyType = $scope->getType($keyArg->value);
			$keyStrings = $keyType->getConstantStrings();
			if (count($keyStrings) != 1) {
				return null;
			}
			$keyName = $keyStrings[0]->getValue();
		}
		else {
			$keyName = $aroClass->getNativeProperty('_pk')->getNativeReflection()->getDefaultValue();
		}

		if (!$aroClass->hasProperty($keyName)) {
			return null;
		}

		$keyType = $aroClass->getProperty($keyName, new OutOfClassScope())
			->getReadableType();
		return new ArrayType($keyType, $objectType);
	}

}
